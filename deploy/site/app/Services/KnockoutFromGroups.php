<?php

namespace App\Services;

use App\Models\Tournament;
use Illuminate\Validation\ValidationException;

// Takes the top finishers of every group and places them in the knockout bracket.
// Group winners are seeded first; players from the same group are kept apart as long as possible.
class KnockoutFromGroups
{
    public function __construct(private BracketBuilder $builder)
    {
    }

    // [['participant' => ..., 'group' => 'A', 'rank' => 1, 'label' => 'Group A #1'], ...] in seed order
    public static function qualifiers(Tournament $tournament, bool $allowUnfinished = false): array
    {
        $tables = Standings::forTournament($tournament);
        $advance = (int) $tournament->advance_per_group;

        if (! $allowUnfinished && collect($tables)->contains(fn ($t) => ! $t['finished'])) {
            throw ValidationException::withMessages(['bracket' => 'The group stage is not finished yet. Record every group match first.']);
        }

        // Tiers: all group winners, then all runners-up, ...
        $tiers = [];
        foreach ($tables as $table) {
            foreach (array_slice($table['rows'], 0, $advance) as $i => $row) {
                $played = max(1, $row['played']);
                $tiers[$i][] = [
                    'participant' => $row['participant'],
                    'group'       => $table['group']->name,
                    'rank'        => $i + 1,
                    'label'       => $table['group']->label() . ' #' . ($i + 1),
                    // compare across groups of different sizes: averages per match
                    'score'       => [$row['points'] / $played, ($row['sets_won'] - $row['sets_lost']) / $played, ($row['games_won'] - $row['games_lost']) / $played],
                ];
            }
        }

        $count = collect($tiers)->flatten(1)->count();
        if ($count < 2) {
            throw ValidationException::withMessages(['bracket' => 'At least 2 qualifiers are needed for a knockout bracket.']);
        }

        $size = BracketBuilder::size($count);
        $order = BracketBuilder::seedOrder($size);
        $position = [];
        foreach ($order as $pos => $seed) {
            $position[$seed] = $pos;
        }
        // Round in which two bracket positions could meet (1 = first round ... log2(size) = final)
        $meet = fn (int $a, int $b) => (int) floor(log($a ^ $b, 2)) + 1;

        $seeded = [];        // seed number => qualifier
        $nextSeed = 1;
        foreach ($tiers as $tier) {
            usort($tier, fn ($x, $y) => $y['score'] <=> $x['score']);
            $free = range($nextSeed, $nextSeed + count($tier) - 1);

            foreach ($tier as $q) {
                // Pick the free seed that keeps this player furthest from their group-mates
                $best = null;
                foreach ($free as $s) {
                    $closest = PHP_INT_MAX;
                    foreach ($seeded as $otherSeed => $other) {
                        if ($other['group'] === $q['group']) {
                            $closest = min($closest, $meet($position[$s], $position[$otherSeed]));
                        }
                    }
                    if ($best === null || $closest > $best[1]) {
                        $best = [$s, $closest];
                    }
                }
                $seeded[$best[0]] = $q;
                $free = array_values(array_diff($free, [$best[0]]));
            }
            $nextSeed += count($tier);
        }

        ksort($seeded);

        return array_values($seeded);
    }

    public function generate(Tournament $tournament, bool $allowUnfinished = false): void
    {
        $qualifiers = self::qualifiers($tournament, $allowUnfinished);

        $this->builder->build(
            $tournament,
            array_map(fn ($q) => $q['participant'], $qualifiers),
            array_map(fn ($q) => $q['label'], $qualifiers)
        );
    }
}