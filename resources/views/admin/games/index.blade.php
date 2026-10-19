@extends('layouts.admin')

@section('title', 'All Games')

@section('content')
    <div class="flex items-center gap-3 mb-5">
        <span class="w-10 h-10 rounded-xl bg-green-100 text-green-700 flex items-center justify-center"><x-icon name="ball" /></span>
        <div>
            <h1 class="text-2xl font-bold">All Games</h1>
            <p class="text-sm text-gray-500">Pick any date to see, edit or delete the games recorded that day.</p>
        </div>
    </div>

    {{-- Date picker --}}
    <div class="bg-white rounded-xl shadow p-3 mb-4">
        <form method="GET" action="{{ route('admin.games.index') }}" class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.games.index', ['date' => $date->copy()->subDay()->toDateString()]) }}"
               class="rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-100" title="Previous day">&larr;</a>

            <input type="date" name="date" value="{{ $date->toDateString() }}"
                   onchange="this.form.submit()"
                   class="rounded-lg border border-gray-300 px-3 py-2">

            <a href="{{ route('admin.games.index', ['date' => $date->copy()->addDay()->toDateString()]) }}"
               class="rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-100" title="Next day">&rarr;</a>

            <a href="{{ route('admin.games.index') }}"
               class="rounded-lg border px-3 py-2 text-sm {{ $date->isToday() ? 'bg-green-700 text-white border-green-700' : 'border-gray-300 hover:bg-gray-100' }}">Today</a>
            <a href="{{ route('admin.games.index', ['date' => today()->subDay()->toDateString()]) }}"
               class="rounded-lg border px-3 py-2 text-sm {{ $date->isYesterday() ? 'bg-green-700 text-white border-green-700' : 'border-gray-300 hover:bg-gray-100' }}">Yesterday</a>
        </form>
    </div>

    {{-- Recent days that have games --}}
    @if ($days->isNotEmpty())
        <div class="mb-5">
            <p class="text-xs text-gray-500 mb-2">Days with games:</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($days as $day)
                    @php $d = \Illuminate\Support\Carbon::parse($day->day); @endphp
                    <a href="{{ route('admin.games.index', ['date' => $d->toDateString()]) }}"
                       class="rounded-lg border px-3 py-1.5 text-sm {{ $d->isSameDay($date) ? 'bg-green-700 text-white border-green-700' : 'bg-white border-gray-300 hover:bg-gray-100' }}">
                        {{ $d->format('M j') }} <span class="{{ $d->isSameDay($date) ? '' : 'text-gray-500' }}">({{ $day->total }})</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <h2 class="text-lg font-bold mb-3">
        {{ $date->format('l, F j, Y') }}
        <span class="text-sm font-normal text-gray-500">&middot; {{ $matches->count() }} {{ Str::plural('game', $matches->count()) }}</span>
    </h2>

    @if ($matches->isEmpty())
        <div class="bg-white rounded-xl shadow p-8 text-center">
            <p class="font-semibold">No games recorded for this date.</p>
            <p class="text-sm text-gray-500 mt-1">Pick another day above, or <a href="{{ route('admin.matches.create') }}" class="font-medium text-green-700 hover:underline">record a game</a>.</p>
        </div>
    @else
        <div class="bg-white rounded-xl shadow divide-y">
            @foreach ($matches as $match)
                <div class="p-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500">
                            Game {{ $loop->iteration }} &middot; {{ $match->typeLabel() }}
                            @if ($match->timeLabel()) &middot; {{ $match->timeLabel() }} @endif
                            @if ($match->venue) &middot; {{ $match->venue }} @endif
                            @if ($match->referee) &middot; Referee: {{ $match->referee }} @endif
                            @if ($match->ball_boy) &middot; Ball boy: {{ $match->ball_boy }} @endif
                            @unless ($match->is_ranking_game) &middot; not official @endunless
                        </p>
                        @if ($match->isTie())
                            <p class="font-semibold">{{ $match->sideName(1) }}</p>
                            <p class="text-sm text-gray-600"><span class="font-semibold text-amber-700">drew with</span> {{ $match->sideName(2) }}</p>
                        @else
                            <p class="font-semibold text-green-700">&#10003; {{ $match->winnerName() }}</p>
                            <p class="text-sm text-gray-600">def. {{ $match->loserName() }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="tabular-nums font-semibold">{{ $match->scoreLine() }}</span>
                        <a href="{{ route('matches.show', $match) }}" target="_blank" class="text-sm text-gray-600 hover:underline">View</a>
                        <a href="{{ route('admin.matches.edit', [$match, 'return' => 'games']) }}" class="text-sm text-gray-700 hover:underline">Edit</a>
                        <form method="POST" action="{{ route('admin.matches.destroy', $match) }}"
                              onsubmit="return confirm('Delete this game? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button class="text-sm text-red-600 hover:underline">Delete</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection