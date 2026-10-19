@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <div class="mb-5">
        <p class="text-sm text-gray-500">{{ today()->format('l, F j, Y') }}</p>
        <h1 class="text-2xl font-bold">Welcome, {{ auth()->user()->name }}</h1>
    </div>

    {{-- Quick actions --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <a href="{{ route('admin.matches.create') }}"
           class="flex items-center justify-center gap-2 rounded-xl bg-green-700 text-white font-semibold px-4 py-3 hover:bg-green-800 shadow">
            <x-icon name="ball" class="w-5 h-5" /> Record Match
        </a>
        <a href="{{ route('admin.events.create') }}"
           class="flex items-center justify-center gap-2 rounded-xl bg-white font-semibold px-4 py-3 hover:bg-sky-50 shadow text-sky-800">
            <x-icon name="plus" class="w-5 h-5" /> Add Event
        </a>
        <a href="{{ route('admin.tournaments.create') }}"
           class="flex items-center justify-center gap-2 rounded-xl bg-white font-semibold px-4 py-3 hover:bg-amber-50 shadow text-amber-800">
            <x-icon name="trophy" class="w-5 h-5" /> Create Tournament
        </a>
        <a href="{{ route('admin.announcements.create') }}"
           class="flex items-center justify-center gap-2 rounded-xl bg-white font-semibold px-4 py-3 hover:bg-rose-50 shadow text-rose-800">
            <x-icon name="plus" class="w-5 h-5" /> Post Announcement
        </a>
    </div>

    {{-- Information summary --}}
    <div class="grid gap-3 sm:grid-cols-3 mb-3">
        <a href="{{ route('admin.events.index', ['status' => 'upcoming']) }}" class="bg-white rounded-xl shadow p-4 flex items-center gap-4 hover:shadow-md transition">
            <span class="w-12 h-12 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center shrink-0"><x-icon name="calendar" class="w-6 h-6" /></span>
            <span>
                <span class="block text-2xl font-bold">{{ $stats['upcomingEvents'] }}</span>
                <span class="block text-sm text-gray-500">Upcoming Events</span>
                @if ($stats['ongoingEvents'] > 0)
                    <span class="block text-xs text-green-700 font-medium">+ {{ $stats['ongoingEvents'] }} ongoing now</span>
                @endif
            </span>
        </a>
        <a href="{{ route('admin.tournaments.index') }}" class="bg-white rounded-xl shadow p-4 flex items-center gap-4 hover:shadow-md transition">
            <span class="w-12 h-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0"><x-icon name="trophy" class="w-6 h-6" /></span>
            <span>
                <span class="block text-2xl font-bold">{{ $stats['upcomingTournaments'] }}</span>
                <span class="block text-sm text-gray-500">Upcoming Tournaments</span>
                @if ($stats['ongoingTournaments'] > 0)
                    <span class="block text-xs text-green-700 font-medium">+ {{ $stats['ongoingTournaments'] }} ongoing now</span>
                @endif
            </span>
        </a>
        <a href="{{ route('admin.announcements.index') }}" class="bg-white rounded-xl shadow p-4 flex items-center gap-4 hover:shadow-md transition">
            <span class="w-12 h-12 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center shrink-0"><x-icon name="megaphone" class="w-6 h-6" /></span>
            <span>
                <span class="block text-2xl font-bold">{{ $stats['activeAnnouncements'] }}</span>
                <span class="block text-sm text-gray-500">Active Announcements</span>
                @if ($stats['scheduledAnnouncements'] > 0)
                    <span class="block text-xs text-violet-700 font-medium">+ {{ $stats['scheduledAnnouncements'] }} scheduled (not visible yet)</span>
                @endif
            </span>
        </a>
    </div>

    {{-- Match summary --}}
    <div class="grid grid-cols-3 gap-3 mb-6">
        <div class="bg-white rounded-xl shadow p-4">
            <p class="text-xs md:text-sm text-gray-500">Games today</p>
            <p class="text-2xl md:text-3xl font-bold text-green-700">{{ $stats['today'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <p class="text-xs md:text-sm text-gray-500">Total games</p>
            <p class="text-2xl md:text-3xl font-bold">{{ $stats['total'] }}</p>
        </div>
        <a href="{{ route('admin.players.index') }}" class="bg-white rounded-xl shadow p-4 hover:shadow-md transition">
            <p class="text-xs md:text-sm text-gray-500">Members</p>
            <p class="text-2xl md:text-3xl font-bold">{{ $stats['players'] }}</p>
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 grid gap-6">
            {{-- Today's games --}}
            <section>
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-bold">Today's Games</h2>
                    <a href="{{ route('home') }}" target="_blank" class="text-sm text-green-700 hover:underline">See public view &#8599;</a>
                </div>

                @if ($todayMatches->isEmpty())
                    <div class="bg-white rounded-xl shadow p-6 text-center">
                        <p class="text-gray-500">No games recorded today yet.</p>
                        <a href="{{ route('admin.matches.create') }}" class="inline-block mt-3 text-green-700 font-medium hover:underline">Record the first game &rarr;</a>
                    </div>
                @else
                    <div class="bg-white rounded-xl shadow divide-y">
                        @foreach ($todayMatches as $match)
                            <div class="p-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                                <div class="min-w-0">
                                    <p class="text-xs text-gray-500">
                                        Game {{ $loop->iteration }} &middot; {{ $match->typeLabel() }}
                                        @if ($match->timeLabel()) &middot; {{ $match->timeLabel() }} @endif
                                        @if ($match->venue) &middot; {{ $match->venue }} @endif
                                        @if ($match->referee) &middot; Referee: {{ $match->referee }} @endif
                                        @if ($match->ball_boy) &middot; Ball boy: {{ $match->ball_boy }} @endif
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
                                    <a href="{{ route('admin.matches.edit', $match) }}" class="text-sm text-gray-700 hover:underline">Edit</a>
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
            </section>

            {{-- Earlier games --}}
            <section>
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="text-lg font-bold">Earlier Games</h2>
                    <a href="{{ route('admin.games.index', ['date' => today()->subDay()->toDateString()]) }}" class="text-sm text-green-700 hover:underline">All games (any date) &rarr;</a>
                </div>
                @if ($recent->isEmpty())
                    <p class="bg-white rounded-xl shadow p-4 text-gray-500 text-sm">No earlier games yet.</p>
                @else
                    <div class="bg-white rounded-xl shadow divide-y">
                        @foreach ($recent as $match)
                            <div class="p-4 flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="text-xs text-gray-500">
                                        {{ $match->match_date->format('M d, Y') }} &middot; {{ $match->typeLabel() }}
                                        @if ($match->venue) &middot; {{ $match->venue }} @endif
                                        @if ($match->referee) &middot; Referee: {{ $match->referee }} @endif
                                        @if ($match->ball_boy) &middot; Ball boy: {{ $match->ball_boy }} @endif
                                    </p>
                                    @if ($match->isTie())
                                        <p class="text-sm"><span class="font-semibold">{{ $match->sideName(1) }}</span> <span class="font-semibold text-amber-700">drew with</span> {{ $match->sideName(2) }}</p>
                                    @else
                                        <p class="text-sm"><span class="font-semibold text-green-700">{{ $match->winnerName() }}</span> def. {{ $match->loserName() }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-4">
                                    <span class="tabular-nums text-sm font-medium">{{ $match->scoreLine() }}</span>
                                    <a href="{{ route('admin.matches.edit', $match) }}" class="text-sm text-gray-700 hover:underline">Edit</a>
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
            </section>
        </div>

        <div class="grid gap-6 content-start">
        {{-- Coming up --}}
        <section>
            <h2 class="text-lg font-bold mb-3">Coming Up</h2>
            <div class="bg-white rounded-xl shadow divide-y">
                @forelse ($comingUp as $item)
                    <a href="{{ $item['url'] }}" class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50">
                        <span class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $item['type'] === 'Event' ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700' }}">
                            <x-icon :name="$item['type'] === 'Event' ? 'calendar' : 'trophy'" class="w-5 h-5" />
                        </span>
                        <span class="min-w-0">
                            <span class="block font-medium truncate">{{ $item['title'] }}</span>
                            <span class="block text-xs text-gray-500">{{ $item['type'] }} &middot; {{ $item['when']->format('M j, g:i A') }}</span>
                        </span>
                    </a>
                @empty
                    <p class="px-4 py-3 text-gray-500 text-sm">Nothing scheduled yet.</p>
                @endforelse
            </div>
        </section>

        {{-- Most wins: total wins of all recorded games --}}
        <section>
            <h2 class="text-lg font-bold mb-3">Most Wins</h2>
            <div class="bg-white rounded-xl shadow divide-y">
                @forelse ($topPlayers as $i => $player)
                    <a href="{{ route('admin.rankings.player', $player->id) }}" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50">
                        <div class="flex items-center gap-3">
                            <span class="w-7 h-7 rounded-full bg-green-100 text-green-800 text-sm font-bold flex items-center justify-center">{{ $i + 1 }}</span>
                            <span class="font-medium">{{ $player->full_name }}</span>
                        </div>
                        <span class="text-sm text-gray-500">{{ $player->wins }} {{ Str::plural('win', $player->wins) }}</span>
                    </a>
                @empty
                    <p class="px-4 py-3 text-gray-500 text-sm">No wins recorded yet.</p>
                @endforelse
            </div>
        </section>

        {{-- Official ranking: fair ranking (same number of official games for every eligible player) --}}
        <section>
            <div class="flex items-center justify-between gap-2 mb-3">
                <h2 class="text-lg font-bold">Official Ranking</h2>
                <a href="{{ route('admin.rankings.index') }}" class="text-sm text-green-700 hover:underline">{{ $rankingPeriod ? 'Full ranking' : 'Set up' }} &rarr;</a>
            </div>
            <div class="bg-white rounded-xl shadow divide-y divide-gray-100">
                @if (! $rankingPeriod)
                    <p class="px-4 py-3 text-gray-500 text-sm">
                        No ranking period yet. <a href="{{ route('admin.rankings.index') }}" class="font-medium text-green-700 hover:underline">Create one</a>
                        (e.g. this month, 5 official games per player) to start the official ranking.
                    </p>
                @else
                    <p class="px-4 py-2 text-xs text-gray-500">{{ $rankingPeriod->name }} &middot; based on {{ $rankingPeriod->games_required }} official {{ Str::plural('game', $rankingPeriod->games_required) }} per player</p>
                    @forelse ($topRanked as $row)
                        <a href="{{ route('admin.rankings.player', $row['player']) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50">
                            <span class="flex items-center gap-3 min-w-0">
                                <span class="w-7 h-7 shrink-0 rounded-full bg-green-100 text-green-800 text-sm font-bold flex items-center justify-center">{{ $row['rank'] }}</span>
                                <span class="font-medium truncate">{{ $row['player']->full_name }}</span>
                            </span>
                            <span class="text-right shrink-0">
                                <span class="block text-sm font-semibold tabular-nums">{{ $row['wins'] }} / {{ $row['games'] }} Wins</span>
                                <span class="block text-xs text-gray-500 tabular-nums">{{ rtrim(rtrim(number_format($row['win_rate'], 1), '0'), '.') }}% Win Rate</span>
                            </span>
                        </a>
                    @empty
                        <p class="px-4 py-3 text-gray-500 text-sm">No player has completed {{ $rankingPeriod->games_required }} official games yet.</p>
                    @endforelse
                    @if ($pendingCount > 0)
                        <a href="{{ route('admin.rankings.index') }}" class="block px-4 py-2 text-xs text-gray-500 hover:bg-gray-50">
                            {{ $pendingCount }} {{ Str::plural('player', $pendingCount) }} not yet qualified &rarr;
                        </a>
                    @endif
                    <p class="px-4 py-2 text-xs text-gray-500">Rankings are based on the same number of official games for all eligible players.</p>
                @endif
            </div>
        </section>
        </div>
    </div>
@endsection