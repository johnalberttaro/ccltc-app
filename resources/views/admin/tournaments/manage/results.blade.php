@extends('admin.tournaments.manage.shell')

@php
    $done = $matches->filter(fn ($m) => $m->status === 'completed' && ! $m->is_bye)->reverse();
@endphp

@section('tab')
    @if ($champion)
        <div class="mb-5 rounded-2xl bg-gradient-to-r from-amber-400 to-yellow-300 text-amber-950 p-5 flex items-center gap-4 shadow">
            <span class="w-14 h-14 rounded-full bg-white/60 flex items-center justify-center shrink-0"><x-icon name="trophy" class="w-8 h-8" /></span>
            <div>
                <p class="text-xs font-bold uppercase tracking-wide">Champion</p>
                <p class="text-2xl font-extrabold">{{ $champion->name }}</p>
            </div>
        </div>
    @endif

    @include('partials.tournament.results-list', ['results' => $done])
@endsection