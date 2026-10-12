@extends('layouts.public')

@section('title', 'Events')

@section('content')
    <section class="mb-5">
        <p class="text-sm font-semibold text-sky-700 uppercase tracking-wide">What's happening</p>
        <h1 class="text-2xl md:text-3xl font-bold">Events &amp; Activities</h1>
        <p class="text-gray-600 mt-1">Clinics, socials, open courts and other tennis activities.</p>
    </section>

    @if ($events->isEmpty())
        @include('public.partials.empty', ['icon' => 'calendar', 'message' => 'No events to show right now.'])
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($events as $event)
                <a href="{{ route('events.show', $event) }}"
                   class="group flex flex-col bg-white rounded-2xl shadow overflow-hidden hover:shadow-lg transition {{ $event->status === 'completed' ? 'opacity-80' : '' }}">
                    <div class="relative aspect-[16/9] bg-gradient-to-br from-sky-500 to-sky-700 flex items-center justify-center overflow-hidden">
                        @if ($event->image_url)
                            <img src="{{ $event->image_url }}" alt="" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        @else
                            <x-icon name="calendar" class="w-14 h-14 text-white/70" />
                        @endif
                        <x-status-badge :status="$event->status" class="absolute top-3 left-3 shadow" />
                        <span class="absolute bottom-3 left-3 rounded-lg bg-white/95 text-gray-900 px-2.5 py-1 text-center leading-tight shadow">
                            <span class="block text-[11px] font-bold uppercase text-sky-700">{{ $event->starts_at->format('M') }}</span>
                            <span class="block text-lg font-bold">{{ $event->starts_at->format('j') }}</span>
                        </span>
                    </div>
                    <div class="p-4 flex flex-col gap-2 flex-1">
                        <h2 class="font-bold text-lg leading-snug group-hover:text-green-700">{{ $event->title }}</h2>
                        <p class="text-sm text-gray-600 flex items-center gap-1.5">
                            <x-icon name="clock" class="w-4 h-4 text-gray-400 shrink-0" />{{ $event->starts_at->format('D, M j \a\t g:i A') }}
                        </p>
                        @if ($event->location)
                            <p class="text-sm text-gray-600 flex items-center gap-1.5">
                                <x-icon name="map-pin" class="w-4 h-4 text-gray-400 shrink-0" />{{ $event->location }}
                            </p>
                        @endif
                        @if ($event->description)
                            <p class="text-sm text-gray-500 line-clamp-2">{{ $event->description }}</p>
                        @endif
                        <span class="mt-auto pt-2 inline-flex items-center gap-1 text-sm font-semibold text-green-700">
                            View details <x-icon name="arrow-right" class="w-4 h-4" />
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $events->links() }}</div>
    @endif
@endsection