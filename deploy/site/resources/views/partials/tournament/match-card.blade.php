{{-- One bracket match. Expects $m (TournamentMatch), $editable (bool). --}}
@php
    $playable = $m->isPlayable();
    $clickable = $editable && $playable;
    $void = $m->isVoid();
@endphp

<div @if ($playable && ! $m->winner_id) data-pending @endif class="relative {{ $cardWidth ?? 'w-60' }} rounded-lg border bg-white shadow-sm overflow-hidden text-sm
    {{ $m->is_bye ? 'opacity-55 border-dashed' : 'border-gray-200' }}
    {{ $clickable && ! $m->winner_id ? 'ring-1 ring-green-600/40' : '' }}">

    <div class="flex items-center justify-between gap-2 px-2.5 py-1 bg-gray-50 text-[11px] text-gray-500 border-b border-gray-100">
        <span class="font-semibold text-gray-700">{{ $m->code }}</span>
        <span class="truncate">
            @if ($m->status === 'in_progress')
                <span class="text-amber-700 font-semibold">Live</span>
            @elseif ($m->is_bye)
                Bye
            @elseif ($m->scheduled_at && ! $m->winner_id)
                {{ $m->scheduled_at->format('M j, g:i A') }}@if ($m->court) &middot; {{ $m->court }}@endif
            @elseif ($m->is_walkover)
                Walkover
            @endif
        </span>
    </div>

    @foreach ([1, 2] as $slot)
        @php
            $p = $m->participant($slot);
            $won = $m->wonBy($slot);
            $lost = $m->winner_id && ! $won && ! $m->is_bye;
        @endphp
        <div class="flex items-center justify-between gap-2 px-2.5 h-9 {{ $slot === 1 ? 'border-b border-gray-100' : '' }}
            {{ $won && ! $m->is_bye ? 'bg-green-50' : '' }}">
            <span class="truncate {{ $won ? 'font-semibold text-green-900' : ($lost ? 'text-gray-400' : '') }}" @if ($p) title="{{ $p->name }}" @endif>
                @if ($p)
                    @php $from = $m->{"participant{$slot}_source"}; @endphp
                    @if ($from && preg_match('/^(?:Group )?(\S+) #(\d+)$/', $from, $g))
                        <span class="rounded bg-gray-100 text-gray-600 text-[10px] font-bold px-1 mr-0.5" title="{{ $from }}">{{ $g[1] }}{{ $g[2] }}</span>
                    @elseif ($p->seed)
                        <span class="text-[10px] text-gray-400 mr-0.5">{{ $p->seed }}</span>
                    @endif
                    {{ $p->name }}
                @elseif ($void || ($m->is_bye && ! $p))
                    <span class="italic text-gray-400">&mdash;</span>
                @else
                    <span class="italic text-gray-400 text-xs">{{ $m->slotLabel($slot) }}</span>
                @endif
            </span>
            <span class="flex items-center gap-1.5 shrink-0 tabular-nums">
                @foreach ($m->setsFor($slot) as $set)
                    <span class="{{ $set['won'] ? 'font-bold text-gray-900' : 'text-gray-400' }} {{ $set['mtb'] ? 'text-xs' : '' }}">
                        {{ $set['games'] }}@if ($set['tb'] !== null)<sup class="text-[9px]">{{ $set['tb'] }}</sup>@endif
                    </span>
                @endforeach
                @if ($won && ! $m->is_bye)
                    <x-icon name="check" class="w-4 h-4 text-green-600" />
                @endif
            </span>
        </div>
    @endforeach

    @if ($clickable)
        <button type="button" data-result='@json(\App\Support\ResultDialog::payload($m))'
                class="absolute inset-0 w-full h-full hover:bg-green-600/5 focus:outline-none focus:ring-2 focus:ring-green-600 rounded-lg"
                aria-label="{{ $m->winner_id ? 'Edit result' : 'Record result' }} for {{ $m->code }}"></button>
    @endif
</div>