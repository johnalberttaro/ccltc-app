{{-- Expects $action (URL) and optional $label / $confirm --}}
<form method="POST" action="{{ $action }}" onsubmit="return confirm('{{ $confirm ?? 'Delete this? This cannot be undone.' }}')">
    @csrf
    @method('DELETE')
    <button type="submit" class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-sm text-red-700 hover:bg-red-50" title="Delete">
        <x-icon name="trash" class="w-4 h-4" />
        <span>{{ $label ?? 'Delete' }}</span>
    </button>
</form>