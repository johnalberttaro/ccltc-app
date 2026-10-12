@extends('layouts.public')

@section('title', $announcement->title)

@section('content')
    <a href="{{ route('announcements.index') }}" class="inline-flex items-center gap-1 text-sm text-green-700 hover:underline">
        <x-icon name="arrow-left" class="w-4 h-4" /> All announcements
    </a>

    <article class="bg-white rounded-2xl shadow overflow-hidden mt-3 max-w-3xl">
        @if ($announcement->image_url)
            <img src="{{ $announcement->image_url }}" alt="{{ $announcement->title }}" class="w-full max-h-[420px] object-cover">
        @endif

        <div class="p-5 md:p-8">
            <div class="flex flex-wrap items-center gap-2 text-sm text-gray-500">
                @if ($announcement->is_pinned)
                    <x-status-badge status="pinned" />
                @endif
                <span class="inline-flex items-center gap-1">
                    <x-icon name="clock" class="w-4 h-4" />
                    Posted {{ ($announcement->published_at ?? $announcement->created_at)->format('F j, Y \a\t g:i A') }}
                </span>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold mt-2">{{ $announcement->title }}</h1>

            @if ($announcement->event_at)
                <div class="mt-4 flex items-start gap-3 rounded-xl border px-4 py-3 {{ $announcement->eventIsPast() ? 'border-gray-200 bg-gray-50' : 'border-green-200 bg-green-50' }}">
                    <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $announcement->eventIsPast() ? 'bg-gray-200 text-gray-500' : 'bg-green-600 text-white' }}">
                        <x-icon name="calendar" class="w-5 h-5" />
                    </span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide {{ $announcement->eventIsPast() ? 'text-gray-500' : 'text-green-700' }}">
                            {{ $announcement->eventIsPast() ? 'This was held on' : 'When' }}
                        </p>
                        <p class="font-semibold text-gray-900 text-lg leading-snug">{{ $announcement->eventLabel() }}</p>
                        @if ($announcement->event_place)
                            <p class="text-sm text-gray-600 inline-flex items-center gap-1 mt-0.5">
                                <x-icon name="map-pin" class="w-4 h-4" /> {{ $announcement->event_place }}
                            </p>
                        @endif
                    </div>
                </div>
            @endif
            <div class="mt-4 text-gray-700 leading-relaxed whitespace-pre-line">{{ $announcement->body }}</div>
        </div>
    </article>
@endsection