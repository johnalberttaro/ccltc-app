<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\TennisMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TennisMatchController extends Controller
{
    // Admin: all games of one date (any date), with Edit / Delete
    public function index(Request $request)
    {
        try {
            $date = $request->filled('date') ? \Illuminate\Support\Carbon::parse($request->query('date'))->startOfDay() : today();
        } catch (\Throwable $e) {
            $date = today();
        }

        $matches = TennisMatch::with(['players', 'sets'])
            ->whereDate('match_date', $date->toDateString())
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        // The most recent days that have games, as quick links
        $days = TennisMatch::query()
            ->selectRaw('DATE(match_date) as day, COUNT(*) as total')
            ->groupByRaw('DATE(match_date)')
            ->orderByDesc('day')
            ->limit(21)
            ->get();

        return view('admin.games.index', compact('date', 'matches', 'days'));
    }

    // Admin: record match form
    public function create()
    {
        return view('admin.matches.create', ['players' => $this->suggestions(), 'match' => null, 'prefill' => []]);
    }

    // Admin: edit a recorded match (fix a misspelled name, the score, date...)
    public function edit(TennisMatch $match)
    {
        $match->load(['players', 'sets']);

        // Format box value: long single sets are "to7" / "pro", the rest is 1 / 3 / 5
        $format = match ((int) $match->games_per_set) {
            7 => 'to7',
            8 => 'pro',
            default => (string) ((int) $match->best_of ?: 1),
        };

        $prefill = [
            'match_type'  => $match->match_type,
            'format'      => $format,
            'match_date'  => $match->match_date->toDateString(),
            'start_time'  => $match->start_time ? substr($match->start_time, 0, 5) : null,
            'venue'       => $match->venue,
            'referee'     => $match->referee,
            'ball_boy'    => $match->ball_boy,
            'is_ranking_game' => $match->is_ranking_game ? '1' : '0',
            'status'      => $match->status,
            'winner_side' => $match->status === 'completed' ? null : $match->winner_side,
        ];

        foreach ([1, 2] as $side) {
            $names = $match->players->filter(fn ($p) => (int) $p->pivot->side === $side)->values();
            $prefill["p{$side}a"] = isset($names[0]) ? PlayerController::cleanName($names[0]->full_name) : null;
            $prefill["p{$side}b"] = isset($names[1]) ? PlayerController::cleanName($names[1]->full_name) : null;
        }

        foreach ($match->sets as $i => $set) {
            $tb = ($set->side1_tiebreak !== null && $set->side2_tiebreak !== null)
                ? min($set->side1_tiebreak, $set->side2_tiebreak)   // the form asks for the LOSER's tiebreak points
                : null;
            $prefill['sets'][$i] = ['p1' => $set->side1_games, 'p2' => $set->side2_games, 'tb' => $tb];
        }

        return view('admin.matches.create', [
            'players' => $this->suggestions(),
            'match' => $match,
            'prefill' => $prefill,
            'returnTo' => request('return') === 'games' ? 'games' : null,
        ]);
    }

    // Save a new match
    public function store(Request $request)
    {
        $match = $this->save($request, null);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', $match->isTie()
                ? 'Match recorded: draw (' . $match->sideName(1) . ' drew with ' . $match->sideName(2) . ').'
                : 'Match recorded: ' . $match->winnerName() . ' won.');
    }

    // Save changes to a recorded match
    public function update(Request $request, TennisMatch $match)
    {
        $match = $this->save($request, $match);

        // Opened from "All Games": go back to that page, on the game's (possibly new) date
        $to = $request->input('return_to') === 'games'
            ? redirect()->route('admin.games.index', ['date' => $match->match_date->toDateString()])
            : redirect()->route('admin.dashboard');

        return $to
            ->with('success', $match->isTie()
                ? 'Match updated: draw (' . $match->sideName(1) . ' drew with ' . $match->sideName(2) . ').'
                : 'Match updated: ' . $match->winnerName() . ' won.');
    }

    // Member names for the suggestion list while typing
    private function suggestions()
    {
        return Player::where('is_member', true)->orderBy('first_name')->orderBy('last_name')->get();
    }

    // Shared by "Record Match" and "Edit": check everything, then create or update the match
    private function save(Request $request, ?TennisMatch $existing): TennisMatch
    {
        $data = $request->validate([
            'match_date'  => 'required|date',
            'start_time'  => 'nullable|date_format:H:i',
            'venue'       => 'nullable|string|max:100',
            'referee'     => 'nullable|string|max:100',
            'ball_boy'    => 'nullable|string|max:150',
            'is_ranking_game' => 'nullable|boolean',
            'match_type'  => 'required|in:singles,doubles',
            'format'      => 'required|in:1,to7,pro,3,5',
            'status'      => 'required|in:completed,retired,walkover',
            'p1a'         => 'required|string|max:100',
            'p1b'         => 'nullable|required_if:match_type,doubles|string|max:100',
            'p2a'         => 'required|string|max:100',
            'p2b'         => 'nullable|required_if:match_type,doubles|string|max:100',
            'winner_side' => 'nullable|in:1,2',
            'sets'        => 'array',
            'sets.*.p1'   => 'nullable|integer|min:0|max:9',
            'sets.*.p2'   => 'nullable|integer|min:0|max:9',
            'sets.*.tb'   => 'nullable|integer|min:0|max:99',
        ], [
            'p1a.required'    => 'Type the name of the first player of Team 1.',
            'p2a.required'    => 'Type the name of the first player of Team 2.',
            'p1b.required_if' => 'Doubles needs a second player for Team 1.',
            'p2b.required_if' => 'Doubles needs a second player for Team 2.',
        ]);

        // Build the two teams from the typed names (1 player each for singles, 2 for doubles).
        // Any name can be used: members, guests, nicknames.
        $doubles = $data['match_type'] === 'doubles';
        $team1 = array_values(array_filter([PlayerController::cleanName($data['p1a']), $doubles ? PlayerController::cleanName($data['p1b'] ?? '') : '']));
        $team2 = array_values(array_filter([PlayerController::cleanName($data['p2a']), $doubles ? PlayerController::cleanName($data['p2b'] ?? '') : '']));

        $everyone = array_map(fn ($name) => mb_strtolower($name), array_merge($team1, $team2));
        if (count($everyone) !== count(array_unique($everyone))) {
            $this->reject('Each player can only appear once in a match.', 'p1a');
        }

        // One long set: "to7" = first to 7 (tiebreak at 7-7), "pro" = first to 8 (tiebreak at 8-8).
        // Other formats use normal sets to 6 (tiebreak at 6-6).
        $longSet = ['to7' => 7, 'pro' => 8];
        $games = $longSet[$data['format']] ?? 6;
        $bestOf = isset($longSet[$data['format']]) ? 1 : (int) $data['format'];
        $sets = $this->cleanSets($data['sets'] ?? [], $bestOf);
        $winnerSide = $this->decideWinner($data['status'], $data['winner_side'] ?? null, $sets, $bestOf, $games);

        return DB::transaction(function () use ($data, $sets, $winnerSide, $bestOf, $games, $team1, $team2, $existing) {
            // Typed names -> player records. A name that is not a member is saved for the match only
            // (it is NOT added to Members).
            $known = Player::all();
            $team1 = array_map(fn ($name) => $this->playerIdFor($name, $known), $team1);
            $team2 = array_map(fn ($name) => $this->playerIdFor($name, $known), $team2);

            $attributes = [
                'match_date'    => $data['match_date'],
                'start_time'    => $data['start_time'] ?? null,
                'venue'         => $data['venue'] ?? null,
                'referee'       => $this->cleanText($data['referee'] ?? null),
                'ball_boy'      => $this->cleanText($data['ball_boy'] ?? null),
                'is_ranking_game' => (bool) ($data['is_ranking_game'] ?? false),
                'match_type'    => $data['match_type'],
                'best_of'       => $bestOf,
                'games_per_set' => $games,
                'status'        => $data['status'],
                'winner_side'   => $winnerSide,
            ];

            $oldPlayerIds = [];
            if ($existing) {
                $oldPlayerIds = $existing->players()->pluck('players.id')->all();
                $existing->update($attributes);
                $existing->players()->detach();
                $existing->sets()->delete();
                $match = $existing;
            } else {
                $match = TennisMatch::create($attributes);
            }

            foreach ($team1 as $playerId) {
                $match->players()->attach($playerId, ['side' => 1]);
            }
            foreach ($team2 as $playerId) {
                $match->players()->attach($playerId, ['side' => 2]);
            }

            foreach ($sets as $i => $s) {
                $tb1 = null;
                $tb2 = null;

                // Tiebreak: user enters the LOSER's points; winner's = max(7, loser + 2)
                if ($s['tb'] !== null && $this->isTiebreakSet($s['p1'], $s['p2'], $games)) {
                    $loserTb = $s['tb'];
                    $winnerTb = max(7, $loserTb + 2);
                    [$tb1, $tb2] = $s['p1'] > $s['p2'] ? [$winnerTb, $loserTb] : [$loserTb, $winnerTb];
                }

                $match->sets()->create([
                    'set_number'     => $i + 1,
                    'side1_games'    => $s['p1'],
                    'side2_games'    => $s['p2'],
                    'side1_tiebreak' => $tb1,
                    'side2_tiebreak' => $tb2,
                ]);
            }

            // A misspelled guest name that is no longer used anywhere is cleaned up (members are kept)
            if ($oldPlayerIds) {
                Player::whereIn('id', $oldPlayerIds)
                    ->where('is_member', false)
                    ->whereDoesntHave('matches')
                    ->delete();
            }

            return $match->load(['players', 'sets']);
        });
    }

    // Delete a match (its sets and participants are deleted automatically)
    public function destroy(TennisMatch $match)
    {
        $match->delete();

        return back()->with('success', 'Match deleted.');
    }

    // Trim extra spaces; an empty box is saved as "no name"
    private function cleanText(?string $text): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

        return $text === '' ? null : $text;
    }

    // ---------- Typed player names ----------

    // Same name as before (member or not) -> same record; a new name -> a non-member record
    private function playerIdFor(string $name, $known): int
    {
        $player = PlayerController::findByName($name, $known);

        if (! $player) {
            $player = new Player(PlayerController::splitName($name));
            $player->is_member = false;
            $player->save();
            $known->push($player);
        }

        return $player->id;
    }

    // ---------- Score rules ----------

    // Keep only filled set rows, in order, and check for gaps / half-filled rows
    private function cleanSets(array $rows, int $bestOf): array
    {
        $sets = [];
        $sawEmpty = false;

        foreach (array_values($rows) as $i => $row) {
            $p1 = $row['p1'] ?? null;
            $p2 = $row['p2'] ?? null;
            $tb = $row['tb'] ?? null;
            $n = $i + 1;

            if ($p1 === null && $p2 === null) {
                $sawEmpty = true;
                continue;
            }
            if ($p1 === null || $p2 === null) {
                $this->reject("Set $n: enter scores for both teams.");
            }
            if ($sawEmpty) {
                $this->reject("Set $n: fill in sets in order without skipping one.");
            }

            $sets[] = ['p1' => (int) $p1, 'p2' => (int) $p2, 'tb' => $tb === null ? null : (int) $tb];
        }

        if (count($sets) > $bestOf) {
            $this->reject($bestOf === 1
                ? 'A 1-set match can only have one set.'
                : "A best-of-$bestOf match can't have more than $bestOf sets.");
        }

        return $sets;
    }

    // Work out the winner from the sets (or use the chosen winner for retired/walkover)
    // Returns 1 or 2 (the winning team), or null for a tie
    private function decideWinner(string $status, $chosenWinner, array $sets, int $bestOf, int $games = 6): ?int
    {
        if ($status !== 'completed') {
            if (empty($chosenWinner)) {
                $this->reject('Choose the winner for a retired or walkover match.', 'winner_side');
            }

            return (int) $chosenWinner;
        }

        if (empty($sets)) {
            $this->reject('Enter the set score for a completed match.');
        }

        // Tie: a 1-set game that ended level without a tiebreak (e.g. 6-6, 7-7, 8-8, or 5-5 when time ran out)
        if ($bestOf === 1 && count($sets) === 1 && $sets[0]['p1'] === $sets[0]['p2']) {
            $level = $sets[0]['p1'];
            if ($level < 1 || $level > $games) {
                $this->reject("Set 1: {$level}-{$level} can't be a draw in a set to $games.");
            }
            if ($sets[0]['tb'] !== null) {
                $this->reject("Set 1: for a draw leave TB empty. If a tiebreak was played, enter " . ($games + 1) . "-$games and the TB points.");
            }

            return null;
        }

        $setsToWin = intdiv($bestOf, 2) + 1;   // 1 for one set, 2 for best of 3, 3 for best of 5
        $won = [1 => 0, 2 => 0];

        foreach ($sets as $i => $s) {
            $n = $i + 1;

            if (max($won) >= $setsToWin) {
                $this->reject("Set $n was entered after the match was already won.");
            }
            if (! $this->isValidSet($s['p1'], $s['p2'], $games)) {
                $examples = match ($games) {
                    7 => '7-4, 7-5, 8-6, 8-7',
                    8 => '8-5, 8-6, 9-7, 9-8',
                    default => '6-4, 7-5, 7-6',
                };
                $this->reject("Set $n: {$s['p1']}-{$s['p2']} is not a valid score (e.g. $examples).");
            }
            if ($s['tb'] !== null && ! $this->isTiebreakSet($s['p1'], $s['p2'], $games)) {
                $this->reject("Set $n: tiebreak points are only for " . ($games + 1) . "-$games sets.");
            }

            $won[$s['p1'] > $s['p2'] ? 1 : 2]++;
        }

        if (max($won) < $setsToWin) {
            $this->reject("The match isn't finished yet: a team needs to win $setsToWin sets.");
        }

        return $won[1] > $won[2] ? 1 : 2;
    }

    // Valid finished set.
    // Sets to 6: 6-0 to 6-4, 7-5, or 7-6 (tiebreak at 6-6).
    // Sets to 7: 7-0 to 7-5, 8-6, or 8-7 (tiebreak at 7-7).
    // Sets to 8 (pro set): 8-0 to 8-6, 9-7, or 9-8 (tiebreak at 8-8).
    private function isValidSet(int $a, int $b, int $games = 6): bool
    {
        $hi = max($a, $b);
        $lo = min($a, $b);

        return ($hi === $games && $lo <= $games - 2)
            || ($hi === $games + 1 && in_array($lo, [$games - 1, $games], true));
    }

    private function isTiebreakSet(int $a, int $b, int $games = 6): bool
    {
        return max($a, $b) === $games + 1 && min($a, $b) === $games;
    }

    private function reject(string $message, string $field = 'sets'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}