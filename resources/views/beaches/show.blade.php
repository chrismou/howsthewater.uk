@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $summary = $conditions->bathingWater;
    $beach = $summary->bathingWater;
    $overflows = $conditions->overflows;
    $latest = $summary->latestSample();
    $count = fn ($value, $qualifier) => \App\Models\BathingWaterSample::formatCount($value, $qualifier) ?? '-';

    $mapMarkers = collect([[
        'lat' => $beach->latitude,
        'lng' => $beach->longitude,
        'label' => $beach->name,
        'kind' => 'place',
        'recency' => $overflows->spillRecency->onMap($overflows->isStale()),
        'note' => $overflows->spillRecency->mapNote($overflows->isStale(), nearby: true),
    ]])->concat($overflows->monitors->map(fn ($monitor) => [
        'lat' => $monitor->monitor->latitude,
        'lng' => $monitor->monitor->longitude,
        'label' => $monitor->monitor->titleName(),
        'url' => route('overflows.show', $monitor->monitor),
        'kind' => 'monitor',
        'recency' => $monitor->spillRecency()->value,
        'note' => $monitor->mapNote(),
        'link' => 'View overflow',
    ]))->values();

    // The page's trail: the visible breadcrumb and the BreadcrumbList are both built from it.
    $breadcrumbs = [['All beaches', route('hubs.beaches')], [$region->name, route('regions.show', $region)], ['Beaches', route('beaches.index', $region)], [$beach->name, route('beaches.show', [$region, $beach])]];

    // "Bathing water quality" is how people search for beaches; it steps down to fit search results' 60 characters.
    $title = collect([': bathing water quality and swimming', ': bathing water quality', ''])
        ->map(fn (string $topic) => $beach->beachSearchName().$topic)
        ->first(fn (string $candidate) => mb_strlen($candidate) <= 60, $beach->beachSearchName());

    // Leads with the beach's own facts, so no two beaches' descriptions read alike; the closing line goes if it runs long.
    $isSepa = $beach->agency === \App\Enums\Agency::Sepa;
    $agencyName = ($isSepa ? '' : 'the ').$beach->agency->label();
    $rated = $beach->classification !== null
        ? 'rated '.$beach->classification->value.($beach->classification_year !== null ? ' in '.$beach->classification_year : '').' by '.$agencyName
        : 'not yet classified by '.$agencyName;
    $overflowCount = $overflows->monitors->count();
    $within = ' within '.($overflows->radiusMetres / 1000).' km';
    $overflowsPhrase = ! $overflows->isCovered() ? '' : ($overflowCount > 0 ? ', with '.$overflowCount.' storm '.\Illuminate\Support\Str::plural('overflow', $overflowCount).$within : ', with no monitored storm overflows'.$within);
    $closing = $overflowCount > 0
        ? ($isSepa ? ' Sample results' : ' Samples, pollution forecasts').' and nearby spills.'
        : ($isSepa ? ' Sample results.' : ' Samples and pollution forecasts.');
    $description = collect([$closing, ''])
        ->map(fn (string $more) => $beach->beachSearchName().': '.$rated.$overflowsPhrase.'.'.$more)
        ->first(fn (string $candidate) => mb_strlen($candidate) <= 160, $beach->beachSearchName().': '.$rated.'.');
@endphp

<x-layouts.app :share-image="$shareImage" :date-modified="collect([$overflows->dataUpdatedAt, $conditions->bathingWater->latestSample()?->sampled_at])->filter()->max()" :region="$region" :feed="route('beaches.feed', [$region, $beach])" :title="$title"
:description="$description"
               :breadcrumbs="$breadcrumbs"
               :structured-data="app(\App\Services\StructuredData::class)->beach($beach, $region)">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <x-breadcrumbs :items="$breadcrumbs" />
            <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $beach->name }}</h1>
            <p class="mt-1 text-ink-2">
                {{ collect([$beach->district, $beach->kind === \App\Enums\BathingWaterKind::Estuary ? 'estuary bathing water' : null, $beach->sewerage_undertaker ? 'sewerage by '.$beach->sewerage_undertaker : null])->filter()->join(' · ') }}
            </p>
            <x-place-summary class="mt-3" :headline="app(\App\Services\Conditions\PlaceHeadline::class)->forBeach($conditions, $weather)" :region="$region" />
        </div>
        <x-place-actions :latitude="$beach->latitude" :longitude="$beach->longitude" :label="$beach->name" :feed="route('beaches.feed', [$region, $beach])" />
    </div>

    <section class="mb-8 rounded-lg border border-line bg-surface p-5">
        <div class="flex flex-wrap items-center gap-2">
            <x-classification-pill :site="$beach" class="px-3 py-1 text-sm" />
            <x-bathing-forecast :summary="$summary" class="px-3 py-1 text-sm" />
            <x-spill-badge :recency="$overflows->spillRecency" :stale="$overflows->isStale()" nearby class="px-3 py-1 text-sm" />
        </div>
        <p class="mt-3 text-sm text-ink-2">
            @if ($beach->classification)
                {{ $beach->classification->meaning() }} <x-help term="bathing_classification" />
            @endif
            @if ($beach->affected_by_heavy_rain)
                Heavy rain can lower water quality here. <x-help term="pollution_risk" />
            @endif
        </p>

        @if ($overflows->isStale())
            <p class="mt-4 rounded-md border border-warn-line bg-warn-bg px-4 py-3 text-sm text-warn-text" role="status">Overflow monitor data is out of date, so the spill status describes the data as it was, not now. <x-help term="stale" /></p>
        @endif

        <dl class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Latest E. coli <x-help term="bathing_samples" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums">{{ $latest ? $count($latest->e_coli, $latest->e_coli_qualifier) : '-' }}</dd>
                @if ($latest)
                    <dd class="text-xs text-muted">per 100 ml, {{ $latest->sampled_at->timezone($timezone)->format('j M Y') }}</dd>
                @elseif (! $summary->hasSamplesHere())
                    <dd class="text-xs text-muted">See the <a href="{{ $summary->url() }}" rel="noopener" target="_blank" class="underline hover:text-ink">{{ $summary->urlLabel() }}</a></dd>
                @endif
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Latest enterococci</dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums">{{ $latest ? $count($latest->enterococci, $latest->enterococci_qualifier) : '-' }}</dd>
                @if ($latest)
                    <dd class="text-xs text-muted">per 100 ml</dd>
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
                <dt class="text-xs uppercase tracking-wide text-muted"><x-figure-link :url="$hasNearbyOverflows ? route('beaches.spills', [$region, $beach]) : null">Spills nearby, 30 days</x-figure-link></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums"><x-figure-link :url="$hasNearbyOverflows ? route('beaches.spills', [$region, $beach]) : null" figure>{{ $hasNearbyOverflows ? $overflows->spillsLast30DaysCount : '-' }}</x-figure-link></dd>
            </div>
        </dl>
    </section>

    {{-- Phones: map, samples, then the description. Wide screens: map and description left, samples right. --}}
    <section class="mb-8">
        <div class="mb-3 flex items-center gap-1.5">
            <h2 class="font-display text-lg font-semibold">Weather</h2>
            <x-help term="weather" />
        </div>
        <x-weather-card :weather="$weather" :sun="$sun" place="this beach" />
    </section>

    <div class="grid gap-8 lg:grid-cols-5 lg:grid-rows-[auto_1fr]">
        <div class="lg:col-span-3 lg:row-start-1">
            <div class="h-80 w-full rounded-lg border border-line lg:h-96" role="region" aria-label="Map of {{ $beach->name }} and the overflow monitors within 3 km" data-map data-map-markers="{{ json_encode($mapMarkers) }}"></div>
            <x-map-key where="nearby" :place="$beach->name" :overflows="$overflows->monitors->isNotEmpty()" :spills="$overflows->isCovered()" />
        </div>

        <aside class="lg:col-span-2 lg:col-start-4 lg:row-span-2 lg:row-start-1">
            <section>
                <div class="flex items-center gap-1.5 mb-3">
                    <h2 class="font-display text-lg font-semibold">Recent samples</h2>
                    <x-help term="bathing_samples" />
                </div>
                <div class="rounded-lg border border-line bg-surface p-5">
                    @if ($summary->recentSamples->count() > 1)
                        <x-sample-bars :samples="$summary->recentSamples" />
                    @elseif (! $summary->hasSamplesHere())
                        <p class="text-sm text-ink-2">SEPA publishes this beach's sample results on its <a href="{{ $summary->url() }}" rel="noopener" target="_blank" class="underline hover:text-ink">results page</a>.</p>
                    @else
                        <p class="text-sm text-ink-2">Not enough samples yet to show a history.</p>
                    @endif
                    @if ($summary->hasSamplesHere() && ! $beach->description)
                        <p class="mt-3 text-xs text-muted">From the <a href="{{ $summary->url() }}" rel="noopener" target="_blank" class="underline hover:text-ink">{{ $summary->urlLabel() }}</a>.</p>
                    @endif
                    @if ($summary->classifications->isNotEmpty())
                        <p class="mt-4 text-xs uppercase tracking-wide text-muted">Classifications</p>
                        <ul class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($summary->classifications->take(6) as $record)
                                <li class="rounded-full bg-pill-grey-bg px-2 py-0.5 text-xs text-pill-grey-text"><span class="font-mono">{{ $record->year }}</span> {{ $record->classification->value }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            <div class="flex items-center gap-1.5 mt-8 mb-3">
                <h2 class="font-display text-lg font-semibold">Rainfall</h2>
                <x-help term="rainfall" />
            </div>
            <x-rainfall-card :rainfall="$conditions->rainfall" place="this beach" note="Heavy rain inland can reach the sea through rivers and overflows." />
        </aside>

            @if ($beach->description)
                <section class="lg:col-span-3 lg:row-start-2">
                    <h2 class="font-display mb-3 text-lg font-semibold">About this beach</h2>
                    <div class="rounded-lg border border-line bg-surface p-5 text-sm text-ink-2">
                        <p>{{ $beach->description }}</p>
                        <p class="mt-3 text-xs text-muted">From the <a href="{{ $summary->url() }}" rel="noopener" target="_blank" class="underline hover:text-ink">{{ $summary->urlLabel() }}</a>.</p>
                    </div>
                </section>
            @endif
    </div>

    <x-nearby-overflows id="overflows" :overflows="$overflows" :region="$region" :all-url="route('beaches.spills', [$region, $beach])" class="mt-10 scroll-mt-24" />

    <x-before-you-go kind="beach" />

    <x-nearby-places :nearby="$nearby" />
</x-layouts.app>
