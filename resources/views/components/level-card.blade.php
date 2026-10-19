{{-- A gauge's latest level, trend, where it sits in its usual range and 48 hour sparkline. empty: what to say when there is no gauge. --}}
@props(['level', 'empty' => 'No gauge is linked here yet.'])

@php
    $trendText = $level?->trend->label() ?? '';

    if ($level?->changeLast3Hours !== null) {
        $trendText .= sprintf(', %s%s m in 3 hours', $level->changeLast3Hours > 0 ? '+' : '', number_format($level->changeLast3Hours, 2));
    }

    $sparkline = $level?->recentReadings->map(fn ($reading) => [$reading['measured_at']->timestamp, $reading['value']])->values() ?? collect();

    // Where the latest reading sits on the usual range bar: the range itself spans 22% to 70% of the bar.
    $rangePosition = null;

    if ($level?->latestValue !== null && $level->station->typical_range_low !== null && $level->station->typical_range_high > $level->station->typical_range_low) {
        $fraction = ($level->latestValue - $level->station->typical_range_low) / ($level->station->typical_range_high - $level->station->typical_range_low);
        $rangePosition = (int) round(max(2, min(98, 22 + $fraction * 48)));
    }
@endphp

<div class="rounded-lg border border-line bg-surface p-5">
    @if ($level === null)
        <p class="text-sm text-ink-2">{{ $empty }}</p>
    @elseif ($level->latestValue === null)
        <p class="text-sm text-ink-2">No recent readings from the {{ $level->station->label }} gauge.</p>
    @else
        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <p class="font-mono text-4xl font-semibold tabular-nums">{{ number_format($level->latestValue, 2) }} {{ $level->station->unitLabel() }}</p>
            <p class="text-sm text-ink-2">{{ $trendText }} <x-help term="trend" /></p>
        </div>
        @if ($level->relativeToTypical)
            <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-ink-2">
                <span class="shrink-0">{{ ucfirst($level->relativeToUsual()) }} <x-help term="typical_range" /></span>
                @if ($rangePosition !== null)
                    <div class="relative order-last h-2 basis-full rounded-full bg-line-2 sm:order-none sm:flex-1 sm:basis-auto" aria-hidden="true">
                        <div class="absolute inset-y-0 rounded-full bg-water" style="left:22%;right:30%;background:var(--color-line)"></div>
                        <span class="absolute -top-1 h-4 w-4 -translate-x-1/2 rounded-full border-2 border-surface bg-accent" style="left:{{ $rangePosition }}%"></span>
                    </div>
                @endif
                <span class="shrink-0 font-mono text-xs text-muted">{{ number_format($level->station->typical_range_low, 2) }} to {{ number_format($level->station->typical_range_high, 2) }} {{ $level->station->unitLabel() }}</span>
            </div>
        @endif
        @if ($level->station->isAboveOrdnanceDatum())
            <p class="mt-2 text-xs text-muted">This gauge reports height above sea level, not depth above the river bed. <x-help term="maod" /></p>
        @endif
        <div class="mt-4 h-24 text-accent" data-sparkline="{{ json_encode($sparkline) }}"></div>
        <p class="mt-2 text-xs text-muted">
            {{ $level->station->label }} gauge, read {{ $level->latestAt->diffForHumans() }}. 48 hour trend.
            @if ($level->station->agencyPageUrl() !== null)
                <a href="{{ $level->station->agencyPageUrl() }}" rel="noopener" class="text-accent hover:underline">Longer history on {{ $level->station->agencyPageName() }}</a>.
            @endif
        </p>
        @if ($level->isStale())
            <p class="mt-3 rounded-md border border-warn-line bg-warn-bg px-3 py-2 text-sm text-warn-text" role="status">
                No reading from this gauge for {{ $level->latestAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }}.
                The level shown is the last one received, not the current level. <x-help term="stale" />
            </p>
        @endif
    @endif
</div>
