@props(['summary', 'spills'])

@php
    $status = $summary->status;
    $event = $summary->latestEvent;
    $spilledRecently = $status !== \App\Enums\OverflowStatus::Discharging && $summary->spilledSince(now()->subHours(48));
@endphp

<tr>
    <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs">{{ $summary->monitor->external_id }}</td>
    <td class="whitespace-nowrap px-4 py-2.5 text-ink-2">{{ $summary->river->name }}</td>
    <td class="whitespace-nowrap px-4 py-2.5 font-mono tabular-nums">{{ number_format($summary->distanceUpstreamMetres / 1000, 1) }} km</td>
    <td class="whitespace-nowrap px-4 py-2.5">
        @if ($status === \App\Enums\OverflowStatus::Discharging)
            <span class="font-medium text-alert">Discharging now</span>
        @elseif ($status === \App\Enums\OverflowStatus::Offline)
            <span class="text-muted">Monitor offline</span>
        @elseif ($spilledRecently)
            <span class="font-medium text-pill-amber-text">Spilled in the last 48h</span>
        @else
            <span class="text-ink-2">Not discharging</span>
        @endif
    </td>
    <td class="whitespace-nowrap px-4 py-2.5 text-ink-2">{{ $event ? $event->started_at->diffForHumans() : 'None recorded' }}</td>
    <td class="whitespace-nowrap px-4 py-2.5 font-mono tabular-nums">
        @if ($event === null)
            <span class="text-muted">-</span>
        @elseif ($event->isOngoing())
            <span class="font-medium text-alert">ongoing</span>
        @else
            {{ $event->durationMinutes() }} min
        @endif
    </td>
    <td class="whitespace-nowrap px-4 py-2.5 font-mono tabular-nums">{{ $spills }}</td>
</tr>
