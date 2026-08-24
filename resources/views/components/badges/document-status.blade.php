@props(['status'])

@php
$classes = match ($status) {
    'pending' => 'bg-amber-100 text-amber-800',
    'awaiting_partner' => 'bg-blue-100 text-blue-800',
    'signed' => 'bg-emerald-100 text-emerald-800',
    default => 'bg-gray-100 text-gray-800',
};

$labels = [
    'pending' => 'Pending',
    'awaiting_partner' => 'Awaiting Partner',
    'signed' => 'Signed',
];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {$classes}"]) }}>
    {{ $labels[$status] ?? ucfirst(str_replace('_', ' ', $status)) }}
</span>
