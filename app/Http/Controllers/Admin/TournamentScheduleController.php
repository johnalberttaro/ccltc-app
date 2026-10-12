<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\GroupSchedule;

class TournamentScheduleController extends Controller
{
    public function __construct(private GroupSchedule $schedule)
    {
    }

    // Create every group (or round robin) match
    public function generate(Tournament $tournament)
    {
        $count = $this->schedule->generate($tournament);

        return redirect()->route('admin.tournaments.manage', [$tournament, 'matches'])
            ->with('success', "{$count} matches created. Set dates and courts if you like, then record results as they finish.");
    }

    public function reset(Tournament $tournament)
    {
        $this->schedule->reset($tournament);

        return redirect()->route('admin.tournaments.manage', [$tournament, $tournament->usesGroups() ? 'groups' : 'matches'])
            ->with('success', 'The match schedule and its results were removed.');
    }
}