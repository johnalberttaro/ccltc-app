@extends('layouts.public')

@section('title', 'Announcements')

@section('content')
    <section class="mb-5">
        <p class="text-sm font-semibold text-rose-700 uppercase tracking-wide">Stay informed</p>
        <h1 class="text-2xl md:text-3xl font-bold">Announcements</h1>
        <p class="text-gray-600 mt-1">Rule updates, schedule changes, tournament news and reminders.</p>
    </section>

    @if ($announcements->isEmpty())
        @include('public.partials.empty', ['icon' => 'megaphone', 'message' => 'No announcements yet.'])
    @else
        <div class="grid gap-4">
            @foreach ($announcements as $announcement)
                <a href="{{ route('announcements.show', $announcement) }}"
                   class="group flex flex-col sm:flex-row bg-white rounded-2xl shadow overflow-hidden hover:shadow-lg transition {{ $announcement->is_pinned ? 'ring-2 ring-yellow-300' : '' }}">
                    @if ($announcement->image_url)
                        <img src="{{ $announcement->image_url }}" alt="" class="w-full sm:w-48 aspect-[16/9] sm:aspect-auto object-cover shrink-0">
                    @endif
                    <div class="p-4 md:p-5 flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                            @if ($announcement->is_pinned)
                                <x-status-badge status="pinned" />
                            @endif
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="clock" class="w-4 h-4" />{{ ($announcement->published_at ?? $announcement->created_at)->format('M j, Y') }}
                            </span>
                        </div>
                        <h2 class="font-bold text-lg mt-1 group-hover:text-green-700">{{ $announcement->title }}</h2>
                        @if ($announcement->event_at)
                            <p class="mt-1 inline-flex flex-wrap items-center gap-x-3 gap-y-1 text-sm font-medium {{ $announcement->eventIsPast() ? 'text-gray-500' : 'text-green-700' }}">
                                <span class="inline-flex items-center gap-1"><x-icon name="calendar" class="w-4 h-4" /> {{ $announcement->eventLabel(true) }}</span>
                                @if ($announcement->event_place)
                                    <span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="w-4 h-4" /> {{ $announcement->event_place }}</span>
                                @endif
                            </p>
                        @endif
                        <p class="text-sm text-gray-600 mt-1 line-clamp-3 whitespace-pre-line">{{ $announcement->body }}</p>
                        <span class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-green-700">
                            Read more <x-icon name="arrow-right" class="w-4 h-4" />
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-6">{{ $announcements->links() }}</div>
    @endif
@endsection