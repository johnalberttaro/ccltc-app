{{-- One group's standings. Expects $table (from App\Services\Standings) and $tournament. --}}
@php
    $advance = $tournament->usesGroups() ? (int) $tournament->advance_per_group : 0;
    $pct = $tournament->standings_method === 'percentage';
@endphp
<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="text-xs uppercase text-gray-500 bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left w-10">#</th>
                <th class="px-3 py-2 text-left">{{ ucfirst($tournament->entryWord(false)) }}</th>
                <th class="px-2 py-2 text-center" title="Played">P</th>
                <th class="px-2 py-2 text-center" title="Wins">W</th>
                <th class="px-2 py-2 text-center" title="Losses">L</th>
                <th class="px-2 py-2 text-center hidden sm:table-cell" title="Sets won">SW</th>
                <th class="px-2 py-2 text-center hidden sm:table-cell" title="Sets lost">SL</th>
                <th class="px-2 py-2 text-center hidden md:table-cell" title="Games won">GW</th>
                <th class="px-2 py-2 text-center hidden md:table-cell" title="Games lost">GL</th>
                <th class="px-2 py-2 text-center sm:hidden" title="Games won minus lost">+/-</th>
                <th class="px-3 py-2 text-center" title="Points">Pts</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($table['rows'] as $row)
                @php $inZone = $advance && $row['rank'] <= $advance; @endphp
                <tr class="border-t {{ $row['qualified'] ? 'bg-green-50' : '' }}">
                    <td class="px-3 py-2">
                        <span class="inline-flex w-6 h-6 rounded-full items-center justify-center text-xs font-bold
                            {{ $row['qualified'] ? 'bg-green-600 text-white' : ($inZone ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600') }}">{{ $row['rank'] }}</span>
                    </td>
                    <td class="px-3 py-2">
                        <span class="font-medium">{{ $row['participant']->name }}</span>
                        @if ($row['qualified'])
                            <x-status-badge status="qualified" class="ml-1 align-middle" />
                        @endif
                        @if ($row['tie_note'])
                            <span class="block text-[11px] text-gray-500">{{ $row['tie_note'] }}</span>
                        @endif
                    </td>
                    <td class="px-2 py-2 text-center">{{ $row['played'] }}</td>
                    <td class="px-2 py-2 text-center text-green-700 font-semibold">{{ $row['wins'] }}</td>
                    <td class="px-2 py-2 text-center text-gray-500">{{ $row['losses'] }}</td>
                    <td class="px-2 py-2 text-center hidden sm:table-cell">{{ $row['sets_won'] }}</td>
                    <td class="px-2 py-2 text-center hidden sm:table-cell">{{ $row['sets_lost'] }}</td>
                    <td class="px-2 py-2 text-center hidden md:table-cell">{{ $row['games_won'] }}</td>
                    <td class="px-2 py-2 text-center hidden md:table-cell">{{ $row['games_lost'] }}</td>
                    @php $diff = $row['games_won'] - $row['games_lost']; @endphp
                    <td class="px-2 py-2 text-center sm:hidden">{{ $diff > 0 ? '+' : '' }}{{ $diff }}</td>
                    <td class="px-3 py-2 text-center font-bold">{{ $row['points'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>