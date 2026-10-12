<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Creates single or double elimination brackets.
class BracketBuilder
{
    public const KNOCKOUT_STAGES = ['winners', 'losers', 'final'];

    public function __construct(private MatchResults $results)
    {
    }

    public static function size(int $entrants): int
    {
        $size = 2;
        while ($size < $entrants) {
            $size *= 2;
        }

        return $size;
    }

    // Standard seeding positions, e.g. 8 => [1, 8, 4, 5, 2, 7, 3, 6]: seeds 1 and 2 can only meet in the final
    public static function seedOrder(int $size): array
    {
        $order = [1, 2];
        while (count($order) < $size) {
            $total = count($order) * 2;
            $next = [];
            foreach ($order as $seed) {
                $next[] = $seed;
                $next[] = $total + 1 - $seed;
            }
            $order = $next;
        }

        return $order;
    }

    // Single elimination round code: F, SF1, QF3, R16-5 ...
    public static function seCode(int $round, int $rounds, int $number): string
    {
        return match ($rounds - $round) {
            0       => 'F',
            1       => "SF{$number}",
            2       => "QF{$number}",
            default => 'R' . (2 ** ($rounds - $round + 1)) . "-{$number}",
        };
    }

    /**
     * Build the bracket.
     * $entrants: participants in seed order (index 0 = best). Missing spots become byes.
     * $labels:   optional label per entrant index, e.g. "Group A #1".
     */
    public function build(Tournament $tournament, array $entrants, array $labels = []): void
    {
        if ($tournament->knockout_generated_at) {
            throw ValidationException::withMessages(['bracket' => 'The bracket has already been generated. Reset it first if you really want to draw again.']);
        }

        $count = count($entrants);
        if ($count < 2) {
            throw ValidationException::withMessages(['bracket' => 'At least 2 participants are needed for a bracket.']);
        }
        if ($count > 128) {
            throw ValidationException::withMessages(['bracket' => 'Brackets support up to 128 participants.']);
        }

        DB::transaction(function () use ($tournament, $entrants, $labels, $count) {
            $tournament->matches()->whereIn('stage', self::KNOCKOUT_STAGES)->delete();

            $double = $tournament->knockoutIsDouble();
            $size = self::size($count);
            $rounds = (int) round(log($size, 2));
            $order = self::seedOrder($size);
            $make = fn (array $attrs) => $tournament->matches()->create($attrs + ['status' => 'scheduled']);

            // ---------- Winners bracket (or the whole single elimination bracket) ----------
            $wb = [];
            for ($r = 1; $r <= $rounds; $r++) {
                $perRound = intdiv($size, 2 ** $r);
                for ($p = 0; $p < $perRound; $p++) {
                    $isFinal = ! $double && $r === $rounds;
                    $code = $double
                        ? ($r === $rounds ? 'WF' : "W{$r}-" . ($p + 1))
                        : self::seCode($r, $rounds, $p + 1);
                    $wb[$r][$p] = $make([
                        'stage'        => $isFinal ? 'final' : 'winners',
                        'round'        => $r,
                        'match_number' => $p + 1,
                        'code'         => $code,
                    ]);
                }
            }

            // ---------- Losers bracket + grand final (double elimination) ----------
            $lb = [];
            $gf = $gf2 = null;
            if ($double) {
                $lbRounds = 2 * ($rounds - 1);
                for ($j = 1; $j <= $lbRounds; $j++) {
                    $perRound = intdiv($size, 2 ** (intdiv($j + 1, 2) + 1));
                    for ($q = 0; $q < $perRound; $q++) {
                        $lb[$j][$q] = $make([
                            'stage'        => 'losers',
                            'round'        => $j,
                            'match_number' => $q + 1,
                            'code'         => $j === $lbRounds ? 'LF' : "L{$j}-" . ($q + 1),
                        ]);
                    }
                }

                $gf = $make(['stage' => 'final', 'round' => 1, 'match_number' => 1, 'code' => 'GF']);
                if ($tournament->grand_final_reset) {
                    $gf2 = $make([
                        'stage' => 'final', 'round' => 2, 'match_number' => 1, 'code' => 'GF2',
                        'participant1_source' => 'Winner of GF (if needed)',
                        'participant2_source' => 'Loser of GF (if needed)',
                    ]);
                }
            }

            $link = function (TournamentMatch $from, TournamentMatch $to, int $slot, bool $loser = false) {
                if ($loser) {
                    $from->loser_next_match_id = $to->id;
                    $from->loser_next_slot = $slot;
                } else {
                    $from->next_match_id = $to->id;
                    $from->next_slot = $slot;
                }
                $from->save();
                $to->{"participant{$slot}_source"} = ($loser ? 'Loser of ' : 'Winner of ') . $from->code;
                $to->save();
            };

            // Winners advance inside the winners bracket
            for ($r = 1; $r < $rounds; $r++) {
                foreach ($wb[$r] as $p => $match) {
                    $link($match, $wb[$r + 1][intdiv($p, 2)], $p % 2 + 1);
                }
            }

            if ($double) {
                $lbRounds = 2 * ($rounds - 1);
                $link($wb[$rounds][0], $gf, 1);

                if ($rounds === 1) {
                    $link($wb[1][0], $gf, 2, true);
                } else {
                    // Losers of winners round 1 meet in losers round 1
                    foreach ($wb[1] as $p => $match) {
                        $link($match, $lb[1][intdiv($p, 2)], $p % 2 + 1, true);
                    }
                    // Losers of winners round k+1 drop into losers round 2k (order flipped to avoid quick rematches)
                    for ($k = 1; $k <= $rounds - 1; $k++) {
                        $drop = $wb[$k + 1];
                        $countInRound = count($drop);
                        foreach ($drop as $p => $match) {
                            $q = $k % 2 === 1 ? $countInRound - 1 - $p : $p;
                            $link($match, $lb[2 * $k][$q], 2, true);
                        }
                    }
                    // Inside the losers bracket
                    for ($j = 1; $j <= $lbRounds; $j++) {
                        foreach ($lb[$j] as $q => $match) {
                            if ($j === $lbRounds) {
                                $link($match, $gf, 2);
                            } elseif ($j % 2 === 1) {
                                $link($match, $lb[$j + 1][$q], 1);
                            } else {
                                $link($match, $lb[$j + 1][intdiv($q, 2)], $q % 2 + 1);
                            }
                        }
                    }
                }
            }

            // Place entrants in round 1 (empty spots = byes for the top seeds)
            foreach ($wb[1] as $p => $match) {
                foreach ([1, 2] as $slot) {
                    $seedIndex = $order[2 * $p + $slot - 1] - 1;
                    $entrant = $entrants[$seedIndex] ?? null;
                    $match->{"participant{$slot}_id"} = $entrant?->id;
                    $match->{"participant{$slot}_source"} = $entrant ? ($labels[$seedIndex] ?? null) : 'Bye';
                }
                $match->save();
            }

            $tournament->knockout_generated_at = now();
            $tournament->save();

            $this->results->resolveByes($tournament);
        });
    }

    public function reset(Tournament $tournament): void
    {
        DB::transaction(function () use ($tournament) {
            $tournament->matches()->whereIn('stage', self::KNOCKOUT_STAGES)->delete();
            $tournament->knockout_generated_at = null;
            $tournament->save();
            $this->results->syncStatus($tournament->fresh());
        });
    }
}