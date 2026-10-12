<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

// Checks a set-by-set tennis score and works out the winner.
class TennisScore
{
    /**
     * @param  array  $rows        [['p1' => '6', 'p2' => '4', 'tb' => null], ...]
     *                             tb = the LOSER's tiebreak points (only for 7-6 / 5-4 sets).
     *                             For a 10-point match tiebreak, p1/p2 are the tiebreak points (e.g. 10 and 8).
     * @param  int    $bestOf      1, 3 or 5
     * @param  string $field       error key
     * @param  int    $games       games to win a set: 6 (standard), 4 (short sets), 7, or 8 (pro set)
     * @param  bool   $matchTiebreak  the deciding set is a 10-point match tiebreak
     * @return array  ['sets' => [[p1, p2, tb1, tb2, mtb], ...], 'winner' => 1|2]
     */
    public static function evaluate(array $rows, int $bestOf, string $field = 'score', int $games = 6, bool $matchTiebreak = false): array
    {
        $fail = function (string $message) use ($field) {
            throw ValidationException::withMessages([$field => $message]);
        };

        $matchTiebreak = $matchTiebreak && $bestOf > 1;
        $sets = [];
        $sawEmpty = false;

        foreach (array_values($rows) as $i => $row) {
            $p1 = self::num($row['p1'] ?? null);
            $p2 = self::num($row['p2'] ?? null);
            $tb = self::num($row['tb'] ?? null);
            $n = $i + 1;

            if ($p1 === null && $p2 === null) {
                $sawEmpty = true;
                continue;
            }
            if ($p1 === null || $p2 === null) {
                $fail("Set $n: enter a score for both sides.");
            }
            if ($sawEmpty) {
                $fail("Set $n: fill in the sets in order without skipping one.");
            }
            $sets[] = ['p1' => $p1, 'p2' => $p2, 'tb' => $tb];
        }

        if (empty($sets)) {
            $fail('Enter the score, or choose a walkover winner.');
        }
        if (count($sets) > $bestOf) {
            $fail($bestOf === 1 ? 'This tournament plays 1 set per match.' : "A best-of-$bestOf match can't have more than $bestOf sets.");
        }

        $setsToWin = intdiv($bestOf, 2) + 1;
        $won = [1 => 0, 2 => 0];
        $result = [];

        foreach ($sets as $i => $s) {
            $n = $i + 1;
            if (max($won) >= $setsToWin) {
                $fail("Set $n was entered after the match was already won.");
            }

            $hi = max($s['p1'], $s['p2']);
            $lo = min($s['p1'], $s['p2']);
            $winner = $s['p1'] > $s['p2'] ? 1 : 2;
            $isDecider = $matchTiebreak && $won[1] === $setsToWin - 1 && $won[2] === $setsToWin - 1;

            if ($isDecider) {
                // 10-point match tiebreak: first to 10, win by 2
                if ($hi < 10 || $hi - $lo < 2 || ($hi > 10 && $hi - $lo !== 2)) {
                    $fail("Set $n is a match tiebreak: enter the points, e.g. 10-8 or 12-10 (first to 10, win by 2).");
                }
                $won[$winner]++;
                $result[] = [
                    'p1' => $winner === 1 ? 1 : 0, 'p2' => $winner === 2 ? 1 : 0,
                    'tb1' => $s['p1'], 'tb2' => $s['p2'], 'mtb' => true,
                ];
                continue;
            }

            $valid = ($hi === $games && $lo <= $games - 2) || ($hi === $games + 1 && in_array($lo, [$games - 1, $games], true));
            if (! $valid) {
                $examples = match ($games) {
                    4 => '4-2, 5-3, 5-4',
                    7 => '7-4, 7-5, 8-6, 8-7',
                    8 => '8-5, 8-6, 9-7, 9-8',
                    default => '6-4, 7-5, 7-6',
                };
                $fail("Set $n: {$s['p1']}-{$s['p2']} is not a valid set score (e.g. $examples).");
            }

            $tiebreakSet = $hi === $games + 1 && $lo === $games;
            if ($s['tb'] !== null && ! $tiebreakSet) {
                $fail("Set $n: tiebreak points are only entered for " . ($games + 1) . "-$games sets.");
            }

            $won[$winner]++;
            $tb1 = $tb2 = null;
            if ($tiebreakSet && $s['tb'] !== null) {
                $winnerTb = max(7, $s['tb'] + 2);
                [$tb1, $tb2] = $winner === 1 ? [$winnerTb, $s['tb']] : [$s['tb'], $winnerTb];
            }

            $result[] = ['p1' => $s['p1'], 'p2' => $s['p2'], 'tb1' => $tb1, 'tb2' => $tb2, 'mtb' => false];
        }

        if (max($won) < $setsToWin) {
            $fail($setsToWin === 1 ? 'Enter a finished set.' : "The match isn't finished: a side needs to win $setsToWin sets.");
        }

        return ['sets' => $result, 'winner' => $won[1] > $won[2] ? 1 : 2];
    }

    private static function num($value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}