@extends('layouts.admin')

@php
    $drawLocked = $drawLocked ?? false;
    $rulesLocked = $rulesLocked ?? false;
    $val = fn ($field, $default = null) => old($field, $tournament->{$field} ?? $default);
@endphp

@section('title', $tournament->exists ? 'Edit Tournament' : 'Create Tournament')

@section('content')
    <a href="{{ $tournament->exists ? route('admin.tournaments.manage', [$tournament, 'overview']) : route('admin.tournaments.index') }}"
       class="inline-flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900">
        <x-icon name="arrow-left" class="w-4 h-4" /> {{ $tournament->exists ? 'Back to tournament' : 'Back to tournaments' }}
    </a>
    <h1 class="text-2xl font-bold mt-2">{{ $tournament->exists ? 'Edit Tournament' : 'Create Tournament' }}</h1>
    <p class="text-sm text-gray-500 mb-5">Fill in the details, choose a format, then add participants on the next screen.</p>

    <form method="POST" enctype="multipart/form-data" id="tournament-form"
          action="{{ $tournament->exists ? route('admin.tournaments.update', $tournament) : route('admin.tournaments.store') }}"
          class="grid gap-5">
        @csrf
        @if ($tournament->exists) @method('PUT') @endif

        @include('admin.partials.errors')

        {{-- ================= 1. Basic information ================= --}}
        <section class="bg-white rounded-xl shadow p-4 md:p-6 grid gap-4">
            <h2 class="font-bold text-lg">Basic information</h2>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Tournament name</label>
                    <input type="text" name="name" value="{{ $val('name') }}" required placeholder="e.g. CCLTC Open Tennis Tournament"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Status</label>
                    <select name="status" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        @foreach (\App\Models\Tournament::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected($val('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Draft = hidden from the public.</p>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium mb-1">Category <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" name="category" value="{{ $val('category') }}" placeholder="e.g. Men's, Women's, Mixed, Juniors, Open"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Singles / Doubles</label>
                    <select name="match_type" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        @foreach (\App\Models\Tournament::TYPES as $value => $label)
                            <option value="{{ $value }}" @selected($val('match_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Description</label>
                <textarea name="description" rows="3" placeholder="Prizes, rules, dress code..."
                          class="w-full rounded-lg border border-gray-300 px-3 py-2">{{ $val('description') }}</textarea>
            </div>
        </section>

        {{-- ================= 2. Schedule & venue ================= --}}
        <section class="bg-white rounded-xl shadow p-4 md:p-6 grid gap-4">
            <h2 class="font-bold text-lg">Schedule &amp; venue</h2>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium mb-1">Start date &amp; time</label>
                    <input type="datetime-local" name="starts_at" required value="{{ old('starts_at', $tournament->starts_at?->format('Y-m-d\TH:i')) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">End date &amp; time <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $tournament->ends_at?->format('Y-m-d\TH:i')) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium mb-1">Venue</label>
                    <input type="text" name="venue" value="{{ $val('venue') }}" placeholder="e.g. City Sports Complex"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Court(s) <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="text" name="court" value="{{ $val('court') }}" placeholder="e.g. Courts 1-4"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium mb-1">Registration deadline <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="date" name="registration_deadline" value="{{ old('registration_deadline', $tournament->registration_deadline?->format('Y-m-d')) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Maximum players / teams <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="number" name="max_participants" min="2" max="256" value="{{ $val('max_participants') }}" placeholder="No limit"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
            </div>
        </section>

        {{-- ================= 3. Format ================= --}}
        <section class="bg-white rounded-xl shadow p-4 md:p-6 grid gap-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-bold text-lg">Tournament format</h2>
                @if ($drawLocked)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                        <x-icon name="lock" class="w-3.5 h-3.5" /> Locked: the draw has been made
                    </span>
                @endif
            </div>

            @if ($drawLocked)
                <p class="text-sm text-gray-600 rounded-lg bg-gray-50 px-3 py-2">
                    The format and group settings are locked because groups or the bracket were already generated.
                    To change them, reset the draw from the tournament's <strong>Settings</strong> tab.
                </p>
                @foreach (['format', 'group_count', 'group_size', 'advance_per_group', 'knockout_format'] as $field)
                    <input type="hidden" name="{{ $field }}" value="{{ $tournament->{$field} }}">
                @endforeach
                @if ($tournament->grand_final_reset)
                    <input type="hidden" name="grand_final_reset" value="1">
                @endif
            @endif

            <fieldset @disabled($drawLocked) class="grid gap-4 {{ $drawLocked ? 'opacity-60' : '' }}">
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach (\App\Models\Tournament::FORMATS as $value => $label)
                        <label class="relative flex gap-3 rounded-xl border-2 p-4 cursor-pointer hover:bg-gray-50 has-[:checked]:border-green-600 has-[:checked]:bg-green-50">
                            <input type="radio" name="format" value="{{ $value }}" class="mt-1 accent-green-700"
                                   @checked($val('format', 'group_knockout') === $value)>
                            <span>
                                <span class="block font-semibold">{{ $label }}</span>
                                <span class="block text-sm text-gray-600">{{ \App\Models\Tournament::FORMAT_HELP[$value] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                {{-- Group stage settings --}}
                <div data-show-for="group_knockout" class="rounded-xl border border-green-200 bg-green-50/50 p-4 grid gap-4">
                    <h3 class="font-semibold">Group stage settings</h3>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="block text-sm font-medium mb-1">Number of groups</label>
                            <input type="number" name="group_count" id="group_count" min="1" max="16" value="{{ $val('group_count', 4) }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Players / teams per group (max)</label>
                            <input type="number" name="group_size" id="group_size" min="2" max="16" value="{{ $val('group_size', 4) }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Advancing from each group</label>
                            <input type="number" name="advance_per_group" id="advance_per_group" min="1" max="8" value="{{ $val('advance_per_group', 2) }}"
                                   class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium mb-1">Knockout format</label>
                            <select name="knockout_format" id="knockout_format" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                                @foreach (\App\Models\Tournament::KNOCKOUT_FORMATS as $value => $label)
                                    <option value="{{ $value }}" @selected($val('knockout_format', 'single') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <p id="group-summary" class="self-end rounded-lg bg-white border border-green-200 px-3 py-2 text-sm text-green-900"></p>
                    </div>
                </div>

                {{-- Double elimination option --}}
                <label data-show-for="double" class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                    <input type="hidden" name="grand_final_reset" value="0">
                    <input type="checkbox" name="grand_final_reset" value="1" class="mt-1 rounded border-gray-300"
                           @checked(old('grand_final_reset', $tournament->grand_final_reset ?? true))>
                    <span>
                        <span class="block text-sm font-medium">Grand final reset</span>
                        <span class="block text-xs text-gray-500">If the losers-bracket finalist wins the grand final, a second deciding match is played (both then have one loss).</span>
                    </span>
                </label>
            </fieldset>
        </section>

        {{-- ================= 4. Match rules ================= --}}
        <section class="bg-white rounded-xl shadow p-4 md:p-6 grid gap-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-bold text-lg">Match rules</h2>
                @if ($rulesLocked)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">
                        <x-icon name="lock" class="w-3.5 h-3.5" /> Locked: results already recorded
                    </span>
                @endif
            </div>

            @if ($rulesLocked)
                @foreach (['best_of', 'games_per_set', 'final_set'] as $field)
                    <input type="hidden" name="{{ $field }}" value="{{ $tournament->{$field} }}">
                @endforeach
            @endif

            <fieldset @disabled($rulesLocked) class="grid gap-4 sm:grid-cols-3 {{ $rulesLocked ? 'opacity-60' : '' }}">
                <div>
                    <label class="block text-sm font-medium mb-1">Sets per match</label>
                    <select name="best_of" id="best_of" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        @foreach (\App\Models\Tournament::SCORE_FORMATS as $value => $label)
                            <option value="{{ $value }}" @selected((int) $val('best_of', 1) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Games per set</label>
                    <select name="games_per_set" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        @foreach (\App\Models\Tournament::GAMES_PER_SET as $value => $label)
                            <option value="{{ $value }}" @selected((int) $val('games_per_set', 6) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div data-show-when-sets>
                    <label class="block text-sm font-medium mb-1">Deciding set</label>
                    <select name="final_set" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        @foreach (\App\Models\Tournament::FINAL_SET as $value => $label)
                            <option value="{{ $value }}" @selected($val('final_set', 'full') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </fieldset>
        </section>

        {{-- ================= 5. Standings rules ================= --}}
        <section data-show-for="standings" class="bg-white rounded-xl shadow p-4 md:p-6 grid gap-4">
            <div>
                <h2 class="font-bold text-lg">Standings rules</h2>
                <p class="text-sm text-gray-500">How group / round robin tables are ranked. You can change these anytime; standings recalculate automatically.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Points for a win</label>
                    <input type="number" name="points_win" min="0" max="20" value="{{ $val('points_win', 3) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Points for a loss</label>
                    <input type="number" name="points_loss" min="0" max="20" value="{{ $val('points_loss', 0) }}"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                    <p class="text-xs text-gray-500 mt-1">A walkover loss always gets 0.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Compare sets &amp; games by</label>
                    <select name="standings_method" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                        @foreach (\App\Models\Tournament::STANDINGS_METHODS as $value => $label)
                            <option value="{{ $value }}" @selected($val('standings_method', 'difference') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer hover:bg-gray-50">
                <input type="hidden" name="use_head_to_head" value="0">
                <input type="checkbox" name="use_head_to_head" value="1" class="mt-1 rounded border-gray-300"
                       @checked(old('use_head_to_head', $tournament->use_head_to_head ?? true))>
                <span>
                    <span class="block text-sm font-medium">Use head-to-head when two players are tied</span>
                    <span class="block text-xs text-gray-500">The player who won their match against the other ranks higher.</span>
                </span>
            </label>

            <div class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600">
                <strong>Tie-break order:</strong> 1. Points &rarr; 2. Head-to-head (two-way ties) &rarr; 3. Sets &rarr; 4. Games &rarr; 5. Seed &rarr; 6. Name.
            </div>
        </section>

        {{-- ================= 6. Poster & registration info ================= --}}
        <section class="bg-white rounded-xl shadow p-4 md:p-6 grid gap-4">
            <h2 class="font-bold text-lg">Poster &amp; registration info</h2>

            @include('admin.partials.image-field', ['model' => $tournament, 'label' => 'Tournament poster / image'])

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium mb-1">Registration information <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea name="registration_info" rows="3" placeholder="Entry fee, how to join, who to contact..."
                              class="w-full rounded-lg border border-gray-300 px-3 py-2">{{ $val('registration_info') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Registration link <span class="text-gray-400 font-normal">(optional)</span></label>
                    <input type="url" name="registration_link" value="{{ $val('registration_link') }}" placeholder="https://forms.gle/..."
                           class="w-full rounded-lg border border-gray-300 px-3 py-2">
                </div>
            </div>
        </section>

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
            <a href="{{ $tournament->exists ? route('admin.tournaments.manage', [$tournament, 'overview']) : route('admin.tournaments.index') }}"
               class="rounded-lg px-4 py-2.5 text-center text-gray-700 hover:bg-gray-100">Cancel</a>
            <button type="submit" data-loading="Saving..." class="rounded-lg bg-green-700 text-white font-semibold px-6 py-2.5 hover:bg-green-800">
                {{ $tournament->exists ? 'Save Changes' : 'Create Tournament' }}
            </button>
        </div>
    </form>

    <script>
        (function () {
            var form = document.getElementById('tournament-form');

            function value(name) {
                var checked = form.querySelector('input[name="' + name + '"]:checked');
                return checked ? checked.value : null;
            }

            function roundName(players) {
                var size = 2;
                while (size < players) size *= 2;
                return size === 2 ? 'Final' : size === 4 ? 'Semifinals' : size === 8 ? 'Quarterfinals' : 'Round of ' + size;
            }

            function sync() {
                var format = value('format');
                var knockout = document.getElementById('knockout_format').value;
                var isDouble = format === 'double_elimination' || (format === 'group_knockout' && knockout === 'double');

                form.querySelectorAll('[data-show-for="group_knockout"]').forEach(function (el) { el.style.display = (format !== 'group_knockout') ? 'none' : ''; });
                form.querySelectorAll('[data-show-for="double"]').forEach(function (el) { el.style.display = (!isDouble) ? 'none' : ''; });
                form.querySelectorAll('[data-show-for="standings"]').forEach(function (el) {
                    el.style.display = (!(format === 'group_knockout' || format === 'round_robin')) ? 'none' : '';
                });
                form.querySelectorAll('[data-show-when-sets]').forEach(function (el) {
                    el.style.display = (parseInt(document.getElementById('best_of').value, 10) === 1) ? 'none' : '';
                });

                var groups = parseInt(document.getElementById('group_count').value, 10) || 0;
                var size = parseInt(document.getElementById('group_size').value, 10) || 0;
                var advance = parseInt(document.getElementById('advance_per_group').value, 10) || 0;
                var summary = document.getElementById('group-summary');
                var qualifiers = groups * advance;

                if (advance > size) {
                    summary.textContent = 'More players cannot advance than there are in a group.';
                    summary.className = 'self-end rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800';
                } else if (groups && size && advance && qualifiers >= 2) {
                    summary.textContent = groups + ' groups x ' + size + ' = room for ' + (groups * size) + ' entries (players or teams). '
                        + qualifiers + ' advance to the ' + roundName(qualifiers) + '.';
                    summary.className = 'self-end rounded-lg bg-white border border-green-200 px-3 py-2 text-sm text-green-900';
                } else {
                    summary.textContent = 'At least 2 players in total must advance.';
                    summary.className = 'self-end rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800';
                }
            }

            form.addEventListener('input', sync);
            form.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection