# Adds doubles + 1-set matches to ccltc-app.
# Run from the project root (F:\System Projects\ccltc-app).

$root = Get-Location
$utf8 = New-Object System.Text.UTF8Encoding $false   # UTF-8 without BOM (PHP needs this)

function Save-File($relPath, $content) {
    $full = Join-Path $root $relPath
    $dir = Split-Path $full -Parent
    if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    [System.IO.File]::WriteAllText($full, $content, $utf8)
    Write-Host "Updated: $relPath" -ForegroundColor Green
}

if (-not (Test-Path (Join-Path $root "artisan"))) {
    Write-Host "Run this from your Laravel project folder (where 'artisan' is)." -ForegroundColor Red
    exit 1
}

# ---------- Model: TennisMatch ----------
Save-File "app\Models\TennisMatch.php" @'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TennisMatch extends Model
{
    protected $fillable = [
        'match_date', 'start_time', 'venue',
        'match_type', 'best_of', 'status', 'winner_side',
    ];

    protected $casts = [
        'match_date' => 'date',
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
        return (int) $this->best_of === 1 ? '1 set' : "Best of {$this->best_of}";
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
'@

# ---------- Controller ----------
Save-File "app\Http\Controllers\TennisMatchController.php" @'
<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\TennisMatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TennisMatchController extends Controller
{
    // Results for one date (defaults to today)
    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);

        $date = Carbon::parse($request->input('date', today()->toDateString()));

        $matches = TennisMatch::with(['players', 'sets'])
            ->whereDate('match_date', $date)
            ->orderBy('start_time')
            ->get();

        return view('matches.index', compact('matches', 'date'));
    }

    // Record match form
    public function create()
    {
        $players = Player::orderBy('last_name')->get();

        return view('matches.create', compact('players'));
    }

    // Save a match
    public function store(Request $request)
    {
        $data = $request->validate([
            'match_date'  => 'required|date',
            'start_time'  => 'nullable|date_format:H:i',
            'venue'       => 'nullable|string|max:100',
            'match_type'  => 'required|in:singles,doubles',
            'best_of'     => 'required|in:1,3,5',
            'status'      => 'required|in:completed,retired,walkover',
            'p1a'         => 'required|exists:players,id',
            'p1b'         => 'nullable|required_if:match_type,doubles|exists:players,id',
            'p2a'         => 'required|exists:players,id',
            'p2b'         => 'nullable|required_if:match_type,doubles|exists:players,id',
            'winner_side' => 'nullable|in:1,2',
            'sets'        => 'array',
            'sets.*.p1'   => 'nullable|integer|min:0|max:7',
            'sets.*.p2'   => 'nullable|integer|min:0|max:7',
            'sets.*.tb'   => 'nullable|integer|min:0|max:99',
        ], [
            'p1a.required'    => 'Choose the first player of Team 1.',
            'p2a.required'    => 'Choose the first player of Team 2.',
            'p1b.required_if' => 'Doubles needs a second player for Team 1.',
            'p2b.required_if' => 'Doubles needs a second player for Team 2.',
        ]);

        // Build the two teams (1 player each for singles, 2 for doubles)
        $doubles = $data['match_type'] === 'doubles';
        $team1 = array_values(array_filter([$data['p1a'], $doubles ? ($data['p1b'] ?? null) : null]));
        $team2 = array_values(array_filter([$data['p2a'], $doubles ? ($data['p2b'] ?? null) : null]));

        $everyone = array_merge($team1, $team2);
        if (count($everyone) !== count(array_unique($everyone))) {
            $this->reject('Each player can only appear once in a match.', 'p1a');
        }

        $bestOf = (int) $data['best_of'];
        $sets = $this->cleanSets($data['sets'] ?? [], $bestOf);
        $winnerSide = $this->decideWinner($data['status'], $data['winner_side'] ?? null, $sets, $bestOf);

        $match = DB::transaction(function () use ($data, $sets, $winnerSide, $bestOf, $team1, $team2) {
            $match = TennisMatch::create([
                'match_date'  => $data['match_date'],
                'start_time'  => $data['start_time'] ?? null,
                'venue'       => $data['venue'] ?? null,
                'match_type'  => $data['match_type'],
                'best_of'     => $bestOf,
                'status'      => $data['status'],
                'winner_side' => $winnerSide,
            ]);

            foreach ($team1 as $playerId) {
                $match->players()->attach($playerId, ['side' => 1]);
            }
            foreach ($team2 as $playerId) {
                $match->players()->attach($playerId, ['side' => 2]);
            }

            foreach ($sets as $i => $s) {
                $tb1 = null;
                $tb2 = null;

                // Tiebreak: user enters the LOSER's points; winner's = max(7, loser + 2)
                if ($s['tb'] !== null && $this->isTiebreakSet($s['p1'], $s['p2'])) {
                    $loserTb = $s['tb'];
                    $winnerTb = max(7, $loserTb + 2);
                    [$tb1, $tb2] = $s['p1'] > $s['p2'] ? [$winnerTb, $loserTb] : [$loserTb, $winnerTb];
                }

                $match->sets()->create([
                    'set_number'     => $i + 1,
                    'side1_games'    => $s['p1'],
                    'side2_games'    => $s['p2'],
                    'side1_tiebreak' => $tb1,
                    'side2_tiebreak' => $tb2,
                ]);
            }

            return $match;
        });

        return redirect()
            ->route('matches.index', ['date' => $match->match_date->toDateString()])
            ->with('success', 'Match recorded.');
    }

    // Match detail / scoreboard
    public function show(TennisMatch $match)
    {
        $match->load(['players', 'sets']);

        return view('matches.show', compact('match'));
    }

    // Delete a match (its sets and participants are deleted automatically)
    public function destroy(TennisMatch $match)
    {
        $date = $match->match_date->toDateString();
        $match->delete();

        return redirect()
            ->route('matches.index', ['date' => $date])
            ->with('success', 'Match deleted.');
    }

    // ---------- Score rules ----------

    // Keep only filled set rows, in order, and check for gaps / half-filled rows
    private function cleanSets(array $rows, int $bestOf): array
    {
        $sets = [];
        $sawEmpty = false;

        foreach (array_values($rows) as $i => $row) {
            $p1 = $row['p1'] ?? null;
            $p2 = $row['p2'] ?? null;
            $tb = $row['tb'] ?? null;
            $n = $i + 1;

            if ($p1 === null && $p2 === null) {
                $sawEmpty = true;
                continue;
            }
            if ($p1 === null || $p2 === null) {
                $this->reject("Set $n: enter scores for both teams.");
            }
            if ($sawEmpty) {
                $this->reject("Set $n: fill in sets in order without skipping one.");
            }

            $sets[] = ['p1' => (int) $p1, 'p2' => (int) $p2, 'tb' => $tb === null ? null : (int) $tb];
        }

        if (count($sets) > $bestOf) {
            $this->reject($bestOf === 1
                ? 'A 1-set match can only have one set.'
                : "A best-of-$bestOf match can't have more than $bestOf sets.");
        }

        return $sets;
    }

    // Work out the winner from the sets (or use the chosen winner for retired/walkover)
    private function decideWinner(string $status, $chosenWinner, array $sets, int $bestOf): int
    {
        if ($status !== 'completed') {
            if (empty($chosenWinner)) {
                $this->reject('Choose the winner for a retired or walkover match.', 'winner_side');
            }

            return (int) $chosenWinner;
        }

        if (empty($sets)) {
            $this->reject('Enter the set score for a completed match.');
        }

        $setsToWin = intdiv($bestOf, 2) + 1;   // 1 for one set, 2 for best of 3, 3 for best of 5
        $won = [1 => 0, 2 => 0];

        foreach ($sets as $i => $s) {
            $n = $i + 1;

            if (max($won) >= $setsToWin) {
                $this->reject("Set $n was entered after the match was already won.");
            }
            if (! $this->isValidSet($s['p1'], $s['p2'])) {
                $this->reject("Set $n: {$s['p1']}-{$s['p2']} is not a valid set score (e.g. 6-4, 7-5, 7-6).");
            }
            if ($s['tb'] !== null && ! $this->isTiebreakSet($s['p1'], $s['p2'])) {
                $this->reject("Set $n: tiebreak points are only for 7-6 sets.");
            }

            $won[$s['p1'] > $s['p2'] ? 1 : 2]++;
        }

        if (max($won) < $setsToWin) {
            $this->reject("The match isn't finished yet: a team needs to win $setsToWin sets.");
        }

        return $won[1] > $won[2] ? 1 : 2;
    }

    // Valid finished set: 6-0 to 6-4, 7-5, or 7-6
    private function isValidSet(int $a, int $b): bool
    {
        $hi = max($a, $b);
        $lo = min($a, $b);

        return ($hi === 6 && $lo <= 4) || ($hi === 7 && in_array($lo, [5, 6], true));
    }

    private function isTiebreakSet(int $a, int $b): bool
    {
        return max($a, $b) === 7 && min($a, $b) === 6;
    }

    private function reject(string $message, string $field = 'sets'): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
'@

# ---------- Record match form ----------
Save-File "resources\views\matches\create.blade.php" @'
@extends('layouts.app')

@section('title', 'Record Match')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Record Match</h1>

    @if ($players->count() < 2)
        <div class="rounded-lg bg-yellow-100 text-yellow-800 px-4 py-3">
            You need at least 2 players first (4 for doubles).
            <a href="{{ route('players.index') }}" class="underline font-medium">Add players</a>
        </div>
    @else
        <form method="POST" action="{{ route('matches.store') }}" class="bg-white rounded-xl shadow p-4 grid gap-5">
            @csrf

            @if ($errors->any())
                <div class="rounded-lg bg-red-100 text-red-800 px-4 py-3 text-sm">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Type & format --}}
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium mb-1">Match type</label>
                    <select name="match_type" id="match_type" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="doubles" @selected(old('match_type', 'doubles') == 'doubles')>Doubles (2 vs 2)</option>
                        <option value="singles" @selected(old('match_type') == 'singles')>Singles (1 vs 1)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Format</label>
                    <select name="best_of" id="best_of" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="1" @selected(old('best_of', '1') == '1')>1 set</option>
                        <option value="3" @selected(old('best_of') == '3')>Best of 3 sets</option>
                        <option value="5" @selected(old('best_of') == '5')>Best of 5 sets</option>
                    </select>
                </div>
            </div>

            {{-- When & where --}}
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Date</label>
                    <input type="date" name="match_date" value="{{ old('match_date', today()->toDateString()) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Start time (optional)</label>
                    <input type="time" name="start_time" value="{{ old('start_time') }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Venue / court (optional)</label>
                    <input type="text" name="venue" value="{{ old('venue') }}" placeholder="e.g. Court 1"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
            </div>

            {{-- Teams --}}
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ([1, 2] as $team)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-sm font-semibold mb-2">Team {{ $team }}</p>

                        @foreach (['a' => 'Player', 'b' => 'Partner'] as $slot => $label)
                            <div class="{{ $slot === 'b' ? 'partner mt-2' : '' }}">
                                <label class="block text-xs text-gray-500 mb-1">{{ $label }}</label>
                                <select name="p{{ $team }}{{ $slot }}" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                                    <option value="">Select player</option>
                                    @foreach ($players as $player)
                                        <option value="{{ $player->id }}" @selected(old("p$team$slot") == $player->id)>
                                            {{ $player->full_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            {{-- Result type --}}
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium mb-1">Result type</label>
                    <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="completed" @selected(old('status', 'completed') == 'completed')>Completed</option>
                        <option value="retired" @selected(old('status') == 'retired')>Retired (injury, etc.)</option>
                        <option value="walkover" @selected(old('status') == 'walkover')>Walkover (no-show)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Winner (only for retired/walkover)</label>
                    <select name="winner_side" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="">Auto from score</option>
                        <option value="1" @selected(old('winner_side') == '1')>Team 1</option>
                        <option value="2" @selected(old('winner_side') == '2')>Team 2</option>
                    </select>
                </div>
            </div>

            {{-- Set scores --}}
            <div>
                <p class="font-medium">Score</p>
                <p class="text-xs text-gray-500 mb-3">
                    Games won by each team. TB = the losing team's tiebreak points, only for 7-6.
                </p>

                <div class="grid grid-cols-4 gap-2 text-xs font-medium text-gray-500 mb-1">
                    <span>Set</span><span class="text-center">Team 1</span><span class="text-center">Team 2</span><span class="text-center">TB</span>
                </div>

                <div class="grid gap-2">
                    @for ($i = 0; $i < 5; $i++)
                        <div class="set-row grid grid-cols-4 gap-2 items-center">
                            <span class="text-sm text-gray-600">Set {{ $i + 1 }}</span>
                            <input type="number" inputmode="numeric" min="0" max="7"
                                   name="sets[{{ $i }}][p1]" value="{{ old("sets.$i.p1") }}"
                                   class="rounded-lg border border-gray-300 px-2 py-2 text-center">
                            <input type="number" inputmode="numeric" min="0" max="7"
                                   name="sets[{{ $i }}][p2]" value="{{ old("sets.$i.p2") }}"
                                   class="rounded-lg border border-gray-300 px-2 py-2 text-center">
                            <input type="number" inputmode="numeric" min="0"
                                   name="sets[{{ $i }}][tb]" value="{{ old("sets.$i.tb") }}"
                                   class="rounded-lg border border-gray-200 bg-gray-50 px-2 py-2 text-center">
                        </div>
                    @endfor
                </div>
            </div>

            <button type="submit"
                    class="rounded-lg bg-green-700 text-white font-medium px-4 py-3 hover:bg-green-800">
                Save Match
            </button>
        </form>

        <script>
            // Show partner fields only for doubles, and only as many set rows as the format needs
            function syncForm() {
                const doubles = document.getElementById('match_type').value === 'doubles';
                document.querySelectorAll('.partner').forEach(function (el) {
                    el.style.display = doubles ? '' : 'none';
                    el.querySelector('select').disabled = !doubles;
                });

                const sets = parseInt(document.getElementById('best_of').value, 10);
                document.querySelectorAll('.set-row').forEach(function (row, i) {
                    const show = i < sets;
                    row.style.display = show ? '' : 'none';
                    row.querySelectorAll('input').forEach(function (input) { input.disabled = !show; });
                });
            }

            document.getElementById('match_type').addEventListener('change', syncForm);
            document.getElementById('best_of').addEventListener('change', syncForm);
            syncForm();
        </script>
    @endif
@endsection
'@

# ---------- Match detail / scoreboard ----------
Save-File "resources\views\matches\show.blade.php" @'
@extends('layouts.app')

@section('title', 'Match Details')

@section('content')
    <a href="{{ route('matches.index', ['date' => $match->match_date->toDateString()]) }}"
       class="text-sm text-green-700 hover:underline">&larr; Back to results</a>

    <div class="bg-white rounded-xl shadow p-4 mt-3">
        <p class="text-sm text-gray-500 mb-4">
            {{ $match->match_date->format('M d, Y') }}
            @if ($match->timeLabel()) &middot; {{ $match->timeLabel() }} @endif
            @if ($match->venue) &middot; {{ $match->venue }} @endif
            &middot; {{ $match->typeLabel() }}
            &middot; {{ $match->formatLabel() }}
            &middot; {{ ucfirst($match->status) }}
        </p>

        {{-- Scoreboard --}}
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-xs text-gray-500">
                        <th class="text-left py-2">{{ $match->match_type === 'doubles' ? 'Team' : 'Player' }}</th>
                        @foreach ($match->sets as $set)
                            <th class="w-12 text-center">{{ $match->sets->count() > 1 ? 'S' . $set->set_number : 'Games' }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ([1, 2] as $side)
                        @php $isWinner = (int) $match->winner_side === $side; @endphp
                        <tr class="border-t {{ $isWinner ? 'font-bold' : 'text-gray-600' }}">
                            <td class="py-3">
                                {{ $match->sideName($side) }}
                                @if ($isWinner) <span class="text-green-700">&#10003;</span> @endif
                            </td>
                            @foreach ($match->sets as $set)
                                @php
                                    $games = $side === 1 ? $set->side1_games : $set->side2_games;
                                    $tb = $side === 1 ? $set->side1_tiebreak : $set->side2_tiebreak;
                                @endphp
                                <td class="text-center text-lg">
                                    {{ $games }}@if ($tb !== null)<sup class="text-xs">{{ $tb }}</sup>@endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-sm">
            <span class="text-gray-500">Score:</span>
            <span class="font-mono">{{ $match->scoreLine() }}</span>
        </p>
    </div>

    <form method="POST" action="{{ route('matches.destroy', $match) }}" class="mt-4"
          onsubmit="return confirm('Delete this match?')">
        @csrf
        @method('DELETE')
        <button class="text-red-600 text-sm hover:underline">Delete match</button>
    </form>
@endsection
'@

Write-Host ""
Write-Host "Done! Open http://127.0.0.1:8000/matches/create to record a doubles match." -ForegroundColor Cyan
