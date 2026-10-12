<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentMatch extends Model
{
    public const STATUSES = [
        'scheduled'   => 'Scheduled',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
    ];

    protected $fillable = [
        'tournament_id', 'stage', 'group_id', 'round', 'match_number', 'code',
        'participant1_id', 'participant2_id', 'participant1_source', 'participant2_source',
        'winner_id', 'status', 'is_bye', 'is_walkover',
        'next_match_id', 'next_slot', 'loser_next_match_id', 'loser_next_slot',
        'scheduled_at', 'court',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'is_bye'       => 'boolean',
        'is_walkover'  => 'boolean',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function group()
    {
        return $this->belongsTo(TournamentGroup::class, 'group_id');
    }

    public function participant1()
    {
        return $this->belongsTo(TournamentParticipant::class, 'participant1_id');
    }

    public function participant2()
    {
        return $this->belongsTo(TournamentParticipant::class, 'participant2_id');
    }

    public function winner()
    {
        return $this->belongsTo(TournamentParticipant::class, 'winner_id');
    }

    public function sets()
    {
        return $this->hasMany(TournamentMatchSet::class)->orderBy('set_number');
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    // Both players known, not a bye, not cancelled: a result can be entered
    public function isPlayable(): bool
    {
        return ! $this->is_bye && $this->status !== 'cancelled'
            && $this->participant1_id !== null && $this->participant2_id !== null;
    }

    // A bye where nobody plays at all (both spots empty)
    public function isVoid(): bool
    {
        return $this->is_bye && ! $this->winner_id;
    }

    public function participant(int $slot): ?TournamentParticipant
    {
        return $slot === 1 ? $this->participant1 : $this->participant2;
    }

    public function participantId(int $slot): ?int
    {
        $id = $slot === 1 ? $this->participant1_id : $this->participant2_id;

        return $id === null ? null : (int) $id;
    }

    public function wonBy(int $slot): bool
    {
        return $this->winner_id !== null && $this->participantId($slot) === (int) $this->winner_id;
    }

    public function loser(): ?TournamentParticipant
    {
        if (! $this->winner_id || $this->is_bye) {
            return null;
        }

        return $this->wonBy(1) ? $this->participant2 : $this->participant1;
    }

    // What to show in an empty slot: "Winner of QF1", "Bye", "To be decided"
    public function slotLabel(int $slot): string
    {
        return $this->{"participant{$slot}_source"} ?: 'To be decided';
    }

    // Per-set display for one side: [['games' => 6, 'tb' => null, 'mtb' => false], ...]
    public function setsFor(int $slot): array
    {
        $other = 3 - $slot;

        return $this->sets->map(fn ($set) => [
            'games'    => $set->is_match_tiebreak ? $set->{"participant{$slot}_tiebreak"} : $set->{"participant{$slot}_games"},
            'tb'       => $set->is_match_tiebreak ? null : ($set->{"participant{$slot}_tiebreak"} !== null
                && $set->{"participant{$slot}_tiebreak"} < $set->{"participant{$other}_tiebreak"} ? $set->{"participant{$slot}_tiebreak"} : null),
            'mtb'      => $set->is_match_tiebreak,
            'won'      => $set->is_match_tiebreak
                ? $set->{"participant{$slot}_tiebreak"} > $set->{"participant{$other}_tiebreak"}
                : $set->{"participant{$slot}_games"} > $set->{"participant{$other}_games"},
        ])->all();
    }

    // Score from the winner's side, e.g. "6-4, 3-6, [10-8]"
    public function scoreLine(): string
    {
        if ($this->is_walkover) {
            return 'Walkover';
        }
        if ($this->is_bye) {
            return 'Bye';
        }

        $w = $this->wonBy(2) ? 2 : 1;
        $l = 3 - $w;

        return $this->sets->map(function ($set) use ($w, $l) {
            if ($set->is_match_tiebreak) {
                return '[' . $set->{"participant{$w}_tiebreak"} . '-' . $set->{"participant{$l}_tiebreak"} . ']';
            }
            $text = $set->{"participant{$w}_games"} . '-' . $set->{"participant{$l}_games"};
            if ($set->participant1_tiebreak !== null && $set->participant2_tiebreak !== null) {
                $text .= '(' . min($set->participant1_tiebreak, $set->participant2_tiebreak) . ')';
            }

            return $text;
        })->join(', ');
    }

    // Short name of the round this match belongs to
    public function roundName(): string
    {
        return match (true) {
            $this->code === 'F'                    => 'Final',
            $this->code === 'GF'                   => 'Grand Final',
            $this->code === 'GF2'                  => 'Grand Final Reset',
            $this->code === 'WF'                   => 'Winners Final',
            $this->code === 'LF'                   => 'Losers Final',
            str_starts_with((string) $this->code, 'SF') => 'Semifinal',
            str_starts_with((string) $this->code, 'QF') => 'Quarterfinal',
            $this->stage === 'winners' && str_starts_with((string) $this->code, 'W') => 'Winners Round ' . $this->round,
            $this->stage === 'losers'              => 'Losers Round ' . $this->round,
            $this->stage === 'group'               => ($this->group?->label() ?? 'Group') . ' - Match ' . $this->match_number,
            default                                => 'Round of ' . preg_replace('/^R(\d+)-.*/', '$1', (string) $this->code),
        };
    }
}