@props(['status'])
@php
    $styles = [
        'draft'               => 'bg-gray-100 text-gray-600 ring-1 ring-inset ring-gray-300',
        'upcoming'            => 'bg-sky-100 text-sky-800',
        'registration_open'   => 'bg-emerald-100 text-emerald-800',
        'registration_closed' => 'bg-orange-100 text-orange-800',
        'ongoing'             => 'bg-green-100 text-green-800',
        'completed'           => 'bg-gray-200 text-gray-700',
        'cancelled'           => 'bg-red-100 text-red-700',
        'scheduled'           => 'bg-sky-50 text-sky-700',
        'in_progress'         => 'bg-amber-100 text-amber-800',
        'published'           => 'bg-green-100 text-green-800',
        'unpublished'         => 'bg-amber-100 text-amber-800',
        'pinned'              => 'bg-yellow-100 text-yellow-800',
        'open'                => 'bg-emerald-100 text-emerald-800',
        'closed'              => 'bg-red-100 text-red-700',
        'qualified'           => 'bg-green-600 text-white',
    ];
    $labels = [
        'registration_open'   => 'Registration Open',
        'registration_closed' => 'Registration Closed',
        'in_progress'         => 'In Progress',
        'open'                => 'Registration open',
        'closed'              => 'Registration closed',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap ' . ($styles[$status] ?? 'bg-gray-100 text-gray-700')]) }}>
    @if (in_array($status, ['ongoing', 'in_progress'], true))
        <span class="w-1.5 h-1.5 rounded-full bg-current animate-pulse"></span>
    @elseif ($status === 'pinned')
        <x-icon name="star" class="w-3 h-3" />
    @elseif ($status === 'qualified')
        <x-icon name="check" class="w-3 h-3" />
    @endif
    {{ $labels[$status] ?? ucfirst($status) }}
</span>