<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\TournamentMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

// Records results, moves winners (and double-elimination losers) forward, handles byes,
// undoes results, and keeps the tournament status up to date.
class MatchResults
{
    /**
     * Save a result.
     * $sets: [['p1' => 6, 'p2' => 4, 'tb1' => null, 'tb2' => null, 'mtb' => false], ...] (empty for a walkover)
     */
    public function record(TournamentMatch $match, int $winnerSlot, array $sets, bool $walkover): void
    {
        $field = "result_{$match->id}";
        $fail = fn (string $message) => throw ValidationException::withMessages([$field => $message]);

        if ($match->status === 'cancelled') {
            $fail('This match is cancelled, so a result cannot be recorded.');
        }
        if ($match->stage === 'group' && $match->tournament->knockout_generated_at) {
            $fail('Group results are locked because the knockout bracket has been generated. Reset the bracket first to change them.');
        }
        if ($match->is_bye || ! $match->participant1_id || ! $match->participant2_id) {
            $fail('Both players must be known before a result can be recorded.');
        }

        $newWinner = (int) $match->{"participant{$winnerSlot}_id"};
        $winnerChanges = $match->winner_id && (int) $match->winner_id !== $newWinner;

        if ($winnerChanges && $this->downstreamPlayed($match)) {
            $fail('The winner cannot be changed because a later match involving these players already has a result. Clear that result first.');
        }
        if ($match->code === 'GF' && $winnerChanges && $this->grandFinalResetPlayed($match)) {
            $fail('The grand final reset has already been played. Clear it first.');
        }

        DB::transaction(function () use ($match, $winnerSlot, $sets, $walkover, $newWinner, $winnerChanges) {
            if ($winnerChanges) {
                $this->unpropagate($match);
            }

            $match->sets()->delete();
            foreach ($sets as $i => $set) {
                $match->sets()->create([
                    'set_number'            => $i + 1,
                    'participant1_games'    => $set['p1'],
                    'participant2_games'    => $set['p2'],
                    'participant1_tiebreak' => $set['tb1'] ?? null,
                    'participant2_tiebreak' => $set['tb2'] ?? null,
                    'is_match_tiebreak'     => $set['mtb'] ?? false,
                ]);
            }

            $match->winner_id = $newWinner;
            $match->is_walkover = $walkover;
            $match->status = 'completed';
            $match->save();

            $this->propagate($match);
            $this->handleGrandFinal($match);
            $this->resolveByes($match->tournament);
            $this->syncStatus($match->tournament->fresh());
        });
    }

    // Remove a result (and undo anything it moved forward)
    public function clear(TournamentMatch $match): void
    {
        if ($match->stage === 'group' && $match->tournament->knockout_generated_at) {
            throw ValidationException::withMessages([
                "result_{$match->id}" => 'Group results are locked because the knockout bracket has been generated. Reset the bracket first.',
            ]);
        }

        if ($this->downstreamPlayed($match)) {
            throw ValidationException::withMessages([
                "result_{$match->id}" => 'A later match involving these players already has a result. Clear that result first.',
            ]);
        }
        if ($match->code === 'GF' && $this->grandFinalResetPlayed($match)) {
            throw ValidationException::withMessages(["result_{$match->id}" => 'Clear the grand final reset first.']);
        }

        DB::transaction(function () use ($match) {
            $this->unpropagate($match);

            if ($match->code === 'GF') {
                $this->closeGrandFinalReset($match, 'scheduled');
            }

            $match->sets()->delete();
            $match->winner_id = null;
            $match->is_walkover = false;
            $match->status = 'scheduled';
            $match->save();

            $this->resolveByes($match->tournament);
            $this->syncStatus($match->tournament->fresh());
        });
    }

    // ---------- Moving players forward ----------

    private function loserId(TournamentMatch $match): ?int
    {
        if (! $match->winner_id || ! $match->participant1_id || ! $match->participant2_id) {
            return null;
        }

        return (int) $match->winner_id === (int) $match->participant1_id
            ? (int) $match->participant2_id
            : (int) $match->participant1_id;
    }

    private function propagate(TournamentMatch $match): void
    {
        if ($match->next_match_id && $match->winner_id) {
            TournamentMatch::whereKey($match->next_match_id)->update(["participant{$match->next_slot}_id" => $match->winner_id]);
        }
        if ($match->loser_next_match_id && ($loser = $this->loserId($match))) {
            TournamentMatch::whereKey($match->loser_next_match_id)->update(["participant{$match->loser_next_slot}_id" => $loser]);
        }
    }

    // Take this match's winner/loser back out of later matches (and undo automatic byes they caused)
    private function unpropagate(TournamentMatch $match): void
    {
        foreach ([[$match->next_match_id, $match->next_slot], [$match->loser_next_match_id, $match->loser_next_slot]] as [$nextId, $slot]) {
            if (! $nextId) {
                continue;
            }
            $next = TournamentMatch::find($nextId);
            if (! $next) {
                continue;
            }

            if ($next->is_bye && $next->winner_id) {
                $this->unpropagate($next);
                $next->winner_id = null;
                $next->is_bye = false;
                $next->status = 'scheduled';
            }

            $next->{"participant{$slot}_id"} = null;
            $next->save();
        }
    }

    // Is there a real (not automatic) result further along this match's path?
    private function downstreamPlayed(TournamentMatch $match): bool
    {
        foreach ([$match->next_match_id, $match->loser_next_match_id] as $nextId) {
            if (! $nextId || ! ($next = TournamentMatch::find($nextId))) {
                continue;
            }
            if ($next->winner_id && ! $next->is_bye) {
                return true;
            }
            if ($next->is_bye && $next->winner_id && $this->downstreamPlayed($next)) {
                return true;
            }
        }

        if ($match->code === 'GF') {
            return $this->grandFinalResetPlayed($match);
        }

        return false;
    }

    // ---------- Double elimination grand final ----------

    private function grandFinalReset(TournamentMatch $gf): ?TournamentMatch
    {
        return TournamentMatch::where('tournament_id', $gf->tournament_id)->where('code', 'GF2')->first();
    }

    private function grandFinalResetPlayed(TournamentMatch $gf): bool
    {
        $reset = $this->grandFinalReset($gf);

        return $reset && $reset->winner_id !== null;
    }

    private function closeGrandFinalReset(TournamentMatch $gf, string $status): void
    {
        if ($reset = $this->grandFinalReset($gf)) {
            $reset->sets()->delete();
            $reset->update(['participant1_id' => null, 'participant2_id' => null, 'winner_id' => null, 'is_walkover' => false, 'status' => $status]);
        }
    }

    private function handleGrandFinal(TournamentMatch $match): void
    {
        if ($match->code !== 'GF' || ! ($reset = $this->grandFinalReset($match))) {
            return;
        }

        if ((int) $match->winner_id === (int) $match->participant1_id) {
            // Winners-bracket champion won: no reset needed
            $this->closeGrandFinalReset($match, 'cancelled');
        } else {
            // Losers-bracket champion won: both have one loss, play again
            $reset->update([
                'participant1_id' => $match->participant1_id,
                'participant2_id' => $match->participant2_id,
                'status'          => $reset->winner_id ? $reset->status : 'scheduled',
            ]);
        }
    }

    // ---------- Byes ----------

    // Automatically advance players whose opponent will never come (empty draw spots / byes)
    public function resolveByes(Tournament $tournament): void
    {
        $matches = TournamentMatch::where('tournament_id', $tournament->id)
            ->whereIn('stage', ['winners', 'losers', 'final'])
            ->where('code', '!=', 'GF2')
            ->get()
            ->keyBy('id');

        // Which match feeds each slot?
        $feeders = [];
        foreach ($matches as $m) {
            if ($m->next_match_id) {
                $feeders[$m->next_match_id][$m->next_slot] = [$m->id, 'winner'];
            }
            if ($m->loser_next_match_id) {
                $feeders[$m->loser_next_match_id][$m->loser_next_slot] = [$m->id, 'loser'];
            }
        }

        $isVoid = fn ($m) => $m->is_bye && ! $m->winner_id;

        $slotState = function ($m, int $slot) use (&$matches, $feeders, $isVoid) {
            if ($m->{"participant{$slot}_id"}) {
                return 'filled';
            }
            if (! isset($feeders[$m->id][$slot])) {
                return 'dead';
            }
            [$fromId, $type] = $feeders[$m->id][$slot];
            $from = $matches[$fromId];
            if ($isVoid($from)) {
                return 'dead';
            }
            if (! $from->winner_id) {
                return 'pending';
            }

            return ($type === 'loser' && $from->is_bye) ? 'dead' : 'pending';
        };

        do {
            $changed = false;
            foreach ($matches as $m) {
                if ($m->winner_id || $isVoid($m)) {
                    continue;
                }

                $s1 = $slotState($m, 1);
                $s2 = $slotState($m, 2);

                if ($s1 === 'dead' && $s2 === 'dead') {
                    $m->is_bye = true;
                    $m->status = 'completed';
                    $m->save();
                    $changed = true;
                } elseif (($s1 === 'filled' && $s2 === 'dead') || ($s1 === 'dead' && $s2 === 'filled')) {
                    $m->is_bye = true;
                    $m->winner_id = $s1 === 'filled' ? $m->participant1_id : $m->participant2_id;
                    $m->status = 'completed';
                    $m->save();

                    if ($m->next_match_id && isset($matches[$m->next_match_id])) {
                        $next = $matches[$m->next_match_id];
                        $next->{"participant{$m->next_slot}_id"} = $m->winner_id;
                        $next->save();
                    }
                    $changed = true;
                }
            }
        } while ($changed);
    }

    // ---------- Tournament status ----------

    public function syncStatus(Tournament $tournament): void
    {
        // Cancelled and draft tournaments are left exactly as the admin set them
        if (in_array($tournament->status, ['cancelled', 'draft'], true)) {
            return;
        }

        $started = $tournament->matches()->where('status', 'completed')->where('is_bye', false)->exists();
        $champion = $tournament->champion();

        $status = $tournament->status;
        if ($champion) {
            $status = 'completed';
        } elseif ($started && in_array($status, ['upcoming', 'registration_open', 'registration_closed', 'completed'], true)) {
            $status = 'ongoing';
        } elseif (! $started && $status === 'completed') {
            $status = 'ongoing';
        }

        if ($status !== $tournament->status) {
            $tournament->status = $status;
            $tournament->save();
        }
    }
}