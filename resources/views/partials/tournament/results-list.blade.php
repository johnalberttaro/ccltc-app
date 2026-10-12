{{-- Completed matches, newest first. Expects $results. --}}
@if ($results->isEmpty())
    <div class="bg-white rounded-xl shadow p-10 text-center">
        <x-icon name="trophy" class="w-10 h-10 mx-auto text-gray-300" />
        <p class="mt-2 font-semibold">No results yet</p>
        <p class="text-sm text-gray-500">Completed matches will appear here.</p>
    </div>
@else
    <div class="grid gap-3 md:grid-cols-2">
        @foreach ($results as $m)
            <article class="bg-white rounded-xl shadow overflow-hidden">
                <div class="flex items-center justify-between px-4 py-2 bg-gray-50 text-xs text-gray-500 border-b border-gray-100">
                    <span><span class="font-bold text-gray-700">{{ $m->code }}</span> &middot; {{ $m->roundName() }}</span>
                    <span>{{ $m->updated_at->format('M j, g:i A') }}</span>
                </div>
                @foreach ([1, 2] as $slot)
                    @php $won = $m->wonBy($slot); @endphp
                    <div class="flex items-center justify-between gap-3 px-4 py-2 {{ $won ? 'bg-green-50' : '' }}">
                        <span class="truncate {{ $won ? 'font-semibold text-green-900' : 'text-gray-500' }}">{{ $m->participant($slot)?->name }}</span>
                        <span class="flex gap-3 tabular-nums">
                            @foreach ($m->setsFor($slot) as $set)
                                <span class="text-lg {{ $set['won'] ? 'font-bold' : 'text-gray-400' }}">{{ $set['games'] }}@if ($set['tb'] !== null)<sup class="text-[10px]">{{ $set['tb'] }}</sup>@endif</span>
                            @endforeach
                        </span>
                    </div>
                @endforeach
                <p class="px-4 py-2 text-sm text-green-800 border-t border-gray-100 flex items-center gap-1.5">
                    <x-icon name="check" class="w-4 h-4" />
                    {{ $m->winner?->name }}
                    @if ($m->is_walkover) wins by walkover @elseif ($m->stage === 'group') wins @elseif (in_array($m->code, ['F', 'GF2'], true)) wins the title @elseif ($m->code === 'GF') wins the grand final @else advances @endif
                    @unless ($m->is_walkover)<span class="text-gray-500">({{ $m->scoreLine() }})</span>@endunless
                </p>
            </article>
        @endforeach
    </div>
@endif