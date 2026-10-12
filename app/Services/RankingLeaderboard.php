<?php

namespace App\Services;

use App\Models\Player;
use App\Models\RankingPeriod;
use App\Models\TennisMatch;
use Illuminate\Support\Collection;

/**
 * Fair "Most Wins" leaderboard.
 *
 * OVERALL RECORD  = every recorded game of the player (nothing is ever hidden or deleted).
 * RANKING RECORD  = the player's FIRST N official ranking games inside the ranking period,
 *                   where N = the period's "games required". Everyone is compared on the same N games.
 *
 * Which games count (deterministic, never random):
 *   - the game is marked "Official ranking game" (tennis_matches.is_ranking_game),
 *   - its date is inside the period (start date to end date),
 *   - it was actually played and has a result: a win/loss or a tie (walkovers do not count),
 *   - and it is one of the player's first N such games, in the order they were played
 *     (date, then start time, then the order they were recorded).
 *   Extra official games after the first N stay in the overall record only.
 *
 * A tie counts as a game played, but not as a win (and not as a loss).
 * A player is "qualified" only after completing N ranking games.
 * Order: ranking wins, then ranking win rate, then set difference, then game difference
 * (all from the counted ranking games only), then name.
 */
class RankingLeaderboard
{
    public function __construct(public readonly RankingPeriod $period)
    {
    }

    /**
     * @return array{qualified: Collection, pending: Collection}
     */
    public function build(): array
    {
        $required = max(1, (int) $this->period->games_required);
        $overall = $this->overallRecords();

        $matches = $this->rankingGames()->with(['players', 'sets'])->get();

        $rows = [];

        foreach ($matches as $match) {
            foreach ($match->players as $player) {
                $id = $player->id;
                $rows[$id] ??= [
                    'player' => $player,
                    'games' => 0, 'wins' => 0, 'losses' => 0, 'ties' => 0,
                    'sets_won' => 0, 'sets_lost' => 0, 'games_won' => 0, 'games_lost' => 0,
                    'extra' => 0,
                ];

                // Only the first N official games count; later ones are noted as extra
                if ($rows[$id]['games'] >= $required) {
                    $rows[$id]['extra']++;
                    continue;
                }

                $side = (int) $player->pivot->side;

                $rows[$id]['games']++;
                if ($match->winner_side === null) {
                    $rows[$id]['ties']++;
                } else {
                    $rows[$id][(int) $match->winner_side === $side ? 'wins' : 'losses']++;
                }

                foreach ($match->sets as $set) {
                    $mine = $side === 1 ? $set->side1_games : $set->side2_games;
                    $theirs = $side === 1 ? $set->side2_games : $set->side1_games;
                    $rows[$id]['games_won'] += $mine;
                    $rows[$id]['games_lost'] += $theirs;
                    if ($mine > $theirs) {
                        $rows[$id]['sets_won']++;
                    } elseif ($theirs > $mine) {
                        $rows[$id]['sets_lost']++;
                    }
                }
            }
        }

        $rows = collect($rows)->map(function ($r) use ($required, $overall) {
            $r['required'] = $required;
            $r['qualified'] = $r['games'] >= $required;
            $r['win_rate'] = $r['games'] > 0 ? $r['wins'] / $r['games'] * 100 : 0.0;
            $r['set_diff'] = $r['sets_won'] - $r['sets_lost'];
            $r['game_diff'] = $r['games_won'] - $r['games_lost'];
            $r['overall'] = $overall[$r['player']->id] ?? ['games' => 0, 'wins' => 0, 'losses' => 0, 'ties' => 0, 'win_rate' => 0.0];

            return $r;
        });

        $qualified = $rows->where('qualified', true)
            ->sort(fn ($a, $b) => [$b['wins'], $b['win_rate'], $b['set_diff'], $b['game_diff'], mb_strtolower($a['player']->full_name)]
                <=> [$a['wins'], $a['win_rate'], $a['set_diff'], $a['game_diff'], mb_strtolower($b['player']->full_name)])
            ->values();

        // Rank numbers: players equal on every tie-breaker share the same rank (1, 2, 2, 4 ...)
        $previous = null;
        $qualified = $qualified->map(function ($r, $i) use (&$previous) {
            $key = [$r['wins'], round($r['win_rate'], 6), $r['set_diff'], $r['game_diff']];
            $r['rank'] = ($previous && $previous['key'] === $key) ? $previous['rank'] : $i + 1;
            $previous = ['key' => $key, 'rank' => $r['rank']];

            return $r;
        });

        $pending = $rows->where('qualified', false)
            ->sort(fn ($a, $b) => [$b['games'], mb_strtolower($a['player']->full_name)] <=> [$a['games'], mb_strtolower($b['player']->full_name)])
            ->values();

        return ['qualified' => $qualified, 'pending' => $pending];
    }

    // Official ranking games inside the period, in the order they were played
    public function rankingGames()
    {
        return TennisMatch::query()
            ->where('is_ranking_game', true)
            ->where(fn ($q) => $q->whereNotNull('winner_side')->orWhere('status', 'completed'))
            ->where('status', '!=', 'walkover')
            ->whereDate('match_date', '>=', $this->period->start_date->toDateString())
            ->whereDate('match_date', '<=', $this->period->end_date->toDateString())
            ->orderBy('match_date')
            ->orderByRaw('CASE WHEN start_time IS NULL THEN 1 ELSE 0 END')
            ->orderBy('start_time')
            ->orderBy('id');
    }

    // IDs of the games that count for this player's ranking record (their first N official games)
    public function countedMatchIds(Player $player): array
    {
        return $this->rankingGames()
            ->whereHas('players', fn ($q) => $q->where('players.id', $player->id))
            ->limit(max(1, (int) $this->period->games_required))
            ->pluck('tennis_matches.id')
            ->all();
    }

    /**
     * Overall record of every player: ALL recorded games with a result (any date, ranking or not).
     * Ties count as games played (not wins, not losses).
     *
     * @return array<int, array{games:int, wins:int, losses:int, ties:int, win_rate:float}>
     */
    public static function overallRecords(): array
    {
        $rows = Player::query()
            ->select('players.id')
            ->selectRaw('COUNT(*) as games')
            ->selectRaw('SUM(CASE WHEN match_participants.side = tennis_matches.winner_side THEN 1 ELSE 0 END) as wins')
            ->selectRaw('SUM(CASE WHEN tennis_matches.winner_side IS NULL THEN 1 ELSE 0 END) as ties')
            ->join('match_participants', 'players.id', '=', 'match_participants.player_id')
            ->join('tennis_matches', 'tennis_matches.id', '=', 'match_participants.tennis_match_id')
            ->where(fn ($q) => $q->whereNotNull('tennis_matches.winner_side')->orWhere('tennis_matches.status', 'completed'))
            ->groupBy('players.id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $games = (int) $row->games;
            $wins = (int) $row->wins;
            $ties = (int) $row->ties;
            $out[$row->id] = [
                'games' => $games,
                'wins' => $wins,
                'losses' => $games - $wins - $ties,
                'ties' => $ties,
                'win_rate' => $games > 0 ? $wins / $games * 100 : 0.0,
            ];
        }

        return $out;
    }
}