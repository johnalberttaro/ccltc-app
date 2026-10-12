<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['title', 'body', 'event_at', 'event_place', 'published_at', 'is_pinned', 'is_published'];

    protected $casts = [
        'event_at'     => 'datetime',
        'published_at' => 'datetime',
        'is_pinned'    => 'boolean',
        'is_published' => 'boolean',
    ];

    // Visible to the public: published, and the publish date has arrived
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    // Pinned first, newest first
    public function scopeDisplayOrder(Builder $query): Builder
    {
        return $query->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('body', 'like', "%{$term}%");
        });
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    // Published but with a future date = scheduled
    public function isScheduled(): bool
    {
        return $this->is_published && $this->published_at && $this->published_at->isFuture();
    }

    // Meeting / event date, e.g. "Saturday, October 10, 2026 at 4:00 PM"
    public function eventLabel(bool $short = false): ?string
    {
        if (! $this->event_at) {
            return null;
        }

        return $short
            ? $this->event_at->format('D, M j \a\t g:i A')
            : $this->event_at->format('l, F j, Y \a\t g:i A');
    }

    public function eventIsPast(): bool
    {
        return $this->event_at && $this->event_at->isPast();
    }

    public function isLive(): bool
    {
        return $this->is_published && ! $this->isScheduled();
    }
}