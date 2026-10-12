@extends('layouts.admin')

@section('title', $match ? 'Edit Match' : 'Record Match')

@php
    // Value for a field: what was just typed (after an error) > the saved match (when editing) > default
    $defaults = ['match_type' => 'doubles', 'format' => '1', 'match_date' => today()->toDateString(), 'status' => 'completed', 'is_ranking_game' => '1'];
    $v = fn (string $key) => old($key, data_get($prefill ?? [], $key, $defaults[$key] ?? null));
@endphp

@section('content')
    @if ($match)
        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-1 text-sm text-gray-600 hover:underline mb-2">
            <x-icon name="arrow-left" class="w-4 h-4" /> Back to dashboard
        </a>
    @endif
    <h1 class="text-2xl font-bold mb-4">{{ $match ? 'Edit Match' : 'Record Match' }}</h1>
    @if ($match)
        <p class="-mt-3 mb-4 text-sm text-gray-500">Fix a misspelled name, the score, date or court, then click <strong>Save Changes</strong>.</p>
    @endif

        <form method="POST" action="{{ $match ? route('admin.matches.update', $match) : route('admin.matches.store') }}" class="bg-white rounded-xl shadow p-4 grid gap-5">
            @csrf
            @if ($match) @method('PUT') @endif

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
                        <option value="doubles" @selected($v('match_type') == 'doubles')>Doubles (2 vs 2)</option>
                        <option value="singles" @selected($v('match_type') == 'singles')>Singles (1 vs 1)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Format</label>
                    <select name="format" id="format" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="1" @selected($v('format') == '1')>1 set (first to 6)</option>
                        <option value="to7" @selected($v('format') == 'to7')>1 set, first to 7</option>
                        <option value="pro" @selected($v('format') == 'pro')>1 set, first to 8 (pro set)</option>
                        <option value="3" @selected($v('format') == '3')>Best of 3 sets</option>
                        <option value="5" @selected($v('format') == '5')>Best of 5 sets</option>
                    </select>
                </div>
            </div>

            {{-- When & where --}}
            <div class="grid gap-3 md:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Date</label>
                    <input type="date" name="match_date" value="{{ $v('match_date') }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Start time (optional)</label>
                    <input type="time" name="start_time" value="{{ $v('start_time') }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Venue / court (optional)</label>
                    <input type="text" name="venue" value="{{ $v('venue') }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
            </div>

            {{-- Teams: type any name. Members are suggested; other names are recorded but never added to Members. --}}
            <datalist id="player-names">
                @foreach ($players as $player)
                    <option value="{{ $player->full_name }}"></option>
                @endforeach
            </datalist>

            <div>
            <p class="text-xs text-gray-500 mb-2">
                Type the players' names (nicknames are OK) or pick from the suggestions.
                Names that are not in <a href="{{ route('admin.players.index') }}" class="font-medium text-green-700 hover:underline">Members</a>
                are still recorded, but are not added to Members.
            </p>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ([1, 2] as $team)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <p class="text-sm font-semibold mb-2">Team {{ $team }}</p>

                        @foreach (['a' => 'Player', 'b' => 'Partner'] as $slot => $label)
                            <div class="{{ $slot === 'b' ? 'partner mt-2' : '' }}">
                                <label class="block text-xs text-gray-500 mb-1">{{ $label }}</label>
                                <input type="text" name="p{{ $team }}{{ $slot }}" value="{{ $v("p$team$slot") }}"
                                       list="player-names" autocomplete="off" maxlength="100"
                                       class="w-full rounded-lg border px-3 py-2 @error("p$team$slot") border-red-400 bg-red-50 @else border-gray-300 @enderror">
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            </div>

            {{-- Officials (optional). Plain names: they are NOT added to Members and get no games. --}}
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium mb-1">Referee (optional)</label>
                    <input type="text" name="referee" value="{{ $v('referee') }}" list="player-names" autocomplete="off" maxlength="100"
                           class="w-full rounded-lg border px-3 py-2 @error('referee') border-red-400 bg-red-50 @else border-gray-300 @enderror">
                    @error('referee') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Ball boy (optional)</label>
                    <input type="text" name="ball_boy" value="{{ $v('ball_boy') }}" autocomplete="off" maxlength="150"
                           class="w-full rounded-lg border px-3 py-2 @error('ball_boy') border-red-400 bg-red-50 @else border-gray-300 @enderror">
                    @error('ball_boy') <p class="text-red-600 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-500 mt-1">For two ball boys, separate the names with a comma.</p>
                </div>
            </div>

            {{-- Official ranking game: counts toward the Official Ranking (the game is always recorded either way) --}}
            <div>
                <label class="flex items-start gap-2 text-sm">
                    <input type="hidden" name="is_ranking_game" value="0">
                    <input type="checkbox" name="is_ranking_game" value="1" class="mt-1 rounded border-gray-300" @checked((string) $v('is_ranking_game') === '1')>
                    <span>
                        <span class="font-medium">Official ranking game</span>
                        <span class="block text-xs text-gray-500">Counts toward the Official Ranking. Untick for practice / recreational games; they are still recorded in the players' overall record.</span>
                    </span>
                </label>
            </div>

            {{-- Result type --}}
            <div class="grid gap-3 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium mb-1">Result type</label>
                    <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="completed" @selected($v('status') == 'completed')>Completed</option>
                        <option value="retired" @selected($v('status') == 'retired')>Retired (injury, etc.)</option>
                        <option value="walkover" @selected($v('status') == 'walkover')>Walkover (no-show)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Winner (only for retired/walkover)</label>
                    <select name="winner_side" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        <option value="">Auto from score</option>
                        <option value="1" @selected($v('winner_side') == '1')>Team 1</option>
                        <option value="2" @selected($v('winner_side') == '2')>Team 2</option>
                    </select>
                </div>
            </div>

            {{-- Set scores --}}
            <div>
                <p class="font-medium">Score</p>
                <p class="text-xs text-gray-500 mb-3" id="score-help">
                    Games won by each team. TB = the losing team's tiebreak points, only for 7-6.
                </p>

                <div class="grid grid-cols-4 gap-2 text-xs font-medium text-gray-500 mb-1">
                    <span>Set</span><span class="text-center">Team 1</span><span class="text-center">Team 2</span><span class="text-center">TB</span>
                </div>

                <div class="grid gap-2">
                    @for ($i = 0; $i < 5; $i++)
                        <div class="set-row grid grid-cols-4 gap-2 items-center">
                            <span class="text-sm text-gray-600">Set {{ $i + 1 }}</span>
                            <input type="number" inputmode="numeric" min="0" max="9"
                                   name="sets[{{ $i }}][p1]" value="{{ $v("sets.$i.p1") }}"
                                   class="rounded-lg border border-gray-300 px-2 py-2 text-center">
                            <input type="number" inputmode="numeric" min="0" max="9"
                                   name="sets[{{ $i }}][p2]" value="{{ $v("sets.$i.p2") }}"
                                   class="rounded-lg border border-gray-300 px-2 py-2 text-center">
                            <input type="number" inputmode="numeric" min="0"
                                   name="sets[{{ $i }}][tb]" value="{{ $v("sets.$i.tb") }}"
                                   class="rounded-lg border border-gray-200 bg-gray-50 px-2 py-2 text-center">
                        </div>
                    @endfor
                </div>
            </div>

            <button type="submit"
                    class="rounded-lg bg-green-700 text-white font-medium px-4 py-3 hover:bg-green-800">
                {{ $match ? 'Save Changes' : 'Save Match' }}
            </button>
        </form>

        <script>
            // Show partner fields only for doubles, and only as many set rows as the format needs
            function syncForm() {
                const doubles = document.getElementById('match_type').value === 'doubles';
                document.querySelectorAll('.partner').forEach(function (el) {
                    el.style.display = doubles ? '' : 'none';
                    el.querySelector('input').disabled = !doubles;
                });

                const format = document.getElementById('format').value;
                const longSet = { to7: 7, pro: 8 };
                const games = longSet[format] || 6;
                const sets = longSet[format] ? 1 : parseInt(format, 10);
                document.querySelectorAll('.set-row').forEach(function (row, i) {
                    const show = i < sets;
                    row.style.display = show ? '' : 'none';
                    row.querySelectorAll('input').forEach(function (input, k) {
                        input.disabled = !show;
                        if (k < 2) input.max = games + 1;
                    });
                });

                document.getElementById('score-help').textContent = games > 6
                    ? 'Games won by each team, first to ' + games + ' (e.g. ' + games + '-' + (games - 3) + ', ' + (games + 1) + '-' + (games - 1) + '). At ' + games + '-' + games + ' a tiebreak decides it: enter ' + (games + 1) + '-' + games + ', and TB = the losing team\u2019s tiebreak points.'
                    : 'Games won by each team. TB = the losing team\u2019s tiebreak points, only for 7-6.';
                if (sets === 1) {
                    document.getElementById('score-help').textContent += ' No tiebreak played? Enter the same games for both teams (e.g. ' + games + '-' + games + ') and leave TB empty: it is saved as a draw.';
                }
            }

            document.getElementById('match_type').addEventListener('change', syncForm);
            document.getElementById('format').addEventListener('change', syncForm);
            syncForm();
        </script>
@endsection