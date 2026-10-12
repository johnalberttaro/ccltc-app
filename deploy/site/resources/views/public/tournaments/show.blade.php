@extends('layouts.public')

@section('title', $tournament->name)

@section('content')
    <a href="{{ route('tournaments.index') }}" class="inline-flex items-center gap-1 text-sm text-green-700 hover:underline">
        <x-icon name="arrow-left" class="w-4 h-4" /> All tournaments
    </a>

    <header class="mt-3 mb-4">
        <div class="flex flex-wrap items-center gap-2">
            <x-status-badge :status="$tournament->status" />
            <span class="rounded-full bg-gray-900 text-white px-2.5 py-0.5 text-xs font-semibold">{{ $tournament->formatLabel() }}</span>
            <span class="rounded-full bg-gray-100 text-gray-700 px-2.5 py-0.5 text-xs font-semibold">{{ $tournament->typeLabel() }}</span>
            @if ($tournament->category)
                <span class="rounded-full bg-amber-100 text-amber-800 px-2.5 py-0.5 text-xs font-semibold">{{ $tournament->category }}</span>
            @endif
        </div>
        <h1 class="text-2xl md:text-3xl font-bold mt-2">{{ $tournament->name }}</h1>
        <p class="text-sm text-gray-500 flex flex-wrap gap-x-4 gap-y-1 mt-1">
            <span class="inline-flex items-center gap-1"><x-icon name="calendar" class="w-4 h-4" />{{ $tournament->starts_at->format('M j, Y') }}@if ($tournament->ends_at && ! $tournament->ends_at->isSameDay($tournament->starts_at)) &ndash; {{ $tournament->ends_at->format('M j, Y') }}@endif</span>
            @if ($tournament->venue)
                <span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="w-4 h-4" />{{ $tournament->venue }}</span>
            @endif
        </p>
    </header>

    @if (count($tabs) > 1)
        <nav class="mb-5 -mx-4 px-4 overflow-x-auto">
            <div class="flex gap-1 border-b border-gray-200 min-w-max">
                @foreach ($tabs as $key => $label)
                    <a href="{{ route('tournaments.show', $tournament) }}?tab={{ $key }}"
                       class="px-4 py-2.5 text-sm border-b-2 -mb-px {{ $tab === $key ? 'border-green-700 text-green-800 font-semibold' : 'border-transparent text-gray-600 hover:text-gray-900' }}">{{ $label }}</a>
                @endforeach
            </div>
        </nav>
    @endif

    @if ($tab === 'details')
    <article class="bg-white rounded-2xl shadow overflow-hidden">
        @if ($tournament->image_url)
            <img src="{{ $tournament->image_url }}" alt="{{ $tournament->name }}" class="w-full max-h-[420px] object-cover">
        @else
            <div class="h-32 md:h-40 bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center">
                <x-icon name="trophy" class="w-14 h-14 text-white/80" />
            </div>
        @endif

        <div class="p-5 md:p-8 grid gap-6 md:grid-cols-3">
            <div class="md:col-span-2">
                <h2 class="text-lg font-bold">About this tournament</h2>
                @if ($tournament->description)
                    <div class="mt-2 text-gray-700 leading-relaxed whitespace-pre-line">{{ $tournament->description }}</div>
                @else
                    <p class="mt-2 text-gray-500">More details will be posted soon.</p>
                @endif
            </div>

            <aside class="grid gap-3 content-start">
                <div class="rounded-xl bg-gray-50 p-4 grid gap-3 text-sm">
                    <p class="flex gap-2"><x-icon name="calendar" class="w-5 h-5 text-amber-700 shrink-0" /><span>{{ $tournament->whenLabel() }}</span></p>
                    @if ($tournament->venue)
                        <p class="flex gap-2"><x-icon name="map-pin" class="w-5 h-5 text-amber-700 shrink-0" /><span>{{ $tournament->venue }}@if ($tournament->court) &middot; {{ $tournament->court }}@endif</span></p>
                    @endif
                    <p class="flex gap-2"><x-icon name="users" class="w-5 h-5 text-amber-700 shrink-0" /><span>{{ $tournament->typeLabel() }}</span></p>
                    <p class="flex gap-2"><x-icon name="bracket" class="w-5 h-5 text-amber-700 shrink-0" /><span>{{ $tournament->formatLabel() }}</span></p>
                    @if ($tournament->registration_deadline)
                        <p class="flex gap-2"><x-icon name="clock" class="w-5 h-5 text-amber-700 shrink-0" />
                            <span>Register by {{ $tournament->registration_deadline->format('M j, Y') }}</span></p>
                    @endif
                </div>

                <div class="rounded-xl border p-4 text-sm {{ $tournament->registrationOpen() === false ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50' }}">
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold flex items-center gap-2"><x-icon name="clipboard" class="w-5 h-5" /> Registration</p>
                        @if ($tournament->registrationOpen() !== null)
                            <x-status-badge :status="$tournament->registrationOpen() ? 'open' : 'closed'" />
                        @endif
                    </div>
                    @if ($tournament->registrationLabel())
                        <p class="mt-2 font-medium {{ $tournament->registrationOpen() ? 'text-emerald-800' : 'text-red-700' }}">{{ $tournament->registrationLabel() }}</p>
                    @endif
                    @if ($tournament->registration_info)
                        <p class="mt-2 text-gray-700 whitespace-pre-line">{{ $tournament->registration_info }}</p>
                    @elseif (! $tournament->registration_link)
                        <p class="mt-2 text-gray-500">Registration details will be announced soon.</p>
                    @endif
                    @if ($tournament->registration_link && $tournament->registrationOpen() !== false)
                        <a href="{{ $tournament->registration_link }}" target="_blank" rel="noopener"
                           class="mt-3 flex items-center justify-center gap-2 rounded-lg bg-green-700 text-white font-semibold px-4 py-2.5 hover:bg-green-800">
                            Register now <x-icon name="external" class="w-4 h-4" />
                        </a>
                    @endif
                </div>
            </aside>
        </div>
    </article>
    @elseif ($tab === 'bracket')
        <div class="bg-white rounded-2xl shadow p-4 md:p-6">
            @include('partials.tournament.bracket', ['bracket' => $bracket, 'editable' => false])
        </div>
    @elseif ($tab === 'matches')
        @include('partials.tournament.schedule', ['matches' => $matches])
    @elseif ($tab === 'results')
        @if ($champion)
            <div class="mb-5 rounded-2xl bg-gradient-to-r from-amber-400 to-yellow-300 text-amber-950 p-5 flex items-center gap-4 shadow">
                <span class="w-14 h-14 rounded-full bg-white/60 flex items-center justify-center shrink-0"><x-icon name="trophy" class="w-8 h-8" /></span>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide">Champion</p>
                    <p class="text-2xl font-extrabold">{{ $champion->name }}</p>
                </div>
            </div>
        @endif
        @include('partials.tournament.results-list', ['results' => $matches->filter(fn ($m) => $m->status === 'completed' && ! $m->is_bye)->reverse()])
    @elseif (in_array($tab, ['groups', 'standings'], true))
        @if ($tab === 'standings' && ($champion ?? null))
            <div class="mb-5 rounded-2xl bg-gradient-to-r from-amber-400 to-yellow-300 text-amber-950 p-5 flex items-center gap-4 shadow">
                <span class="w-14 h-14 rounded-full bg-white/60 flex items-center justify-center shrink-0"><x-icon name="trophy" class="w-8 h-8" /></span>
                <div><p class="text-xs font-bold uppercase tracking-wide">Champion</p><p class="text-2xl font-extrabold">{{ $champion->name }}</p></div>
            </div>
        @endif
        @if ($tab === 'groups')
            <p class="text-sm text-gray-600 mb-4">Top {{ $tournament->advance_per_group }} of each group advance to the knockout stage.</p>
        @endif
        <div class="grid gap-5 {{ count($tables) > 1 || $tournament->groups->count() > 1 ? 'xl:grid-cols-2' : '' }}">
            @if ($tables)
                @foreach ($tables as $table)
                    <section class="bg-white rounded-2xl shadow overflow-hidden">
                        <div class="px-4 py-3 bg-gray-900 text-white flex items-center justify-between">
                            <h3 class="font-bold uppercase tracking-wide">{{ $table['group']->label() }}</h3>
                            <span class="text-xs text-gray-300">{{ $table['played'] }} / {{ $table['total'] }} played</span>
                        </div>
                        @include('partials.tournament.standings-table', ['table' => $table])
                    </section>
                @endforeach
            @else
                @foreach ($tournament->groups as $group)
                    <section class="bg-white rounded-2xl shadow overflow-hidden">
                        <div class="px-4 py-3 bg-gray-900 text-white"><h3 class="font-bold uppercase tracking-wide">{{ $group->label() }}</h3></div>
                        <ol class="divide-y">
                            @foreach ($group->participants as $p)
                                <li class="px-4 py-2.5 flex items-center gap-3"><span class="w-6 text-sm text-gray-400">{{ $loop->iteration }}.</span><span class="font-medium">{{ $p->name }}</span></li>
                            @endforeach
                        </ol>
                    </section>
                @endforeach
            @endif
        </div>
    @elseif ($tab === 'participants')
        <div class="bg-white rounded-2xl shadow divide-y max-w-2xl">
            @foreach ($participants as $p)
                <div class="flex items-center gap-3 px-4 py-3">
                    <span class="w-7 text-sm text-gray-400">{{ $loop->iteration }}.</span>
                    <span class="font-medium flex-1">{{ $p->name }}</span>
                    @if ($p->group && strlen($p->group->name) <= 2)
                        <span class="rounded bg-gray-900 text-white px-2 py-0.5 text-xs font-bold">{{ $p->group->name }}</span>
                    @endif
                    @if ($p->seed)
                        <span class="rounded bg-amber-100 text-amber-800 px-2 py-0.5 text-xs font-bold">Seed {{ $p->seed }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection