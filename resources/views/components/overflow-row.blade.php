@props(['summary', 'spills'])

@php
    $status = $summary->status;
    $event = $summary->latestEvent;
    $spilledRecently = $status !== \App\Enums\OverflowStatus::Discharging && $summary->spilledSince(now()->subHours(48));
@endphp

{{-- On phones only the monitor and status columns show; the rest folds into a second line under each. --}}
<tr>
    <td class="px-4 py-2.5">
        <x-overflow-link :monitor="$summary->monitor" />
        <span class="block text-xs text-muted sm:hidden">{{ $summary->river->name }} · <span class="whitespace-nowrap">{{ number_format($summary->distanceUpstreamMetres / 1000, 1) }} km</span></span>
    </td>
    <td class="hidden whitespace-nowrap px-4 py-2.5 text-ink-2 sm:table-cell">{{ $summary->river->name }}</td>
    <td class="hidden whitespace-nowrap px-4 py-2.5 font-mono tabular-nums sm:table-cell">{{ number_format($summary->distanceUpstreamMetres / 1000, 1) }} km</td>
    <td class="px-4 py-2.5">
        <x-overflow-status :monitor="$summary->monitor" :spilled-recently="$spilledRecently" />
        <span class="block text-xs text-muted sm:hidden">
            @if ($event)
                Last spill started {{ $event->started_at->diffForHumans() }}, {{ $event->isOngoing() ? 'ongoing' : 'lasted '.$event->durationLabel() }}
            @else
                No spill recorded
            @endif
            · {{ $spills }} in 30 days
        </span>
    </td>
    <td class="hidden whitespace-nowrap px-4 py-2.5 text-ink-2 sm:table-cell">{{ $event ? $event->started_at->diffForHumans() : 'None recorded' }}</td>
    <td class="hidden whitespace-nowrap px-4 py-2.5 font-mono tabular-nums sm:table-cell">
        @if ($event === null)
            <span class="text-muted">-</span>
        @elseif ($event->isOngoing())
            <span class="font-medium text-alert">ongoing</span>
        @else
            {{ $event->durationLabel() }}
        @endif
    </td>
    <td class="hidden whitespace-nowrap px-4 py-2.5 font-mono tabular-nums sm:table-cell">{{ $spills }}</td>
</tr>
