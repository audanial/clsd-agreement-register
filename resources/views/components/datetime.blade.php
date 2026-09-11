{{--
    Renders a timestamp in Malaysian local time, e.g. "9 Sep 2026, 10:24 AM".

    Timestamps are stored in UTC and stay that way — config/app.php deliberately
    remains 'UTC'. Changing APP_TIMEZONE would re-interpret every timestamp already
    written to production and disturb the day-boundary tests. Convert for display
    only, which is what this component is for.

    Use <x-date> when only the day matters; use this when the time of day is part
    of the information, such as an audit trail entry.
--}}
@props(['value' => null])

@php
    $moment = $value instanceof \Carbon\CarbonInterface
        ? $value
        : (filled($value) ? \Carbon\Carbon::parse($value) : null);
@endphp

<span>{{ $moment?->timezone('Asia/Kuala_Lumpur')->format('j M Y, g:i A') ?? '—' }}</span>
