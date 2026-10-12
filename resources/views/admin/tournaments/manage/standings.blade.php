@extends('admin.tournaments.manage.shell')

@php
    $t = $tournament;
@endphp

@section('tab')
    @if (! $t->schedule_generated_at)
        <div class="bg-white rounded-xl shadow p-10 text-center max-w-xl mx-auto">
            <x-icon name="chart" class="w-10 h-10 mx-auto text-gray-300" />
            <h2 class="text-lg font-bold mt-3">No standings yet</h2>
            <p class="text-gray-600 mt-1">Standings appear once the {{ $t->usesGroups() ? 'group' : '' }} matches are created and results come in.</p>
            <a href="{{ route('admin.tournaments.manage', [$t, $t->usesGroups() ? 'groups' : 'matches']) }}" class="inline-block mt-4 rounded-lg bg-green-700 text-white font-semibold px-4 py-2 hover:bg-green-800">
                {{ $t->usesGroups() ? 'Go to Groups' : 'Go to Matches' }}
            </a>
        </div>
    @else
        @if ($t->isRoundRobin() && ($champ = $t->champion()))
            <div class="mb-5 rounded-2xl bg-gradient-to-r from-amber-400 to-yellow-300 text-amber-950 p-5 flex items-center gap-4 shadow">
                <span class="w-14 h-14 rounded-full bg-white/60 flex items-center justify-center shrink-0"><x-icon name="trophy" class="w-8 h-8" /></span>
                <div><p class="text-xs font-bold uppercase tracking-wide">Champion</p><p class="text-2xl font-extrabold">{{ $champ->name }}</p></div>
            </div>
        @endif

        @if ($t->usesGroups())
            <div class="mb-4 rounded-xl p-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between {{ $groupStageFinished ? 'bg-green-50 border border-green-200' : 'bg-white shadow' }}">
                <p class="text-sm {{ $groupStageFinished ? 'text-green-900' : 'text-gray-600' }} flex gap-2">
                    <x-icon :name="$groupStageFinished ? 'check' : 'info'" class="w-5 h-5 shrink-0" />
                    @if ($t->hasBracket())
                        The group stage is finished and the knockout bracket has been generated.
                    @elseif ($groupStageFinished)
                        Every group match is played. The top {{ $t->advance_per_group }} of each group qualify.
                    @else
                        Top {{ $t->advance_per_group }} of each group qualify. Qualification is confirmed when every match in the group is played.
                    @endif
                </p>
                @if ($groupStageFinished && ! $t->hasBracket())
                    <a href="{{ route('admin.tournaments.manage', [$t, 'bracket']) }}" class="shrink-0 rounded-lg bg-green-700 text-white font-semibold px-4 py-2 hover:bg-green-800">Generate knockout</a>
                @endif
            </div>
        @endif

        <div class="grid gap-5 {{ count($tables) > 1 ? 'xl:grid-cols-2' : '' }}">
            @foreach ($tables as $table)
                <section class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="px-4 py-3 bg-gray-900 text-white flex items-center justify-between">
                        <h3 class="font-bold uppercase tracking-wide">{{ $table['group']->label() }}</h3>
                        <span class="text-xs text-gray-300">{{ $table['played'] }} / {{ $table['total'] }} matches played</span>
                    </div>
                    @include('partials.tournament.standings-table', ['table' => $table])
                </section>
            @endforeach
        </div>

        <p class="mt-4 text-xs text-gray-500">
            P = played, W = wins, L = losses, SW / SL = sets won / lost, GW / GL = games won / lost, Pts = points
            (win {{ $t->points_win }}, loss {{ $t->points_loss }}). Ties: points, {{ $t->use_head_to_head ? 'head-to-head (two-way ties), ' : '' }}sets, games, seed.
        </p>
    @endif
@endsection