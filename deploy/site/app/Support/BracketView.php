<?php

namespace App\Support;

use App\Models\Tournament;
use Illuminate\Support\Collection;

// Arranges a tournament's knockout matches into sections and columns for drawing.
class BracketView
{
    /** @var array<int, array{title: ?string, columns: array}> */
    public array $sections = [];

    public ?\App\Models\TournamentParticipant $champion;

    public function __construct(public Tournament $tournament, Collection $matches)
    {
        $knockout = $matches->whereIn('stage', ['winners', 'losers', 'final']);
        $this->champion = $tournament->champion();

        if (! $tournament->knockoutIsDouble()) {
            $byRound = $knockout->sortBy([['round', 'asc'], ['match_number', 'asc']])->groupBy('round');
            $columns = [];
            foreach ($byRound as $round => $roundMatches) {
                $columns[] = ['name' => $roundMatches->first()->roundName() === 'Semifinal' ? 'Semifinals'
                    : ($roundMatches->first()->roundName() === 'Quarterfinal' ? 'Quarterfinals' : $roundMatches->first()->roundName()),
                    'matches' => $roundMatches->values()];
            }
            $this->sections[] = ['title' => null, 'columns' => $columns, 'champion' => true];

            return;
        }

        // Double elimination: winners bracket, losers bracket, grand final
        $wb = $knockout->where('stage', 'winners')->sortBy([['round', 'asc'], ['match_number', 'asc']])->groupBy('round');
        $wbRounds = $wb->count();
        $columns = [];
        foreach ($wb as $round => $roundMatches) {
            $name = match ($wbRounds - $round) {
                0       => 'Winners Final',
                1       => $wbRounds > 2 ? 'Winners Semifinals' : 'Winners Round ' . $round,
                default => 'Winners Round ' . $round,
            };
            $columns[] = ['name' => $name, 'matches' => $roundMatches->values()];
        }
        $this->sections[] = ['title' => 'Winners Bracket', 'columns' => $columns, 'champion' => false];

        $lb = $knockout->where('stage', 'losers')->sortBy([['round', 'asc'], ['match_number', 'asc']])->groupBy('round');
        if ($lb->isNotEmpty()) {
            $lbRounds = $lb->count();
            $columns = [];
            foreach ($lb as $round => $roundMatches) {
                $columns[] = ['name' => $round === $lbRounds ? 'Losers Final' : 'Losers Round ' . $round, 'matches' => $roundMatches->values()];
            }
            $this->sections[] = ['title' => 'Losers Bracket', 'columns' => $columns, 'champion' => false];
        }

        $finals = $knockout->where('stage', 'final')->sortBy('round')
            ->reject(fn ($m) => $m->code === 'GF2' && $m->status === 'cancelled')
            ->values();
        $columns = $finals->map(fn ($m) => ['name' => $m->roundName(), 'matches' => collect([$m])])->all();
        $this->sections[] = ['title' => 'Grand Final', 'columns' => $columns, 'champion' => true];
    }
}