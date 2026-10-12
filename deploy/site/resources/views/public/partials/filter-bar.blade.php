{{-- Search + status filter for public lists. Expects $action, $placeholder, $statuses; optional $types --}}
<form method="GET" action="{{ $action }}" class="bg-white rounded-xl shadow p-3 mb-6 grid gap-2 sm:flex">
    <div class="relative flex-1">
        <x-icon name="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" />
        <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}"
               class="w-full rounded-lg border border-gray-300 pl-9 pr-3 py-2">
    </div>

    @isset($types)
        <select name="type" class="rounded-lg border border-gray-300 px-3 py-2" onchange="this.form.submit()">
            <option value="">Singles &amp; Doubles</option>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    @endisset

    @isset($statuses)
        <select name="status" class="rounded-lg border border-gray-300 px-3 py-2" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    @endisset

    <button class="rounded-lg bg-green-700 text-white font-medium px-4 py-2 hover:bg-green-800">Search</button>
    @if (request()->hasAny(['q', 'status', 'type']))
        <a href="{{ $action }}" class="rounded-lg px-4 py-2 text-center text-gray-600 hover:bg-gray-100">Clear</a>
    @endif
</form>