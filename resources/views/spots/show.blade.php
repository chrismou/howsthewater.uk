@php
    $spot = $conditions->spot;
    $level = $conditions->level;
    $rainfall = $conditions->rainfall;
    $timezone = config('app.display_timezone', 'Europe/London');

    // The table renders the most relevant monitors and loads the rest on request: low on a big river system (Goole)
    // hundreds sit upstream. The map shows every monitor with recent activity plus the nearest quiet ones.
    $spillsByMonitor = $conditions->spillCountsByMonitor();
    $monitorsByActivity = $conditions->upstreamMonitorsByActivity();
    $shownMonitors = $monitorsByActivity->take(\App\Http\Controllers\SpotController::MONITORS_SHOWN);
    $hiddenMonitorCount = $monitorsByActivity->count() - $shownMonitors->count();
    $activeMonitorCount = $conditions->activeUpstreamMonitorCount();
    $mapMonitors = $monitorsByActivity->take($activeMonitorCount + 50);

    $mapMarkers = collect([[
        'lat' => $spot->latitude,
        'lng' => $spot->longitude,
        'label' => $spot->name,
        'kind' => 'spot',
        'recency' => $conditions->spillRecency->value,
    ]])->concat($mapMonitors->map(fn ($summary) => [
        'lat' => $summary->monitor->latitude,
        'lng' => $summary->monitor->longitude,
        'label' => ($summary->monitor->displayName() ?? $summary->monitor->external_id).', '.$summary->river->name.' ('.$summary->status->label().')',
        'url' => route('overflows.show', $summary->monitor),
        'kind' => 'monitor',
        'status' => $summary->status->value,
    ]))->values();

    // Places whose water company feed we do not read yet show dashes, never zeros that would read as "no spills".
    $covered = $conditions->spillRecency !== \App\Enums\SpillRecency::NotCovered;
    // With no monitor matched upstream, zero counts would read as a clean record, so spill figures show dashes.
    $hasMonitors = $covered && $conditions->upstreamMonitors->isNotEmpty();

    // The longest title that fits search results' 60 characters: the full one, then a shorter topic, then just the place.
    $place = $spot->name.', '.$spot->river->name;
    $title = collect($covered ? [': swimming, river level and spills', ': swimming and spills', ''] : [': swimming and river level', ': swimming', ''])
        ->map(fn (string $topic) => $place.$topic)
        ->first(fn (string $candidate) => mb_strlen($candidate) <= 60, $place);

    // The description carries the spot's own facts (where it is, how many monitors, which gauge), so no two read alike.
    $upstreamCount = $conditions->upstreamMonitors->count();
    $facts = collect([
        ! $covered ? null : ($upstreamCount > 0 ? "spills from {$upstreamCount} storm ".\Illuminate\Support\Str::plural('overflow', $upstreamCount).' upstream' : 'no monitored storm overflows upstream'),
        $level ? "the {$level->station->label} gauge's level" : 'river level',
        'rainfall',
    ])->filter()->join(', ', ' and ');
    $located = $spot->name.' on the '.$spot->river->name.($spot->river_metres !== null ? ', '.number_format($spot->river_metres / 1000).' km from the source' : '');
    $description = collect([$located, $spot->name.' on the '.$spot->river->name])
        ->crossJoin([', for swimming, paddling and fishing.', ', for swimming and paddling.', '.'])
        ->map(fn (array $parts) => "{$parts[0]}: {$facts}{$parts[1]}")
        ->first(fn (string $candidate) => mb_strlen($candidate) <= 160, \Illuminate\Support\Str::limit("{$spot->name} on the {$spot->river->name}: {$facts}.", 157));


    // The page's trail: the visible breadcrumb and the BreadcrumbList are both built from it.
    $breadcrumbs = [['All rivers', route('hubs.rivers')], [$spot->river->region->name, route('regions.show', $spot->river->region)], ['Rivers', route('rivers.index', $spot->river->region)], [$spot->river->name, route('rivers.show', [$spot->river->region, $spot->river])], [$spot->name, route('spots.show', [$spot->river->region, $spot->river, $spot])]];
@endphp

<x-layouts.app :share-image="$shareImage" :date-modified="$conditions->latestDataAt()" :region="$spot->river->region" :feed="route('spots.feed', [$spot->river->region, $spot->river, $spot])" :title="$title"
               :description="$description"
               :breadcrumbs="$breadcrumbs"
               :structured-data="app(\App\Services\StructuredData::class)->spot($spot, $spot->river, $spot->river->region)">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <x-breadcrumbs :items="$breadcrumbs" />
            {{-- Names the river too ("Ilkley, River Wharfe"), as people search for both, with the place standing out. --}}
            <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $spot->name }}<span class="text-2xl font-medium text-muted sm:text-3xl">, {{ $spot->river->name }}</span></h1>
            <p class="mt-1 text-ink-2">
                @if ($spot->description){{ $spot->description }} @endif
                @if ($spot->river_metres !== null)
                    <span class="text-muted">{{ number_format($spot->river_metres / 1000) }} km from the source.</span>
                @endif
            </p>
            <x-place-summary class="mt-3" :headline="app(\App\Services\Conditions\PlaceHeadline::class)->forSpot($conditions, $weather)" :region="$spot->river->region" />
        </div>
        <x-place-actions :latitude="$spot->latitude" :longitude="$spot->longitude" :label="$spot->name" :feed="route('spots.feed', [$spot->river->region, $spot->river, $spot])"
                         feed-title="Follow overflows upstream and flood warnings in a feed reader" />
    </div>

    @if ($conditions->floodWarnings->isNotEmpty())
        <x-flood-warnings :warnings="$conditions->floodWarnings" />
    @endif

    @if ($conditions->bathingWater)
        <section class="mb-8">
            <h2 class="font-display mb-3 text-lg font-semibold">Designated bathing water</h2>
            <x-bathing-water :summary="$conditions->bathingWater" heading />
        </section>
    @else
        {{-- Says why there are no samples, so their absence never reads as a clean result. --}}
        <p class="mb-8 text-sm text-ink-2">Not a designated bathing water, so there are no official water quality samples here. <x-help term="bathing_classification" /></p>
    @endif

    <section class="mb-8 rounded-lg border border-line bg-surface p-5">
        <div class="flex flex-wrap items-center gap-3">
            <x-spill-badge :recency="$conditions->spillRecency" :stale="$conditions->overflowDataIsStale()" class="text-sm px-3 py-1" />
            @if ($conditions->dataUpdatedAt)
                <span class="text-sm text-muted">Monitor data updated {{ $conditions->dataUpdatedAt->diffForHumans() }} · <span class="font-mono text-xs">{{ $conditions->dataUpdatedAt->timezone($timezone)->format('H:i T') }}</span></span>
            @endif
        </div>

        @if ($conditions->overflowDataIsStale())
            <div class="mt-4 rounded-md border border-warn-line bg-warn-bg px-4 py-3 text-sm text-warn-text" role="status">
                <p class="font-medium">Monitor data is out of date. <x-help term="stale" /></p>
                <p class="mt-1">
                    @if ($conditions->dataUpdatedAt)
                        The overflow feed has not updated for {{ $conditions->dataUpdatedAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }}
                        (last update {{ $conditions->dataUpdatedAt->timezone($timezone)->format('j M, H:i T') }}).
                    @else
                        The overflow feed gave no update time for these monitors.
                    @endif
                    The spill status and counts below describe the data as it was then, not what is happening now.
                </p>
            </div>
        @endif

        {{-- With monitors upstream, each figure opens what it counts: the overflows table below or the spills page. --}}
        @php
            $overflowsUrl = $hasMonitors ? '#overflows' : null;
            $spillsUrl = $hasMonitors ? route('spots.spills', [$spot->river->region, $spot->river, $spot]) : null;
        @endphp
        <dl class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted"><x-figure-link :url="$overflowsUrl">Discharging upstream</x-figure-link> <x-help term="discharging_now" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums {{ $conditions->dischargingNowCount > 0 ? 'text-alert' : '' }}"><x-figure-link :url="$overflowsUrl" figure>{{ $hasMonitors ? $conditions->dischargingNowCount : '-' }}</x-figure-link></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted"><x-figure-link :url="$spillsUrl">Spills, last 48h</x-figure-link> <x-help term="spill" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums"><x-figure-link :url="$spillsUrl" figure>{{ $hasMonitors ? $conditions->spillsLast48HoursCount : '-' }}</x-figure-link></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted"><x-figure-link :url="$spillsUrl">Spills, last 30 days</x-figure-link> <x-help term="history" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums"><x-figure-link :url="$spillsUrl" figure>{{ $hasMonitors ? $conditions->spillsLast30DaysCount : '-' }}</x-figure-link></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted"><x-figure-link :url="$overflowsUrl">Upstream monitors</x-figure-link> <x-help term="upstream" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums">
                    <x-figure-link :url="$overflowsUrl" figure>{{ $covered ? $conditions->upstreamMonitors->count() : '-' }}</x-figure-link>
                    @if ($conditions->offlineCount > 0)
                        <span class="font-sans text-sm font-normal text-muted">{{ $conditions->offlineCount }} offline <x-help term="offline" /></span>
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    <section class="mb-8">
        <div class="mb-3 flex items-center gap-1.5">
            <h2 class="font-display text-lg font-semibold">Weather</h2>
            <x-help term="weather" />
        </div>
        <x-weather-card :weather="$weather" :sun="$sun" place="this spot" />
    </section>

    <div class="grid gap-8 lg:grid-cols-5">
        <div class="min-w-0 lg:col-span-3">
            <div
                class="h-80 w-full rounded-lg border border-line"
                role="region" aria-label="Map of {{ $spot->name }} on the {{ $spot->river->name }}, with the overflow monitors upstream"
                data-map
                data-map-centreline="{{ json_encode($river->centreline ?? []) }}"
                data-map-markers="{{ json_encode($mapMarkers) }}"
            ></div>
            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-label="Map key">
                <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-accent"></span>{{ $spot->name }}</li>
                <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#b91c1c"></span>Overflow discharging</li>
                <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#64748b"></span>Monitor</li>
                <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#a8a29e"></span>Offline</li>
            </ul>

            <div class="flex items-center gap-1.5 mt-8 mb-3">
                <h2 class="font-display text-lg font-semibold">Last 30 days</h2>
                <x-help term="history" />
            </div>
            @if ($hasMonitors)
                <x-spill-history :days="$conditions->dailyHistory" :events="$conditions->recentSpillEvents" :recency="$conditions->spillRecency"
                                 :all-url="$conditions->upstreamMonitors->isNotEmpty() ? route('spots.spills', [$spot->river->region, $spot->river, $spot]) : null" />
            @elseif ($covered)
                <p class="rounded-lg border border-line bg-surface p-5 text-sm text-ink-2">No spill history, as no storm overflow monitor is matched upstream of this spot.</p>
            @else
                <p class="rounded-lg border border-line bg-surface p-5 text-sm text-ink-2">No spill history, as we do not read the water company's overflow data here yet. <x-help term="not_covered" /> <x-overflow-map-link :region="$spot->river->region" /></p>
            @endif
        </div>

        <aside class="lg:col-span-2">
            <div class="flex items-center gap-1.5 mb-3">
                <h2 class="font-display text-lg font-semibold">River level</h2>
                <x-help term="level" />
            </div>
            <x-level-card :level="$level" :water="'the '.$spot->river->shortName()" empty="No gauge is linked to this spot yet." />

            <div class="flex items-center gap-1.5 mt-8 mb-3">
                <h2 class="font-display text-lg font-semibold">Rainfall</h2>
                <x-help term="rainfall" />
            </div>
            <x-rainfall-card :rainfall="$rainfall" place="this spot" note="Rain upstream in the catchment may differ." />
        </aside>
    </div>

    <section id="overflows" class="mt-10 scroll-mt-24">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <div class="flex items-center gap-1.5">
                <h2 class="font-display text-lg font-semibold">Upstream storm overflows</h2>
                <x-help term="upstream" />
            </div>
            <span class="text-sm text-muted">Recent spills first, then nearest. Distances follow the water, including tributaries.</span>
        </div>
        @if (! $covered)
            <p class="text-sm text-ink-2">We do not read the water company's overflow data for this river yet, so overflows upstream of this spot are not shown. <x-help term="not_covered" /> <x-overflow-map-link :region="$spot->river->region" /></p>
        @elseif ($conditions->upstreamMonitors->isEmpty())
            <p class="text-sm text-ink-2">No monitors have been matched upstream of this spot yet.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-line bg-surface">
                <table class="w-full text-sm">
                    <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Monitor</th>
                            <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Watercourse</th>
                            <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Upstream</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                            <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Last spill started</th>
                            <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Lasted</th>
                            <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Spills, 30 days</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-2" data-monitor-rows>
                        @if ($activeMonitorCount === 0)
                            <tr><td colspan="7" class="px-4 py-3 text-ink-2">No upstream monitor has spilled in the last 30 days.</td></tr>
                        @endif
                        @foreach ($shownMonitors as $summary)
                            <x-overflow-row :summary="$summary" :spills="$spillsByMonitor->get($summary->monitor->id, 0)" />
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($hiddenMonitorCount > 0)
                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted" data-more-monitors="{{ route('spots.monitors', [$spot->river->region, $spot->river, $spot]) }}">
                    <span>Showing {{ $shownMonitors->count() }} of {{ $monitorsByActivity->count() }} upstream monitors, those with recent spills first.</span>
                    <button type="button" data-more-monitors-button class="font-medium text-accent hover:underline">Show the other {{ $hiddenMonitorCount }}</button>
                    <span data-more-monitors-loading hidden class="inline-flex items-center gap-2 text-accent" role="status">
                        <svg class="h-4 w-8 overflow-hidden" viewBox="0 0 32 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <path class="animate-wave" d="M-16 8 q4 -5 8 0 t8 0 t8 0 t8 0 t8 0 t8 0"/>
                        </svg>
                        Loading monitors
                    </span>
                </div>
            @endif
        @endif
    </section>

    <x-before-you-go kind="river" />

    <x-nearby-places :nearby="$nearby" />
</x-layouts.app>
