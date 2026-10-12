@extends('layouts.admin')

@section('title', 'Members')

@section('content')
    <div class="flex flex-wrap items-end justify-between gap-2 mb-4">
        <div>
            <h1 class="text-2xl font-bold">Members</h1>
            <p class="text-sm text-gray-500">Club members. Their names are suggested when recording a match.</p>
        </div>
        <p class="text-sm text-gray-500">{{ $players->count() }} {{ Str::plural('member', $players->count()) }}</p>
    </div>

    {{-- Add members --}}
    <form method="POST" action="{{ route('admin.players.store') }}" class="bg-white rounded-xl shadow p-4 mb-6 grid gap-3">
        @csrf

        <div>
            <label for="names" class="block text-sm font-medium mb-1">Full name</label>
            <textarea id="names" name="names" rows="2" required
                      class="w-full rounded-lg border border-gray-300 px-3 py-2">{{ old('names') }}</textarea>
            @error('names')
                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
            @enderror
            <p class="text-xs text-gray-500 mt-1">Type the complete name the way it should appear on the games list. To add several at once, put one name per line.</p>
        </div>

        <div>
            <button type="submit" data-loading="Adding..."
                    class="inline-flex items-center gap-2 rounded-lg bg-green-700 text-white font-medium px-4 py-2 hover:bg-green-800">
                <x-icon name="plus" class="w-4 h-4" /> Add Member
            </button>
        </div>
    </form>

    @if ($players->isEmpty())
        <div class="bg-white rounded-xl shadow p-8 text-center text-gray-500">
            No members yet. Add the club members above.
        </div>
    @else
        <div class="mb-3">
            <label for="member-filter" class="block text-sm font-medium mb-1">Find a member</label>
            <input type="search" id="member-filter" autocomplete="off"
                   class="w-full md:w-72 rounded-lg border border-gray-300 px-3 py-2 text-sm">
        </div>

        <div class="bg-white rounded-xl shadow divide-y divide-gray-100" id="member-list">
            @foreach ($players as $player)
                <div class="member-row p-3 md:px-4 flex flex-wrap items-center gap-x-4 gap-y-2" data-name="{{ strtolower($player->full_name) }}">
                    <span class="w-8 text-sm text-gray-400 tabular-nums">{{ $loop->iteration }}</span>
                    <div class="flex-1 min-w-[10rem]">
                        <a href="{{ route('admin.rankings.player', $player) }}" class="font-medium hover:underline" title="Overall record and game history">{{ $player->full_name }}</a>
                        <p class="text-xs text-gray-500">
                            {{ $player->matches_count }} {{ Str::plural('game', $player->matches_count) }}
                            &middot; added {{ $player->created_at->format('M d, Y') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-1">
                        <button type="button" class="rounded-lg px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-100 inline-flex items-center gap-1"
                                data-edit-toggle aria-label="Edit name of {{ $player->full_name }}">
                            <x-icon name="pencil" class="w-4 h-4" /> Edit
                        </button>
                        @php $games = (int) $player->matches_count; @endphp
                        <form method="POST" action="{{ route('admin.players.remove', $player) }}"
                              data-confirm="Remove {{ $player->full_name }} from Members?"
                              data-confirm-body="{{ $games > 0
                                  ? 'They disappear from Members. Their ' . $games . ' recorded ' . Str::plural('game', $games) . ' stay on the scores.'
                                  : 'They disappear from Members.' }}"
                              data-confirm-button="Remove" data-confirm-tone="danger">
                            @csrf
                            <button class="rounded-lg px-2.5 py-1.5 text-sm text-red-700 hover:bg-red-50 inline-flex items-center gap-1">
                                <x-icon name="trash" class="w-4 h-4" /> Remove
                            </button>
                        </form>
                    </div>

                    <form method="POST" action="{{ route('admin.players.update', $player) }}" class="edit-form hidden w-full md:pl-12 flex flex-wrap gap-2">
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" value="{{ $player->full_name }}" required maxlength="100"
                               class="flex-1 min-w-[12rem] rounded-lg border border-gray-300 px-3 py-2">
                        <button class="rounded-lg bg-green-700 text-white text-sm font-medium px-4 py-2 hover:bg-green-800">Save name</button>
                        <button type="button" data-edit-toggle class="rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-100">Cancel</button>
                    </form>
                </div>
            @endforeach
            <p id="member-none" class="hidden p-4 text-sm text-gray-500">No member matches that name.</p>
        </div>

    @endif

    <script>
        (function () {
            document.querySelectorAll('[data-edit-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var form = btn.closest('.member-row').querySelector('.edit-form');
                    form.classList.toggle('hidden');
                    if (!form.classList.contains('hidden')) form.querySelector('input[name=name]').focus();
                });
            });

            var filter = document.getElementById('member-filter');
            if (!filter) return;
            filter.addEventListener('input', function () {
                var q = filter.value.trim().toLowerCase();
                var shown = 0;
                document.querySelectorAll('#member-list .member-row').forEach(function (row) {
                    var match = !q || row.dataset.name.indexOf(q) !== -1;
                    row.style.display = match ? '' : 'none';
                    if (match) shown++;
                });
                document.getElementById('member-none').classList.toggle('hidden', shown > 0);
            });
        })();
    </script>
@endsection