@extends('layouts.public')

@section('title', 'Match Details')

@section('content')
    <a href="{{ route('home', ['date' => $match->match_date->toDateString()]) }}"
       class="text-sm text-green-700 hover:underline">&larr; Back to games</a>

    <div class="bg-white rounded-xl shadow p-4 mt-3">
        <p class="text-sm text-gray-500 mb-4">
            {{ $match->match_date->format('M d, Y') }}
            @if ($match->timeLabel()) &middot; {{ $match->timeLabel() }} @endif
            @if ($match->venue) &middot; {{ $match->venue }} @endif
            &middot; {{ $match->typeLabel() }}
            &middot; {{ $match->formatLabel() }}
        </p>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="text-xs text-gray-500">
                        <th class="text-left py-2">{{ $match->match_type === 'doubles' ? 'Team' : 'Player' }}</th>
                        @foreach ($match->sets as $set)
                            <th class="w-14 text-center">{{ $match->sets->count() > 1 ? 'Set ' . $set->set_number : 'Games' }}</th>
                        @endforeach
                        <th class="w-20"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ([1, 2] as $side)
                        @php $won = (int) $match->winner_side === $side; @endphp
                        <tr class="border-t {{ $won ? 'font-bold' : 'text-gray-600' }}">
                            <td class="py-3 pr-3">{{ $match->sideName($side) }}</td>
                            @foreach ($match->sets as $set)
                                @php
                                    $games = $side === 1 ? $set->side1_games : $set->side2_games;
                                    $tb = $side === 1 ? $set->side1_tiebreak : $set->side2_tiebreak;
                                @endphp
                                <td class="text-center text-xl">
                                    {{ $games }}@if ($tb !== null)<sup class="text-xs">{{ $tb }}</sup>@endif
                                </td>
                            @endforeach
                            <td class="text-right">
                                @if ($won)
                                    <span class="rounded bg-green-600 text-white text-[11px] font-bold uppercase px-2 py-0.5">Winner</span>
                                @elseif ($match->winner_side)
                                    <span class="rounded bg-gray-200 text-gray-600 text-[11px] font-bold uppercase px-2 py-0.5">Lost</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-sm">
            <span class="text-gray-500">Final score:</span>
            <span class="tabular-nums font-semibold">{{ $match->scoreLine() }}</span>
            @if ($match->isTie())
                <span class="font-semibold text-amber-700">(draw)</span>
            @endif
            @if ($match->status !== 'completed')
                <span class="text-amber-700">({{ $match->status === 'retired' ? 'retired' : 'walkover' }})</span>
            @endif
        </p>

        @if ($match->referee || $match->ball_boy)
            <dl class="mt-3 grid gap-1 text-sm sm:flex sm:gap-6">
                @if ($match->referee)
                    <div><dt class="inline text-gray-500">Referee:</dt> <dd class="inline font-medium">{{ $match->referee }}</dd></div>
                @endif
                @if ($match->ball_boy)
                    <div><dt class="inline text-gray-500">Ball boy:</dt> <dd class="inline font-medium">{{ $match->ball_boy }}</dd></div>
                @endif
            </dl>
        @endif
    </div>
@endsection