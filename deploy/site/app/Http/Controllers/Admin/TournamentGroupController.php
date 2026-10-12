<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use App\Services\GroupDraw;
use App\Services\GroupSchedule;
use Illuminate\Http\Request;

class TournamentGroupController extends Controller
{
    public function __construct(private GroupDraw $draw)
    {
    }

    public function generate(Tournament $tournament)
    {
        $this->draw->generate($tournament);

        return redirect()->route('admin.tournaments.manage', [$tournament, 'groups'])
            ->with('success', 'Groups generated. Review them, move players if needed, then create the match schedule.');
    }

    public function reset(Tournament $tournament)
    {
        $this->draw->reset($tournament);

        return redirect()->route('admin.tournaments.manage', [$tournament, 'groups'])
            ->with('success', 'Groups were reset. Participants were kept.');
    }

    // Draw again: reset + generate in one step (only before any results)
    public function reshuffle(Tournament $tournament)
    {
        if ($tournament->hasResults()) {
            return back()->with('error', 'Results have already been recorded. Use "Reset groups" in Settings if you really want to start over.');
        }

        $this->draw->reset($tournament);
        $this->draw->generate($tournament);

        return back()->with('success', 'Groups were drawn again.');
    }

    // Move one participant to another group
    public function move(Request $request, Tournament $tournament, TournamentParticipant $participant)
    {
        $data = $request->validate([
            'group_id' => 'required|integer',
        ]);

        $target = $tournament->groups()->whereKey($data['group_id'])->first();
        abort_unless($target, 404);

        $lost = GroupDraw::resultsInGroups([$participant->group_id, $target->id]);
        if ($lost > 0 && ! $request->boolean('confirm_loss')) {
            return back()->with('error', "Moving {$participant->name} would delete {$lost} recorded "
                . str('result')->plural($lost) . ' in the affected groups. Tick the confirmation box to continue.');
        }

        $affected = $this->draw->move($participant, $target);

        // Matches already created? Rebuild them for the two groups involved
        if ($tournament->schedule_generated_at) {
            foreach ($tournament->groups()->whereIn('id', $affected)->get() as $group) {
                app(GroupSchedule::class)->buildGroup($group);
            }
        }

        return back()->with('success', "{$participant->name} moved to {$target->label()}.");
    }
}