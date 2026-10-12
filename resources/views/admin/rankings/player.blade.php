@extends('layouts.admin')

@section('title', $player->full_name)

@php
    $pct = fn ($v) => rtrim(rtrim(number_format($v, 1), '0'), '.') . '%';
@endphp

@section('content')
    <a href="{{ route('admin.rankings.index', ['period' => $period?->id]) }}" class="text-sm text-green-700 hover:underline">&larr; Back to ranking</a>

    <h1 class="text-2xl font-bold mt-2 mb-5">{{ $player->full_name }}</h1>

    <div class="grid gap-4 md:grid-cols-2 mb-6">
        {{-- Overall record: every game ever recorded --}}
        <div class="bg-white rounded-xl shadow p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Overall record (all games)</p>
            <div class="grid grid-cols-4 gap-2 text-center">
                <div><p class="text-2xl font-bold tabular-nums">{{ $overall['games'] }}</p><p class="text-xs text-gray-500">Games</p></div>
                <div><p class="text-2xl font-bold tabular-nums text-green-700">{{ $overall['wins'] }}</p><p class="text-xs text-gray-500">Wins</p></div>
                <div><p class="text-2xl font-bold tabular-nums">{{ $overall['losses'] }}@if ($overall['ties'] ?? 0)<span class="text-base text-gray-500"> / {{ $overall['ties'] }}</span>@endif</p><p class="text-xs text-gray-500">Losses{{ ($overall['ties'] ?? 0) ? ' / Draws' : '' }}</p></div>
                <div><p class="text-2xl font-bold tabular-nums">{{ $pct($overall['win_rate']) }}</p><p class="text-xs text-gray-500">Win rate</p></div>
            </div>
        </div>

        {{-- Ranking record: only the official games that count --}}
        <div class="bg-white rounded-xl shadow p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">
                Ranking record{{ $period ? ' - ' . $period->name : '' }}
            </p>
            @if (! $period)
                <p class="text-sm text-gray-500">No ranking period yet.</p>
            @elseif (! $ranking)
                <p class="text-sm text-gray-500">No official ranking games in this period yet (0 of {{ $period->games_required }}).</p>
            @elseif (! $ranking['qualified'])
                <p class="text-lg font-semibold">Not Yet Qualified</p>
                <p class="text-sm text-gray-500">{{ $ranking['games'] }} of {{ $ranking['required'] }} official games played.</p>
            @else
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div><p class="text-2xl font-bold tabular-nums">#{{ $ranking['rank'] }}</p><p class="text-xs text-gray-500">Rank</p></div>
                    <div><p class="text-2xl font-bold tabular-nums text-green-700">{{ $ranking['wins'] }}/{{ $ranking['games'] }}</p><p class="text-xs text-gray-500">Wins</p></div>
                    <div><p class="text-2xl font-bold tabular-nums">{{ $pct($ranking['win_rate']) }}</p><p class="text-xs text-gray-500">Win rate</p></div>
                </div>
            @endif
        </div>
    </div>

    <h2 class="text-lg font-bold mb-3">Game history</h2>
    @if ($history->isEmpty())
        <div class="bg-white rounded-xl shadow p-6 text-sm text-gray-500">No games recorded yet.</div>
    @else
        <div class="bg-white rounded-xl shadow overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 border-b border-gray-100">
                        <th class="text-left font-medium px-4 py-3">Date</th>
                        <th class="text-left font-medium px-3 py-3">Partner</th>
                        <th class="text-left font-medium px-3 py-3">Opponent</th>
                        <th class="text-center font-medium px-3 py-3">Score</th>
                        <th class="text-center font-medium px-3 py-3">Result</th>
                        <th class="text-left font-medium px-3 py-3">Ranking</th>
                        <th class="px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($history as $h)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $h['match']->match_date->format('M d, Y') }}</td>
                            <td class="px-3 py-3">{{ $h['partner'] ?: '-' }}</td>
                            <td class="px-3 py-3">{{ $h['opponents'] }}</td>
                            <td class="px-3 py-3 text-center tabular-nums whitespace-nowrap">{{ $h['score'] }}</td>
                            <td class="px-3 py-3 text-center">
                                @if ($h['result'] === 'W')
                                    <span class="font-bold text-green-700">Won</span>
                                @elseif ($h['result'] === 'L')
                                    <span class="text-gray-500">Lost</span>
                                @elseif ($h['result'] === 'T')
                                    <span class="font-semibold text-amber-700">Draw</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-xs whitespace-nowrap">
                                @if ($h['counted'])
                                    <span class="rounded bg-green-100 text-green-800 font-semibold px-2 py-0.5">Counted</span>
                                @elseif ($h['match']->is_ranking_game)
                                    <span class="text-gray-500">Official</span>
                                @else
                                    <span class="text-gray-400">Not official</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ route('admin.matches.edit', $h['match']) }}" class="text-gray-700 hover:underline">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 mt-2">
            <strong>Counted</strong> = one of the games used for the ranking in {{ $period?->name ?? 'the ranking period' }}.
            <strong>Official</strong> = an official game that is not counted (outside the period, a walkover, or after the first {{ $period?->games_required ?? 'N' }}).
        </p>
    @endif
@endsection