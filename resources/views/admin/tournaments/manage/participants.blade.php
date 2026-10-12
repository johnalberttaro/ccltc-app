@extends('admin.tournaments.manage.shell')

@php
    $t = $tournament;
    $count = $t->participants->count();
    $locked = (bool) $t->knockout_generated_at;
    $capacity = $t->usesGroups() ? $t->groupCapacity() : null;
    // Doubles with individual players (not yet paired into teams)
    $asPlayers = $t->match_type !== 'singles' && $count > 0 && $t->participants->every(fn ($p) => ! str_contains($p->name, '/'));
@endphp

@section('tab')
    <div class="grid gap-5 lg:grid-cols-3">
        {{-- Add participants --}}
        <section class="bg-white rounded-xl shadow p-4 md:p-5 content-start">
            <h2 class="font-bold">Add participants</h2>
            @if ($locked)
                <p class="mt-2 text-sm text-gray-600 flex gap-2"><x-icon name="lock" class="w-4 h-4 shrink-0 mt-0.5" /> The bracket is generated, so no more participants can be added.</p>
            @else
                <p class="text-sm text-gray-500 mt-1 mb-3">
                    One name per line.
                </p>
                <form method="POST" action="{{ route('admin.tournaments.participants.store', $t) }}" class="grid gap-2">
                    @csrf
                    <textarea name="names" rows="8" required
                              class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">{{ old('names') }}</textarea>
                    @error('names')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <button type="submit" data-loading="Adding..." class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-700 text-white font-semibold px-4 py-2 hover:bg-green-800">
                        <x-icon name="plus" class="w-4 h-4" /> Add
                    </button>
                </form>
            @endif

            {{-- Make doubles teams from individual players --}}
            @php
                $singlesOnly = $count >= 2 && $t->participants->every(fn ($p) => ! str_contains($p->name, '/'));
            @endphp
            @if ($t->match_type !== 'singles' && $singlesOnly && ! $t->drawStarted())
                <div class="mt-5 rounded-xl border border-green-200 bg-green-50 p-4">
                    <p class="font-semibold text-green-900 flex items-center gap-2"><x-icon name="users" class="w-5 h-5" /> Pair into doubles teams</p>
                    <p class="text-sm text-green-900/80 mt-1">
                        You have {{ $count }} individual players. Turn them into {{ intdiv($count, 2) }} doubles teams automatically.
                    </p>
                    @if ($count % 2 === 1)
                        <p class="text-sm text-amber-800 mt-2">An even number of players is needed. Add or remove one player first.</p>
                    @else
                        <div class="grid gap-2 mt-3">
                            <form method="POST" action="{{ route('admin.tournaments.participants.pair', $t) }}"
                                  data-confirm="Pair players randomly?"
                                  data-confirm-body="{{ $count }} players will become {{ intdiv($count, 2) }} doubles teams with random partners.&#10;The individual entries are replaced by the teams."
                                  data-confirm-button="Make teams">
                                @csrf
                                <input type="hidden" name="mode" value="random">
                                <button class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-green-700 text-white font-semibold px-4 py-2 hover:bg-green-800">
                                    <x-icon name="shuffle" class="w-4 h-4" /> Random partners
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.tournaments.participants.pair', $t) }}"
                                  data-confirm="Make balanced teams?"
                                  data-confirm-body="Seeded players are paired strongest with weakest (seed 1 with the lowest-ranked player, and so on). Unseeded players are paired randomly.&#10;The individual entries are replaced by the teams."
                                  data-confirm-button="Make teams">
                                @csrf
                                <input type="hidden" name="mode" value="balanced">
                                <button class="w-full inline-flex items-center justify-center gap-2 rounded-lg border border-green-700 text-green-800 bg-white font-semibold px-4 py-2 hover:bg-green-50">
                                    <x-icon name="swap" class="w-4 h-4" /> Balanced (uses seeds)
                                </button>
                            </form>
                        </div>
                        <p class="text-xs text-green-900/70 mt-2">For balanced teams, first give the players seeds by skill (1 = best).</p>
                    @endif
                </div>
            @elseif ($t->match_type !== 'singles' && $singlesOnly && $t->drawStarted())
                <p class="mt-5 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">
                    These look like individual players. To pair them into doubles teams, first reset the draw in <strong>Settings</strong>.
                </p>
            @endif

            {{-- Capacity --}}
            <div class="mt-5 grid gap-2 text-sm">
                @if ($t->max_participants)
                    @php
                        $max = $asPlayers ? $t->max_participants * 2 : $t->max_participants;
                        $pct = min(100, round($count / $max * 100));
                    @endphp
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ $asPlayers ? 'Players (before pairing)' : 'Maximum' }}</span>
                        <span class="font-medium">{{ $count }} / {{ $max }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden"><div class="h-full bg-green-600" style="width: {{ $pct }}%"></div></div>
                    @if ($asPlayers)
                        <p class="text-xs text-gray-500">{{ $t->max_participants }} teams = up to {{ $max }} individual players. Pair them into teams before drawing the groups.</p>
                    @endif
                @endif
                @if ($capacity && ! ($asPlayers && $count <= $capacity * 2))
                    <p class="text-xs {{ $count > $capacity ? 'text-red-700' : 'text-gray-500' }}">
                        Groups have room for {{ $capacity }} ({{ $t->group_count }} x {{ $t->group_size }}).
                        @if ($count > $capacity)
                            You have {{ $count - $capacity }} too many. Change the group settings or remove participants.
                        @endif
                    </p>
                @endif
            </div>
        </section>

        {{-- List --}}
        <section class="lg:col-span-2 bg-white rounded-xl shadow">
            <div class="px-4 md:px-5 py-3 border-b flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-between">
                <h2 class="font-bold">Tournament participants <span class="text-gray-400 font-normal">({{ $count }})</span></h2>
                <div class="flex gap-2">
                    <div class="relative flex-1 sm:w-56">
                        <x-icon name="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
                        <input type="search" id="participant-search" placeholder="Search names"
                               class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-1.5 text-sm">
                    </div>
                    @if ($t->participants->whereNotNull('seed')->isNotEmpty())
                        <form method="POST" action="{{ route('admin.tournaments.participants.clear-seeds', $t) }}"
                              data-confirm="Clear all seeds?" data-confirm-body="Every participant will become unseeded." data-confirm-button="Clear seeds">
                            @csrf
                            <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm hover:bg-gray-50 whitespace-nowrap">Clear seeds</button>
                        </form>
                    @endif
                </div>
            </div>

            <p class="px-4 md:px-5 pt-3 text-xs text-gray-500 flex gap-1.5">
                <x-icon name="info" class="w-4 h-4 shrink-0" />
                Seeds are optional (1 = strongest). Seeded players are spread across different groups / bracket halves; everyone else is drawn randomly.
            </p>

            @if ($count === 0)
                <div class="p-10 text-center">
                    <x-icon name="users" class="w-10 h-10 mx-auto text-gray-300" />
                    <p class="mt-2 font-semibold">No participants yet</p>
                    <p class="text-sm text-gray-500">Add names using the box on the left.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs uppercase text-gray-500">
                            <tr class="border-b">
                                <th class="px-4 md:px-5 py-2 w-10">#</th>
                                <th class="px-2 py-2">Name</th>
                                <th class="px-2 py-2 w-24">Seed</th>
                                @if ($t->usesGroups() && $t->groups_generated_at)
                                    <th class="px-2 py-2 w-20">Group</th>
                                @endif
                                <th class="px-4 md:px-5 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="participant-rows">
                            @foreach ($t->participants as $p)
                                <tr class="border-b last:border-0 hover:bg-gray-50" data-name="{{ mb_strtolower($p->name) }}">
                                    <td class="px-4 md:px-5 py-2 text-gray-400">{{ $loop->iteration }}</td>
                                    <td class="px-2 py-2" colspan="2">
                                        <form method="POST" action="{{ route('admin.tournaments.participants.update', [$t, $p]) }}" class="flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="name" value="{{ $p->name }}" required aria-label="Name"
                                                   class="flex-1 min-w-[9rem] rounded-lg border border-transparent hover:border-gray-300 focus:border-gray-300 px-2 py-1">
                                            <input type="number" name="seed" value="{{ $p->seed }}" min="1" max="256" placeholder="-" aria-label="Seed"
                                                   class="w-16 rounded-lg border border-gray-300 px-2 py-1 text-center">
                                            <button class="rounded-lg px-2 py-1 text-green-700 font-medium hover:bg-green-50" title="Save">Save</button>
                                        </form>
                                    </td>
                                    @if ($t->usesGroups() && $t->groups_generated_at)
                                        <td class="px-2 py-2">
                                            @if ($p->group)
                                                <span class="rounded-md bg-gray-900 text-white px-2 py-0.5 text-xs font-bold">{{ $p->group->name }}</span>
                                            @else
                                                <span class="text-xs text-amber-700">None</span>
                                            @endif
                                        </td>
                                    @endif
                                    <td class="px-4 md:px-5 py-2 text-right">
                                        <form method="POST" action="{{ route('admin.tournaments.participants.destroy', [$t, $p]) }}" class="inline"
                                              data-confirm="Remove {{ $p->name }}?" data-confirm-body="They will be taken out of this tournament." data-confirm-button="Remove" data-confirm-tone="danger">
                                            @csrf
                                            @method('DELETE')
                                            <button class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-red-700 hover:bg-red-50">
                                                <x-icon name="trash" class="w-4 h-4" /><span class="hidden sm:inline">Remove</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p id="no-results" class="hidden p-6 text-center text-sm text-gray-500">No participants match your search.</p>
                </div>
            @endif

            @error('name')
                <p class="px-5 py-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
            @error('seed')
                <p class="px-5 py-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </section>
    </div>

    <script>
        (function () {
            var search = document.getElementById('participant-search');
            if (!search) return;
            search.addEventListener('input', function () {
                var term = search.value.trim().toLowerCase();
                var shown = 0;
                document.querySelectorAll('#participant-rows tr').forEach(function (row) {
                    var match = row.dataset.name.indexOf(term) !== -1;
                    row.style.display = match ? '' : 'none';
                    if (match) shown++;
                });
                var empty = document.getElementById('no-results');
                if (empty) empty.classList.toggle('hidden', shown > 0);
            });
        })();
    </script>
@endsection