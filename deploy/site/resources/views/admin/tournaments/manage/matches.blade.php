@extends('admin.tournaments.manage.shell')

@php
    $t = $tournament;
    $filter = request('filter', 'all');
    $list = $matches->reject(fn ($m) => $m->is_bye || ($m->code === 'GF2' && $m->status === 'cancelled'));
    $shown = match ($filter) {
        'todo' => $list->filter(fn ($m) => ! $m->winner_id && $m->status !== 'cancelled'),
        'done' => $list->filter(fn ($m) => $m->status === 'completed'),
        default => $list,
    };
    $groupsOfMatches = $shown->groupBy(fn ($m) => $m->stage === 'group' ? ($m->group?->label() ?? 'Group') : $m->roundName());
    $done = $list->where('status', 'completed')->count();
    $total = $list->where('status', '!=', 'cancelled')->count();
@endphp

@section('tab')
    @if ($list->isEmpty())
        <div class="bg-white rounded-xl shadow p-10 text-center max-w-xl mx-auto">
            <x-icon name="ball" class="w-10 h-10 mx-auto text-gray-300" />
            <h2 class="text-lg font-bold mt-3">No matches yet</h2>
            @if ($t->isRoundRobin())
                @php $n = $t->participants->count(); @endphp
                <p class="text-gray-600 mt-1">Everyone plays everyone once: {{ $n }} {{ $t->entryWord() }} = {{ intdiv($n * ($n - 1), 2) }} matches.</p>
                @if ($n >= 2)
                    <form method="POST" action="{{ route('admin.tournaments.schedule.generate', $t) }}" class="mt-4"
                          data-confirm="Create {{ intdiv($n * ($n - 1), 2) }} matches?"
                          data-confirm-body="Every {{ $t->entryWord(false) }} plays each of the others once. After this, participants can't be added unless you reset the schedule."
                          data-confirm-button="Create matches">
                        @csrf
                        <button type="submit" data-loading="Creating..." class="rounded-lg bg-green-700 text-white font-semibold px-5 py-2.5 hover:bg-green-800">Create round robin schedule</button>
                    </form>
                @else
                    <a href="{{ route('admin.tournaments.manage', [$t, 'participants']) }}" class="inline-block mt-4 rounded-lg bg-green-700 text-white font-semibold px-4 py-2 hover:bg-green-800">Add participants</a>
                @endif
            @elseif ($t->usesGroups())
                <p class="text-gray-600 mt-1">Generate the groups, then create the group matches.</p>
                <a href="{{ route('admin.tournaments.manage', [$t, 'groups']) }}" class="inline-block mt-4 rounded-lg bg-green-700 text-white font-semibold px-4 py-2 hover:bg-green-800">Go to Groups</a>
            @else
                <p class="text-gray-600 mt-1">Generate the bracket first; its matches will be listed here.</p>
                <a href="{{ route('admin.tournaments.manage', [$t, 'bracket']) }}" class="inline-block mt-4 rounded-lg bg-green-700 text-white font-semibold px-4 py-2 hover:bg-green-800">Go to Bracket</a>
            @endif
        </div>
    @else
        {{-- Summary + filter --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <div class="flex items-center gap-4">
                <div>
                    <p class="text-sm text-gray-500">Completed</p>
                    <p class="text-xl font-bold">{{ $done }} / {{ $total }}</p>
                </div>
                <div class="w-40 h-2 rounded-full bg-gray-200 overflow-hidden">
                    <div class="h-full bg-green-600" style="width: {{ $total ? round($done / $total * 100) : 0 }}%"></div>
                </div>
            </div>
            <div class="inline-flex rounded-lg border border-gray-300 bg-white p-1 text-sm self-start">
                @foreach (['all' => 'All', 'todo' => 'To play', 'done' => 'Completed'] as $key => $label)
                    <a href="{{ route('admin.tournaments.manage', [$t, 'matches']) }}?filter={{ $key }}"
                       class="rounded-md px-3 py-1.5 {{ $filter === $key ? 'bg-gray-900 text-white font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        @if ($t->usesGroups() && $t->schedule_generated_at && ! $t->hasBracket() && \App\Services\Standings::groupStageFinished($t))
            <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                <p class="text-sm text-green-900 flex gap-2"><x-icon name="check" class="w-5 h-5 shrink-0" /> All group matches are played. The qualifiers are ready for the knockout stage.</p>
                <a href="{{ route('admin.tournaments.manage', [$t, 'bracket']) }}" class="shrink-0 rounded-lg bg-green-700 text-white font-semibold px-4 py-2 hover:bg-green-800">Generate knockout</a>
            </div>
        @endif

        @if ($nextMatch)
            <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-green-700">Next match &middot; {{ $nextMatch->code }} {{ $nextMatch->roundName() }}</p>
                    <p class="font-bold">{{ $nextMatch->participant1->name }} <span class="text-gray-400 font-normal">vs</span> {{ $nextMatch->participant2->name }}</p>
                    @if ($nextMatch->scheduled_at || $nextMatch->court)
                        <p class="text-sm text-gray-600">{{ $nextMatch->scheduled_at?->format('D, M j, g:i A') }}@if ($nextMatch->court) &middot; {{ $nextMatch->court }}@endif</p>
                    @endif
                </div>
                <button type="button" data-result='@json(\App\Support\ResultDialog::payload($nextMatch))'
                        class="rounded-lg bg-green-700 text-white font-semibold px-4 py-2 hover:bg-green-800">Record result</button>
            </div>
        @endif

        @forelse ($groupsOfMatches as $heading => $roundMatches)
            <section class="mb-6">
                <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-2">{{ $heading }}{{ Str::endsWith($heading, ['Semifinal', 'Quarterfinal']) ? 's' : '' }}</h3>
                <div class="bg-white rounded-xl shadow divide-y">
                    @foreach ($roundMatches as $m)
                        @php $payload = \App\Support\ResultDialog::payload($m); @endphp
                        <div class="p-3 md:p-4 flex flex-col md:flex-row md:items-center gap-3">
                            <div class="flex items-center gap-3 md:w-28 shrink-0">
                                <span class="rounded-md bg-gray-900 text-white text-xs font-bold px-2 py-1">{{ $m->code }}</span>
                                <x-status-badge :status="$m->status" class="md:hidden" />
                            </div>

                            <div class="flex-1 min-w-0 grid gap-1">
                                @foreach ([1, 2] as $slot)
                                    @php $p = $m->participant($slot); @endphp
                                    <div class="flex items-center justify-between gap-3 {{ $m->wonBy($slot) ? 'font-semibold text-green-800' : ($m->winner_id ? 'text-gray-400' : '') }}">
                                        <span class="truncate">
                                            {{ $p?->name ?? '' }}
                                            @unless ($p)
                                                <span class="italic text-gray-400 text-sm">{{ $m->slotLabel($slot) }}</span>
                                            @endunless
                                            @if ($m->wonBy($slot)) <x-icon name="check" class="inline w-4 h-4" /> @endif
                                        </span>
                                        <span class="flex gap-2 tabular-nums shrink-0">
                                            @foreach ($m->setsFor($slot) as $set)
                                                <span class="{{ $set['won'] ? 'font-bold text-gray-900' : 'text-gray-400' }}">{{ $set['games'] }}@if ($set['tb'] !== null)<sup class="text-[10px]">{{ $set['tb'] }}</sup>@endif</span>
                                            @endforeach
                                            @if ($m->is_walkover && $m->wonBy($slot))<span class="text-xs">W/O</span>@endif
                                        </span>
                                    </div>
                                @endforeach
                            </div>

                            <div class="md:w-44 text-sm text-gray-600 shrink-0">
                                @if ($m->scheduled_at || $m->court)
                                    <span class="inline-flex items-center gap-1"><x-icon name="clock" class="w-4 h-4 text-gray-400" />{{ $m->scheduled_at?->format('M j, g:i A') ?? 'No time' }}</span>
                                    @if ($m->court)<span class="block text-xs text-gray-500 pl-5">{{ $m->court }}</span>@endif
                                @else
                                    <span class="text-gray-400 text-xs">Not scheduled</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2 md:justify-end md:w-64 shrink-0">
                                <x-status-badge :status="$m->status" class="hidden md:inline-flex" />
                                <button type="button" data-schedule='@json($payload)'
                                        class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm hover:bg-gray-50" title="Date, time, court, status">
                                    <x-icon name="calendar" class="w-4 h-4" />
                                </button>
                                @if ($m->isPlayable())
                                    <button type="button" data-result='@json($payload)'
                                            class="rounded-lg px-3 py-1.5 text-sm font-semibold {{ $m->winner_id ? 'border border-gray-300 hover:bg-gray-50' : 'bg-green-700 text-white hover:bg-green-800' }}">
                                        {{ $m->winner_id ? 'Edit result' : 'Record result' }}
                                    </button>
                                @else
                                    <span class="text-xs text-gray-400 px-2">{{ $m->status === 'cancelled' ? 'Cancelled' : 'Waiting for players' }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <p class="bg-white rounded-xl shadow p-8 text-center text-gray-500">No matches in this view.</p>
        @endforelse

        @include('admin.tournaments.manage.partials.match-dialogs')
    @endif
@endsection