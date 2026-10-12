<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Services\BracketBuilder;
use App\Services\GroupDraw;
use App\Services\KnockoutFromGroups;
use Illuminate\Http\Request;

class TournamentBracketController extends Controller
{
    public function __construct(private BracketBuilder $builder)
    {
    }

    // Single / double elimination straight from the participant list
    public function generate(Request $request, Tournament $tournament, KnockoutFromGroups $fromGroups)
    {
        if ($tournament->usesGroups()) {
            // Top finishers of each group; "force" = admin chose to continue before every group match is played
            $fromGroups->generate($tournament, $request->boolean('force'));
        } elseif ($tournament->isDirectKnockout()) {
            $this->builder->build($tournament, GroupDraw::drawOrder($tournament->participants()->get()));
        } else {
            return back()->with('error', 'Round robin tournaments do not use a bracket.');
        }

        return redirect()->route('admin.tournaments.manage', [$tournament, 'bracket'])
            ->with('success', 'Bracket generated. Click a match to record its result; winners move on automatically.');
    }

    public function reset(Tournament $tournament)
    {
        $this->builder->reset($tournament);

        return redirect()->route('admin.tournaments.manage', [$tournament, 'bracket'])
            ->with('success', 'The bracket was reset. Participants were kept.');
    }
}