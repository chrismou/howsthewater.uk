{{--
    One of a river's level gauges on its river page: the latest reading, its trend, where it sits in its usual range,
    the highest level on record and a week of readings. spot: the SpotSummary the gauge is linked to; station: the
    GaugeStation itself (its range, record and agency page); points: hourly [timestamp, value] pairs for the chart;
    water: the river as named in the sentence giving the gauge's usual range ("the Teith"), which search engines quote.
--}}
@props(['spot', 'station', 'points' => [], 'water' => null])

@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $unit = $station->unitLabel();
    $trendText = $spot->levelTrend->label();

    if ($spot->levelChangeLast3Hours !== null) {
        $trendText .= sprintf(', %s%s m in 3 hours', $spot->levelChangeLast3Hours > 0 ? '+' : '', number_format($spot->levelChangeLast3Hours, 2));
    }

    // Where the latest reading sits on the usual range bar: the range itself spans 22% to 70% of the bar.
    $rangePosition = null;
    $hasRange = $station->typical_range_low !== null && $station->typical_range_high > $station->typical_range_low;

    if ($spot->levelValue !== null && $hasRange) {
        $fraction = ($spot->levelValue - $station->typical_range_low) / ($station->typical_range_high - $station->typical_range_low);
        $rangePosition = (int) round(max(2, min(98, 22 + $fraction * 48)));
    }

    $readAt = $spot->levelAt?->copy()->timezone($timezone);
    $recordPhrase = $station->recordLevelPhrase();
    $recordsFrom = $station->records_from === null ? '' : ' (records from '.$station->records_from->year.')';
    $readLine = 'Read '.$spot->levelAt?->diffForHumans().($readAt === null ? '' : ', '.$readAt->format($readAt->isSameDay(now($timezone)) ? 'H:i T' : 'H:i T, j M')).'.';
    // How long the chart covers, in days once it spans two, else in hours, so a new gauge never says "1 days".
    $spanHours = count($points) > 1 ? ($points[array_key_last($points)][0] - $points[0][0]) / 3600 : 0;
    $spanLabel = $spanHours >= 48 ? round($spanHours / 24).' days' : max(1, (int) round($spanHours)).' '.\Illuminate\Support\Str::plural('hour', max(1, (int) round($spanHours)));

    // The usual range in words, saying what each agency's range means, as components/level-card does.
    $usualRange = null;

    if ($hasRange) {
        $usualRange = collect([
            ($water !== null ? ucfirst($water).' at the ' : 'The ').$station->label.' gauge is usually between '
                .number_format($station->typical_range_low, 2).' '.$unit.' and '.number_format($station->typical_range_high, 2).' '.$unit.'.',
            match ($station->network) {
                \App\Enums\Agency::EnvironmentAgency => 'Nine in ten of the Environment Agency\'s readings there fall inside that band.',
                \App\Enums\Agency::NaturalResourcesWales => 'Nine in ten of its readings over the past year fall inside that band.',
                \App\Enums\Agency::Sepa => 'That is SEPA\'s normal range, from the low of a typical year to its high.',
            },
        ])->join(' ');
    }
@endphp

<article class="rounded-lg border border-line bg-surface p-5">
    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
        <h3 class="font-display text-lg font-semibold">{{ $station->label }} gauge</h3>
        <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}" class="text-sm font-medium text-accent hover:underline">{{ $spot->name }}</a>
    </div>

    @if ($spot->levelValue === null)
        <p class="mt-3 text-sm text-ink-2">No recent readings from this gauge.</p>
    @else
        <div class="mt-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <p class="font-mono text-3xl font-semibold tabular-nums">{{ number_format($spot->levelValue, 2) }} {{ $unit }}</p>
            <p class="text-sm text-ink-2">{{ $trendText }}</p>
        </div>

        @if ($spot->levelRelativeToTypical)
            <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-ink-2">
                <span class="shrink-0 font-medium text-ink">{{ ucfirst(\App\Data\LevelSummary::usualPhrase($spot->levelRelativeToTypical)) }}</span>
                @if ($rangePosition !== null)
                    <div class="relative order-last h-2 basis-full rounded-full bg-line-2 sm:order-none sm:flex-1 sm:basis-auto" aria-hidden="true">
                        <div class="absolute inset-y-0 rounded-full" style="left:22%;right:30%;background:var(--color-line)"></div>
                        <span class="absolute -top-1 h-4 w-4 -translate-x-1/2 rounded-full border-2 border-surface bg-accent" style="left:{{ $rangePosition }}%"></span>
                    </div>
                @endif
                @if ($hasRange)
                    <span class="shrink-0 font-mono text-xs text-muted">usually {{ number_format($station->typical_range_low, 2) }} to {{ number_format($station->typical_range_high, 2) }} {{ $unit }}</span>
                @endif
            </div>
        @endif

        @if ($usualRange !== null)
            <p class="mt-3 text-sm text-ink-2">{{ $usualRange }}</p>
        @endif

        @if ($station->isAboveOrdnanceDatum())
            <p class="mt-2 text-xs text-muted">This gauge reports height above sea level, not depth above the river bed. <x-help term="maod" /></p>
        @endif

        @if (count($points) > 1)
            <div class="mt-4 h-20 text-accent" data-sparkline="{{ json_encode($points) }}" data-sparkline-label="{{ $station->label }} gauge level over the last {{ $spanLabel }}"></div>
        @endif

        <p class="mt-2 text-xs text-muted">
            {{ $readLine }}
            @if (count($points) > 1)
                Trend over the last {{ $spanLabel }}.
            @endif
            @if ($station->agencyPageUrl() !== null)
                <a href="{{ $station->agencyPageUrl() }}" rel="noopener" class="text-accent hover:underline">Longer history on {{ $station->agencyPageName() }}</a>.
            @endif
        </p>

        @if ($spot->levelIsStale())
            <p class="mt-3 rounded-md border border-warn-line bg-warn-bg px-3 py-2 text-sm text-warn-text" role="status">
                No reading from this gauge for {{ $spot->levelAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }}.
                The level shown is the last one received, not the current level. <x-help term="stale" />
            </p>
        @endif
    @endif

    {{-- The record is the agency's figure, a guide to how high the river has gone here, never a threshold. It shows
         with or without a current reading. --}}
    @if ($recordPhrase !== null)
        <p class="mt-3 text-sm text-ink-2">
            Highest on record <span class="font-medium text-ink">{{ $recordPhrase }}</span><span class="text-muted">{{ $recordsFrom }}</span>.
            <x-help term="record_level" />
        </p>
    @endif
</article>
