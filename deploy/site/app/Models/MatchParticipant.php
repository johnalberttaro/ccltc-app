<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchParticipant extends Model
{
    protected $fillable = ['tennis_match_id', 'player_id', 'side'];

    public function tennisMatch()
    {
        return $this->belongsTo(TennisMatch::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}