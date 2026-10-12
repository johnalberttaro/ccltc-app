@extends('layouts.public')

@section('title', $date->isToday() ? "Today's Games" : 'Games on ' . $date->format('M j, Y'))

@section('content')
    {{-- Pinned announcements --}}
    @if ($pinned->isNotEmpty())
        <section class="grid gap-2 mb-5">
            @foreach ($pinned as $note)
                <a href="{{ route('announcements.show', $note) }}"
                   class="flex items-start gap-3 rounded-xl border border-yellow-200 bg-yellow-50 px-4 py-3 hover:bg-yellow-100 transition">
                    <span class="w-8 h-8 rounded-lg bg-yellow-200 text-yellow-800 flex items-center justify-center shrink-0">
                        <x-icon name="megaphone" class="w-5 h-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-xs font-semibold uppercase tracking-wide text-yellow-800">Important</span>
                        <span class="block font-semibold text-gray-900">{{ $note->title }}</span>
                        @if ($note->event_at)
                            <span class="block text-sm font-medium text-yellow-900">
                                {{ $note->eventLabel(true) }}@if ($note->event_place) &middot; {{ $note->event_place }}@endif
                            </span>
                        @endif
                        <span class="block text-sm text-gray-600 line-clamp-1">{{ $note->body }}</span>
                    </span>
                </a>
            @endforeach
        </section>
    @endif

    {{-- Heading --}}
    <section class="mb-4">
        <p class="text-sm font-semibold text-green-700 uppercase tracking-wide">
            {{ $date->isToday() ? "Today's Games" : ($date->isYesterday() ? "Yesterday's Games" : 'Games') }}
        </p>
        <h1 class="text-2xl md:text-3xl font-bold">{{ $date->format('l, F j, Y') }}</h1>
    </section>

    {{-- Date picker --}}
    <div class="bg-white rounded-xl shadow p-3 mb-5">
        <form method="GET" action="{{ route('home') }}" class="flex flex-wrap items-center gap-2">
            <a href="{{ route('home', ['date' => $date->copy()->subDay()->toDateString()]) }}"
               class="rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-100" title="Previous day">&larr;</a>

            <input type="date" name="date" value="{{ $date->toDateString() }}"
                   onchange="this.form.submit()"
                   class="rounded-lg border border-gray-300 px-3 py-2">

            <a href="{{ route('home', ['date' => $date->copy()->addDay()->toDateString()]) }}"
               class="rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-100" title="Next day">&rarr;</a>

            <a href="{{ route('home') }}"
               class="rounded-lg border px-3 py-2 text-sm {{ $date->isToday() ? 'bg-green-700 text-white border-green-700' : 'border-gray-300 hover:bg-gray-100' }}">Today</a>
            <a href="{{ route('home', ['date' => today()->subDay()->toDateString()]) }}"
               class="rounded-lg border px-3 py-2 text-sm {{ $date->isYesterday() ? 'bg-green-700 text-white border-green-700' : 'border-gray-300 hover:bg-gray-100' }}">Yesterday</a>
        </form>
    </div>

    {{-- Quick numbers --}}
    <div class="grid grid-cols-2 gap-3 mb-6">
        <div class="bg-white rounded-xl shadow p-4 text-center">
            <p class="text-3xl font-bold text-green-700">{{ $matches->count() }}</p>
            <p class="text-sm text-gray-500">{{ Str::plural('Game', $matches->count()) }} played</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4 text-center">
            <p class="text-3xl font-bold text-green-700">{{ $playersToday->count() }}</p>
            <p class="text-sm text-gray-500">{{ Str::plural('Player', $playersToday->count()) }}</p>
        </div>
    </div>

    @if ($matches->isEmpty())
        <div class="bg-white rounded-xl shadow p-8 text-center">
            <p class="text-lg font-semibold">No games recorded for this date.</p>
            <p class="text-sm text-gray-500 mt-1">Check another day using the date picker above.</p>
        </div>
    @else
        {{-- Scores: numbered list, winner on top (highlighted), loser below --}}
        <section>
            <h2 class="text-lg font-bold mb-3">Games</h2>

            <ol class="bg-white rounded-xl shadow divide-y divide-gray-100 overflow-hidden">
                @foreach ($matches as $match)
                    @php $order = (int) $match->winner_side === 2 ? [2, 1] : [1, 2]; @endphp
                    <li>
                        <a href="{{ route('matches.show', $match) }}" class="flex gap-3 sm:gap-4 px-4 py-3 hover:bg-gray-50 transition">
                            <span class="w-6 shrink-0 pt-0.5 text-right text-lg font-bold tabular-nums text-gray-400">{{ $loop->iteration }}.</span>

                            <div class="flex-1 min-w-0">
                                @foreach ($order as $side)
                                    @php $won = (int) $match->winner_side === $side; @endphp
                                    <div class="flex items-baseline justify-between gap-3 {{ $loop->first ? '' : 'mt-1' }}">
                                        <p class="min-w-0 truncate text-base {{ $won ? 'font-bold text-green-800' : 'font-medium text-gray-500' }}" title="{{ $match->sideName($side) }}">
                                            {{ $match->sideName($side) }}
                                        </p>
                                        <div class="flex gap-3 shrink-0">
                                            @forelse ($match->sets as $set)
                                                @php
                                                    $games = $side === 1 ? $set->side1_games : $set->side2_games;
                                                    $tb = $side === 1 ? $set->side1_tiebreak : $set->side2_tiebreak;
                                                @endphp
                                                <span class="w-7 text-right text-lg tabular-nums {{ $won ? 'font-bold text-green-800' : 'font-semibold text-gray-400' }}">
                                                    {{ $games }}@if ($tb !== null)<sup class="text-[10px]">{{ $tb }}</sup>@endif
                                                </span>
                                            @empty
                                                <span class="text-sm text-gray-400">{{ $match->status === 'walkover' && $won ? 'W/O' : '' }}</span>
                                            @endforelse
                                        </div>
                                    </div>
                                @endforeach

                                <p class="mt-1 text-xs text-gray-400">
                                    {{ $match->typeLabel() }}
                                    @if ($match->venue) &middot; {{ $match->venue }} @endif
                                    @if ($match->timeLabel()) &middot; {{ $match->timeLabel() }} @endif
                                    @if ($match->referee) &middot; Referee: {{ $match->referee }} @endif
                                    @if ($match->ball_boy) &middot; Ball boy: {{ $match->ball_boy }} @endif
                                    @if ($match->isTie()) &middot; <span class="font-semibold text-amber-700">Draw</span> @endif
                                    @if ($match->status === 'retired') &middot; <span class="text-amber-700">ended early (retired)</span> @endif
                                    @if ($match->status === 'walkover') &middot; <span class="text-amber-700">walkover</span> @endif
                                </p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
@endsection