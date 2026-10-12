@extends('admin.tournaments.manage.shell')

@php
    $t = $tournament;
    $results = $t->hasResults();
@endphp

@section('tab')
    <div class="grid gap-5 lg:grid-cols-3">
        <section class="lg:col-span-2 bg-white rounded-xl shadow">
            <div class="px-5 py-4 border-b flex items-center justify-between gap-3">
                <div>
                    <h2 class="font-bold">Tournament configuration</h2>
                    @if ($t->drawStarted())
                        <p class="text-xs text-gray-500 flex items-center gap-1 mt-0.5"><x-icon name="lock" class="w-3.5 h-3.5" /> Format and group settings are locked because the draw has been made.</p>
                    @endif
                </div>
                <a href="{{ route('admin.tournaments.edit', $t) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 text-white px-3 py-2 text-sm font-medium hover:bg-gray-800">
                    <x-icon name="pencil" class="w-4 h-4" /> Edit
                </a>
            </div>
            <dl class="divide-y text-sm">
                @php
                    $rows = [
                        'Format'        => $t->formatLabel(),
                        'Singles / Doubles' => $t->typeLabel(),
                        'Category'      => $t->category ?: '-',
                        'Maximum players / teams' => $t->max_participants ?: 'No limit',
                        'Match rules'   => $t->matchRulesLabel(),
                    ];
                    if ($t->usesGroups()) {
                        $rows['Groups'] = "{$t->group_count} groups, up to {$t->group_size} players each";
                        $rows['Advancing'] = "Top {$t->advance_per_group} of each group ({$t->qualifierCount()} players)";
                    }
                    if ($t->knockoutIsDouble()) {
                        $rows['Grand final reset'] = $t->grand_final_reset ? 'Yes' : 'No';
                    }
                    if ($t->usesGroups() || $t->isRoundRobin()) {
                        $rows['Points'] = "Win {$t->points_win}, loss {$t->points_loss}";
                        $rows['Tie-breaks'] = 'Points, ' . ($t->use_head_to_head ? 'head-to-head, ' : '') . ($t->standings_method === 'percentage' ? 'set %, game %' : 'set difference, game difference') . ', seed';
                    }
                @endphp
                @foreach ($rows as $label => $value)
                    <div class="px-5 py-3 grid sm:grid-cols-3 gap-1">
                        <dt class="text-gray-500">{{ $label }}</dt>
                        <dd class="sm:col-span-2 font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        {{-- Danger zone --}}
        <section class="rounded-xl border-2 border-red-200 bg-white p-5 content-start grid gap-4">
            <h2 class="font-bold text-red-800 flex items-center gap-2"><x-icon name="warning" class="w-5 h-5" /> Danger zone</h2>

            @if ($t->usesGroups() && $t->groups_generated_at)
                <div>
                    <p class="text-sm font-semibold">Reset groups</p>
                    <p class="text-xs text-gray-500 mb-2">Removes the groups{{ $results ? ', the group matches and all their results' : ' and any group matches' }}. Participants are kept. Unlocks the format settings.</p>
                    @if ($t->knockout_generated_at)
                        <p class="text-xs text-gray-600">Reset the knockout stage first.</p>
                    @else
                        <form method="POST" action="{{ route('admin.tournaments.groups.reset', $t) }}"
                              data-confirm="Reset the groups?"
                              data-confirm-body="{{ $results ? 'Recorded group results will be permanently deleted. ' : '' }}Participants are kept, and you can draw again."
                              data-confirm-button="Reset groups" data-confirm-tone="danger">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-lg border border-red-300 text-red-700 px-3 py-2 text-sm font-medium hover:bg-red-50">Reset groups</button>
                        </form>
                    @endif
                </div>
            @endif

            @if ($t->schedule_generated_at && ! $t->hasBracket())
                <div>
                    <p class="text-sm font-semibold">Reset {{ $t->usesGroups() ? 'group matches' : 'schedule' }}</p>
                    <p class="text-xs text-gray-500 mb-2">Deletes every {{ $t->usesGroups() ? 'group' : '' }} match{{ $results ? ' and its result' : '' }}. {{ $t->usesGroups() ? 'Groups' : 'Participants' }} are kept.</p>
                    <form method="POST" action="{{ route('admin.tournaments.schedule.reset', $t) }}"
                          data-confirm="Reset the {{ $t->usesGroups() ? 'group matches' : 'schedule' }}?"
                          data-confirm-body="{{ $results ? 'All recorded results will be permanently deleted. ' : '' }}You can create the matches again afterwards."
                          data-confirm-button="Reset" data-confirm-tone="danger">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-red-300 text-red-700 px-3 py-2 text-sm font-medium hover:bg-red-50">Reset {{ $t->usesGroups() ? 'group matches' : 'schedule' }}</button>
                    </form>
                </div>
            @endif

            @if ($t->hasBracket())
                <div>
                    <p class="text-sm font-semibold">Reset bracket</p>
                    <p class="text-xs text-gray-500 mb-2">Deletes the bracket{{ $results ? ' and every bracket result' : '' }}. Participants are kept, and you can generate it again.</p>
                    <form method="POST" action="{{ route('admin.tournaments.bracket.reset', $t) }}"
                          data-confirm="Reset the bracket?"
                          data-confirm-body="{{ $results ? 'All recorded bracket results will be permanently deleted. ' : '' }}You can generate a new bracket afterwards."
                          data-confirm-button="Reset bracket" data-confirm-tone="danger">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-red-300 text-red-700 px-3 py-2 text-sm font-medium hover:bg-red-50">Reset bracket</button>
                    </form>
                </div>
            @endif

            <div>
                <p class="text-sm font-semibold">Delete tournament</p>
                <p class="text-xs text-gray-500 mb-2">Deletes the tournament, its participants, groups, matches and results.</p>
                <form method="POST" action="{{ route('admin.tournaments.destroy', $t) }}"
                      data-confirm="Delete {{ $t->name }}?"
                      data-confirm-body="Everything in this tournament will be permanently deleted. This cannot be undone."
                      data-confirm-button="Delete tournament" data-confirm-tone="danger">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-lg bg-red-600 text-white px-3 py-2 text-sm font-semibold hover:bg-red-700">Delete tournament</button>
                </form>
            </div>
        </section>
    </div>
@endsection