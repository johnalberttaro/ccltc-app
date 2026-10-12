<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\TennisEvent;
use App\Models\Tournament;
use Illuminate\Http\Request;

class PublicInfoController extends Controller
{
    // ---------- Events ----------
    public function events(Request $request)
    {
        $events = TennisEvent::query()
            ->search($request->input('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->displayOrder()
            ->paginate(9)
            ->withQueryString();

        return view('public.events.index', compact('events'));
    }

    public function event(TennisEvent $event)
    {
        return view('public.events.show', compact('event'));
    }

    // ---------- Tournaments ----------
    public function tournaments(Request $request)
    {
        $tournaments = Tournament::query()
            ->public()
            ->search($request->input('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('match_type', $request->input('type')))
            ->displayOrder()
            ->paginate(9)
            ->withQueryString();

        return view('public.tournaments.index', compact('tournaments'));
    }

    public function tournament(Request $request, Tournament $tournament)
    {
        abort_if($tournament->status === 'draft', 404);

        $hasMatches = $tournament->matches()->where('is_bye', false)->exists();

        $tabs = ['details' => 'Details'];
        if ($tournament->usesGroups() && $tournament->groups_generated_at) {
            $tabs['groups'] = 'Groups';
        }
        if ($tournament->isRoundRobin() && $tournament->schedule_generated_at) {
            $tabs['standings'] = 'Standings';
        }
        if ($tournament->hasBracket()) {
            $tabs['bracket'] = 'Bracket';
        }
        if ($hasMatches) {
            $tabs += ['matches' => 'Matches', 'results' => 'Results'];
        }
        if ($tournament->participants()->exists()) {
            $tabs['participants'] = 'Participants';
        }

        $default = match (true) {
            $tournament->hasBracket()                    => 'bracket',
            isset($tabs['groups'])                       => 'groups',
            isset($tabs['standings'])                    => 'standings',
            default                                      => 'details',
        };
        $tab = $request->query('tab', $default);
        if (! array_key_exists($tab, $tabs)) {
            $tab = 'details';
        }

        $data = compact('tournament', 'tabs', 'tab');

        if (in_array($tab, ['bracket', 'matches', 'results'], true)) {
            $matches = $tournament->matches()
                ->with(['participant1', 'participant2', 'winner', 'sets', 'group'])
                ->orderByRaw("CASE stage WHEN 'group' THEN 0 WHEN 'winners' THEN 1 WHEN 'losers' THEN 2 ELSE 3 END")
                ->orderBy('group_id')
                ->orderBy('round')
                ->orderBy('match_number')
                ->get();
            $data['matches'] = $matches;
            $data['bracket'] = $tournament->hasBracket() ? new \App\Support\BracketView($tournament, $matches) : null;
        }

        if (in_array($tab, ['groups', 'standings'], true)) {
            $tournament->load('groups.participants');
            $data['tables'] = $tournament->schedule_generated_at ? \App\Services\Standings::forTournament($tournament) : [];
        }

        if (in_array($tab, ['results', 'standings'], true)) {
            $data['champion'] = $tournament->champion();
        }

        if ($tab === 'participants') {
            $data['participants'] = $tournament->participants()->with('group')->orderByRaw('seed IS NULL')->orderBy('seed')->orderBy('name')->get();
        }

        return view('public.tournaments.show', $data);
    }

    // ---------- Announcements ----------
    public function announcements(Request $request)
    {
        $announcements = Announcement::published()
            ->search($request->input('q'))
            ->displayOrder()
            ->paginate(10)
            ->withQueryString();

        return view('public.announcements.index', compact('announcements'));
    }

    public function announcement(Announcement $announcement)
    {
        // Unpublished or scheduled announcements are hidden from the public
        abort_unless($announcement->isLive(), 404);

        return view('public.announcements.show', compact('announcement'));
    }
}