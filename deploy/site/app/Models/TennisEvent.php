<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TennisEvent extends Model
{
    protected $table = 'events';

    public const STATUSES = [
        'upcoming'  => 'Upcoming',
        'ongoing'   => 'Ongoing',
        'completed' => 'Completed',
    ];

    protected $fillable = [
        'title', 'description', 'starts_at', 'ends_at', 'location',
        'registration_info', 'registration_link', 'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    // Ongoing first, then upcoming (soonest first), then completed (most recent first)
    public function scopeDisplayOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE status WHEN 'ongoing' THEN 0 WHEN 'upcoming' THEN 1 ELSE 2 END")
            ->orderByRaw("CASE WHEN status = 'completed' THEN starts_at END DESC")
            ->orderBy('starts_at');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('location', 'like', "%{$term}%");
        });
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    public function whenLabel(): string
    {
        $label = $this->starts_at->format('D, M j, Y \a\t g:i A');

        if ($this->ends_at) {
            $label .= $this->ends_at->isSameDay($this->starts_at)
                ? ' - ' . $this->ends_at->format('g:i A')
                : ' to ' . $this->ends_at->format('M j, Y g:i A');
        }

        return $label;
    }
}