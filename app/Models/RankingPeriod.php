<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// The club's award / ranking period, e.g. "October 2026", Oct 1 - Oct 31, 5 official games per player.
// Only one period is active at a time; the active one is used for the Most Wins leaderboard.
class RankingPeriod extends Model
{
    protected $fillable = ['name', 'start_date', 'end_date', 'games_required', 'is_active'];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'games_required' => 'integer',
        'is_active'      => 'boolean',
    ];

    public static function active(): ?self
    {
        return static::where('is_active', true)->orderByDesc('start_date')->first();
    }

    public function dateLabel(): string
    {
        return $this->start_date->format('M j, Y') . ' - ' . $this->end_date->format('M j, Y');
    }
}