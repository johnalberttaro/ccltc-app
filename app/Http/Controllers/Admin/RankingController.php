<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\RankingPeriod;
use App\Models\TennisMatch;
use App\Services\RankingLeaderboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    // Full Most Wins leaderboard + ranking period settings
    public function index(Request $request)
    {
        $periods = RankingPeriod::orderByDesc('start_date')->orderByDesc('id')->get();

        $period = $request->filled('period')
            ? $periods->firstWhere('id', (int) $request->query('period'))
            : null;
        $period ??= $periods->firstWhere('is_active', true) ?? $periods->first();

        $board = $period ? (new RankingLeaderboard($period))->build() : ['qualified' => collect(), 'pending' => collect()];

        $edit = $request->filled('edit') ? $periods->firstWhere('id', (int) $request->query('edit')) : null;

        return view('admin.rankings.index', [
            'periods' => $periods,
            'period' => $period,
            'qualified' => $board['qualified'],
            'pending' => $board['pending'],
            'edit' => $edit,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $period = DB::transaction(function () use ($data) {
            if ($data['is_active']) {
                RankingPeriod::query()->update(['is_active' => false]);
            }

            return RankingPeriod::create($data);
        });

        return redirect()->route('admin.rankings.index', ['period' => $period->id])
            ->with('success', 'Ranking period "' . $period->name . '" saved.');
    }

    public function update(Request $request, RankingPeriod $period)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($data, $period) {
            if ($data['is_active']) {
                RankingPeriod::where('id', '!=', $period->id)->update(['is_active' => false]);
            }
            $period->update($data);
        });

        return redirect()->route('admin.rankings.index', ['period' => $period->id])
            ->with('success', 'Ranking period "' . $period->name . '" updated.');
    }

    // Make this the period used by the Dashboard's Most Wins
    public function activate(RankingPeriod $period)
    {
        DB::transaction(function () use ($period) {
            RankingPeriod::query()->update(['is_active' => false]);
            $period->update(['is_active' => true]);
        });

        return redirect()->route('admin.rankings.index', ['period' => $period->id])
            ->with('success', '"' . $period->name . '" is now the active ranking period.');
    }

    // Deleting a period never touches the games themselves
    public function destroy(RankingPeriod $period)
    {
        $name = $period->name;
        $period->delete();

        return redirect()->route('admin.rankings.index')
            ->with('success', 'Ranking period "' . $name . '" deleted. No games were changed.');
    }

    // One player's overall record and full game history
    public function player(Request $request, Player $player)
    {
        $overall = RankingLeaderboard::overallRecords()[$player->id] ?? ['games' => 0, 'wins' => 0, 'losses' => 0, 'ties' => 0, 'win_rate' => 0.0];

        $period = $request->filled('period') ? RankingPeriod::find((int) $request->query('period')) : null;
        $period ??= RankingPeriod::active();

        $counted = [];
        $ranking = null;
        if ($period) {
            $leaderboard = new RankingLeaderboard($period);
            $counted = $leaderboard->countedMatchIds($player);
            $board = $leaderboard->build();
            $ranking = $board['qualified']->firstWhere('player.id', $player->id)
                ?? $board['pending']->firstWhere('player.id', $player->id);
        }

        $matches = TennisMatch::with(['players', 'sets'])
            ->whereHas('players', fn ($q) => $q->where('players.id', $player->id))
            ->orderByDesc('match_date')
            ->orderByDesc('start_time')
            ->orderByDesc('id')
            ->get();

        $history = $matches->map(function (TennisMatch $match) use ($player, $counted) {
            $side = (int) $match->players->firstWhere('id', $player->id)->pivot->side;
            $partners = $match->players->filter(fn ($p) => (int) $p->pivot->side === $side && $p->id !== $player->id)->pluck('full_name')->join(' / ');
            $opponents = $match->sideName(3 - $side);

            // Score from this player's point of view, e.g. "6-4" or "3-8"
            $score = $match->status === 'walkover'
                ? 'Walkover'
                : $match->sets->map(function ($s) use ($side) {
                    $a = $side === 1 ? $s->side1_games : $s->side2_games;
                    $b = $side === 1 ? $s->side2_games : $s->side1_games;

                    return "$a-$b";
                })->join(', ');

            return [
                'match' => $match,
                'partner' => $partners,
                'opponents' => $opponents,
                'score' => $score,
                'result' => $match->winner_side ? ((int) $match->winner_side === $side ? 'W' : 'L') : ($match->isTie() ? 'T' : null),
                'counted' => in_array($match->id, $counted, true),
            ];
        });

        return view('admin.rankings.player', compact('player', 'overall', 'period', 'ranking', 'history'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'games_required' => 'required|integer|min:1|max:100',
        ], [
            'end_date.after_or_equal' => 'The end date must be the same as or after the start date.',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}