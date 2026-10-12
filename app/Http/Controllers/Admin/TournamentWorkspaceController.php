<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\GroupDraw;
use App\Services\KnockoutFromGroups;
use App\Services\Standings;
use App\Support\BracketView;
use App\Support\TournamentProgress;

class TournamentWorkspaceController extends Controller
{
    // One page per tab: overview, participants, groups, matches, standings, bracket, results, settings
    public function show(Tournament $tournament, string $tab = 'overview')
    {
        $tabs = self::tabsFor($tournament);
        abort_unless(array_key_exists($tab, $tabs), 404);

        $tournament->load(['participants' => fn ($q) => $q->with('group')->orderByRaw('seed IS NULL')->orderBy('seed')->orderBy('name')]);

        $data = [
            'tournament' => $tournament,
            'tab'        => $tab,
            'tabs'       => $tabs,
            'progress'   => new TournamentProgress($tournament),
        ];

        if ($tab === 'groups') {
            $tournament->load('groups.participants');
            $data['check'] = GroupDraw::check(
                $tournament->participants->count(),
                (int) $tournament->group_count,
                (int) $tournament->group_size,
                (int) $tournament->advance_per_group
            );
            $data['unassigned'] = $tournament->participants->whereNull('group_id')->values();
        }

        if (in_array($tab, ['overview', 'bracket', 'matches', 'results'], true)) {
            $matches = $tournament->matches()
                ->with(['participant1', 'participant2', 'winner', 'sets', 'group'])
                ->orderByRaw("CASE stage WHEN 'group' THEN 0 WHEN 'winners' THEN 1 WHEN 'losers' THEN 2 ELSE 3 END")
                ->orderBy('round')
                ->orderBy('match_number')
                ->get();
            $tournament->setRelation('matches', $matches);
            $data['matches'] = $matches;
            $data['bracket'] = $tournament->hasBracket() ? new BracketView($tournament, $matches) : null;
            $data['champion'] = $tournament->champion();
            $data['nextMatch'] = $matches
                ->filter(fn ($m) => $m->isPlayable() && ! $m->winner_id)
                ->sortBy(fn ($m) => [$m->scheduled_at?->timestamp ?? PHP_INT_MAX, $m->id])
                ->first();
        }

        if (in_array($tab, ['overview', 'standings', 'bracket', 'groups'], true) && ($tournament->usesGroups() || $tournament->isRoundRobin())) {
            $data['tables'] = $tournament->schedule_generated_at ? Standings::forTournament($tournament) : [];
            $data['groupStageFinished'] = $tournament->schedule_generated_at && Standings::groupStageFinished($tournament);
        }

        if ($tab === 'bracket' && $tournament->usesGroups() && ! $tournament->hasBracket() && $tournament->schedule_generated_at) {
            $data['qualifiers'] = KnockoutFromGroups::qualifiers($tournament, true);
        }

        $view = $tab;

        return view("admin.tournaments.manage.{$view}", $data);
    }

    public static function tabsFor(Tournament $tournament): array
    {
        $tabs = [
            'overview'     => ['Overview', 'grid'],
            'participants' => ['Participants', 'users'],
        ];

        if ($tournament->usesGroups()) {
            $tabs['groups'] = ['Groups', 'list'];
        }

        $tabs['matches'] = ['Matches', 'ball'];

        if ($tournament->usesGroups() || $tournament->isRoundRobin()) {
            $tabs['standings'] = ['Standings', 'chart'];
        }
        if (! $tournament->isRoundRobin()) {
            $tabs['bracket'] = ['Bracket', 'bracket'];
        }

        $tabs['results'] = ['Results', 'trophy'];
        $tabs['settings'] = ['Settings', 'adjust'];

        return $tabs;
    }
}