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
        'kind' => 'bathing',
        'classification' => $beach->classification?->value,
    ]])->concat($overflows->monitors->map(fn ($monitor) => [
        'lat' => $monitor->monitor->latitude,
        'lng' => $monitor->monitor->longitude,
        'label' => $monitor->monitor->external_id.', '.number_format($monitor->distanceMetres / 1000, 1).' km ('.$monitor->status->label().')',
        'kind' => 'monitor',
        'status' => $monitor->status->value,
    ]))->values();

    // The page's trail: the visible breadcrumb and the BreadcrumbList are both built from it.
    $breadcrumbs = [['All beaches', route('hubs.beaches')], [$region->name, route('regions.show', $region)], ['Beaches', route('beaches.index', $region)], [$beach->name, route('beaches.show', [$region, $beach])]];

    // "Bathing water quality" is how people search for beaches; it steps down to fit search results' 60 characters.
    $title = collect([': bathing water quality and swimming', ': bathing water quality', ''])
        ->map(fn (string $topic) => $beach->beachSearchName().$topic)
        ->first(fn (string $candidate) => mb_strlen($candidate) <= 60, $beach->beachSearchName());
@endphp

<x-layouts.app :date-modified="$overflows->dataUpdatedAt" :region="$region" :feed="route('beaches.feed', [$region, $beach])" :title="$title"
               :description="$beach->agency->label().($beach->agency === \App\Enums\Agency::Sepa ? ' classification and sample results' : ' classification, samples and pollution forecasts').' for swimming at '.$beach->beachSearchName().', plus storm overflows nearby.'"
               :breadcrumbs="$breadcrumbs"
               :structured-data="app(\App\Services\StructuredData::class)->beach($beach, $region)">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <x-breadcrumbs :items="$breadcrumbs" />
            <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $beach->name }}</h1>
            <p class="mt-1 text-ink-2">
                {{ collect([$beach->district, $beach->kind === \App\Enums\BathingWaterKind::Estuary ? 'estuary bathing water' : null, $beach->sewerage_undertaker ? 'sewerage by '.$beach->sewerage_undertaker : null])->filter()->join(' · ') }}
            </p>
            <x-dated-summary class="mt-2 max-w-2xl text-sm text-ink-2" :monitors="$overflows->monitors" :where="'within '.($overflows->radiusMetres / 1000).' km of '.$beach->name"
                             :discharging-now="$overflows->dischargingNowCount" :spills-last48-hours="$overflows->spillsLast48HoursCount" :updated-at="$overflows->dataUpdatedAt" :covered="$overflows->isCovered()" :stale="$overflows->isStale()" />
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
                <dt class="text-xs uppercase tracking-wide text-muted">Overflows within {{ $overflows->radiusMetres / 1000 }} km <x-help term="nearby_overflows" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums {{ $overflows->dischargingNowCount > 0 ? 'text-alert' : '' }}">
                    {{ $overflows->isCovered() ? $overflows->monitors->count() : '-' }}
                    @if ($overflows->dischargingNowCount > 0)
                        <span class="font-sans text-sm font-normal">{{ $overflows->dischargingNowCount }} discharging</span>
                    @elseif ($overflows->offlineCount > 0)
                        <span class="font-sans text-sm font-normal text-muted">{{ $overflows->offlineCount }} offline</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Spills nearby, 30 days</dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums">{{ $overflows->isCovered() ? $overflows->spillsLast30DaysCount : '-' }}</dd>
            </div>
        </dl>
    </section>

    {{-- Phones: map, samples, then the description. Wide screens: map and description left, samples right. --}}
    <div class="grid gap-8 lg:grid-cols-5 lg:grid-rows-[auto_1fr]">
        <div class="lg:col-span-3 lg:row-start-1">
            <div class="h-80 w-full rounded-lg border border-line" data-map data-map-markers="{{ json_encode($mapMarkers) }}"></div>
            <x-bathing-map-key />
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

    <x-nearby-overflows :overflows="$overflows" class="mt-10" />
</x-layouts.app>
