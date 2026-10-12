<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TennisMatch extends Model
{
    protected $fillable = [
        'match_date', 'start_time', 'venue',
        'match_type', 'best_of', 'games_per_set', 'status', 'winner_side',
        'referee', 'ball_boy', 'is_ranking_game',
    ];

    protected $casts = [
        'match_date' => 'date',
        'is_ranking_game' => 'boolean',
    ];

    // Players in this match (with their side/team: 1 or 2)
    public function players()
    {
        return $this->belongsToMany(Player::class, 'match_participants')
                    ->withPivot('side')
                    ->withTimestamps();
    }

    public function participants()
    {
        return $this->hasMany(MatchParticipant::class);
    }

    // Set scores, always in order: set 1, 2, 3...
    public function sets()
    {
        return $this->hasMany(MatchSet::class)->orderBy('set_number');
    }

    // ---------- Display helpers ----------

    // Name(s) on a team, e.g. "Juan Dela Cruz / Pedro Santos"
    public function sideName(int $side): string
    {
        return $this->players
            ->filter(fn ($p) => (int) $p->pivot->side === $side)
            ->pluck('full_name')
            ->join(' / ');
    }

    // A finished game with no winner: same games for both teams and no tiebreak played (e.g. 6-6)
    public function isTie(): bool
    {
        return $this->status === 'completed' && $this->winner_side === null;
    }

    public function winnerName(): ?string
    {
        return $this->winner_side ? $this->sideName((int) $this->winner_side) : null;
    }

    public function loserName(): ?string
    {
        return $this->winner_side ? $this->sideName(3 - (int) $this->winner_side) : null;
    }

    // Score from the winner's point of view, e.g. "6-4" or "7-6(5)"
    public function scoreLine(): string
    {
        if ($this->status === 'walkover') {
            return 'Walkover';
        }

        $w = (int) ($this->winner_side ?? 1);

        $line = $this->sets->map(function ($s) use ($w) {
            $a = $w === 1 ? $s->side1_games : $s->side2_games;
            $b = $w === 1 ? $s->side2_games : $s->side1_games;
            $text = "$a-$b";

            if ($s->side1_tiebreak !== null && $s->side2_tiebreak !== null) {
                $text .= '(' . min($s->side1_tiebreak, $s->side2_tiebreak) . ')';
            }

            return $text;
        })->join(', ');

        if ($this->status === 'retired') {
            $line .= ' ret.';
        }

        return $line;
    }

    public function formatLabel(): string
    {
        $label = (int) $this->best_of === 1 ? '1 set' : "Best of {$this->best_of}";

        $games = (int) $this->games_per_set;

        return in_array($games, [7, 8], true) ? "$label to $games" : $label;
    }

    public function typeLabel(): string
    {
        return ucfirst($this->match_type);
    }

    public function timeLabel(): string
    {
        return $this->start_time ? substr($this->start_time, 0, 5) : '';
    }
}