@php
    $spot = $conditions->spot;
    $level = $conditions->level;

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
        <p class="text-sm text-stone-500">
            <a href="{{ route('home') }}" class="hover:underline">All rivers</a>
            <span class="mx-1">/</span>
            <a href="{{ route('rivers.show', $spot->river) }}" class="hover:underline">{{ $spot->river->name }}</a>
        </p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight">{{ $spot->name }}</h1>
        @if ($spot->description)
            <p class="mt-1 text-stone-600">{{ $spot->description }}</p>
        @endif
    </div>

    <section class="mb-8 rounded-lg border border-stone-200 bg-white p-5">
        <div class="flex flex-wrap items-center gap-3">
            <x-spill-badge :recency="$conditions->spillRecency" :stale="$conditions->overflowDataIsStale()" class="text-sm px-3 py-1" />
            @if ($conditions->dataUpdatedAt)
                <span class="text-xs text-stone-500">Monitor data updated {{ $conditions->dataUpdatedAt->diffForHumans() }}</span>
            @endif
        </div>

        @if ($conditions->overflowDataIsStale())
            <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
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
                <dt class="text-xs uppercase tracking-wide text-stone-500">Discharging now</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $conditions->dischargingNowCount }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-stone-500">Spills, last 48h</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $conditions->spillsLast48HoursCount }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-stone-500">Spills, last 30 days</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $conditions->spillsLast30DaysCount }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-stone-500">Upstream monitors</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ $conditions->upstreamMonitors->count() }}</dd>
            </div>
        </dl>
    </section>

    <div class="grid gap-8 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <div
                class="h-80 w-full rounded-lg border border-stone-200"
                data-map
                data-map-centreline="{{ json_encode($spot->river->centreline ?? []) }}"
                data-map-markers="{{ json_encode($mapMarkers) }}"
            ></div>

            <h2 class="mt-8 mb-3 text-lg font-semibold">Last 30 days</h2>
            <x-spill-history :days="$conditions->dailyHistory" :events="$conditions->recentSpillEvents" />

            <h2 class="mt-8 mb-3 text-lg font-semibold">Upstream storm overflows</h2>
            @if ($conditions->upstreamMonitors->isEmpty())
                <p class="text-sm text-stone-600">No monitors have been matched upstream of this spot yet.</p>
            @else
                <div class="overflow-hidden rounded-lg border border-stone-200 bg-white">
                    <table class="w-full text-sm">
                        <thead class="bg-stone-50 text-left text-xs uppercase tracking-wide text-stone-500">
                            <tr>
                                <th class="px-4 py-2 font-medium">Monitor</th>
                                <th class="px-4 py-2 font-medium">Watercourse</th>
                                <th class="px-4 py-2 font-medium">Upstream</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                                <th class="px-4 py-2 font-medium">Last spill</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($conditions->upstreamMonitors as $summary)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $summary->monitor->external_id }}</td>
                                    <td class="px-4 py-2 text-stone-600">{{ $summary->river->name }}</td>
                                    <td class="px-4 py-2">{{ number_format($summary->distanceUpstreamMetres / 1000, 1) }} km</td>
                                    <td class="px-4 py-2 whitespace-nowrap">
                                        @if ($summary->status === \App\Enums\OverflowStatus::Discharging)
                                            <span class="font-medium text-red-700">{{ $summary->status->label() }}</span>
                                        @else
                                            {{ $summary->status->label() }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-stone-600">
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
            <h2 class="mb-3 text-lg font-semibold">River level</h2>
            <div class="rounded-lg border border-stone-200 bg-white p-5">
                @if ($level === null)
                    <p class="text-sm text-stone-600">No gauge is linked to this spot yet.</p>
                @elseif ($level->latestValue === null)
                    <p class="text-sm text-stone-600">No recent readings from the {{ $level->station->label }} gauge.</p>
                @else
                    <p class="text-3xl font-semibold">{{ number_format($level->latestValue, 2) }} {{ $level->station->unitLabel() }}</p>
                    <p class="mt-1 text-sm text-stone-600">{{ $trendText }}</p>
                    @if ($level->relativeToTypical)
                        <p class="mt-1 text-sm text-stone-600">
                            {{ ucfirst($level->relativeToTypical) }}
                            ({{ number_format($level->station->typical_range_low, 2) }} to {{ number_format($level->station->typical_range_high, 2) }} {{ $level->station->unitLabel() }})
                        </p>
                    @endif
                    @if ($level->station->isAboveOrdnanceDatum())
                        <p class="mt-1 text-xs text-stone-500">This gauge reports height above sea level, not depth above the river bed.</p>
                    @endif
                    <p class="mt-3 text-xs text-stone-500">
                        {{ $level->station->label }} gauge, read {{ $level->latestAt->diffForHumans() }}
                    </p>
                    @if ($level->isStale())
                        <p class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" role="status">
                            No reading from this gauge for {{ $level->latestAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }}.
                            The level shown is the last one received, not the current level.
                        </p>
                    @endif
                    <div class="mt-4 h-24" data-sparkline="{{ json_encode($sparkline) }}"></div>
                @endif
            </div>
        </aside>
    </div>
</x-layouts.app>
