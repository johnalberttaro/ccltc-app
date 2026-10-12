@extends('admin.tournaments.manage.shell')

@php
    $s = $progress->stats;
    $t = $tournament;

    // What should the admin do next?
    if ($champion ?? null) {
        $next = ['Tournament complete', "{$champion->name} won the tournament. All results are on the Results tab.", route('admin.tournaments.manage', [$t, 'results']), 'View results'];
    } elseif ($s['participants'] < 2) {
        $next = ['Add participants', 'Type the names of the players or teams taking part.', route('admin.tournaments.manage', [$t, 'participants']), 'Add participants'];
    } elseif ($t->isDirectKnockout() && ! $t->hasBracket()) {
        $next = ['Generate the bracket', "You have {$s['participants']} participants. Create the bracket when everyone is in.", route('admin.tournaments.manage', [$t, 'bracket']), 'Go to Bracket'];
    } elseif ($t->hasBracket()) {
        $next = ['Record match results', 'Enter scores as matches finish. Winners move on automatically.', route('admin.tournaments.manage', [$t, 'bracket']), 'Open the bracket'];
    } elseif ($t->usesGroups() && ! $t->groups_generated_at) {
        $next = ['Generate the groups', "You have {$s['participants']} {$t->entryWord()}. Divide them into groups automatically.", route('admin.tournaments.manage', [$t, 'groups']), 'Go to Groups'];
    } elseif ($t->usesGroups() && ! $t->schedule_generated_at) {
        $next = ['Create the group matches', 'Groups are ready. Create the matches: everyone plays the others in their group once.', route('admin.tournaments.manage', [$t, 'groups']), 'Go to Groups'];
    } elseif ($t->usesGroups() && ! ($groupStageFinished ?? false)) {
        $next = ['Record group results', "{$s['group_done']} of {$s['group_matches']} group matches played. Standings update automatically.", route('admin.tournaments.manage', [$t, 'matches']), 'Go to Matches'];
    } elseif ($t->usesGroups()) {
        $next = ['Generate the knockout stage', 'The group stage is finished. Put the qualifiers into the bracket.', route('admin.tournaments.manage', [$t, 'bracket']), 'Go to Bracket'];
    } elseif ($t->isRoundRobin() && ! $t->schedule_generated_at) {
        $next = ['Create the schedule', "Everyone plays everyone once: {$s['participants']} {$t->entryWord()}.", route('admin.tournaments.manage', [$t, 'matches']), 'Go to Matches'];
    } else {
        $next = ['Record match results', "{$s['group_done']} of {$s['group_matches']} matches played. The standings update automatically.", route('admin.tournaments.manage', [$t, 'matches']), 'Go to Matches'];
    }
@endphp

@section('tab')
    {{-- Summary cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl shadow p-4">
            <p class="text-3xl font-bold text-green-700">{{ $s['participants'] }}</p>
            <p class="text-sm text-gray-500">{{ $t->match_type === 'singles' ? 'Players' : 'Players / teams' }}@if ($t->max_participants) <span class="text-gray-400">of {{ $t->max_participants }}</span>@endif</p>
        </div>
        @if ($t->usesGroups() || $t->isRoundRobin())
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-3xl font-bold">{{ $s['group_matches'] }}</p>
                <p class="text-sm text-gray-500">{{ $t->isRoundRobin() ? 'Matches' : 'Group matches' }}</p>
            </div>
        @endif
        @if ($t->usesGroups())
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-3xl font-bold">{{ $s['qualified'] ?: $t->qualifierCount() }}</p>
                <p class="text-sm text-gray-500">{{ $s['qualified'] ? 'Qualified' : 'Will qualify' }}</p>
            </div>
        @endif
        @unless ($t->isRoundRobin())
            <div class="bg-white rounded-xl shadow p-4">
                <p class="text-3xl font-bold">{{ $s['knockout_matches'] }}</p>
                <p class="text-sm text-gray-500">Knockout matches</p>
            </div>
        @endunless
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="lg:col-span-2 grid gap-5 content-start">
            {{-- Next step --}}
            <section class="rounded-xl bg-gradient-to-br from-green-700 to-green-900 text-white p-5 shadow">
                <p class="text-xs font-semibold uppercase tracking-wide text-green-200">Next step</p>
                <h2 class="text-xl font-bold mt-1">{{ $next[0] }}</h2>
                <p class="text-green-100 text-sm mt-1">{{ $next[1] }}</p>
                <a href="{{ $next[2] }}" class="inline-flex items-center gap-1.5 mt-4 rounded-lg bg-white text-green-800 font-semibold px-4 py-2 hover:bg-green-50">
                    {{ $next[3] }} <x-icon name="arrow-right" class="w-4 h-4" />
                </a>
            </section>

            {{-- Status --}}
            <section class="bg-white rounded-xl shadow divide-y">
                <div class="px-4 py-3 flex items-center justify-between gap-3">
                    <span class="text-sm text-gray-500">Tournament status</span>
                    <x-status-badge :status="$t->status" />
                </div>
                <div class="px-4 py-3 flex items-center justify-between gap-3">
                    <span class="text-sm text-gray-500">Current stage</span>
                    <span class="font-semibold">{{ $progress->currentStage() }}</span>
                </div>
                <div class="px-4 py-3 flex items-center justify-between gap-3">
                    <span class="text-sm text-gray-500">Completed matches</span>
                    <span class="font-semibold">{{ $s['completed_matches'] }} / {{ $s['total_matches'] }}</span>
                </div>
                @if ($nextMatch ?? null)
                    <div class="px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <span class="text-sm text-gray-500">Next match</span>
                        <span class="flex items-center gap-3">
                            <span class="font-semibold text-right">{{ $nextMatch->code }}: {{ $nextMatch->participant1->name }} vs {{ $nextMatch->participant2->name }}</span>
                            <button type="button" data-result='@json(\App\Support\ResultDialog::payload($nextMatch))'
                                    class="shrink-0 rounded-lg bg-green-700 text-white text-sm font-semibold px-3 py-1.5 hover:bg-green-800">Record</button>
                        </span>
                    </div>
                @endif
            </section>
        </div>

        {{-- Configuration --}}
        <section class="bg-white rounded-xl shadow p-4 content-start">
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-bold">Configuration</h2>
                <a href="{{ route('admin.tournaments.manage', [$t, 'settings']) }}" class="text-sm text-green-700 hover:underline">Settings</a>
            </div>
            <dl class="grid gap-3 text-sm">
                <div>
                    <dt class="text-gray-500">Format</dt>
                    <dd class="font-medium">{{ $t->formatLabel() }}</dd>
                </div>
                @if ($t->usesGroups())
                    <div>
                        <dt class="text-gray-500">Groups</dt>
                        <dd class="font-medium">{{ $t->group_count }} groups, up to {{ $t->group_size }} each, top {{ $t->advance_per_group }} advance</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-gray-500">Match rules</dt>
                    <dd class="font-medium">{{ $t->matchRulesLabel() }}</dd>
                </div>
                @if ($t->usesGroups() || $t->isRoundRobin())
                    <div>
                        <dt class="text-gray-500">Points</dt>
                        <dd class="font-medium">Win {{ $t->points_win }} &middot; Loss {{ $t->points_loss }}</dd>
                    </div>
                @endif
            </dl>
        </section>
    </div>

    @if ($nextMatch ?? null)
        @include('admin.tournaments.manage.partials.match-dialogs')
    @endif
@endsection