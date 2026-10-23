{{-- A spot on a river page. spot: a SpotSummary. --}}
@props(['spot'])

<a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}"
   class="block rounded-lg border border-line bg-surface p-4 transition hover:border-muted hover:shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="font-display font-semibold">{{ $spot->name }}</h3>
            <p class="mt-0.5 text-xs text-muted">{{ $spot->riverName }}</p>
        </div>
        <x-spill-badge :recency="$spot->spillRecency" :stale="$spot->overflowDataIsStale()" />
    </div>
    <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-ink-2">
        @if ($spot->overflowDataIsStale())
            <div class="basis-full">
                <dt class="sr-only">Data freshness</dt>
                <dd class="font-medium text-warn-text">
                    @if ($spot->dataUpdatedAt)
                        Monitor data is {{ $spot->dataUpdatedAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }} old
                    @else
                        Monitor data has no update time
                    @endif
                </dd>
            </div>
        @endif
        {{-- Counts would read as "no spills" where we do not read the water company's feed, so the badge says it all there. --}}
        @if ($spot->spillRecency !== \App\Enums\SpillRecency::NotCovered)
            <div>
                <dt class="sr-only">Upstream monitors</dt>
                <dd>{{ $spot->monitorCount }} upstream {{ \Illuminate\Support\Str::plural('monitor', $spot->monitorCount) }}</dd>
            </div>
            @if ($spot->monitorCount > 0)
                <div>
                    <dt class="sr-only">Spills in last 30 days</dt>
                    <dd>{{ $spot->spillsLast30DaysCount }} {{ \Illuminate\Support\Str::plural('spill', $spot->spillsLast30DaysCount) }} in 30 days</dd>
                </div>
            @endif
        @endif
        <div>
            <dt class="sr-only">River level</dt>
            <dd>
                @if ($spot->levelValue === null)
                    <span class="text-xs text-muted">No level data</span>
                @else
                    <span class="inline-flex items-center gap-1 text-xs text-ink-2">
                        <span class="font-medium text-ink">{{ number_format($spot->levelValue, 2) }} {{ $spot->levelUnit }}</span>
                        <span aria-hidden="true">{!! match ($spot->levelTrend) { \App\Enums\LevelTrend::Rising => '&uarr;', \App\Enums\LevelTrend::Falling => '&darr;', default => '&rarr;' } !!}</span>
                        <span>{{ strtolower($spot->levelTrend->label()) }}</span>
                        @if (\App\Data\LevelSummary::usualPhrase($spot->levelRelativeToTypical))
                            <span class="text-faint">&middot;</span>
                            <span>{{ \App\Data\LevelSummary::usualPhrase($spot->levelRelativeToTypical) }}</span>
                        @endif
                        @if ($spot->levelIsStale())
                            <span class="text-faint">&middot;</span>
                            <span class="font-medium text-warn-text">read {{ $spot->levelAt->diffForHumans() }}</span>
                        @endif
                    </span>
                @endif
            </dd>
        </div>
        @if ($spot->rainfallLast24HoursMillimetres !== null)
            <div>
                <dt class="sr-only">Rainfall in last 24 hours</dt>
                <dd>{{ number_format($spot->rainfallLast24HoursMillimetres, 1) }} mm rain in 24h</dd>
            </div>
        @endif
    </dl>
</a>
