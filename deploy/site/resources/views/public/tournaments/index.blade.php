@extends('layouts.public')

@section('title', 'Tournaments')

@section('content')
    <section class="mb-5">
        <p class="text-sm font-semibold text-amber-700 uppercase tracking-wide">Compete</p>
        <h1 class="text-2xl md:text-3xl font-bold">Tournaments</h1>
        <p class="text-gray-600 mt-1">Upcoming and ongoing tournaments, with dates, venues and how to register.</p>
    </section>

    @include('public.partials.filter-bar', [
        'action' => route('tournaments.index'),
        'placeholder' => 'Search tournaments, categories or venues',
        'statuses' => \Illuminate\Support\Arr::except(\App\Models\Tournament::STATUSES, ['draft']),
        'types' => \App\Models\Tournament::TYPES,
    ])

    @if ($tournaments->isEmpty())
        @include('public.partials.empty', ['icon' => 'trophy', 'message' => 'No tournaments to show right now.'])
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($tournaments as $tournament)
                <a href="{{ route('tournaments.show', $tournament) }}"
                   class="group flex flex-col bg-white rounded-2xl shadow overflow-hidden hover:shadow-lg transition {{ $tournament->status === 'completed' ? 'opacity-80' : '' }}">
                    <div class="relative aspect-[16/9] bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center overflow-hidden">
                        @if ($tournament->image_url)
                            <img src="{{ $tournament->image_url }}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        @else
                            <x-icon name="trophy" class="w-14 h-14 text-white/80" />
                        @endif
                        <x-status-badge :status="$tournament->status" class="absolute top-3 left-3 shadow" />
                        <span class="absolute top-3 right-3 rounded-full bg-gray-900/80 text-white px-2.5 py-0.5 text-xs font-semibold">{{ $tournament->typeLabel() }}</span>
                    </div>
                    <div class="p-4 flex flex-col gap-2 flex-1">
                        @if ($tournament->category)
                            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">{{ $tournament->category }}</p>
                        @endif
                        <h2 class="font-bold text-lg leading-snug group-hover:text-green-700">{{ $tournament->name }}</h2>
                        <p class="text-sm text-gray-600 flex items-center gap-1.5">
                            <x-icon name="calendar" class="w-4 h-4 text-gray-400 shrink-0" />
                            {{ $tournament->starts_at->format('M j, Y') }}@if ($tournament->ends_at && ! $tournament->ends_at->isSameDay($tournament->starts_at)) &ndash; {{ $tournament->ends_at->format('M j, Y') }}@endif
                        </p>
                        @if ($tournament->venue)
                            <p class="text-sm text-gray-600 flex items-center gap-1.5">
                                <x-icon name="map-pin" class="w-4 h-4 text-gray-400 shrink-0" />{{ $tournament->venue }}
                            </p>
                        @endif
                        @if ($tournament->registrationLabel())
                            <p class="text-sm font-medium flex items-center gap-1.5 {{ $tournament->registrationOpen() ? 'text-emerald-700' : 'text-red-700' }}">
                                <x-icon name="clipboard" class="w-4 h-4 shrink-0" />{{ $tournament->registrationLabel() }}
                            </p>
                        @endif
                        <span class="mt-auto pt-2 inline-flex items-center gap-1 text-sm font-semibold text-green-700">
                            View details <x-icon name="arrow-right" class="w-4 h-4" />
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $tournaments->links() }}</div>
    @endif
@endsection