<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentParticipant extends Model
{
    protected $fillable = ['tournament_id', 'name', 'seed', 'group_id', 'group_position'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function group()
    {
        return $this->belongsTo(TournamentGroup::class, 'group_id');
    }

    // Is this participant already in the schedule or bracket?
    public function hasMatches(): bool
    {
        return TournamentMatch::where(fn ($q) => $q->where('participant1_id', $this->id)->orWhere('participant2_id', $this->id))
            ->exists();
    }
}