<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tournament;
use App\Models\TournamentParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TournamentParticipantController extends Controller
{
    // Add one or many names (one per line)
    public function store(Request $request, Tournament $tournament)
    {
        if ($tournament->knockout_generated_at) {
            return back()->with('error', 'The bracket has already been generated, so participants can no longer be added.');
        }
        if ($tournament->isRoundRobin() && $tournament->schedule_generated_at) {
            return back()->with('error', 'The round robin schedule is already created. Reset it in Settings to add participants.');
        }

        $request->validate(['names' => 'required|string|max:20000'], ['names.required' => 'Type at least one name.']);

        $existing = $tournament->participants()->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        $added = [];
        $skipped = [];

        foreach (preg_split('/\r\n|\r|\n/', $request->input('names')) as $line) {
            $name = trim(preg_replace('/\s+/', ' ', $line));
            if ($name === '') {
                continue;
            }
            $name = mb_substr($name, 0, 120);

            if (in_array(mb_strtolower($name), $existing, true)) {
                $skipped[] = $name;
                continue;
            }

            $added[] = $name;
            $existing[] = mb_strtolower($name);
        }

        $limit = $tournament->max_participants;
        $current = $tournament->participants()->count();

        // Doubles: individual players (no "/") are allowed up to twice the team limit,
        // because they will be paired into teams ("Pair into doubles teams").
        $individuals = $tournament->match_type !== 'singles'
            && collect($added)->every(fn ($n) => ! str_contains($n, '/'))
            && ! $tournament->participants()->where('name', 'like', '%/%')->exists();
        $cap = $limit ? ($individuals ? $limit * 2 : $limit) : null;

        if ($cap && $current + count($added) > $cap) {
            $left = max(0, $cap - $current);
            return back()->withInput()->withErrors([
                'names' => $individuals
                    ? "This tournament allows {$limit} teams, so up to {$cap} players before pairing. You have {$current}, so you can add {$left} more."
                    : "This tournament allows {$limit} participants. You have {$current}, so you can add {$left} more.",
            ]);
        }

        foreach ($added as $name) {
            $tournament->participants()->create(['name' => $name]);
        }

        $message = count($added) . ' ' . str('participant')->plural(count($added)) . ' added.';
        if ($skipped) {
            $message .= ' Skipped duplicates: ' . implode(', ', $skipped) . '.';
        }
        if ($added && $tournament->groups_generated_at) {
            $message .= ' New participants are not in a group yet; assign them on the Groups tab.';
        }

        return back()->with('success', $message);
    }

    // Rename or set the seed
    public function update(Request $request, Tournament $tournament, TournamentParticipant $participant)
    {
        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('tournament_participants', 'name')->where('tournament_id', $tournament->id)->ignore($participant->id),
            ],
            'seed' => [
                'nullable', 'integer', 'min:1', 'max:256',
                Rule::unique('tournament_participants', 'seed')->where('tournament_id', $tournament->id)->ignore($participant->id),
            ],
        ], [
            'name.unique' => 'Another participant already has that name.',
            'seed.unique' => 'Another participant already has that seed.',
        ]);

        $participant->update($data);

        $message = 'Saved.';
        if ($tournament->drawStarted() && $participant->wasChanged('seed')) {
            $message .= ' Note: the draw is already made, so seeds only matter if you reset and draw again.';
        }

        return back()->with('success', $message);
    }

    public function destroy(Tournament $tournament, TournamentParticipant $participant)
    {
        if ($participant->hasMatches()) {
            return back()->with('error', "{$participant->name} already has matches in the schedule or bracket, so they can't be removed. "
                . 'Record their remaining matches as walkovers instead.');
        }

        $participant->delete();

        return back()->with('success', "{$participant->name} was removed.");
    }

    // Turn individual players into doubles teams ("Juan / Pedro")
    public function pair(Request $request, Tournament $tournament)
    {
        $mode = $request->validate(['mode' => 'required|in:random,balanced'])['mode'];
        $players = $tournament->participants()->get();

        if ($tournament->drawStarted()) {
            return back()->with('error', 'The groups or bracket are already drawn. Reset them in Settings first, then pair the players.');
        }
        if ($players->count() < 2 || $players->count() % 2 === 1) {
            return back()->with('error', "You have {$players->count()} players. An even number is needed to make doubles teams; add or remove one player.");
        }
        if ($players->contains(fn ($p) => str_contains($p->name, '/'))) {
            return back()->with('error', 'Some entries are already teams (they contain "/"). Pairing only works when every entry is one player.');
        }

        $teams = intdiv($players->count(), 2);
        if ($tournament->max_participants && $teams > $tournament->max_participants) {
            return back()->with('error', "{$players->count()} players make {$teams} teams, but this tournament allows {$tournament->max_participants}. Remove some players first.");
        }

        if ($mode === 'balanced') {
            // Strongest with weakest: seeds first (1, 2, 3...), unseeded in random order after them
            $ordered = \App\Services\GroupDraw::drawOrder($players);
            $half = intdiv(count($ordered), 2);
            $pairs = [];
            for ($i = 0; $i < $half; $i++) {
                $pairs[] = [$ordered[$i], $ordered[count($ordered) - 1 - $i]];
            }
        } else {
            $pairs = $players->shuffle()->chunk(2)->map->values()->map(fn ($c) => [$c[0], $c[1]])->all();
        }

        DB::transaction(function () use ($tournament, $players, $pairs) {
            $tournament->participants()->delete();
            foreach ($pairs as [$a, $b]) {
                // The team keeps the better seed of its two players (e.g. seed 1 player -> seed 1 team),
                // so seeded teams still get byes and are kept apart in the draw.
                $seeds = array_filter([$a->seed, $b->seed]);
                $tournament->participants()->create([
                    'name' => mb_substr($a->name . ' / ' . $b->name, 0, 120),
                    'seed' => $seeds ? min($seeds) : null,
                ]);
            }
        });

        return back()->with('success', count($pairs) . ' doubles teams were created from ' . $players->count() . ' players. Teams keep their seeded player\'s seed; you can change team seeds below.');
    }

    public function clearSeeds(Tournament $tournament)
    {
        $tournament->participants()->update(['seed' => null]);

        return back()->with('success', 'All seeds were cleared.');
    }
}