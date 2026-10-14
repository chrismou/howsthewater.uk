@php
    $spot = $conditions->spot;
    $level = $conditions->level;
    $rainfall = $conditions->rainfall;

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
@endphp

<x-layouts.app :title="$spot->name.', '.$spot->river->name.': spills and river level'"
               :description="'Storm overflow spills upstream of '.$spot->name.' on the '.$spot->river->name.', plus the current river level and trend.'">
    <div class="mb-6">
        <p class="text-sm text-muted">
            <a href="{{ route('home') }}" class="hover:underline">All rivers</a>
            <span class="mx-1">/</span>
            <a href="{{ route('rivers.show', [$spot->river->region, $spot->river]) }}" class="hover:underline">{{ $spot->river->name }}</a>
        </p>
        <h1 class="font-display mt-1 text-3xl font-semibold tracking-tight">{{ $spot->name }}</h1>
        @if ($spot->description)
            <p class="mt-1 text-ink-2">{{ $spot->description }}</p>
        @endif
    </div>

    <section class="mb-8 rounded-lg border border-line bg-surface p-5">
        <div class="flex flex-wrap items-center gap-3">
            <x-spill-badge :recency="$conditions->spillRecency" :stale="$conditions->overflowDataIsStale()" class="text-sm px-3 py-1" />
            @if ($conditions->dataUpdatedAt)
                <span class="text-xs text-muted">Monitor data updated {{ $conditions->dataUpdatedAt->diffForHumans() }}</span>
            @endif
        </div>

        @if ($conditions->overflowDataIsStale())
            <div class="mt-4 rounded-md border border-warn-line bg-warn-bg px-4 py-3 text-sm text-warn-text" role="status">
                <p class="font-medium">Monitor data is out of date.</p>
                <p class="mt-1">
                    @if ($conditions->dataUpdatedAt)
                        The overflow feed has not updated for {{ $conditions->dataUpdatedAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }}
                        (last update {{ $conditions->dataUpdatedAt->format('j M, H:i') }}).
                    @else
                        The overflow feed gave no update time for these monitors.
                    @endif
                    The spill status and counts above describe the data as it was then, not what is happening now.
                </p>
            </div>
        @endif

        <dl class="mt-5 grid gap-4 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Discharging now</dt>
                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums">{{ $conditions->dischargingNowCount }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Spills, last 48h</dt>
                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums">{{ $conditions->spillsLast48HoursCount }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Spills, last 30 days</dt>
                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums">{{ $conditions->spillsLast30DaysCount }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-muted">Upstream monitors</dt>
                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums">
                    {{ $conditions->upstreamMonitors->count() }}
                    @if ($conditions->offlineCount > 0)
                        <span class="text-sm font-normal text-muted">{{ $conditions->offlineCount }} offline</span>
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

            <h2 class="font-display mt-8 mb-3 text-lg font-semibold">Last 30 days</h2>
            <x-spill-history :days="$conditions->dailyHistory" :events="$conditions->recentSpillEvents" />

            <h2 class="font-display mt-8 mb-3 text-lg font-semibold">Upstream storm overflows</h2>
            @if ($conditions->upstreamMonitors->isEmpty())
                <p class="text-sm text-ink-2">No monitors have been matched upstream of this spot yet.</p>
            @else
                <div class="overflow-hidden rounded-lg border border-line bg-surface">
                    <table class="w-full text-sm">
                        <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th class="px-4 py-2 font-medium">Monitor</th>
                                <th class="px-4 py-2 font-medium">Watercourse</th>
                                <th class="px-4 py-2 font-medium">Upstream</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2 font-medium">Last spill</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2">
                            @foreach ($conditions->upstreamMonitors as $summary)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $summary->monitor->external_id }}</td>
                                    <td class="px-4 py-2 text-ink-2">{{ $summary->river->name }}</td>
                                    <td class="px-4 py-2">{{ number_format($summary->distanceUpstreamMetres / 1000, 1) }} km</td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        @if ($summary->status === \App\Enums\OverflowStatus::Discharging)
                                            <span class="font-medium text-alert">{{ $summary->status->label() }}</span>
                                        @else
                                            {{ $summary->status->label() }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-ink-2">
                                        @if ($summary->latestEvent)
                                            {{ $summary->latestEvent->started_at->diffForHumans() }},
                                            {{ $summary->latestEvent->isOngoing() ? 'ongoing' : $summary->latestEvent->durationMinutes().' min' }}
                                        @else
                                            None recorded
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <aside class="lg:col-span-2">
            <h2 class="font-display mb-3 text-lg font-semibold">River level</h2>
            <div class="rounded-lg border border-line bg-surface p-5">
                @if ($level === null)
                    <p class="text-sm text-ink-2">No gauge is linked to this spot yet.</p>
                @elseif ($level->latestValue === null)
                    <p class="text-sm text-ink-2">No recent readings from the {{ $level->station->label }} gauge.</p>
                @else
                    <p class="font-mono text-3xl font-semibold tabular-nums">{{ number_format($level->latestValue, 2) }} {{ $level->station->unitLabel() }}</p>
                    <p class="mt-1 text-sm text-ink-2">{{ $trendText }}</p>
                    @if ($level->relativeToTypical)
                        <p class="mt-1 text-sm text-ink-2">
                            {{ ucfirst($level->relativeToTypical) }}
                            ({{ number_format($level->station->typical_range_low, 2) }} to {{ number_format($level->station->typical_range_high, 2) }} {{ $level->station->unitLabel() }})
                        </p>
                    @endif
                    @if ($level->station->isAboveOrdnanceDatum())
                        <p class="mt-1 text-xs text-muted">This gauge reports height above sea level, not depth above the river bed.</p>
                    @endif
                    <p class="mt-3 text-xs text-muted">
                        {{ $level->station->label }} gauge, read {{ $level->latestAt->diffForHumans() }}
                    </p>
                    @if ($level->isStale())
                        <p class="mt-2 rounded-md border border-warn-line bg-warn-bg px-3 py-2 text-sm text-warn-text" role="status">
                            No reading from this gauge for {{ $level->latestAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }}.
                            The level shown is the last one received, not the current level.
                        </p>
                    @endif
                    <div class="mt-4 h-24 text-accent" data-sparkline="{{ json_encode($sparkline) }}"></div>
                @endif
            </div>

            <h2 class="font-display mt-8 mb-3 text-lg font-semibold">Rainfall</h2>
            <div class="rounded-lg border border-line bg-surface p-5">
                @if ($rainfall === null)
                    <p class="text-sm text-ink-2">No rain gauge within {{ \App\Actions\Rivers\LinkNearestRainfallStations::MAX_DISTANCE_METRES / 1000 }} km of this spot.</p>
                @elseif (! $rainfall->hasReadings())
                    <p class="text-sm text-ink-2">No recent readings from the nearest rain gauge.</p>
                @else
                    <p class="font-mono text-3xl font-semibold tabular-nums">{{ number_format($rainfall->last24HoursMillimetres, 1) }} mm</p>
                    <p class="mt-1 text-sm text-ink-2">in the last 24 hours</p>
                    <dl class="mt-3 flex gap-x-6 text-sm text-ink-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-muted">Last 6 hours</dt>
                            <dd class="font-medium text-ink">{{ number_format($rainfall->last6HoursMillimetres, 1) }} mm</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-muted">Last 3 days</dt>
                            <dd class="font-medium text-ink">{{ number_format($rainfall->last72HoursMillimetres, 1) }} mm</dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-xs text-muted">
                        Rain gauge {{ number_format($rainfall->distanceMetres / 1000, 1) }} km from this spot, read {{ $rainfall->latestAt->diffForHumans() }}.
                        Rain upstream in the catchment may differ.
                    </p>
                    @if ($rainfall->isStale())
                        <p class="mt-2 rounded-md border border-warn-line bg-warn-bg px-3 py-2 text-sm text-warn-text" role="status">
                            No reading from this rain gauge for {{ $rainfall->latestAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }}.
                            Recent rain may be missing from these totals.
                        </p>
                    @endif
                @endif
            </div>
        </aside>
    </div>
</x-layouts.app>
