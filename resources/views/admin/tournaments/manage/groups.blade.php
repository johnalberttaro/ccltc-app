@extends('admin.tournaments.manage.shell')

@php
    $t = $tournament;
    $groups = $t->groups;
    $knockoutLocked = (bool) $t->knockout_generated_at;
@endphp

@section('tab')
    @if ($errors->has('groups'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 p-4 text-red-800">
            <p class="font-semibold flex items-center gap-2"><x-icon name="warning" class="w-5 h-5" /> Unable to generate groups.</p>
            <ul class="mt-1 text-sm list-disc pl-6">
                @foreach ($errors->get('groups') as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (! $t->groups_generated_at)
        {{-- ================= Not generated yet ================= --}}
        <div class="grid gap-5 lg:grid-cols-3">
            <section class="lg:col-span-2 bg-white rounded-xl shadow p-5">
                <h2 class="text-lg font-bold">Generate groups</h2>
                <p class="text-sm text-gray-500 mt-1">
                    Participants are divided automatically. Seeded players go into different groups first (snake order: A, B, C, D, then D, C, B, A...), and everyone else is drawn at random.
                </p>

                <div class="grid grid-cols-3 gap-3 my-5">
                    <div class="rounded-lg bg-gray-50 p-3 text-center">
                        <p class="text-2xl font-bold">{{ $check['players'] }}</p>
                        <p class="text-xs text-gray-500">Participants</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 text-center">
                        <p class="text-2xl font-bold">{{ $t->group_count }}</p>
                        <p class="text-xs text-gray-500">Groups (up to {{ $t->group_size }} each)</p>
                    </div>
                    <div class="rounded-lg bg-gray-50 p-3 text-center">
                        <p class="text-2xl font-bold">{{ $t->advance_per_group }}</p>
                        <p class="text-xs text-gray-500">Advance per group</p>
                    </div>
                </div>

                @if ($check['ok'])
                    <div class="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-900 flex gap-2">
                        <x-icon name="check" class="w-5 h-5 shrink-0" />
                        <span>
                            {{ $check['summary'] }}
                            Top {{ $t->advance_per_group }} of each group ({{ $t->qualifierCount() }} in total) go to the
                            {{ \App\Models\Tournament::firstRoundName($t->qualifierCount()) }}
                            ({{ \App\Models\Tournament::KNOCKOUT_FORMATS[$t->knockout_format] ?? 'Single Elimination' }}).
                        </span>
                    </div>

                    <form method="POST" action="{{ route('admin.tournaments.groups.generate', $t) }}" class="mt-5"
                          data-confirm="Generate groups?"
                          data-confirm-body="{{ $check['summary'] }}&#10;&#10;Continue?"
                          data-confirm-button="Generate">
                        @csrf
                        <button type="submit" data-loading="Generating..." class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-green-700 text-white font-semibold px-6 py-3 hover:bg-green-800">
                            <x-icon name="shuffle" class="w-5 h-5" /> Generate Groups
                        </button>
                    </form>
                @else
                    <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-800">
                        <p class="font-semibold flex items-center gap-2"><x-icon name="warning" class="w-5 h-5" /> Unable to generate groups.</p>
                        <ul class="list-disc pl-6 mt-1">
                            @foreach ($check['errors'] as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('admin.tournaments.edit', $t) }}" class="rounded-lg bg-gray-900 text-white px-4 py-2 text-sm font-medium hover:bg-gray-800">Change group settings</a>
                        <a href="{{ route('admin.tournaments.manage', [$t, 'participants']) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium hover:bg-gray-50">Manage participants</a>
                    </div>
                @endif
            </section>

            <aside class="bg-white rounded-xl shadow p-5 content-start text-sm text-gray-600 grid gap-3">
                <h3 class="font-bold text-gray-900">How it works</h3>
                <p><strong>1.</strong> Groups are created (A, B, C...) with {{ $t->entryWord() }} spread evenly.</p>
                <p><strong>2.</strong> You can move {{ $t->entryWord() }} between groups before play starts.</p>
                <p><strong>3.</strong> Each {{ $t->entryWord(false) }} plays every other {{ $t->entryWord(false) }} in its group once (4 {{ $t->entryWord() }} = 6 matches).</p>
                <p><strong>4.</strong> The top {{ $t->advance_per_group }} of each group qualify for the knockout bracket.</p>
            </aside>
        </div>
    @else
        {{-- ================= Generated ================= --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div>
                <h2 class="text-lg font-bold">Group stage</h2>
                <p class="text-sm text-gray-500">{{ $groups->count() }} groups &middot; {{ $t->participants->whereNotNull('group_id')->count() }} {{ $t->entryWord() }} placed</p>
            </div>
            @unless ($knockoutLocked || $t->hasResults())
                <form method="POST" action="{{ route('admin.tournaments.groups.reshuffle', $t) }}"
                      data-confirm="Draw the groups again?"
                      data-confirm-body="All players will be redistributed (seeds are respected). Any match schedule for the groups will be removed."
                      data-confirm-button="Draw again">
                    @csrf
                    <button class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50">
                        <x-icon name="shuffle" class="w-4 h-4" /> Draw again
                    </button>
                </form>
            @endunless
        </div>

        @if ($knockoutLocked)
            <p class="mb-4 rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700 flex gap-2"><x-icon name="lock" class="w-4 h-4 shrink-0 mt-0.5" /> Groups are locked because the knockout stage has been generated.</p>
        @endif

        @if (! $t->schedule_generated_at)
            @php
                $totalMatches = $groups->sum(fn ($g) => intdiv($g->participants->count() * ($g->participants->count() - 1), 2));
            @endphp
            <div class="mb-5 rounded-xl bg-gradient-to-br from-green-700 to-green-900 text-white p-5 shadow flex flex-col md:flex-row md:items-center gap-4 justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-green-200">Next step</p>
                    <h3 class="text-xl font-bold">Create the group matches</h3>
                    <p class="text-sm text-green-100 mt-1">
                        Each {{ $t->entryWord(false) }} plays every other {{ $t->entryWord(false) }} in its group once: {{ $totalMatches }} matches in total.
                        Review the groups first; you can still move {{ $t->entryWord() }} afterwards (that group's matches are rebuilt).
                    </p>
                </div>
                @if ($unassigned->isEmpty())
                    <form method="POST" action="{{ route('admin.tournaments.schedule.generate', $t) }}"
                          data-confirm="Create {{ $totalMatches }} group matches?"
                          data-confirm-body="Every {{ $t->entryWord(false) }} plays each of the others in its group once. You can then set dates, times and courts and record results."
                          data-confirm-button="Create matches">
                        @csrf
                        <button type="submit" data-loading="Creating..." class="w-full md:w-auto shrink-0 rounded-lg bg-white text-green-800 font-semibold px-5 py-3 hover:bg-green-50 whitespace-nowrap">Create group matches</button>
                    </form>
                @else
                    <p class="text-sm bg-white/10 rounded-lg px-3 py-2">Put the {{ $unassigned->count() }} unplaced {{ $t->entryWord() }} into groups first (below).</p>
                @endif
            </div>
        @else
            <div class="mb-5 rounded-xl bg-white shadow p-4 flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
                <p class="text-sm text-gray-600 flex gap-2"><x-icon name="check" class="w-5 h-5 text-green-600 shrink-0" /> Group matches are created. Record results on the Matches tab; standings update automatically.</p>
                <div class="flex gap-2 shrink-0">
                    <a href="{{ route('admin.tournaments.manage', [$t, 'matches']) }}" class="rounded-lg bg-green-700 text-white font-semibold px-4 py-2 text-sm hover:bg-green-800">Matches</a>
                    <a href="{{ route('admin.tournaments.manage', [$t, 'standings']) }}" class="rounded-lg border border-gray-300 font-semibold px-4 py-2 text-sm hover:bg-gray-50">Standings</a>
                </div>
            </div>
        @endif

        {{-- Group selector --}}
        <div class="flex gap-2 overflow-x-auto pb-1 mb-4" role="tablist">
            @foreach ($groups as $group)
                <button type="button" data-group-tab="{{ $group->name }}" role="tab"
                        class="group-tab shrink-0 rounded-lg px-4 py-2 text-sm font-semibold border bg-white border-gray-300 text-gray-700 hover:bg-gray-50">
                    {{ $group->label() }} <span class="ml-1 text-xs font-normal opacity-70">{{ $group->participants->count() }}</span>
                </button>
            @endforeach
            <button type="button" data-group-tab="all" role="tab"
                    class="group-tab shrink-0 rounded-lg px-4 py-2 text-sm font-semibold border bg-white border-gray-300 text-gray-700 hover:bg-gray-50">
                All groups
            </button>
        </div>

        <div class="grid gap-4 md:grid-cols-2" id="group-panels">
            @foreach ($groups as $group)
                <section data-group-panel="{{ $group->name }}" class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="px-4 py-3 bg-gray-900 text-white flex items-center justify-between">
                        <h3 class="font-bold tracking-wide uppercase">{{ $group->label() }}</h3>
                        <span class="text-xs text-gray-300">{{ $group->participants->count() }} {{ $t->entryWord() }} &middot; {{ intdiv($group->participants->count() * ($group->participants->count() - 1), 2) }} matches</span>
                    </div>
                    <ol class="divide-y">
                        @foreach ($group->participants as $p)
                            <li class="px-4 py-2.5 flex flex-col sm:flex-row sm:items-center gap-2">
                                <span class="flex items-center gap-3 flex-1 min-w-0">
                                    <span class="w-6 text-sm text-gray-400">{{ $loop->iteration }}.</span>
                                    <span class="font-medium truncate">{{ $p->name }}</span>
                                    @if ($p->seed)
                                        <span class="rounded bg-amber-100 text-amber-800 px-1.5 text-[11px] font-bold">Seed {{ $p->seed }}</span>
                                    @endif
                                </span>
                                @unless ($knockoutLocked || $groups->count() < 2)
                                    @php $groupResults = \App\Services\GroupDraw::resultsInGroups([$group->id]); @endphp
                                    <form method="POST" action="{{ route('admin.tournaments.groups.move', [$t, $p]) }}" class="flex items-center gap-1.5"
                                          data-confirm="Move {{ $p->name }}?"
                                          data-confirm-body="{{ $groupResults ? 'Results already recorded in the affected groups will be deleted, and their matches rebuilt.' : 'Their group matches will be rebuilt.' }}"
                                          data-confirm-button="Move" @if ($groupResults) data-confirm-tone="danger" @endif>
                                        @csrf
                                        @method('PATCH')
                                        @if ($groupResults)
                                            <input type="hidden" name="confirm_loss" value="1">
                                        @endif
                                        <label class="sr-only" for="move-{{ $p->id }}">Move to</label>
                                        <select name="group_id" id="move-{{ $p->id }}" class="rounded-lg border border-gray-300 px-2 py-1 text-sm">
                                            @foreach ($groups as $other)
                                                @if ($other->id !== $group->id)
                                                    <option value="{{ $other->id }}">Move to {{ $other->name }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <button class="rounded-lg px-2 py-1 text-sm text-gray-700 hover:bg-gray-100" title="Move"><x-icon name="swap" class="w-4 h-4" /></button>
                                    </form>
                                @endunless
                            </li>
                        @endforeach
                    </ol>
                    <div class="px-4 py-3 bg-gray-50 text-xs text-gray-500 border-t flex items-center justify-between gap-2">
                        @if ($t->schedule_generated_at)
                            @php $gt = collect($tables)->firstWhere('group.id', $group->id); @endphp
                            <span>{{ $gt['played'] ?? 0 }} / {{ $gt['total'] ?? 0 }} matches played</span>
                            <a href="{{ route('admin.tournaments.manage', [$t, 'standings']) }}" class="text-green-700 font-medium hover:underline">Standings</a>
                        @else
                            <span>Matches are created with the button above.</span>
                        @endif
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Participants added after the draw --}}
        @if ($unassigned->isNotEmpty())
            <section class="mt-5 rounded-xl border-2 border-dashed border-amber-300 bg-amber-50 p-4">
                <h3 class="font-bold text-amber-900 flex items-center gap-2"><x-icon name="warning" class="w-5 h-5" /> Not in a group ({{ $unassigned->count() }})</h3>
                <p class="text-sm text-amber-800 mb-3">These participants were added after the groups were drawn. Put each one in a group.</p>
                <div class="grid gap-2">
                    @foreach ($unassigned as $p)
                        <form method="POST" action="{{ route('admin.tournaments.groups.move', [$t, $p]) }}" class="flex flex-wrap items-center gap-2 bg-white rounded-lg px-3 py-2">
                            @csrf
                            @method('PATCH')
                            <span class="font-medium flex-1">{{ $p->name }}</span>
                            <select name="group_id" class="rounded-lg border border-gray-300 px-2 py-1 text-sm">
                                @foreach ($groups as $g)
                                    <option value="{{ $g->id }}">{{ $g->label() }} ({{ $g->participants->count() }})</option>
                                @endforeach
                            </select>
                            <button class="rounded-lg bg-green-700 text-white px-3 py-1 text-sm font-medium hover:bg-green-800">Add to group</button>
                        </form>
                    @endforeach
                </div>
            </section>
        @endif

        <script>
            (function () {
                var tabs = document.querySelectorAll('.group-tab');
                var panels = document.querySelectorAll('[data-group-panel]');

                function show(name) {
                    tabs.forEach(function (tab) {
                        var active = tab.dataset.groupTab === name;
                        tab.classList.toggle('bg-green-700', active);
                        tab.classList.toggle('text-white', active);
                        tab.classList.toggle('border-green-700', active);
                        tab.classList.toggle('bg-white', !active);
                        tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    });
                    panels.forEach(function (panel) {
                        panel.style.display = (name === 'all' || panel.dataset.groupPanel === name) ? '' : 'none';
                    });
                    try { history.replaceState(null, '', '#' + name); } catch (e) {}
                }

                tabs.forEach(function (tab) { tab.addEventListener('click', function () { show(tab.dataset.groupTab); }); });
                var start = decodeURIComponent((location.hash || '').slice(1));
                var exists = Array.prototype.some.call(tabs, function (tab) { return tab.dataset.groupTab === start; });
                show(exists ? start : (window.innerWidth >= 768 ? 'all' : (tabs[0] && tabs[0].dataset.groupTab)));
            })();
        </script>
    @endif
@endsection