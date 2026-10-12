{{-- Knockout bracket. Expects $bracket (App\Support\BracketView) and $editable (bool). --}}
@php
    $slotRem = 7;   // height of one match slot in the busiest column
    $cardWidth = $bracket->tournament->match_type === 'singles' ? 'w-60' : 'w-72';   // doubles names are longer
@endphp

@if ($bracket->champion)
    <div class="mb-5 rounded-2xl bg-gradient-to-r from-amber-400 to-yellow-300 text-amber-950 p-4 md:p-5 flex items-center gap-4 shadow">
        <span class="w-12 h-12 rounded-full bg-white/60 flex items-center justify-center shrink-0"><x-icon name="trophy" class="w-7 h-7" /></span>
        <div>
            <p class="text-xs font-bold uppercase tracking-wide">Champion</p>
            <p class="text-xl md:text-2xl font-extrabold">{{ $bracket->champion->name }}</p>
        </div>
    </div>
@endif

@foreach ($bracket->sections as $sIndex => $section)
    @php
        $columns = $section['columns'];
        $densest = max(1, collect($columns)->map(fn ($c) => $c['matches']->count())->max());
        $height = $densest * $slotRem;
        $mobileId = 'bracket-' . $sIndex;
    @endphp

    <section class="mb-8">
        @if ($section['title'])
            <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">{{ $section['title'] }}</h3>
        @endif

        {{-- ===== Desktop / tablet: full bracket with connecting lines ===== --}}
        <div class="hidden md:block overflow-x-auto pb-2">
            <div class="flex gap-8 min-w-max">
                @foreach ($columns as $c => $column)
                    @php
                        $count = $column['matches']->count();
                        $next = $columns[$c + 1] ?? null;
                        $halves = $next && $next['matches']->count() * 2 === $count;
                        $straight = $next && $next['matches']->count() === $count;
                        $prev = $columns[$c - 1] ?? null;
                    @endphp
                    <div class="flex flex-col">
                        <p class="text-xs font-bold uppercase tracking-wide text-center mb-2 {{ $loop->last && $section['champion'] ? 'text-amber-700' : 'text-gray-500' }}">{{ $column['name'] }}</p>
                        <div class="flex flex-col" style="height: {{ $height }}rem">
                            @foreach ($column['matches']->chunk($halves ? 2 : 1) as $chunk)
                                <div class="relative flex-1 flex flex-col">
                                    @if ($halves && $chunk->count() === 2)
                                        {{-- vertical line joining the pair --}}
                                        <span class="absolute -right-4 top-1/4 bottom-1/4 border-r-2 border-gray-300"></span>
                                    @endif
                                    @foreach ($chunk as $m)
                                        <div class="relative flex-1 flex items-center">
                                            @if ($prev)
                                                <span class="absolute -left-4 w-4 top-1/2 border-t-2 border-gray-300"></span>
                                            @endif
                                            @if ($halves || $straight || (! $next && $section['champion']))
                                                <span class="absolute -right-4 w-4 top-1/2 border-t-2 border-gray-300"></span>
                                            @endif
                                            @include('partials.tournament.match-card', ['m' => $m, 'cardWidth' => $cardWidth])
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if ($section['champion'])
                    <div class="flex flex-col">
                        <p class="text-xs font-bold uppercase tracking-wide text-center mb-2 text-amber-700">Champion</p>
                        <div class="flex items-center" style="height: {{ $height }}rem">
                            <div class="relative w-48 rounded-lg border-2 {{ $bracket->champion ? 'border-amber-400 bg-amber-50' : 'border-dashed border-gray-300 bg-white' }} px-3 py-3 text-center">
                                <span class="absolute -left-4 w-4 top-1/2 border-t-2 border-gray-300"></span>
                                <x-icon name="trophy" class="w-6 h-6 mx-auto {{ $bracket->champion ? 'text-amber-600' : 'text-gray-300' }}" />
                                <p class="mt-1 font-bold text-sm {{ $bracket->champion ? 'text-amber-900' : 'text-gray-400 italic font-normal' }}">
                                    {{ $bracket->champion?->name ?? 'To be decided' }}
                                </p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ===== Phone: one round at a time ===== --}}
        <div class="md:hidden" data-round-switcher>
            <div class="flex gap-2 overflow-x-auto pb-2 mb-2">
                @foreach ($columns as $c => $column)
                    <button type="button" data-round-tab="{{ $c }}"
                            class="shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold bg-white border-gray-300 text-gray-700">
                        {{ $column['name'] }}
                    </button>
                @endforeach
            </div>
            @foreach ($columns as $c => $column)
                <div data-round-panel="{{ $c }}" class="grid gap-3 justify-items-center">
                    @foreach ($column['matches'] as $m)
                        @unless ($m->isVoid())
                            @include('partials.tournament.match-card', ['m' => $m, 'cardWidth' => $cardWidth])
                        @endunless
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>
@endforeach

@once
    <script>
        document.querySelectorAll('[data-round-switcher]').forEach(function (box) {
            var tabs = box.querySelectorAll('[data-round-tab]');
            var panels = box.querySelectorAll('[data-round-panel]');
            function show(i) {
                tabs.forEach(function (t) {
                    var on = t.dataset.roundTab === i;
                    t.classList.toggle('bg-green-700', on); t.classList.toggle('text-white', on);
                    t.classList.toggle('border-green-700', on); t.classList.toggle('bg-white', !on);
                });
                panels.forEach(function (p) { p.style.display = p.dataset.roundPanel === i ? '' : 'none'; });
            }
            tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.roundTab); }); });
            // Start on the first round that still has matches to play
            var start = panels.length ? panels[panels.length - 1].dataset.roundPanel : '0';
            for (var i = 0; i < panels.length; i++) {
                if (panels[i].querySelector('[data-pending]')) { start = panels[i].dataset.roundPanel; break; }
            }
            show(start);
        });
    </script>
@endonce