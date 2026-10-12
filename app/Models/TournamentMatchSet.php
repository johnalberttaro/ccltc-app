<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentMatchSet extends Model
{
    protected $fillable = [
        'tournament_match_id', 'set_number',
        'participant1_games', 'participant2_games',
        'participant1_tiebreak', 'participant2_tiebreak', 'is_match_tiebreak',
    ];

    protected $casts = [
        'is_match_tiebreak' => 'boolean',
    ];

    public function match()
    {
        return $this->belongsTo(TournamentMatch::class, 'tournament_match_id');
    }
}