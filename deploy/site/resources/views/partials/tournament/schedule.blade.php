{{-- Public match list grouped by round. Expects $matches. --}}
@php
    $list = $matches->reject(fn ($m) => $m->is_bye || ($m->code === 'GF2' && $m->status === 'cancelled'));
    $rounds = $list->groupBy(fn ($m) => $m->stage === 'group' ? ($m->group?->label() ?? 'Group') : $m->roundName());
@endphp

@forelse ($rounds as $heading => $roundMatches)
    <section class="mb-6">
        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-2">{{ $heading }}{{ Str::endsWith($heading, ['Semifinal', 'Quarterfinal']) ? 's' : '' }}</h3>
        <div class="grid gap-3 md:grid-cols-2">
            @foreach ($roundMatches as $m)
                <article class="bg-white rounded-xl shadow overflow-hidden">
                    <div class="flex items-center justify-between gap-2 px-4 py-2 bg-gray-50 text-xs text-gray-500 border-b border-gray-100">
                        <span class="font-bold text-gray-700">{{ $m->code }}</span>
                        <span class="flex items-center gap-2">
                            @if ($m->scheduled_at){{ $m->scheduled_at->format('D, M j, g:i A') }}@endif
                            @if ($m->court) &middot; {{ $m->court }}@endif
                            <x-status-badge :status="$m->status" />
                        </span>
                    </div>
                    @foreach ([1, 2] as $slot)
                        @php $p = $m->participant($slot); $won = $m->wonBy($slot); @endphp
                        <div class="flex items-center justify-between gap-3 px-4 py-2 {{ $won ? 'bg-green-50' : '' }}">
                            <span class="truncate {{ $won ? 'font-semibold text-green-900' : ($m->winner_id ? 'text-gray-400' : '') }}">
                                @if ($p) {{ $p->name }} @else <span class="italic text-gray-400 text-sm">{{ $m->slotLabel($slot) }}</span> @endif
                            </span>
                            <span class="flex gap-3 tabular-nums">
                                @foreach ($m->setsFor($slot) as $set)
                                    <span class="{{ $set['won'] ? 'font-bold' : 'text-gray-400' }}">{{ $set['games'] }}@if ($set['tb'] !== null)<sup class="text-[10px]">{{ $set['tb'] }}</sup>@endif</span>
                                @endforeach
                                @if ($won)<x-icon name="check" class="w-4 h-4 text-green-600" />@endif
                            </span>
                        </div>
                    @endforeach
                </article>
            @endforeach
        </div>
    </section>
@empty
    <p class="bg-white rounded-xl shadow p-8 text-center text-gray-500">No matches yet.</p>
@endforelse