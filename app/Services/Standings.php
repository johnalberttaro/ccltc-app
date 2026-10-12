<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentGroup;
use Illuminate\Support\Collection;

// Group / round robin tables, calculated live from recorded results.
class Standings
{
    /**
     * Table for one group.
     * Each row: participant, played, wins, losses, sets_won, sets_lost, games_won, games_lost, points,
     *           rank, qualified (bool), tie_note (string|null)
     */
    public static function forGroup(Tournament $tournament, TournamentGroup $group, ?Collection $matches = null, ?Collection $members = null): array
    {
        $members ??= $group->participants;
        $matches ??= $group->matches()->with('sets')->get();

        $rows = [];
        foreach ($members as $p) {
            $rows[$p->id] = [
                'participant' => $p, 'played' => 0, 'wins' => 0, 'losses' => 0,
                'sets_won' => 0, 'sets_lost' => 0, 'games_won' => 0, 'games_lost' => 0,
                'points' => 0, 'rank' => 0, 'qualified' => false, 'tie_note' => null,
            ];
        }

        $completed = $matches->filter(fn ($m) => $m->status === 'completed' && $m->winner_id && ! $m->is_bye);
        $h2h = [];   // [winnerId][loserId] = true

        foreach ($completed as $m) {
            $ids = [1 => (int) $m->participant1_id, 2 => (int) $m->participant2_id];
            $winnerSlot = (int) $m->winner_id === $ids[1] ? 1 : 2;
            $loserSlot = 3 - $winnerSlot;
            if (! isset($rows[$ids[1]], $rows[$ids[2]])) {
                continue;
            }

            foreach ([1, 2] as $slot) {
                $rows[$ids[$slot]]['played']++;
            }
            $rows[$ids[$winnerSlot]]['wins']++;
            $rows[$ids[$winnerSlot]]['points'] += (int) $tournament->points_win;
            $rows[$ids[$loserSlot]]['losses']++;
            if (! $m->is_walkover) {
                $rows[$ids[$loserSlot]]['points'] += (int) $tournament->points_loss;
            }
            $h2h[$ids[$winnerSlot]][$ids[$loserSlot]] = true;

            foreach ($m->sets as $set) {
                foreach ([1, 2] as $slot) {
                    $own = $set->{"participant{$slot}_games"};
                    $opp = $set->{"participant" . (3 - $slot) . "_games"};
                    $rows[$ids[$slot]]['games_won'] += $own;
                    $rows[$ids[$slot]]['games_lost'] += $opp;
                    if ($own > $opp) {
                        $rows[$ids[$slot]]['sets_won']++;
                    } else {
                        $rows[$ids[$slot]]['sets_lost']++;
                    }
                }
            }
        }

        $pct = $tournament->standings_method === 'percentage';
        $setScore = fn ($r) => $pct
            ? ($r['sets_won'] + $r['sets_lost'] ? $r['sets_won'] / ($r['sets_won'] + $r['sets_lost']) : 0)
            : $r['sets_won'] - $r['sets_lost'];
        $gameScore = fn ($r) => $pct
            ? ($r['games_won'] + $r['games_lost'] ? $r['games_won'] / ($r['games_won'] + $r['games_lost']) : 0)
            : $r['games_won'] - $r['games_lost'];
        $seed = fn ($r) => $r['participant']->seed ?? PHP_INT_MAX;

        // 1) points, then sets, games, seed, name
        $list = array_values($rows);
        usort($list, function ($a, $b) use ($setScore, $gameScore, $seed) {
            return [$b['points'], $setScore($b), $gameScore($b), $seed($a), $a['participant']->name]
               <=> [$a['points'], $setScore($a), $gameScore($a), $seed($b), $b['participant']->name];
        });

        // 2) head-to-head decides a tie between exactly two teams on the same points
        if ($tournament->use_head_to_head) {
            $byPoints = [];
            foreach ($list as $i => $r) {
                $byPoints[$r['points']][] = $i;
            }
            foreach ($byPoints as $indexes) {
                if (count($indexes) !== 2) {
                    continue;
                }
                [$i, $j] = $indexes;
                $a = $list[$i]['participant']->id;
                $b = $list[$j]['participant']->id;
                if (isset($h2h[$b][$a])) {
                    [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
                    $list[$i]['tie_note'] = 'Ahead on head-to-head';
                } elseif (isset($h2h[$a][$b])) {
                    $list[$i]['tie_note'] = 'Ahead on head-to-head';
                }
            }
        }

        // 3) qualification: only once every (non-cancelled) match in the group is finished
        $live = $matches->filter(fn ($m) => $m->status !== 'cancelled' && ! $m->is_bye);
        $finished = $live->isNotEmpty() && $live->every(fn ($m) => $m->status === 'completed');
        $advance = $tournament->usesGroups() ? (int) $tournament->advance_per_group : 0;

        foreach ($list as $i => &$r) {
            $r['rank'] = $i + 1;
            $r['qualified'] = $finished && $i < $advance;
        }

        return [
            'group'    => $group,
            'rows'     => $list,
            'finished' => $finished,
            'played'   => $live->where('status', 'completed')->count(),
            'total'    => $live->count(),
        ];
    }

    // Tables for every group of a tournament
    public static function forTournament(Tournament $tournament): array
    {
        $groups = $tournament->groups()->with(['participants', 'matches.sets'])->get();

        return $groups->map(fn ($g) => self::forGroup($tournament, $g, $g->matches, $g->participants))->all();
    }

    // Is every group match finished?
    public static function groupStageFinished(Tournament $tournament): bool
    {
        $live = $tournament->matches()->where('stage', 'group')->where('status', '!=', 'cancelled');

        return (clone $live)->exists() && ! (clone $live)->where('status', '!=', 'completed')->exists();
    }
}