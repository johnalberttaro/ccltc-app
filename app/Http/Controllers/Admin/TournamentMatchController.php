<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentMatch;
use App\Services\MatchResults;
use App\Support\TennisScore;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TournamentMatchController extends Controller
{
    public function __construct(private MatchResults $results)
    {
    }

    // Save a result from the "Record result" dialog
    public function result(Request $request, Tournament $tournament, TournamentMatch $match)
    {
        $field = "result_{$match->id}";

        try {
            $request->validate([
                'walkover'  => 'nullable|in:1,2',
                'sets'      => 'array',
                'sets.*.p1' => 'nullable|integer|min:0|max:99',
                'sets.*.p2' => 'nullable|integer|min:0|max:99',
                'sets.*.tb' => 'nullable|integer|min:0|max:99',
            ]);

            if ($request->filled('walkover')) {
                $this->results->record($match, (int) $request->input('walkover'), [], true);
            } else {
                $score = TennisScore::evaluate(
                    $request->input('sets', []),
                    (int) ($tournament->best_of ?: 1),
                    $field,
                    (int) ($tournament->games_per_set ?: 6),
                    $tournament->final_set === 'match_tiebreak'
                );
                $this->results->record($match, $score['winner'], $score['sets'], false);
            }
        } catch (ValidationException $e) {
            return back()
                ->withErrors([$field => collect($e->errors())->flatten()->first()])
                ->withInput()
                ->with('open_result', $match->id);
        }

        $match->refresh()->load('winner');
        $message = "{$match->winner->name} wins {$match->code}.";
        if ($champion = $tournament->fresh()->champion()) {
            $message = "{$champion->name} " . ($tournament->match_type === 'singles' ? 'is the champion' : 'are the champions') . '! The tournament is marked as completed.';
        } elseif ($match->next_match_id) {
            $message = "{$match->winner->name} advances.";
        }

        return back()->with('success', $message);
    }

    public function clear(Tournament $tournament, TournamentMatch $match)
    {
        try {
            $this->results->clear($match);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "Result of {$match->code} cleared.");
    }

    // Date, time, court and status
    public function schedule(Request $request, Tournament $tournament, TournamentMatch $match)
    {
        $statuses = $match->stage === 'group' ? 'scheduled,in_progress,cancelled' : 'scheduled,in_progress';

        $data = $request->validate([
            'scheduled_at' => 'nullable|date',
            'court'        => 'nullable|string|max:60',
            'status'       => "required|in:{$statuses}",
        ]);

        if ($match->status === 'completed') {
            unset($data['status']);   // a finished match keeps its result; clear it first to change the status
        }

        $match->update($data);

        return back()->with('success', "Match {$match->code} updated.");
    }
}