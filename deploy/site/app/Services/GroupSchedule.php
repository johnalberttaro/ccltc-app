<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Creates the "everyone plays everyone once" matches for each group (and for round robin tournaments).
class GroupSchedule
{
    /**
     * Pairings for n players, in the familiar order (4 players: 1-2, 3-4, 1-3, 2-4, 1-4, 2-3).
     * Returns [[round, indexA, indexB], ...] using 0-based player indexes.
     */
    public static function pairings(int $n): array
    {
        $list = range(0, $n - 1);
        if ($n % 2 === 1) {
            $list[] = null;   // odd number: one player rests each round
        }
        $size = count($list);
        $rounds = [];

        for ($r = 0; $r < $size - 1; $r++) {
            $games = [];
            for ($i = 0; $i < intdiv($size, 2); $i++) {
                $a = $list[$i];
                $b = $list[$size - 1 - $i];
                if ($a !== null && $b !== null) {
                    $games[] = [min($a, $b), max($a, $b)];
                }
            }
            usort($games, fn ($x, $y) => $x[0] <=> $y[0]);
            $rounds[] = $games;
            $list = array_merge([$list[0]], [$list[$size - 1]], array_slice($list, 1, $size - 2));
        }

        $out = [];
        foreach (array_reverse($rounds) as $r => $games) {
            foreach ($games as [$a, $b]) {
                $out[] = [$r + 1, $a, $b];
            }
        }

        return $out;
    }

    // Create the matches for every group
    public function generate(Tournament $tournament): int
    {
        if ($tournament->schedule_generated_at) {
            throw ValidationException::withMessages(['schedule' => 'The matches were already created.']);
        }

        return DB::transaction(function () use ($tournament) {
            if ($tournament->isRoundRobin()) {
                $this->ensureLeagueGroup($tournament);
            } elseif (! $tournament->groups_generated_at) {
                throw ValidationException::withMessages(['schedule' => 'Generate the groups first.']);
            }

            if ($tournament->participants()->whereNull('group_id')->exists()) {
                throw ValidationException::withMessages(['schedule' => 'Some participants are not in a group yet. Put them in a group first.']);
            }

            $total = 0;
            foreach ($tournament->groups()->get() as $group) {
                $total += $this->buildGroup($group);
            }

            $tournament->schedule_generated_at = now();
            $tournament->save();

            return $total;
        });
    }

    // (Re)build the matches of one group. Existing matches of that group are replaced.
    public function buildGroup(TournamentGroup $group): int
    {
        $group->matches()->delete();
        $members = $group->participants()->get()->values();
        $prefix = strlen($group->name) <= 2 ? $group->name . '-' : 'M';

        $count = 0;
        foreach (self::pairings($members->count()) as $i => [$round, $a, $b]) {
            $group->matches()->create([
                'tournament_id'   => $group->tournament_id,
                'stage'           => 'group',
                'round'           => $round,
                'match_number'    => $i + 1,
                'code'            => $prefix . ($i + 1),
                'participant1_id' => $members[$a]->id,
                'participant2_id' => $members[$b]->id,
                'status'          => 'scheduled',
            ]);
            $count++;
        }

        return $count;
    }

    // Remove all group matches (and their results)
    public function reset(Tournament $tournament): void
    {
        if ($tournament->knockout_generated_at) {
            throw ValidationException::withMessages(['schedule' => 'Reset the knockout bracket first.']);
        }

        DB::transaction(function () use ($tournament) {
            $tournament->matches()->where('stage', 'group')->delete();
            $tournament->schedule_generated_at = null;
            if ($tournament->isRoundRobin()) {
                $tournament->participants()->update(['group_id' => null, 'group_position' => null]);
                $tournament->groups()->delete();
            }
            $tournament->save();
            app(MatchResults::class)->syncStatus($tournament->fresh());
        });
    }

    // Round robin = one big group containing everyone
    private function ensureLeagueGroup(Tournament $tournament): void
    {
        $tournament->groups()->delete();
        $league = $tournament->groups()->create(['name' => 'League', 'position' => 0]);

        foreach ($tournament->participants()->get()->values() as $i => $p) {
            $p->update(['group_id' => $league->id, 'group_position' => $i + 1]);
        }

        if ($tournament->participants()->count() < 2) {
            throw ValidationException::withMessages(['schedule' => 'Add at least 2 participants first.']);
        }
    }
}