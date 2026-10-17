{{-- Storm overflow monitors within a fixed distance of a beach or lake: those active in 30 days first, the rest tucked away. --}}
@props(['overflows', 'region' => null, 'allUrl' => null])

@php
    $spilledMonitorIds = $overflows->recentSpillEvents->pluck('overflow_monitor_id')->unique();
    [$active, $quiet] = $overflows->monitors->partition(fn ($summary) => $summary->status === \App\Enums\OverflowStatus::Discharging || $spilledMonitorIds->contains($summary->monitor->id));
    $spillCounts = $overflows->recentSpillEvents->countBy('overflow_monitor_id');
@endphp

<section {{ $attributes }}>
    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
        <div class="flex items-center gap-1.5">
            <h2 class="font-display text-lg font-semibold">Storm overflows within {{ $overflows->radiusMetres / 1000 }} km</h2>
            <x-help term="nearby_overflows" />
        </div>
        <span class="text-sm text-muted">Nearest first, straight-line distance.</span>
    </div>

    @if ($overflows->spillRecency === \App\Enums\SpillRecency::NotCovered)
        <p class="text-sm text-ink-2">We do not read the water company's overflow data here yet, so overflows within {{ $overflows->radiusMetres / 1000 }} km are not shown. <x-help term="not_covered" /> <x-overflow-map-link :region="$region" /></p>
    @elseif ($overflows->monitors->isEmpty())
        <p class="text-sm text-ink-2">No water company monitors within {{ $overflows->radiusMetres / 1000 }} km.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-line bg-surface">
            <table class="w-full text-sm">
                <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Monitor</th>
                        <th class="px-4 py-2.5 font-medium">Distance</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Last spill started</th>
                        <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Lasted</th>
                        <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Spills, 30 days</th>
                    </tr>
                </thead>
                @foreach (['active' => $active, 'quiet' => $quiet] as $group => $monitors)
                    <tbody class="divide-y divide-line-2" @if ($group === 'quiet' && $active->isNotEmpty()) data-quiet-monitors hidden @endif>
                        @foreach ($monitors as $summary)
                            @php($event = $summary->latestEvent)
                            <tr>
                                <td class="px-4 py-2.5">
                                    <x-overflow-link :monitor="$summary->monitor" />
                                    <span class="block text-xs text-muted">{{ $summary->monitor->intoName() }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono tabular-nums">{{ number_format($summary->distanceMetres / 1000, 1) }} km</td>
                                <td class="px-4 py-2.5">
                                    <x-overflow-status :monitor="$summary->monitor" />
                                    <span class="block text-xs text-muted sm:hidden">
                                        @if ($event)
                                            Last spill started {{ $event->started_at->diffForHumans() }}, {{ $event->isOngoing() ? 'ongoing' : 'lasted '.$event->durationLabel() }}
                                        @else
                                            No spill recorded
                                        @endif
                                        · {{ $spillCounts->get($summary->monitor->id, 0) }} in 30 days
                                    </span>
                                </td>
                                <td class="hidden whitespace-nowrap px-4 py-2.5 text-ink-2 sm:table-cell">{{ $event ? $event->started_at->diffForHumans() : 'None recorded' }}</td>
                                <td class="hidden whitespace-nowrap px-4 py-2.5 font-mono tabular-nums sm:table-cell">
                                    @if ($event === null)
                                        <span class="text-muted">-</span>
                                    @elseif ($event->isOngoing())
                                        <span class="font-medium text-alert">ongoing</span>
                                    @else
                                        {{ $event->durationLabel() }}
                                    @endif
                                </td>
                                <td class="hidden px-4 py-2.5 font-mono tabular-nums sm:table-cell">{{ $spillCounts->get($summary->monitor->id, 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>
        @if ($active->isNotEmpty() && $quiet->isNotEmpty())
            <p class="mt-2 text-sm text-muted">
                {{ $quiet->count() }} more {{ \Illuminate\Support\Str::plural('monitor', $quiet->count()) }} nearby had no spill in the last 30 days.
                <button type="button" data-show-quiet class="font-medium text-accent hover:underline">Show all {{ $overflows->monitors->count() }}</button>
            </p>
        @endif

        <div class="mt-6 flex flex-wrap items-baseline justify-between gap-2">
            <h3 class="text-sm font-semibold">Latest spills nearby</h3>
            @if ($allUrl)
                <a href="{{ $allUrl }}" class="text-sm font-medium text-accent hover:underline">All spills nearby</a>
            @endif
        </div>
        @if ($overflows->recentSpillEvents->isEmpty())
            <p class="mt-2 text-sm text-ink-2">{{ $overflows->spillRecency === \App\Enums\SpillRecency::Offline ? 'No spill recorded nearby in the last 30 days, but the monitors nearby are offline.' : 'No spill recorded nearby in the last 30 days.' }}</p>
        @else
            <div class="mt-2 overflow-x-auto rounded-lg border border-line bg-surface">
                <table class="w-full text-sm">
                    <caption class="sr-only">The latest spills from overflows nearby, newest first</caption>
                    <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">Started</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Overflow</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Lasted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-2">
                        @foreach ($overflows->recentSpillEvents->take(10) as $event)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs tabular-nums">{{ $event->started_at->copy()->timezone(config('app.display_timezone', 'Europe/London'))->format('D j M, H:i') }}</td>
                                <td class="px-4 py-2.5"><x-overflow-link :monitor="$event->overflowMonitor" /></td>
                                <td class="whitespace-nowrap px-4 py-2.5 font-mono tabular-nums"><x-spill-duration :spill="$event" :monitor="$event->overflowMonitor" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</section>
