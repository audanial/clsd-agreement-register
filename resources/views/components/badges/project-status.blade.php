@props(['status'])

@php
$classes = match ($status) {
    'not_started' => 'bg-gray-100 text-gray-800',
    'ongoing' => 'bg-blue-100 text-blue-800',
    'stalled' => 'bg-red-100 text-red-800',
    'completed' => 'bg-emerald-100 text-emerald-800',
    default => 'bg-gray-100 text-gray-800',
};

$labels = [
    'not_started' => 'Not Started',
    'ongoing' => 'Ongoing',
    'stalled' => 'Stalled',
    'completed' => 'Completed',
];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {$classes}"]) }}>
    {{ $labels[$status] ?? ucfirst(str_replace('_', ' ', $status)) }}
</span>
