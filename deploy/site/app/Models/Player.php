<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Player extends Model
{
    protected $fillable = ['first_name', 'last_name'];

    // All matches this player played in
    public function matches()
    {
        return $this->belongsToMany(TennisMatch::class, 'match_participants')
                    ->withPivot('side')
                    ->withTimestamps();
    }

    // Lets you use $player->full_name
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}