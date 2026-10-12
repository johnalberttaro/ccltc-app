<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentGroup;
use App\Models\TournamentMatch;
use App\Models\TournamentParticipant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GroupDraw
{
    /**
     * Check whether the current participants fit the group settings.
     * Returns ['ok' => bool, 'errors' => [...], 'sizes' => [5, 5, 4, 4], 'players' => 18, 'summary' => '...']
     */
    public static function check(int $players, int $groups, int $maxPerGroup, int $advance): array
    {
        $errors = [];

        if ($players < 2) {
            $errors[] = 'Add at least 2 participants before generating groups.';
        }
        if ($groups < 1) {
            $errors[] = 'Set the number of groups to at least 1.';
        }

        if (! $errors) {
            if ($players > $groups * $maxPerGroup) {
                $errors[] = "You have {$players} participants, but your current configuration only allows "
                    . ($groups * $maxPerGroup) . " ({$groups} groups x {$maxPerGroup} per group). "
                    . 'Please increase the number of groups or the players per group.';
            }
            if ($players < $groups * 2) {
                $errors[] = "Every group needs at least 2 players. With {$players} participants you can have at most "
                    . intdiv($players, 2) . ' groups.';
            }
        }

        $sizes = [];
        if (! $errors) {
            $base = intdiv($players, $groups);
            $extra = $players % $groups;
            for ($g = 0; $g < $groups; $g++) {
                $sizes[] = $base + ($g < $extra ? 1 : 0);
            }

            if ($advance > min($sizes)) {
                $errors[] = 'The smallest group would have ' . min($sizes) . " players, but {$advance} must advance from each group. "
                    . 'Lower the number advancing or use fewer groups.';
            }
            if ($groups * $advance < 2) {
                $errors[] = 'At least 2 players must advance to make a knockout bracket.';
            }
        }

        return [
            'ok'      => ! $errors,
            'errors'  => $errors,
            'sizes'   => $sizes,
            'players' => $players,
            'summary' => $errors ? null : self::describe($players, $sizes),
        ];
    }

    // "16 players will be divided into 4 groups with 4 players per group."
    public static function describe(int $players, array $sizes): string
    {
        $groups = count($sizes);
        $counts = array_count_values($sizes);
        krsort($counts);

        if (count($counts) === 1) {
            return "{$players} players will be divided into {$groups} " . ($groups === 1 ? 'group' : 'groups')
                . ' with ' . $sizes[0] . ' players per group.';
        }

        $parts = [];
        foreach ($counts as $size => $howMany) {
            $parts[] = "{$howMany} " . ($howMany === 1 ? 'group' : 'groups') . " of {$size}";
        }

        return "{$players} players will be divided into {$groups} groups (" . implode(' and ', $parts) . ').';
    }

    // Seeds first (1, 2, 3...), then everyone else in random order
    public static function drawOrder(iterable $participants): array
    {
        $list = collect($participants);

        return $list->whereNotNull('seed')->sortBy('seed')->values()
            ->concat($list->whereNull('seed')->shuffle()->values())
            ->all();
    }

    // Snake order: A B C D | D C B A | A B C D ... so the top seeds end up in different groups
    public static function snake(array $ordered, int $groups): array
    {
        $buckets = array_fill(0, $groups, []);
        foreach (array_values($ordered) as $i => $item) {
            $row = intdiv($i, $groups);
            $col = $i % $groups;
            $buckets[$row % 2 === 0 ? $col : $groups - 1 - $col][] = $item;
        }

        return $buckets;
    }

    public static function groupName(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;   // A..Z, AA, AB...
    }

    public function generate(Tournament $tournament): void
    {
        if ($tournament->groups_generated_at) {
            throw ValidationException::withMessages(['groups' => 'Groups were already generated. Reset them first to draw again.']);
        }

        $participants = $tournament->participants()->get();
        $check = self::check($participants->count(), (int) $tournament->group_count, (int) $tournament->group_size, (int) $tournament->advance_per_group);
        if (! $check['ok']) {
            throw ValidationException::withMessages(['groups' => $check['errors']]);
        }

        $buckets = self::snake(self::drawOrder($participants), (int) $tournament->group_count);

        DB::transaction(function () use ($tournament, $buckets) {
            foreach ($buckets as $g => $members) {
                $group = $tournament->groups()->create(['name' => self::groupName($g), 'position' => $g]);
                foreach ($members as $pos => $participant) {
                    $participant->update(['group_id' => $group->id, 'group_position' => $pos + 1]);
                }
            }

            $tournament->groups_generated_at = now();
            $tournament->save();
        });
    }

    // Remove the groups (and any group matches); participants are kept
    public function reset(Tournament $tournament): void
    {
        if ($tournament->knockout_generated_at) {
            throw ValidationException::withMessages(['groups' => 'The knockout stage has already been generated. Reset the knockout first.']);
        }

        DB::transaction(function () use ($tournament) {
            $tournament->matches()->where('stage', 'group')->delete();
            $tournament->participants()->update(['group_id' => null, 'group_position' => null]);
            $tournament->groups()->delete();
            $tournament->groups_generated_at = null;
            $tournament->schedule_generated_at = null;
            $tournament->save();
        });
    }

    // Move a participant to another group. Matches of both groups are rebuilt.
    public function move(TournamentParticipant $participant, TournamentGroup $target): array
    {
        $tournament = $participant->tournament;

        if ($tournament->knockout_generated_at) {
            throw ValidationException::withMessages(['groups' => 'Groups are locked because the knockout stage has started.']);
        }
        if ((int) $participant->group_id === (int) $target->id) {
            return [];
        }

        $affected = array_values(array_filter([$participant->group_id, $target->id]));

        DB::transaction(function () use ($participant, $target, $affected) {
            TournamentMatch::whereIn('group_id', $affected)->delete();

            $participant->update([
                'group_id'       => $target->id,
                'group_position' => (int) $target->participants()->max('group_position') + 1,
            ]);
        });

        return $affected;
    }

    // How many results would be lost if these groups' matches are rebuilt?
    public static function resultsInGroups(array $groupIds): int
    {
        return TournamentMatch::whereIn('group_id', array_filter($groupIds))
            ->where('status', 'completed')
            ->count();
    }
}