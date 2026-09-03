@php
    $start = $agreement->agreement_date;
    $end = $agreement->expiry_date;
    $duration = $agreement->durationLabel();
@endphp

@if ($start === null && $end === null)
    <div>—</div>
@else
    <div>{{ $start === null ? '—' : \Carbon\Carbon::parse($start)->format('d M Y') }} – {{ $end === null ? 'Indefinite' : \Carbon\Carbon::parse($end)->format('d M Y') }}</div>

    @if ($duration !== null)
        <div class="text-xs text-gray-500">({{ $duration }})</div>
    @endif
@endif
