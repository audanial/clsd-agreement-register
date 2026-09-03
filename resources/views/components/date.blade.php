@props(['value' => null])

@php
    $date = $value instanceof \Carbon\CarbonInterface
        ? $value
        : (filled($value) ? \Carbon\Carbon::parse($value) : null);
@endphp

<span>{{ $date?->format('d M Y') ?? '—' }}</span>
