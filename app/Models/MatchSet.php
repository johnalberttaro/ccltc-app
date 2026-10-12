<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchSet extends Model
{
    protected $fillable = [
        'tennis_match_id', 'set_number',
        'side1_games', 'side2_games',
        'side1_tiebreak', 'side2_tiebreak',
    ];

    public function tennisMatch()
    {
        return $this->belongsTo(TennisMatch::class);
    }
}