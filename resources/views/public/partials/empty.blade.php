<div class="bg-white rounded-2xl shadow p-10 text-center">
    <x-icon :name="$icon" class="w-12 h-12 mx-auto text-gray-300" />
    <p class="mt-3 text-lg font-semibold">{{ $message }}</p>
    @if (request()->hasAny(['q', 'status', 'type']))
        <p class="text-sm text-gray-500 mt-1">Try clearing your search or filters.</p>
    @else
        <p class="text-sm text-gray-500 mt-1">Please check back soon.</p>
    @endif
</div>