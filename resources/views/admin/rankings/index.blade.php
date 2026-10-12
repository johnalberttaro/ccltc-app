@extends('layouts.admin')

@section('title', 'Official Ranking')

@php
    $pct = fn ($v) => rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
    $signed = fn ($v) => $v > 0 ? '+' . $v : (string) $v;
    $form = $edit ?? null;
@endphp

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-5">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-green-100 text-green-700 flex items-center justify-center"><x-icon name="chart" /></span>
            <div>
                <h1 class="text-2xl font-bold">Official Ranking</h1>
                <p class="text-sm text-gray-500">Rankings are based on the same number of official games for all eligible players.</p>
            </div>
        </div>

        @if ($periods->count() > 1)
            <form method="GET">
                <label for="period-pick" class="block text-xs text-gray-500 mb-1">Ranking period</label>
                <select id="period-pick" name="period" class="rounded-lg border border-gray-300 px-3 py-2" onchange="this.form.submit()">
                    @foreach ($periods as $p)
                        <option value="{{ $p->id }}" @selected($period && $period->id === $p->id)>{{ $p->name }}{{ $p->is_active ? ' (active)' : '' }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @if (! $period)
        <div class="bg-white rounded-xl shadow p-6 mb-6">
            <p class="font-semibold">No ranking period yet</p>
            <p class="text-sm text-gray-500 mt-1">Create one below, e.g. <strong>October 2026</strong>, Oct 1 to Oct 31, <strong>5</strong> official games per player. The official ranking then compares every player on those 5 games.</p>
        </div>
    @else
        {{-- Period summary --}}
        <div class="bg-white rounded-xl shadow p-4 mb-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <div>
                <span class="text-gray-500">Period:</span>
                <span class="font-semibold">{{ $period->name }}</span>
                @if ($period->is_active)
                    <span class="ml-1 rounded bg-green-100 text-green-800 text-[11px] font-bold uppercase px-2 py-0.5">Active</span>
                @endif
            </div>
            <div><span class="text-gray-500">Dates:</span> {{ $period->dateLabel() }}</div>
            <div><span class="text-gray-500">Official games per player:</span> <span class="font-semibold">{{ $period->games_required }}</span></div>
        </div>

        {{-- Qualified players --}}
        <h2 class="text-lg font-bold mb-3">Ranking</h2>
        @if ($qualified->isEmpty())
            <div class="bg-white rounded-xl shadow p-6 mb-6 text-sm text-gray-500">
                No player has completed {{ $period->games_required }} official ranking {{ Str::plural('game', $period->games_required) }} in this period yet.
            </div>
        @else
            <div class="bg-white rounded-xl shadow mb-2 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-gray-500 border-b border-gray-100">
                            <th class="text-left font-medium px-4 py-3">Rank</th>
                            <th class="text-left font-medium px-4 py-3">Player</th>
                            <th class="text-center font-medium px-3 py-3">Wins</th>
                            <th class="text-center font-medium px-3 py-3">Ranking Games</th>
                            <th class="text-center font-medium px-3 py-3">Record</th>
                            <th class="text-center font-medium px-3 py-3">Win Rate</th>
                            <th class="text-center font-medium px-3 py-3" title="Sets won minus sets lost (ranking games only)">Sets +/-</th>
                            <th class="text-center font-medium px-3 py-3" title="Games won minus games lost (ranking games only)">Games +/-</th>
                            <th class="text-center font-medium px-3 py-3" title="All recorded games, any date">Overall</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($qualified as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <span class="w-7 h-7 rounded-full bg-green-100 text-green-800 text-sm font-bold flex items-center justify-center">{{ $row['rank'] }}</span>
                                </td>
                                <td class="px-4 py-3 font-medium whitespace-nowrap">
                                    <a href="{{ route('admin.rankings.player', [$row['player'], 'period' => $period->id]) }}" class="hover:underline">{{ $row['player']->full_name }}</a>
                                </td>
                                <td class="px-3 py-3 text-center font-semibold tabular-nums">{{ $row['wins'] }}</td>
                                <td class="px-3 py-3 text-center tabular-nums">{{ $row['games'] }}</td>
                                <td class="px-3 py-3 text-center font-semibold text-green-700 tabular-nums whitespace-nowrap">{{ $row['wins'] }}/{{ $row['games'] }}</td>
                                <td class="px-3 py-3 text-center tabular-nums">{{ $pct($row['win_rate']) }}</td>
                                <td class="px-3 py-3 text-center tabular-nums text-gray-600">{{ $signed($row['set_diff']) }}</td>
                                <td class="px-3 py-3 text-center tabular-nums text-gray-600">{{ $signed($row['game_diff']) }}</td>
                                <td class="px-3 py-3 text-center tabular-nums text-gray-500 whitespace-nowrap">{{ $row['overall']['wins'] }}-{{ $row['overall']['losses'] }}@if ($row['overall']['ties'])-{{ $row['overall']['ties'] }}@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <p class="text-xs text-gray-500 mb-6">
            Each player's first {{ $period->games_required }} official {{ Str::plural('game', $period->games_required) }} played in this period count (in the order they were played).
            Later games, non-ranking games and walkovers stay in the player's overall record but do not change the ranking.
            Same number of wins: decided by win rate, then sets +/-, then games +/-. <strong>Overall</strong> = wins-losses (-draws) of all recorded games. A draw counts as a game played but not as a win.
        </p>

        {{-- Not yet qualified --}}
        @if ($pending->isNotEmpty())
            <h2 class="text-lg font-bold mb-1">Not Yet Qualified</h2>
            <p class="text-xs text-gray-500 mb-3">These players need {{ $period->games_required }} official games in this period before they are ranked. Their games still count in their overall record.</p>
            <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3 mb-6">
                @foreach ($pending as $row)
                    <a href="{{ route('admin.rankings.player', [$row['player'], 'period' => $period->id]) }}"
                       class="bg-white rounded-lg shadow px-3 py-2 flex items-center justify-between gap-2 text-sm hover:bg-gray-50">
                        <span class="font-medium truncate">{{ $row['player']->full_name }}</span>
                        <span class="shrink-0 text-xs text-gray-500 tabular-nums">{{ $row['games'] }} of {{ $row['required'] }} games</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endif

    {{-- Ranking periods (settings) --}}
    <h2 class="text-lg font-bold mb-3">Ranking Periods</h2>
    <div class="grid gap-5 md:grid-cols-2 mb-6">
        <div class="bg-white rounded-xl shadow divide-y divide-gray-100 self-start">
            @forelse ($periods as $p)
                <div class="p-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                    <div class="flex-1 min-w-[10rem]">
                        <p class="font-medium">
                            {{ $p->name }}
                            @if ($p->is_active)
                                <span class="ml-1 rounded bg-green-100 text-green-800 text-[11px] font-bold uppercase px-2 py-0.5">Active</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-500">{{ $p->dateLabel() }} &middot; {{ $p->games_required }} official {{ Str::plural('game', $p->games_required) }} per player</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-1">
                        <a href="{{ route('admin.rankings.index', ['period' => $p->id]) }}" class="rounded-lg px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-100">View</a>
                        <a href="{{ route('admin.rankings.index', ['period' => $period?->id, 'edit' => $p->id]) }}#period-form" class="rounded-lg px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-100 inline-flex items-center gap-1"><x-icon name="pencil" class="w-4 h-4" /> Edit</a>
                        @unless ($p->is_active)
                            <form method="POST" action="{{ route('admin.rankings.activate', $p) }}">
                                @csrf
                                <button class="rounded-lg px-2.5 py-1.5 text-sm text-green-700 hover:bg-green-50">Make active</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('admin.rankings.destroy', $p) }}"
                              data-confirm="Delete the period {{ $p->name }}?"
                              data-confirm-body="Only the period settings are deleted. All games stay recorded."
                              data-confirm-button="Delete" data-confirm-tone="danger">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-lg px-2.5 py-1.5 text-sm text-red-700 hover:bg-red-50 inline-flex items-center gap-1"><x-icon name="trash" class="w-4 h-4" /> Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="p-4 text-sm text-gray-500">No ranking periods yet.</p>
            @endforelse
        </div>

        <form method="POST" id="period-form"
              action="{{ $form ? route('admin.rankings.update', $form) : route('admin.rankings.store') }}"
              class="bg-white rounded-xl shadow p-4 grid gap-3 self-start">
            @csrf
            @if ($form) @method('PUT') @endif

            <p class="font-semibold">{{ $form ? 'Edit ' . $form->name : 'New ranking period' }}</p>

            @if ($errors->any())
                <div class="rounded-lg bg-red-100 text-red-800 px-3 py-2 text-sm">{{ $errors->first() }}</div>
            @endif

            <div>
                <label for="rp-name" class="block text-sm font-medium mb-1">Name</label>
                <input type="text" id="rp-name" name="name" required maxlength="100"
                       value="{{ old('name', $form?->name ?? now()->format('F Y')) }}"
                       class="w-full rounded-lg border border-gray-300 px-3 py-2">
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="rp-start" class="block text-sm font-medium mb-1">Start date</label>
                    <input type="date" id="rp-start" name="start_date" required
                           value="{{ old('start_date', $form?->start_date?->toDateString() ?? now()->startOfMonth()->toDateString()) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label for="rp-end" class="block text-sm font-medium mb-1">End date</label>
                    <input type="date" id="rp-end" name="end_date" required
                           value="{{ old('end_date', $form?->end_date?->toDateString() ?? now()->endOfMonth()->toDateString()) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
            </div>
            <div>
                <label for="rp-games" class="block text-sm font-medium mb-1">Official ranking games per player</label>
                <input type="number" id="rp-games" name="games_required" required min="1" max="100"
                       value="{{ old('games_required', $form?->games_required ?? 5) }}"
                       class="w-full md:w-72 rounded-lg border border-gray-300 px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">Every player is compared on this many games. Players with fewer are "Not Yet Qualified".</p>
            </div>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300"
                       @checked(old('is_active', $form ? $form->is_active : true))>
                Active period (shown as Official Ranking on the Dashboard)
            </label>
            <div class="flex flex-wrap gap-2">
                <button class="rounded-lg bg-green-700 text-white font-medium px-4 py-2 hover:bg-green-800">{{ $form ? 'Save changes' : 'Create period' }}</button>
                @if ($form)
                    <a href="{{ route('admin.rankings.index', ['period' => $period?->id]) }}" class="rounded-lg px-4 py-2 text-gray-600 hover:bg-gray-100">Cancel</a>
                @endif
            </div>
        </form>
    </div>
@endsection