{{--
    A swimming spot on a river page, in plain words: its upstream spills, level, rain and any flood warning, then what
    its own page adds, so a reader knows the next click gives them more. spot: a SpotSummary.
--}}
@props(['spot'])

@php
    $covered = $spot->spillRecency !== \App\Enums\SpillRecency::NotCovered;
    $warning = $spot->floodWarnings->first();
    $kind = ($spot->isBathingWater ? 'Designated bathing water' : 'Swimming spot').($spot->riverMetres !== null ? ', '.number_format($spot->riverMetres / 1000).' km from the source' : '');
    $facts = array_values(array_filter([
        ! $covered ? null : ($spot->monitorCount > 0
            ? $spot->monitorCount.' storm '.\Illuminate\Support\Str::plural('overflow', $spot->monitorCount).' upstream, '.$spot->spillsLast30DaysCount.' '.\Illuminate\Support\Str::plural('spill', $spot->spillsLast30DaysCount).' in 30 days'
            : 'No monitored storm overflows upstream'),
        $spot->levelValue === null ? ($spot->gaugeLabel === null ? 'No level gauge linked yet' : 'No recent reading from the '.$spot->gaugeLabel.' gauge') : collect([
            number_format($spot->levelValue, 2).' '.$spot->levelUnit.' at the '.$spot->gaugeLabel.' gauge',
            \App\Data\LevelSummary::usualPhrase($spot->levelRelativeToTypical),
            $spot->levelTrend === \App\Enums\LevelTrend::Unknown ? null : strtolower($spot->levelTrend->label()),
            $spot->levelIsStale() ? 'read '.$spot->levelAt->diffForHumans() : null,
        ])->filter()->join(', '),
        $spot->rainfallLast24HoursMillimetres === null ? null : number_format($spot->rainfallLast24HoursMillimetres, 1).' mm of rain in the last 24 hours',
        $warning === null ? null : $warning->severity->label().' in force',
    ]));
@endphp

<a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}"
   class="flex h-full flex-col rounded-lg border border-line bg-surface p-4 transition hover:border-muted hover:shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="font-display font-semibold">{{ $spot->name }}</h3>
            <p class="mt-0.5 text-xs text-muted">{{ $kind }}</p>
        </div>
        <x-spill-badge :recency="$spot->spillRecency" :stale="$spot->overflowDataIsStale()" />
    </div>
    @if ($spot->overflowDataIsStale())
        <p class="mt-2 text-xs font-medium text-warn-text">
            @if ($spot->dataUpdatedAt)
                Monitor data is {{ $spot->dataUpdatedAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }} old
            @else
                Monitor data has no update time
            @endif
        </p>
    @endif
    <ul class="mt-3 grid gap-1 text-sm text-ink-2">
        @foreach ($facts as $fact)
            <li class="flex gap-2"><span class="text-muted" aria-hidden="true">-</span><span>{{ $fact }}</span></li>
        @endforeach
    </ul>
    <p class="mt-auto pt-3 text-xs font-medium text-accent">Level chart, overflows upstream, 30 day spill history, weather and daylight</p>
</a>
