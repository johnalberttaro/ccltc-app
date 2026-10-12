@extends('layouts.public')

@section('title', $event->title)

@section('content')
    <a href="{{ route('events.index') }}" class="inline-flex items-center gap-1 text-sm text-green-700 hover:underline">
        <x-icon name="arrow-left" class="w-4 h-4" /> All events
    </a>

    <article class="bg-white rounded-2xl shadow overflow-hidden mt-3">
        @if ($event->image_url)
            <img src="{{ $event->image_url }}" alt="{{ $event->title }}" class="w-full max-h-[420px] object-cover">
        @else
            <div class="h-32 md:h-40 bg-gradient-to-br from-sky-500 to-sky-700 flex items-center justify-center">
                <x-icon name="calendar" class="w-14 h-14 text-white/70" />
            </div>
        @endif

        <div class="p-5 md:p-8 grid gap-6 md:grid-cols-3">
            <div class="md:col-span-2">
                <x-status-badge :status="$event->status" />
                <h1 class="text-2xl md:text-3xl font-bold mt-2">{{ $event->title }}</h1>
                @if ($event->description)
                    <div class="mt-4 text-gray-700 leading-relaxed whitespace-pre-line">{{ $event->description }}</div>
                @endif
            </div>

            <aside class="grid gap-3 content-start">
                <div class="rounded-xl bg-gray-50 p-4 grid gap-3 text-sm">
                    <p class="flex gap-2"><x-icon name="calendar" class="w-5 h-5 text-sky-700 shrink-0" /><span>{{ $event->whenLabel() }}</span></p>
                    @if ($event->location)
                        <p class="flex gap-2"><x-icon name="map-pin" class="w-5 h-5 text-sky-700 shrink-0" /><span>{{ $event->location }}</span></p>
                    @endif
                </div>

                @if ($event->registration_info || $event->registration_link)
                    <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm">
                        <p class="font-semibold text-green-900 flex items-center gap-2"><x-icon name="clipboard" class="w-5 h-5" /> Registration</p>
                        @if ($event->registration_info)
                            <p class="mt-2 text-gray-700 whitespace-pre-line">{{ $event->registration_info }}</p>
                        @endif
                        @if ($event->registration_link && $event->status !== 'completed')
                            <a href="{{ $event->registration_link }}" target="_blank" rel="noopener"
                               class="mt-3 flex items-center justify-center gap-2 rounded-lg bg-green-700 text-white font-semibold px-4 py-2.5 hover:bg-green-800">
                                Register now <x-icon name="external" class="w-4 h-4" />
                            </a>
                        @endif
                    </div>
                @endif
            </aside>
        </div>
    </article>
@endsection