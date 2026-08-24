@props(['since' => null])

@php
$text = $since === null
    ? 'Project status never updated'
    : 'Project status last updated '.$since->diffForHumans();
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800']) }} title="{{ $text }}">
    Stale
</span>
