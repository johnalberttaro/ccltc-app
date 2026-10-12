<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Player;
use App\Models\RankingPeriod;
use App\Models\TennisEvent;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Services\RankingLeaderboard;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'today'   => TennisMatch::whereDate('match_date', today())->count(),
            'total'   => TennisMatch::count(),
            'players' => Player::where('is_member', true)->count(),

            'upcomingEvents'      => TennisEvent::where('status', 'upcoming')->count(),
            'upcomingTournaments' => Tournament::whereIn('status', Tournament::UPCOMING_STATUSES)->count(),
            'activeAnnouncements' => Announcement::published()->count(),
            'scheduledAnnouncements' => Announcement::where('is_published', true)->where('published_at', '>', now())->count(),
            'ongoingEvents'       => TennisEvent::where('status', 'ongoing')->count(),
            'ongoingTournaments'  => Tournament::where('status', 'ongoing')->count(),
        ];

        // Next things on the calendar (events, tournaments that are not finished, upcoming meetings)
        $comingUp = TennisEvent::whereIn('status', ['upcoming', 'ongoing'])->displayOrder()->take(3)->get()
            ->map(fn ($e) => ['type' => 'Event', 'title' => $e->title, 'when' => $e->starts_at, 'status' => $e->status, 'url' => route('admin.events.edit', $e)])
            ->concat(
                Tournament::whereIn('status', [...Tournament::UPCOMING_STATUSES, 'ongoing'])->displayOrder()->take(3)->get()
                    ->map(fn ($t) => ['type' => 'Tournament', 'title' => $t->name, 'when' => $t->starts_at, 'status' => $t->status, 'url' => route('admin.tournaments.manage', [$t, 'overview'])])
            )
            ->concat(
                // Meetings / events written as announcements
                Announcement::whereNotNull('event_at')->where('event_at', '>=', now())->orderBy('event_at')->take(3)->get()
                    ->map(fn ($a) => ['type' => 'Meeting', 'title' => $a->title, 'when' => $a->event_at, 'status' => 'upcoming', 'url' => route('admin.announcements.edit', $a)])
            )
            ->sortBy('when')
            ->take(4)
            ->values();

        $todayMatches = TennisMatch::with(['players', 'sets'])
            ->whereDate('match_date', today())
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $recent = TennisMatch::with(['players', 'sets'])
            ->whereDate('match_date', '<', today())
            ->orderByDesc('match_date')
            ->orderByDesc('id')
            ->take(5)
            ->get();

        // Most Wins: total wins of all recorded games (as before)
        $topPlayers = Player::query()
            ->select('players.id', 'players.first_name', 'players.last_name')
            ->selectRaw('COUNT(*) as wins')
            ->join('match_participants', 'players.id', '=', 'match_participants.player_id')
            ->join('tennis_matches', 'tennis_matches.id', '=', 'match_participants.tennis_match_id')
            ->whereColumn('match_participants.side', 'tennis_matches.winner_side')
            ->groupBy('players.id', 'players.first_name', 'players.last_name')
            ->orderByDesc('wins')
            ->limit(5)
            ->get();

        // Official Ranking: fair ranking, every player compared on the same number of official games
        $rankingPeriod = RankingPeriod::active();
        $ranking = $rankingPeriod ? (new RankingLeaderboard($rankingPeriod))->build() : null;
        $topRanked = $ranking ? $ranking['qualified']->take(5) : collect();
        $pendingCount = $ranking ? $ranking['pending']->count() : 0;

        return view('admin.dashboard', compact('stats', 'todayMatches', 'recent', 'comingUp', 'topPlayers', 'rankingPeriod', 'topRanked', 'pendingCount'));
    }
}