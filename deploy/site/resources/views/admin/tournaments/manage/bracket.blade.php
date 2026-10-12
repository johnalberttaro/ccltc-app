@extends('admin.tournaments.manage.shell')

@php
    $t = $tournament;
    $n = $t->participants->count();
    $size = $n >= 2 ? \App\Services\BracketBuilder::size($n) : 0;
    $double = $t->knockoutIsDouble();
@endphp

@section('tab')
    @if ($errors->has('bracket'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-800">{{ $errors->first('bracket') }}</div>
    @endif

    @if (! $t->hasBracket())
        @if ($t->usesGroups())
            @php
                $playedAll = collect($tables ?? [])->sum('played');
                $totalAll = collect($tables ?? [])->sum('total');
                $qualifiers = $qualifiers ?? [];
                $qCount = count($qualifiers);
                $koSize = $qCount >= 2 ? \App\Services\BracketBuilder::size($qCount) : 0;
            @endphp
            @if (! $t->schedule_generated_at)
                <div class="bg-white rounded-xl shadow p-10 text-center max-w-xl mx-auto">
                    <span class="w-14 h-14 mx-auto rounded-2xl bg-gray-100 text-gray-500 flex items-center justify-center"><x-icon name="bracket" class="w-7 h-7" /></span>
                    <h2 class="text-xl font-bold mt-4">The bracket comes after the group stage</h2>
                    <p class="text-gray-600 mt-2">
                        First generate the groups and create the group matches. When they are played, the top {{ $t->advance_per_group }} of each group
                        ({{ $t->qualifierCount() }} {{ $t->entryWord() }}) go into the knockout bracket.
                    </p>
                    <a href="{{ route('admin.tournaments.manage', [$t, 'groups']) }}" class="inline-block mt-4 rounded-lg bg-green-700 text-white font-semibold px-4 py-2 hover:bg-green-800">Go to Groups</a>
                </div>
            @else
                <div class="grid gap-5 lg:grid-cols-3">
                    <section class="lg:col-span-2 bg-white rounded-xl shadow p-5">
                        <h2 class="text-lg font-bold">Generate the knockout stage</h2>
                        <div class="flex items-center gap-3 mt-3">
                            <div class="flex-1 h-2 rounded-full bg-gray-200 overflow-hidden">
                                <div class="h-full bg-green-600" style="width: {{ $totalAll ? round($playedAll / $totalAll * 100) : 0 }}%"></div>
                            </div>
                            <span class="text-sm font-semibold whitespace-nowrap">{{ $playedAll }} / {{ $totalAll }} group matches played</span>
                        </div>

                        @if ($groupStageFinished)
                            <div class="mt-4 rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-900">
                                The group stage is finished. {{ $qCount }} {{ $t->entryWord() }} qualified for the
                                <strong>{{ \App\Models\Tournament::firstRoundName($qCount) }}</strong>
                                ({{ strtolower(\App\Models\Tournament::KNOCKOUT_FORMATS[$t->knockout_format] ?? 'single elimination') }}).
                                Group winners are seeded first, and {{ $t->entryWord() }} from the same group are kept apart for as long as possible.
                            </div>
                        @else
                            <div class="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">
                                Record the remaining {{ $totalAll - $playedAll }} group {{ Str::plural('match', $totalAll - $playedAll) }} first.
                                The list below shows who would qualify with the current standings.
                            </div>
                        @endif

                        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mt-5 mb-2">
                            {{ $groupStageFinished ? 'Qualified' : 'Currently qualifying' }} (in seed order)
                        </h3>
                        <ol class="grid gap-1.5 sm:grid-cols-2">
                            @foreach ($qualifiers as $i => $q)
                                <li class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm">
                                    <span class="w-6 h-6 rounded-full bg-gray-900 text-white text-xs font-bold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                                    <span class="font-medium truncate flex-1">{{ $q['participant']->name }}</span>
                                    <span class="text-xs text-gray-500 whitespace-nowrap">{{ $q['label'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                        @if ($koSize > $qCount)
                            <p class="text-xs text-gray-500 mt-2">{{ $koSize - $qCount }} top {{ Str::plural('seed', $koSize - $qCount) }} get a bye into the second round.</p>
                        @endif

                        <div class="mt-5 flex flex-col sm:flex-row gap-3">
                            @if ($groupStageFinished)
                                <form method="POST" action="{{ route('admin.tournaments.bracket.generate', $t) }}"
                                      data-confirm="Generate the knockout stage?"
                                      data-confirm-body="{{ $qCount }} qualifiers will be placed in the bracket. Group results are then locked (reset the bracket to change them)."
                                      data-confirm-button="Generate">
                                    @csrf
                                    <button type="submit" data-loading="Generating..." class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-green-700 text-white font-semibold px-6 py-3 hover:bg-green-800">
                                        <x-icon name="bracket" class="w-5 h-5" /> Generate Knockout Stage
                                    </button>
                                </form>
                            @else
                                <button type="button" disabled class="rounded-lg bg-gray-200 text-gray-500 font-semibold px-6 py-3 cursor-not-allowed">Generate Knockout Stage</button>
                                <form method="POST" action="{{ route('admin.tournaments.bracket.generate', $t) }}"
                                      data-confirm="Generate the knockout before the groups finish?"
                                      data-confirm-body="{{ $totalAll - $playedAll }} group {{ Str::plural('match', $totalAll - $playedAll) }} are still unplayed. The bracket will use the current standings, and the remaining group matches can no longer be recorded."
                                      data-confirm-button="Generate anyway" data-confirm-tone="danger">
                                    @csrf
                                    <input type="hidden" name="force" value="1">
                                    <button class="text-sm text-gray-600 underline hover:text-gray-900 sm:mt-3">Generate anyway with current standings</button>
                                </form>
                            @endif
                        </div>
                    </section>

                    <aside class="bg-white rounded-xl shadow p-5 content-start text-sm text-gray-600 grid gap-3">
                        <h3 class="font-bold text-gray-900">How it works</h3>
                        <p><strong>1.</strong> When every group match is played, the top {{ $t->advance_per_group }} of each group qualify.</p>
                        <p><strong>2.</strong> Group winners get the top seeds and face runners-up from <em>other</em> groups in the first round.</p>
                        <p><strong>3.</strong> {{ ucfirst($t->entryWord()) }} from the same group can only meet again in the final (when 2 advance).</p>
                        <p><strong>4.</strong> Then record knockout results just like any bracket; winners advance automatically.</p>
                    </aside>
                </div>
            @endif
        @else
            <div class="grid gap-5 lg:grid-cols-3">
                <section class="lg:col-span-2 bg-white rounded-xl shadow p-5">
                    <h2 class="text-lg font-bold">Generate the bracket</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        Seeded players are placed so the top seeds meet as late as possible (seeds 1 and 2 only in the final).
                        Everyone else is drawn at random.
                    </p>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 my-5">
                        <div class="rounded-lg bg-gray-50 p-3 text-center"><p class="text-2xl font-bold">{{ $n }}</p><p class="text-xs text-gray-500">Participants</p></div>
                        <div class="rounded-lg bg-gray-50 p-3 text-center"><p class="text-2xl font-bold">{{ $size ?: '-' }}</p><p class="text-xs text-gray-500">Bracket size</p></div>
                        <div class="rounded-lg bg-gray-50 p-3 text-center"><p class="text-2xl font-bold">{{ $size ? $size - $n : '-' }}</p><p class="text-xs text-gray-500">Byes (top seeds)</p></div>
                        <div class="rounded-lg bg-gray-50 p-3 text-center">
                            <p class="text-2xl font-bold">{{ $n >= 2 ? ($double ? (2 * $n - 2) . '-' . (2 * $n - 1) : $n - 1) : '-' }}</p>
                            <p class="text-xs text-gray-500">Matches</p>
                        </div>
                    </div>

                    @if ($n < 2)
                        <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900">
                            Add at least 2 participants first.
                            <a href="{{ route('admin.tournaments.manage', [$t, 'participants']) }}" class="font-semibold underline">Go to Participants</a>
                        </div>
                    @else
                        <div class="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-900">
                            {{ $double ? 'Double elimination' : 'Single elimination' }}: first round is the
                            <strong>{{ \App\Models\Tournament::firstRoundName($n) }}</strong>.
                            @if ($size > $n)
                                The top {{ $size - $n }} {{ Str::plural('seed', $size - $n) }} {{ $size - $n === 1 ? 'gets' : 'get' }} a bye into the next round.
                            @endif
                            @if ($double)
                                Players are out after two losses{{ $t->grand_final_reset ? '; the grand final can go to a reset match' : '' }}.
                            @endif
                        </div>

                        <form method="POST" action="{{ route('admin.tournaments.bracket.generate', $t) }}" class="mt-5"
                              data-confirm="Generate the bracket?"
                              data-confirm-body="{{ $n }} participants{{ $size > $n ? ', ' . ($size - $n) . ' ' . Str::plural('bye', $size - $n) . ' for the top seeds' : '' }}.&#10;&#10;After this, participants can't be added or removed unless you reset the bracket."
                              data-confirm-button="Generate">
                            @csrf
                            <button type="submit" data-loading="Generating..." class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-lg bg-green-700 text-white font-semibold px-6 py-3 hover:bg-green-800">
                                <x-icon name="bracket" class="w-5 h-5" /> Generate Bracket
                            </button>
                        </form>
                    @endif
                </section>

                <aside class="bg-white rounded-xl shadow p-5 content-start text-sm text-gray-600 grid gap-3">
                    <h3 class="font-bold text-gray-900">How it works</h3>
                    <p><strong>1.</strong> Generate the bracket once all participants are in.</p>
                    <p><strong>2.</strong> Click a match and type the score. The system checks it and finds the winner.</p>
                    <p><strong>3.</strong> The winner moves to the next round automatically{{ $double ? ', and the loser drops to the losers bracket' : '' }}.</p>
                    <p><strong>4.</strong> When the final is played, the champion is shown and the tournament is marked completed.</p>
                </aside>
            </div>
        @endif
    @else
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <p class="text-sm text-gray-600 flex items-center gap-2">
                <x-icon name="info" class="w-4 h-4 shrink-0" />
                Click a match to enter its score. Winners{{ $double ? ' (and losers, into the losers bracket)' : '' }} move on automatically.
            </p>
            <a href="{{ route('admin.tournaments.manage', [$t, 'matches']) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-50 shrink-0">
                <x-icon name="list" class="w-4 h-4" /> Match list &amp; schedule
            </a>
        </div>

        <div class="bg-white rounded-xl shadow p-4 md:p-6">
            @include('partials.tournament.bracket', ['bracket' => $bracket, 'editable' => true])
        </div>

        @include('admin.tournaments.manage.partials.match-dialogs')
    @endif
@endsection