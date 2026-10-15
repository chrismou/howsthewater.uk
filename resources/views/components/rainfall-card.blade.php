{{-- Rainfall totals at the nearest rain gauge. place: how to refer to the place ("this spot"); note: a caveat after the gauge line. --}}
@props(['rainfall', 'place' => 'here', 'note' => ''])

<div class="rounded-lg border border-line bg-surface p-5">
    @if ($rainfall === null)
        <p class="text-sm text-ink-2">No rain gauge within {{ \App\Actions\Rivers\LinkNearestRainfallStations::MAX_DISTANCE_METRES / 1000 }} km of {{ $place }}.</p>
    @elseif (! $rainfall->hasReadings())
        <p class="text-sm text-ink-2">No recent readings from the nearest rain gauge.</p>
    @else
        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <p class="font-mono text-4xl font-semibold tabular-nums">{{ number_format($rainfall->last24HoursMillimetres, 1) }} mm</p>
            <p class="text-sm text-ink-2">in the last 24 hours</p>
        </div>
        <dl class="mt-3 flex gap-x-8 text-sm text-ink-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Last 6 hours</dt>
                <dd class="font-mono font-medium text-ink tabular-nums">{{ number_format($rainfall->last6HoursMillimetres, 1) }} mm</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Last 3 days</dt>
                <dd class="font-mono font-medium text-ink tabular-nums">{{ number_format($rainfall->last72HoursMillimetres, 1) }} mm</dd>
            </div>
        </dl>
        <p class="mt-3 text-xs text-muted">
            Rain gauge {{ number_format($rainfall->distanceMetres / 1000, 1) }} km from {{ $place }}, read {{ $rainfall->latestAt->diffForHumans() }}.
            {{ $note }}
        </p>
        @if ($rainfall->isStale())
            <p class="mt-3 rounded-md border border-warn-line bg-warn-bg px-3 py-2 text-sm text-warn-text" role="status">
                No reading from this rain gauge for {{ $rainfall->latestAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }}.
                Recent rain may be missing from these totals. <x-help term="stale" />
            </p>
        @endif
    @endif
</div>
