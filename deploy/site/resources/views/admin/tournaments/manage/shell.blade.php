@extends('layouts.admin')

@section('title', $tabs[$tab][0] . ' - ' . $tournament->name)

@section('content')
    <a href="{{ route('admin.tournaments.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900">
        <x-icon name="arrow-left" class="w-4 h-4" /> All tournaments
    </a>

    {{-- Header --}}
    <header class="mt-2 mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <x-status-badge :status="$tournament->status" />
                <span class="rounded-full bg-gray-900 text-white px-2.5 py-0.5 text-xs font-semibold">{{ $tournament->formatLabel() }}</span>
                <span class="rounded-full bg-gray-100 text-gray-700 px-2.5 py-0.5 text-xs font-medium">{{ $tournament->typeLabel() }}</span>
                @if ($tournament->category)
                    <span class="rounded-full bg-amber-100 text-amber-800 px-2.5 py-0.5 text-xs font-medium">{{ $tournament->category }}</span>
                @endif
            </div>
            <h1 class="text-2xl md:text-3xl font-bold mt-1 break-words">{{ $tournament->name }}</h1>
            <p class="text-sm text-gray-500 flex flex-wrap gap-x-4 gap-y-1 mt-1">
                <span class="inline-flex items-center gap-1"><x-icon name="calendar" class="w-4 h-4" />{{ $tournament->starts_at->format('M j, Y') }}@if ($tournament->ends_at && ! $tournament->ends_at->isSameDay($tournament->starts_at)) &ndash; {{ $tournament->ends_at->format('M j, Y') }}@endif</span>
                @if ($tournament->venue)
                    <span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="w-4 h-4" />{{ $tournament->venue }}@if ($tournament->court) &middot; {{ $tournament->court }}@endif</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.tournaments.edit', $tournament) }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-50">
                <x-icon name="pencil" class="w-4 h-4" /> Edit details
            </a>
            @if ($tournament->status !== 'draft')
                <a href="{{ route('tournaments.show', $tournament) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-gray-300 px-3 py-2 text-sm font-medium hover:bg-gray-50">
                    <x-icon name="external" class="w-4 h-4" /> Public page
                </a>
            @endif
        </div>
    </header>

    {{-- Progress --}}
    <ol class="bg-white rounded-xl shadow px-4 py-3 mb-4 flex gap-1 overflow-x-auto">
        @foreach ($progress->steps as $step)
            <li class="flex items-center gap-2 shrink-0">
                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold
                    {{ $step['state'] === 'done' ? 'bg-green-600 text-white' : ($step['state'] === 'current' ? 'bg-amber-400 text-amber-950 ring-4 ring-amber-100' : 'bg-gray-100 text-gray-400') }}">
                    @if ($step['state'] === 'done')
                        <x-icon name="check" class="w-4 h-4" />
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>
                <span class="text-sm whitespace-nowrap {{ $step['state'] === 'pending' ? 'text-gray-400' : 'font-semibold text-gray-800' }}">{{ $step['label'] }}</span>
                @unless ($loop->last)
                    <span class="w-6 md:w-10 h-px mx-1 {{ $step['state'] === 'done' ? 'bg-green-600' : 'bg-gray-200' }}"></span>
                @endunless
            </li>
        @endforeach
    </ol>

    {{-- Tabs --}}
    <nav class="mb-5 -mx-4 px-4 overflow-x-auto">
        <div class="flex gap-1 border-b border-gray-200 min-w-max">
            @foreach ($tabs as $key => [$label, $icon])
                <a href="{{ route('admin.tournaments.manage', [$tournament, $key]) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2.5 text-sm border-b-2 -mb-px
                       {{ $tab === $key ? 'border-green-700 text-green-800 font-semibold' : 'border-transparent text-gray-600 hover:text-gray-900 hover:border-gray-300' }}">
                    <x-icon :name="$icon" class="w-4 h-4" /> {{ $label }}
                </a>
            @endforeach
        </div>
    </nav>

    @yield('tab')
@endsection