@extends('layouts.admin')

@section('title', 'Tournaments')

@section('content')
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-5">
        <div class="flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center"><x-icon name="trophy" /></span>
            <div>
                <h1 class="text-2xl font-bold">Tournaments</h1>
                <p class="text-sm text-gray-500">Upcoming, ongoing and completed tournaments.</p>
            </div>
        </div>
        <a href="{{ route('admin.tournaments.create') }}"
           class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-700 text-white font-semibold px-4 py-2 hover:bg-green-800">
            <x-icon name="plus" class="w-4 h-4" /> Create Tournament
        </a>
    </div>

    {{-- Search & filter --}}
    <form method="GET" class="bg-white rounded-xl shadow p-3 mb-5 flex flex-col sm:flex-row gap-2">
        <div class="relative flex-1">
            <x-icon name="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search name, category or venue"
                   class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2">
        </div>
        <select name="status" class="rounded-lg border border-gray-300 px-3 py-2" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach (\App\Models\Tournament::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="rounded-lg bg-gray-900 text-white px-4 py-2 hover:bg-gray-800">Search</button>
        @if (request()->hasAny(['q', 'status']))
            <a href="{{ route('admin.tournaments.index') }}" class="rounded-lg px-4 py-2 text-center text-gray-600 hover:bg-gray-100">Clear</a>
        @endif
    </form>

    @if ($tournaments->isEmpty())
        <div class="bg-white rounded-xl shadow p-10 text-center">
            <x-icon name="trophy" class="w-10 h-10 mx-auto text-gray-300" />
            <p class="mt-2 font-semibold">No tournaments found.</p>
            <a href="{{ route('admin.tournaments.create') }}" class="inline-block mt-2 text-green-700 font-medium hover:underline">Create your first tournament &rarr;</a>
        </div>
    @else
        <div class="bg-white rounded-xl shadow divide-y">
            @foreach ($tournaments as $tournament)
                <div class="p-4 flex flex-col gap-3 md:flex-row md:items-center">
                    <div class="w-full md:w-24 aspect-[4/3] md:aspect-square rounded-lg bg-gradient-to-br from-amber-100 to-amber-200 overflow-hidden flex items-center justify-center shrink-0">
                        @if ($tournament->image_url)
                            <img src="{{ $tournament->image_url }}" alt="" class="w-full h-full object-cover">
                        @else
                            <x-icon name="trophy" class="w-8 h-8 text-amber-600" />
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold"><a href="{{ route('admin.tournaments.manage', [$tournament, 'overview']) }}" class="hover:text-green-700 hover:underline">{{ $tournament->name }}</a></h2>
                            <x-status-badge :status="$tournament->status" />
                            <span class="rounded-full bg-gray-900 text-white px-2.5 py-0.5 text-xs font-medium">{{ \App\Models\Tournament::FORMATS[$tournament->format] ?? '' }}</span>
                            <span class="rounded-full bg-gray-100 text-gray-700 px-2.5 py-0.5 text-xs font-medium">{{ $tournament->typeLabel() }}</span>
                            @if ($tournament->category)
                                <span class="rounded-full bg-gray-100 text-gray-700 px-2.5 py-0.5 text-xs font-medium">{{ $tournament->category }}</span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-600 mt-1 flex flex-wrap gap-x-4 gap-y-1">
                            <span class="inline-flex items-center gap-1"><x-icon name="clock" class="w-4 h-4 text-gray-400" />{{ $tournament->starts_at->format('M j, Y g:i A') }}</span>
                            @if ($tournament->venue)
                                <span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="w-4 h-4 text-gray-400" />{{ $tournament->venue }}</span>
                            @endif
                            <span class="inline-flex items-center gap-1"><x-icon name="users" class="w-4 h-4 text-gray-400" />{{ $tournament->participants_count }} {{ Str::plural('participant', $tournament->participants_count) }}</span>
                            @if ($tournament->registrationLabel())
                                <span class="inline-flex items-center gap-1 {{ $tournament->registrationOpen() ? 'text-emerald-700' : 'text-red-700' }}">
                                    <x-icon name="clipboard" class="w-4 h-4" />{{ $tournament->registrationLabel() }}
                                </span>
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-1 md:justify-end">
                        <a href="{{ route('tournaments.show', $tournament) }}" target="_blank"
                           class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-100" title="View on public page">
                            <x-icon name="eye" class="w-4 h-4" /><span>View</span>
                        </a>
                        <a href="{{ route('admin.tournaments.manage', [$tournament, 'overview']) }}"
                           class="inline-flex items-center gap-1 rounded-lg bg-green-700 text-white px-3 py-1.5 text-sm font-semibold hover:bg-green-800">
                            <x-icon name="trophy" class="w-4 h-4" /><span>Manage</span>
                        </a>
                        <a href="{{ route('admin.tournaments.edit', $tournament) }}"
                           class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-100">
                            <x-icon name="pencil" class="w-4 h-4" /><span>Edit</span>
                        </a>
                        @include('admin.partials.delete-button', ['action' => route('admin.tournaments.destroy', $tournament), 'confirm' => 'Delete this tournament? This cannot be undone.'])
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $tournaments->links() }}</div>
    @endif
@endsection