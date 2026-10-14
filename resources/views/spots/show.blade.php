@php
    $spot = $conditions->spot;
    $level = $conditions->level;
    $rainfall = $conditions->rainfall;
    $timezone = config('app.display_timezone', 'Europe/London');

    $mapMarkers = collect([[
        'lat' => $spot->latitude,
        'lng' => $spot->longitude,
        'label' => $spot->name,
        'kind' => 'spot',
        'recency' => $conditions->spillRecency->value,
    ]])->concat($conditions->upstreamMonitors->map(fn ($summary) => [
        'lat' => $summary->monitor->latitude,
        'lng' => $summary->monitor->longitude,
        'label' => $summary->monitor->external_id.', '.$summary->river->name.' ('.$summary->status->label().')',
        'kind' => 'monitor',
        'status' => $summary->status->value,
    ]))->values();

    $trendText = $level?->trend->label() ?? '';

    if ($level?->changeLast3Hours !== null) {
        $trendText .= sprintf(', %s%s m in 3 hours', $level->changeLast3Hours > 0 ? '+' : '', number_format($level->changeLast3Hours, 2));
    }

    $sparkline = $level?->recentReadings->map(fn ($reading) => [$reading['measured_at']->timestamp, $reading['value']])->values() ?? collect();

    // Where the latest reading sits on the typical range bar: the range itself spans 22% to 70% of the bar.
    $rangePosition = null;

    if ($level?->latestValue !== null && $level->station->typical_range_low !== null && $level->station->typical_range_high > $level->station->typical_range_low) {
        $fraction = ($level->latestValue - $level->station->typical_range_low) / ($level->station->typical_range_high - $level->station->typical_range_low);
        $rangePosition = (int) round(max(2, min(98, 22 + $fraction * 48)));
    }

    // Spills per monitor in the history window, so the table can show which monitors have been active.
    $spillsByMonitor = $conditions->recentSpillEvents->countBy('overflow_monitor_id');
    $activeMonitors = $conditions->upstreamMonitors->filter(fn ($summary) => $summary->status !== \App\Enums\OverflowStatus::NotDischarging || $spillsByMonitor->has($summary->monitor->id));
    $quietMonitors = $conditions->upstreamMonitors->reject(fn ($summary) => $activeMonitors->contains($summary));

    $osmUrl = sprintf('https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=15/%s/%s', $spot->latitude, $spot->longitude, $spot->latitude, $spot->longitude);
@endphp

<x-layouts.app :region="$spot->river->region" :feed="route('spots.feed', [$spot->river->region, $spot->river, $spot])" :title="$spot->name.', '.$spot->river->name.': spills and river level'"
               :description="'Storm overflow spills upstream of '.$spot->name.' on the '.$spot->river->name.', plus the current river level and trend.'">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm text-muted">
                <a href="{{ route('home') }}" class="hover:underline">All regions</a>
                <span class="mx-1">/</span>
                <a href="{{ route('regions.show', $spot->river->region) }}" class="hover:underline">{{ $spot->river->region->name }}</a>
                <span class="mx-1">/</span>
                <a href="{{ route('rivers.show', [$spot->river->region, $spot->river]) }}" class="hover:underline">{{ $spot->river->name }}</a>
            </p>
            <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $spot->name }}</h1>
            <p class="mt-1 text-ink-2">
                @if ($spot->description){{ $spot->description }} @endif
                @if ($spot->river_metres !== null)
                    <span class="text-muted">{{ number_format($spot->river_metres / 1000) }} km from the source, {{ $conditions->upstreamMonitors->count() }} {{ \Illuminate\Support\Str::plural('monitor', $conditions->upstreamMonitors->count()) }} upstream.</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" data-copy-link="{{ url()->current() }}"
                    class="inline-flex h-10 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm text-ink hover:border-muted focus:outline-none focus:ring-1 focus:ring-accent"
                    aria-label="Copy a link to this spot">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.5 1.5"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.5-1.5"/></svg>
                <span data-copy-link-label>Copy link</span>
            </button>
            <a href="{{ $osmUrl }}" rel="noopener" target="_blank"
               class="inline-flex h-10 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm text-ink hover:border-muted focus:outline-none focus:ring-1 focus:ring-accent">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-6-5.3-6-11a6 6 0 0 1 12 0c0 5.7-6 11-6 11z"/><circle cx="12" cy="10" r="2.2"/></svg>
                Open in OpenStreetMap
            </a>
            <a href="{{ route('spots.feed', [$spot->river->region, $spot->river, $spot]) }}" title="Follow overflows upstream and flood warnings in a feed reader"
               class="inline-flex h-10 items-center gap-2 rounded-md border border-line bg-surface px-3 text-sm text-ink hover:border-muted focus:outline-none focus:ring-1 focus:ring-accent">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 11a8 8 0 0 1 8 8"/><path d="M5 5a14 14 0 0 1 14 14"/><circle cx="6" cy="18" r="1.2" fill="currentColor"/></svg>
                RSS feed
            </a>
        </div>
    </div>

    @if ($conditions->floodWarnings->isNotEmpty())
        <x-flood-warnings :warnings="$conditions->floodWarnings" />
    @endif

    @if ($conditions->bathingWater)
        <section class="mb-8">
            <h2 class="font-display mb-3 text-lg font-semibold">Designated bathing water</h2>
            <x-bathing-water :summary="$conditions->bathingWater" heading />
        </section>
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

        <dl class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Discharging now <x-help term="discharging_now" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums {{ $conditions->dischargingNowCount > 0 ? 'text-alert' : '' }}">{{ $conditions->dischargingNowCount }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Spills, last 48h <x-help term="spill" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums">{{ $conditions->spillsLast48HoursCount }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Spills, last 30 days <x-help term="history" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums">{{ $conditions->spillsLast30DaysCount }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Upstream monitors <x-help term="upstream" /></dt>
                <dd class="mt-1 font-mono text-3xl font-semibold tabular-nums">
                    {{ $conditions->upstreamMonitors->count() }}
                    @if ($conditions->offlineCount > 0)
                        <span class="font-sans text-sm font-normal text-muted">{{ $conditions->offlineCount }} offline <x-help term="offline" /></span>
                    @endif
                </dd>
            </div>
        </dl>
    </section>

    <div class="grid gap-8 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <div
                class="h-80 w-full rounded-lg border border-line"
                data-map
                data-map-centreline="{{ json_encode($river->centreline ?? []) }}"
                data-map-markers="{{ json_encode($mapMarkers) }}"
            ></div>
            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-label="Map key">
                <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full bg-accent"></span>{{ $spot->name }}</li>
                <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#b91c1c"></span>Discharging</li>
                <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#64748b"></span>Monitor</li>
                <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#a8a29e"></span>Offline</li>
            </ul>

            <h2 class="font-display mt-8 mb-3 text-lg font-semibold">Last 30 days <x-help term="history" /></h2>
            <x-spill-history :days="$conditions->dailyHistory" :events="$conditions->recentSpillEvents" />
        </div>

        <aside class="lg:col-span-2">
            <h2 class="font-display mb-3 text-lg font-semibold">River level <x-help term="level" /></h2>
            <div class="rounded-lg border border-line bg-surface p-5">
                @if ($level === null)
                    <p class="text-sm text-ink-2">No gauge is linked to this spot yet.</p>
                @elseif ($level->latestValue === null)
                    <p class="text-sm text-ink-2">No recent readings from the {{ $level->station->label }} gauge.</p>
                @else
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <p class="font-mono text-4xl font-semibold tabular-nums">{{ number_format($level->latestValue, 2) }} {{ $level->station->unitLabel() }}</p>
                        <p class="text-sm text-ink-2">{{ $trendText }} <x-help term="trend" /></p>
                    </div>
                    @if ($level->relativeToTypical)
                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-ink-2">
                            <span class="shrink-0">{{ ucfirst($level->relativeToTypical) }} <x-help term="typical_range" /></span>
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
                    </p>
                    @if ($level->isStale())
                        <p class="mt-3 rounded-md border border-warn-line bg-warn-bg px-3 py-2 text-sm text-warn-text" role="status">
                            No reading from this gauge for {{ $level->latestAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }}.
                            The level shown is the last one received, not the current level. <x-help term="stale" />
                        </p>
                    @endif
                @endif
            </div>

            <h2 class="font-display mt-8 mb-3 text-lg font-semibold">Rainfall <x-help term="rainfall" /></h2>
            <div class="rounded-lg border border-line bg-surface p-5">
                @if ($rainfall === null)
                    <p class="text-sm text-ink-2">No rain gauge within {{ \App\Actions\Rivers\LinkNearestRainfallStations::MAX_DISTANCE_METRES / 1000 }} km of this spot.</p>
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
                        Rain gauge {{ number_format($rainfall->distanceMetres / 1000, 1) }} km from this spot, read {{ $rainfall->latestAt->diffForHumans() }}.
                        Rain upstream in the catchment may differ.
                    </p>
                    @if ($rainfall->isStale())
                        <p class="mt-3 rounded-md border border-warn-line bg-warn-bg px-3 py-2 text-sm text-warn-text" role="status">
                            No reading from this rain gauge for {{ $rainfall->latestAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }}.
                            Recent rain may be missing from these totals. <x-help term="stale" />
                        </p>
                    @endif
                @endif
            </div>
        </aside>
    </div>

    <section class="mt-10">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-display text-lg font-semibold">Upstream storm overflows <x-help term="upstream" /></h2>
            <span class="text-sm text-muted">Nearest first. Distances follow the water, including tributaries.</span>
        </div>
        @if ($conditions->upstreamMonitors->isEmpty())
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
                            <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Last spill</th>
                            <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Duration</th>
                            <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Spills, 30 days</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-2">
                        @foreach ($activeMonitors as $summary)
                            <x-overflow-row :summary="$summary" :spills="$spillsByMonitor->get($summary->monitor->id, 0)" />
                        @endforeach
                        @if ($activeMonitors->isEmpty())
                            <tr><td colspan="7" class="px-4 py-3 text-ink-2">No upstream monitor has spilled in the last 30 days.</td></tr>
                        @endif
                    </tbody>
                    @if ($quietMonitors->isNotEmpty())
                        <tbody class="divide-y divide-line-2" data-quiet-monitors hidden>
                            @foreach ($quietMonitors as $summary)
                                <x-overflow-row :summary="$summary" :spills="0" />
                            @endforeach
                        </tbody>
                    @endif
                </table>
            </div>
            @if ($quietMonitors->isNotEmpty())
                <p class="mt-2 text-sm text-muted">
                    {{ $quietMonitors->count() }} more {{ \Illuminate\Support\Str::plural('monitor', $quietMonitors->count()) }} upstream had no spill in the last 30 days.
                    <button type="button" data-show-quiet class="font-medium text-accent hover:underline">Show all {{ $conditions->upstreamMonitors->count() }}</button>
                </p>
            @endif
        @endif
    </section>
</x-layouts.app>
