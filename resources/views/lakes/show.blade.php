@php
    $lake = $conditions->lake;
    $level = $conditions->level;
    $overflows = $conditions->overflows;

    $mapMarkers = collect([[
        'lat' => $lake->latitude,
        'lng' => $lake->longitude,
        'label' => $lake->name,
        'kind' => 'place',
        'recency' => $overflows->spillRecency->onMap($overflows->isStale()),
    ]])->concat($conditions->bathingWaters->map(fn ($summary) => [
        'lat' => $summary->bathingWater->latitude,
        'lng' => $summary->bathingWater->longitude,
        'label' => $summary->bathingWater->name.' ('.($summary->bathingWater->classification?->value ?? 'not classified').')',
        'kind' => 'place',
    ]))->concat($overflows->monitors->map(fn ($monitor) => [
        'lat' => $monitor->monitor->latitude,
        'lng' => $monitor->monitor->longitude,
        'label' => ($monitor->monitor->displayName() ?? $monitor->monitor->external_id).', '.number_format($monitor->distanceMetres / 1000, 1).' km ('.$monitor->status->label().')',
        'url' => route('overflows.show', $monitor->monitor),
        'kind' => 'monitor',
        'recency' => $monitor->spillRecency()->value,
    ]))->values();

    // The page's trail: the visible breadcrumb and the BreadcrumbList are both built from it.
    $breadcrumbs = [['All lakes', route('hubs.lakes')], [$region->name, route('regions.show', $region)], ['Lakes', route('lakes.index', $region)], [$lake->name, route('lakes.show', [$region, $lake])]];
@endphp

<x-layouts.app :share-image="$shareImage" :date-modified="collect([$overflows->dataUpdatedAt, $conditions->level?->latestAt])->filter()->max()" :region="$region" :feed="route('lakes.feed', [$region, $lake])" :title="$lake->name.': swimming, water quality and lake level'"
               :description="'Bathing water results, lake level, rainfall and storm overflows nearby for swimming and paddling at '.$lake->name.'.'"
               :breadcrumbs="$breadcrumbs"
               :structured-data="app(\App\Services\StructuredData::class)->lake($lake, $region)">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <x-breadcrumbs :items="$breadcrumbs" />
            <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $lake->name }}</h1>
            @if ($lake->description)
                <p class="mt-1 text-ink-2">{{ $lake->description }}</p>
            @endif
            <x-place-summary class="mt-3" :headline="app(\App\Services\Conditions\PlaceHeadline::class)->forLake($conditions, $weather)" :region="$region" />
        </div>
        <x-place-actions :latitude="$lake->latitude" :longitude="$lake->longitude" :label="$lake->name" :feed="route('lakes.feed', [$region, $lake])" />
    </div>

    <section class="mb-8 rounded-lg border border-line bg-surface p-5">
        <div class="flex flex-wrap items-center gap-2">
            <x-spill-badge :recency="$overflows->spillRecency" :stale="$overflows->isStale()" nearby class="px-3 py-1 text-sm" />
            @foreach ($conditions->bathingWaters->filter(fn ($summary) => $summary->currentRisk() === \App\Enums\PollutionRisk::Increased) as $summary)
                <x-bathing-forecast :summary="$summary" class="px-3 py-1 text-sm" />
            @endforeach
        </div>

        @if ($overflows->isStale())
            <p class="mt-4 rounded-md border border-warn-line bg-warn-bg px-4 py-3 text-sm text-warn-text" role="status">Overflow monitor data is out of date, so the spill status describes the data as it was, not now. <x-help term="stale" /></p>
        @endif

        <dl class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Lake level <x-help term="level" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums">{{ $level?->latestValue !== null ? number_format($level->latestValue, 2).' '.$level->station->unitLabel() : '-' }}</dd>
                @if ($level?->latestValue !== null)
                    <dd class="text-xs text-muted">{{ strtolower($level->trend->label()) }}{{ $level->relativeToUsual() ? ', '.$level->relativeToUsual() : '' }}</dd>
                @endif
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Bathing sites <x-help term="bathing_classification" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums">{{ $conditions->bathingWaters->count() }}</dd>
                @if ($conditions->bathingWaters->isNotEmpty())
                    <dd class="text-xs text-muted">{{ $conditions->bathingWaters->map(fn ($summary) => $summary->bathingWater->classification?->value ?? 'not classified')->countBy()->map(fn ($count, $classification) => $count.' '.$classification)->join(', ') }}</dd>
                @endif
            </div>
            <div>
                @php
                    // With overflows nearby, the figures open the table below and the spills page.
                    $hasNearbyOverflows = $overflows->isCovered() && $overflows->monitors->isNotEmpty();
                @endphp
                <dt class="text-xs uppercase tracking-wide text-muted"><x-figure-link :url="$hasNearbyOverflows ? '#overflows' : null">Overflows within {{ $overflows->radiusMetres / 1000 }} km</x-figure-link> <x-help term="nearby_overflows" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums {{ $overflows->dischargingNowCount > 0 ? 'text-alert' : '' }}">
                    <x-figure-link :url="$hasNearbyOverflows ? '#overflows' : null" figure>{{ $overflows->isCovered() ? $overflows->monitors->count() : '-' }}</x-figure-link>
                    @if ($overflows->dischargingNowCount > 0)
                        <span class="font-sans text-sm font-normal">{{ $overflows->dischargingNowCount }} discharging</span>
                    @elseif ($overflows->offlineCount > 0)
                        <span class="font-sans text-sm font-normal text-muted">{{ $overflows->offlineCount }} offline</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted"><x-figure-link :url="$hasNearbyOverflows ? route('lakes.spills', [$region, $lake]) : null">Spills nearby, 30 days</x-figure-link></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums"><x-figure-link :url="$hasNearbyOverflows ? route('lakes.spills', [$region, $lake]) : null" figure>{{ $hasNearbyOverflows ? $overflows->spillsLast30DaysCount : '-' }}</x-figure-link></dd>
            </div>
        </dl>
    </section>

    <section class="mb-8">
        <div class="mb-3 flex items-center gap-1.5">
            <h2 class="font-display text-lg font-semibold">Weather</h2>
            <x-help term="weather" />
        </div>
        <x-weather-card :weather="$weather" :sun="$sun" place="this lake" />
    </section>

    <div class="grid gap-8 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <div class="h-80 w-full rounded-lg border border-line" role="region" aria-label="Map of {{ $lake->name }}, its bathing sites and the overflow monitors within 3 km" data-map data-map-markers="{{ json_encode($mapMarkers) }}"></div>
            <x-map-key where="nearby" :place="$lake->name" :overflows="$overflows->monitors->isNotEmpty()" :neutral="$conditions->bathingWaters->isNotEmpty() ? 'Bathing site' : null" :spills="$overflows->isCovered()" />
        </div>
        <aside class="lg:col-span-2">
            <div class="flex items-center gap-1.5 mb-3">
                <h2 class="font-display text-lg font-semibold">Lake level</h2>
                <x-help term="level" />
            </div>
            <x-level-card :level="$level" :water="$lake->name" empty="We don't have a level gauge for this lake." />

            <div class="flex items-center gap-1.5 mt-8 mb-3">
                <h2 class="font-display text-lg font-semibold">Rainfall</h2>
                <x-help term="rainfall" />
            </div>
            <x-rainfall-card :rainfall="$conditions->rainfall" place="this lake" note="Rain across the lake's catchment may differ." />
        </aside>
    </div>

    <section class="mt-10">
        <h2 class="font-display mb-3 text-lg font-semibold">Designated bathing sites</h2>
        @if ($conditions->bathingWaters->isEmpty())
            <p class="text-sm text-ink-2">No bathing water results for {{ $lake->name }} yet.</p>
        @else
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($conditions->bathingWaters as $summary)
                    <x-bathing-water :summary="$summary" />
                @endforeach
            </div>
        @endif
    </section>

    <x-nearby-overflows id="overflows" :overflows="$overflows" :region="$region" :all-url="route('lakes.spills', [$region, $lake])" class="mt-10 scroll-mt-24" />

    <x-before-you-go kind="lake" />

    <x-nearby-places :nearby="$nearby" />
</x-layouts.app>
