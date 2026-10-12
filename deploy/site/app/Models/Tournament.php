<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    public const STATUSES = [
        'draft'               => 'Draft',
        'upcoming'            => 'Upcoming',
        'registration_open'   => 'Registration Open',
        'registration_closed' => 'Registration Closed',
        'ongoing'             => 'Ongoing',
        'completed'           => 'Completed',
        'cancelled'           => 'Cancelled',
    ];

    // Statuses that count as "coming up" on dashboards
    public const UPCOMING_STATUSES = ['upcoming', 'registration_open', 'registration_closed'];

    public const FORMATS = [
        'single_elimination' => 'Single Elimination',
        'double_elimination' => 'Double Elimination',
        'round_robin'        => 'Round Robin',
        'group_knockout'     => 'Group Stage + Knockout',
    ];

    public const FORMAT_HELP = [
        'single_elimination' => 'Lose once and you are out. Winners advance until the final.',
        'double_elimination' => 'You are out after two losses. Losers drop to a second bracket for another chance.',
        'round_robin'        => 'Everyone plays everyone once. Best record wins.',
        'group_knockout'     => 'Players are split into groups (A, B, C...). The top finishers of each group go to a knockout bracket.',
    ];

    public const KNOCKOUT_FORMATS = [
        'single' => 'Single Elimination',
        'double' => 'Double Elimination',
    ];

    public const TYPES = [
        'singles' => 'Singles',
        'doubles' => 'Doubles',
        'both'    => 'Singles & Doubles',
    ];

    public const SCORE_FORMATS = [
        1 => '1 set',
        3 => 'Best of 3 sets',
        5 => 'Best of 5 sets',
    ];

    public const GAMES_PER_SET = [
        6 => 'Standard sets (first to 6, tiebreak at 6-6)',
        4 => 'Short sets (first to 4, tiebreak at 4-4)',
        7 => 'Sets to 7 (first to 7, tiebreak at 7-7)',
        8 => 'Pro set (first to 8, tiebreak at 8-8)',
    ];

    public const FINAL_SET = [
        'full'           => 'Full set',
        'match_tiebreak' => '10-point match tiebreak',
    ];

    public const STANDINGS_METHODS = [
        'difference' => 'Set & game difference (won minus lost)',
        'percentage' => 'Set & game percentage (won / total)',
    ];

    protected $fillable = [
        'name', 'category', 'match_type', 'format', 'starts_at', 'ends_at', 'venue', 'court',
        'registration_deadline', 'max_participants', 'description', 'registration_info',
        'registration_link', 'status',
        'group_count', 'group_size', 'advance_per_group', 'knockout_format', 'grand_final_reset',
        'best_of', 'games_per_set', 'final_set',
        'points_win', 'points_loss', 'standings_method', 'use_head_to_head',
    ];

    protected $casts = [
        'starts_at'             => 'datetime',
        'ends_at'               => 'datetime',
        'registration_deadline' => 'date',
        'groups_generated_at'   => 'datetime',
        'schedule_generated_at' => 'datetime',
        'knockout_generated_at' => 'datetime',
        'grand_final_reset'     => 'boolean',
        'use_head_to_head'      => 'boolean',
    ];

    // ---------- Relationships ----------

    public function participants()
    {
        return $this->hasMany(TournamentParticipant::class);
    }

    public function groups()
    {
        return $this->hasMany(TournamentGroup::class)->orderBy('position');
    }

    public function matches()
    {
        return $this->hasMany(TournamentMatch::class);
    }

    // ---------- Scopes ----------

    // Drafts are only visible to admins
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('status', '!=', 'draft');
    }

    // Ongoing first, then the ones coming up (soonest first), then finished ones (latest first)
    public function scopeDisplayOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE status
                WHEN 'ongoing' THEN 0 WHEN 'registration_open' THEN 1 WHEN 'upcoming' THEN 2
                WHEN 'registration_closed' THEN 3 WHEN 'draft' THEN 4 WHEN 'completed' THEN 5 ELSE 6 END")
            ->orderByRaw("CASE WHEN status IN ('completed', 'cancelled') THEN starts_at END DESC")
            ->orderBy('starts_at');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('category', 'like', "%{$term}%")
              ->orWhere('description', 'like', "%{$term}%")
              ->orWhere('venue', 'like', "%{$term}%");
        });
    }

    // ---------- Labels ----------

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('storage/' . $this->image_path) : null;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->match_type] ?? ucfirst((string) $this->match_type);
    }

    public function formatLabel(): string
    {
        $label = self::FORMATS[$this->format] ?? 'Single Elimination';

        if ($this->format === 'group_knockout') {
            $label .= ' (' . (self::KNOCKOUT_FORMATS[$this->knockout_format] ?? 'Single Elimination') . ')';
        }

        return $label;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function matchRulesLabel(): string
    {
        $label = self::SCORE_FORMATS[(int) $this->best_of] ?? '1 set';
        $label .= match ((int) $this->games_per_set) {
            4 => ', short sets to 4',
            7 => ', sets to 7',
            8 => ', pro set to 8',
            default => ', sets to 6',
        };
        if ((int) $this->best_of > 1 && $this->final_set === 'match_tiebreak') {
            $label .= ', deciding set = 10-point match tiebreak';
        }

        return $label;
    }

    public function whenLabel(): string
    {
        $label = $this->starts_at->format('D, M j, Y \a\t g:i A');

        if ($this->ends_at) {
            $label .= $this->ends_at->isSameDay($this->starts_at)
                ? ' - ' . $this->ends_at->format('g:i A')
                : ' to ' . $this->ends_at->format('M j, Y');
        }

        return $label;
    }

    // ---------- Registration ----------

    public function registrationOpen(): ?bool
    {
        if (in_array($this->status, ['registration_closed', 'ongoing', 'completed', 'cancelled'], true)) {
            return $this->registration_deadline || $this->status === 'registration_closed' ? false : null;
        }
        if ($this->registration_deadline) {
            return today()->lte($this->registration_deadline);
        }

        return $this->status === 'registration_open' ? true : null;
    }

    public function registrationLabel(): ?string
    {
        $open = $this->registrationOpen();
        if ($open === null) {
            return null;
        }
        if (! $open) {
            return 'Registration closed';
        }
        if (! $this->registration_deadline) {
            return 'Registration open';
        }

        $days = (int) today()->diffInDays($this->registration_deadline);

        return match (true) {
            $days === 0 => 'Registration closes today',
            $days === 1 => 'Registration closes tomorrow',
            default     => "Registration closes in {$days} days",
        };
    }

    // ---------- Format helpers ----------

    public function usesGroups(): bool
    {
        return $this->format === 'group_knockout';
    }

    public function isRoundRobin(): bool
    {
        return $this->format === 'round_robin';
    }

    // Single/double elimination straight from the participant list
    public function isDirectKnockout(): bool
    {
        return in_array($this->format, ['single_elimination', 'double_elimination'], true);
    }

    public function knockoutIsDouble(): bool
    {
        return $this->format === 'double_elimination'
            || ($this->format === 'group_knockout' && $this->knockout_format === 'double');
    }

    public function groupCapacity(): int
    {
        return (int) $this->group_count * (int) $this->group_size;
    }

    // "players" or "teams", for labels
    public function entryWord(bool $plural = true): string
    {
        $word = $this->match_type === 'singles' ? 'player' : 'team';

        return $plural ? $word . 's' : $word;
    }

    public function qualifierCount(): int
    {
        return (int) $this->group_count * (int) $this->advance_per_group;
    }

    // Name of the first knockout round for a given number of players
    public static function firstRoundName(int $players): string
    {
        $size = 2;
        while ($size < $players) {
            $size *= 2;
        }

        return match ($size) {
            2       => 'Final',
            4       => 'Semifinals',
            8       => 'Quarterfinals',
            default => "Round of {$size}",
        };
    }

    // ---------- Bracket ----------

    public function hasBracket(): bool
    {
        return $this->knockout_generated_at !== null;
    }

    // Winner of the whole tournament, once decided
    public function champion(): ?TournamentParticipant
    {
        // Round robin: the leader once every match is played
        if ($this->isRoundRobin()) {
            if (! $this->schedule_generated_at || ! \App\Services\Standings::groupStageFinished($this)) {
                return null;
            }
            $table = \App\Services\Standings::forTournament($this)[0] ?? null;

            return $table['rows'][0]['participant'] ?? null;
        }

        if (! $this->knockout_generated_at) {
            return null;
        }

        $finals = $this->matches()->where('stage', 'final')->with('winner')->get()->keyBy('code');

        if (isset($finals['F'])) {
            return $finals['F']->winner;
        }

        $gf = $finals['GF'] ?? null;
        $reset = $finals['GF2'] ?? null;

        if ($reset && $reset->winner_id) {
            return $reset->winner;
        }
        if ($gf && $gf->winner_id) {
            $winnersBracketChampionWon = (int) $gf->winner_id === (int) $gf->participant1_id;
            if ($winnersBracketChampionWon || ! $reset) {
                return $gf->winner;
            }
        }

        return null;
    }

    // ---------- Locking ----------

    // Has any result been recorded? Then format and rules are locked.
    public function hasResults(): bool
    {
        return $this->matches()->where('status', 'completed')->where('is_bye', false)->exists();
    }

    // Has the draw (groups, schedule or bracket) been made? Then format/group settings are locked.
    public function drawStarted(): bool
    {
        return $this->groups_generated_at !== null
            || $this->schedule_generated_at !== null
            || $this->knockout_generated_at !== null;
    }
}