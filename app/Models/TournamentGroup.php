<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentGroup extends Model
{
    protected $fillable = ['tournament_id', 'name', 'position'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function participants()
    {
        return $this->hasMany(TournamentParticipant::class, 'group_id')
            ->orderBy('group_position')
            ->orderBy('id');
    }

    public function matches()
    {
        return $this->hasMany(TournamentMatch::class, 'group_id')
            ->orderBy('round')
            ->orderBy('match_number');
    }

    public function label(): string
    {
        return strlen($this->name) <= 2 ? 'Group ' . $this->name : $this->name;
    }
}