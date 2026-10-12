<?php

namespace App\Support;

use App\Models\Tournament;

// Works out the tournament's progress steps and summary numbers from the database.
class TournamentProgress
{
    public array $steps = [];
    public array $stats = [];

    public function __construct(public Tournament $tournament)
    {
        $matches = $tournament->matches()->where('is_bye', false)->get(['id', 'stage', 'status', 'participant1_id', 'participant2_id']);

        $group = $matches->where('stage', 'group');
        $knockout = $matches->whereIn('stage', ['winners', 'losers']);
        $final = $matches->where('stage', 'final');
        $live = fn ($set) => $set->where('status', '!=', 'cancelled');
        $done = fn ($set) => $set->where('status', 'completed');

        $this->stats = [
            'participants'       => $tournament->participants()->count(),
            'groups'             => $tournament->groups()->count(),
            'group_matches'      => $live($group)->count(),
            'group_done'         => $done($group)->count(),
            'knockout_matches'   => $live($knockout)->count() + $live($final)->count(),
            'knockout_done'      => $done($knockout)->count() + $done($final)->count(),
            'total_matches'      => $live($matches)->count(),
            'completed_matches'  => $done($matches)->count(),
            'qualified'          => $tournament->knockout_generated_at ? $tournament->qualifierCount() : 0,
        ];

        $groupStageDone = $live($group)->isNotEmpty() && $live($group)->count() === $done($group)->count();
        $knockoutStarted = $tournament->knockout_generated_at !== null;
        $knockoutDone = $knockoutStarted && $live($knockout)->count() === $done($knockout)->count();
        $finalDone = $final->isNotEmpty() && $done($final)->isNotEmpty() && $live($final)->count() === $done($final)->count();
        $finalReady = $final->contains(fn ($m) => $m->participant1_id && $m->participant2_id);
        $status = $tournament->status;

        $registration = $tournament->drawStarted() || in_array($status, ['registration_closed', 'ongoing', 'completed'], true)
            ? 'done' : 'current';

        $steps = [['Registration', $registration]];

        if ($tournament->usesGroups()) {
            $steps[] = ['Group Generation', $tournament->groups_generated_at ? 'done' : ($registration === 'done' || $this->stats['participants'] >= 2 ? 'current' : 'pending')];
            $steps[] = ['Group Stage', $groupStageDone ? 'done' : ($tournament->schedule_generated_at ? 'current' : 'pending')];
            $steps[] = ['Knockout Stage', $knockoutStarted ? ($knockoutDone ? 'done' : 'current') : ($groupStageDone ? 'current' : 'pending')];
        } elseif ($tournament->isRoundRobin()) {
            $steps[] = ['Schedule', $tournament->schedule_generated_at ? 'done' : ($this->stats['participants'] >= 2 ? 'current' : 'pending')];
            $steps[] = ['Matches', $groupStageDone ? 'done' : ($tournament->schedule_generated_at ? 'current' : 'pending')];
        } else {
            $steps[] = ['Draw', $knockoutStarted ? 'done' : ($this->stats['participants'] >= 2 ? 'current' : 'pending')];
            $steps[] = ['Knockout Stage', $knockoutStarted ? ($knockoutDone ? 'done' : 'current') : 'pending'];
        }

        if (! $tournament->isRoundRobin()) {
            $steps[] = ['Final', $finalDone ? 'done' : ($finalReady ? 'current' : 'pending')];
        }

        $steps[] = ['Tournament', $status === 'completed' ? 'done' : 'pending'];

        // Only one step should look "current": the first unfinished one
        $seenCurrent = false;
        foreach ($steps as [$label, $state]) {
            if ($state === 'current') {
                $state = $seenCurrent ? 'pending' : 'current';
                $seenCurrent = true;
            }
            $this->steps[] = ['label' => $label, 'state' => $state];
        }
    }

    public function currentStage(): string
    {
        foreach ($this->steps as $step) {
            if ($step['state'] === 'current') {
                return $step['label'];
            }
        }

        return $this->tournament->status === 'completed' ? 'Completed' : 'Not started';
    }
}