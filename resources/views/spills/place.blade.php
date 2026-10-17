@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $place = $history->place;
    $relation = $history->relation();
    $today = now($timezone);
    $hours = fn (float $value) => $value >= 10 ? number_format($value) : number_format($value, 1);
    $recordedFrom = $history->recordingSince?->copy()->timezone($timezone);
@endphp

<x-layouts.app :title="$history->title()" :description="$history->description()" :breadcrumbs="$history->breadcrumbs()" :region="$history->region"
               :noindex="$history->monitorCount() === 0 || $spills->currentPage() > 1" :date-modified="$history->dataUpdatedAt" :share-image="$shareImage">
    <div class="mb-6">
        <x-breadcrumbs :items="$history->breadcrumbs()" />
        <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $history->heading() }}</h1>
        <p class="mt-1 max-w-2xl text-ink-2">
            @if ($history->monitorCount() > 0)
                Every spill from the {{ $history->monitorCount() }} storm {{ \Illuminate\Support\Str::plural('overflow', $history->monitorCount()) }} {{ $history->where() }}@if ($recordedFrom), since we started recording them on {{ $recordedFrom->format('j F Y') }}@endif.
            @endif
            <a href="{{ $history->placeUrl() }}" class="text-accent hover:underline">Back to {{ $place->name }}</a>.
        </p>
    </div>

    @if ($history->dataIsStale())
        <div class="mb-8 rounded-md border border-warn-line bg-warn-bg px-4 py-3 text-sm text-warn-text" role="status">
            <p class="font-medium">Monitor data is out of date. <x-help term="stale" /></p>
            <p class="mt-1">
                @if ($history->dataUpdatedAt)
                    No overflow here has reported for {{ $history->dataUpdatedAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }} (last report {{ $history->dataUpdatedAt->copy()->timezone($timezone)->format('j M, H:i T') }}).
                @else
                    The overflow feeds here gave no report time.
                @endif
                The figures run to the last report, not to now.
            </p>
        </div>
    @endif

    @if ($history->monitorCount() === 0)
        <p class="rounded-lg border border-line bg-surface p-5 text-sm text-ink-2">No storm overflow monitors have been matched {{ $history->where() }} yet.</p>
    @else
        <section class="mb-8 rounded-lg border border-line bg-surface p-5">
            <div class="flex items-center gap-1.5">
                <h2 class="text-xs uppercase tracking-wide text-muted">Hours with an overflow {{ $relation }} spilling</h2>
                <x-help term="spill_hours" />
            </div>
            <dl class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-4">
                @foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 'year' => $history->yearLabel()] as $window => $label)
                    <div>
                        <dt class="text-xs text-muted">{{ $label }}</dt>
                        <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums">{{ $hours($history->record[$window]['hours']) }} h <span class="font-sans text-sm font-normal text-muted">{{ $history->record[$window]['spills'] }} {{ \Illuminate\Support\Str::plural('spill', $history->record[$window]['spills']) }}</span></dd>
                    </div>
                @endforeach
                <div>
                    <dt class="text-xs text-muted">Days with a spill, last 30</dt>
                    <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums">{{ $history->daysWithSpillLast30 }}</dd>
                </div>
            </dl>
        </section>

        <div class="grid gap-8 lg:grid-cols-5">
            <div class="min-w-0 lg:col-span-3">
                <div class="mb-3 flex items-center gap-1.5">
                    <h2 class="font-display text-lg font-semibold">Last 30 days</h2>
                    <x-help term="history" />
                </div>
                <x-spill-rain-chart :days="$history->dailyHistory" :rainfall="$history->dailyRainfall" :gauge="$history->rainfallStation?->label" />
            </div>

            <aside class="lg:col-span-2">
                <h2 class="font-display mb-3 text-lg font-semibold">By month</h2>
                <div class="overflow-x-auto rounded-lg border border-line bg-surface">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Spills and hours with an overflow {{ $relation }} spilling, by month</caption>
                        <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5 font-medium">Month</th>
                                <th scope="col" class="px-4 py-2.5 font-medium">Spills</th>
                                <th scope="col" class="px-4 py-2.5 font-medium">Hours</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2 font-mono tabular-nums">
                            @foreach (array_reverse($history->months) as $month)
                                <tr>
                                    <th scope="row" class="px-4 py-2.5 text-left font-sans font-normal">{{ $month['month']->format('F Y') }}{{ $month['month']->isSameMonth($today) ? ' so far' : '' }}</th>
                                    <td class="px-4 py-2.5">{{ $month['spills'] }}</td>
                                    <td class="px-4 py-2.5">{{ $hours($month['hours']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </aside>
        </div>

        @if ($history->busiest !== [])
            <section class="mt-10">
                <h2 class="font-display mb-3 text-lg font-semibold">Busiest overflows {{ $relation }}, {{ lcfirst($history->yearLabel()) }}</h2>
                <div class="overflow-x-auto rounded-lg border border-line bg-surface">
                    <table class="w-full text-sm">
                        <caption class="sr-only">The overflows {{ $relation }} with the most hours spilling this year</caption>
                        <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5 font-medium">Overflow</th>
                                <th scope="col" class="hidden px-4 py-2.5 font-medium sm:table-cell">{{ $history->isSpot() ? 'Upstream' : 'Distance' }}</th>
                                <th scope="col" class="px-4 py-2.5 font-medium">Spills</th>
                                <th scope="col" class="px-4 py-2.5 font-medium">Hours</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2">
                            @foreach ($history->busiest as $row)
                                <tr>
                                    <td class="px-4 py-2.5">
                                        <x-overflow-link :monitor="$row['monitor']" />
                                        <span class="block text-xs text-muted">{{ $row['monitor']->intoName() }}</span>
                                    </td>
                                    <td class="hidden whitespace-nowrap px-4 py-2.5 font-mono tabular-nums sm:table-cell">{{ $history->distanceLabel($row['monitor']) }}</td>
                                    <td class="px-4 py-2.5 font-mono tabular-nums">{{ $row['spills'] }}</td>
                                    <td class="px-4 py-2.5 font-mono tabular-nums">{{ $hours($row['hours']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section id="spills" class="mt-10 scroll-mt-24">
            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-display text-lg font-semibold">Every spill recorded <span class="font-mono tabular-nums text-muted">{{ $spills->total() }}</span></h2>
                <span class="text-sm text-muted">Newest first. Any spill from before we started recording an overflow is the latest its feed reported then.</span>
            </div>
            @if ($spills->isEmpty())
                <p class="text-sm text-ink-2">No spill recorded yet.</p>
            @else
                <div class="overflow-x-auto rounded-lg border border-line bg-surface">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Every spill from the overflows {{ $relation }}, newest first</caption>
                        <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5 font-medium">Started</th>
                                <th scope="col" class="px-4 py-2.5 font-medium">Overflow</th>
                                <th scope="col" class="hidden px-4 py-2.5 font-medium sm:table-cell">{{ $history->isSpot() ? 'Upstream' : 'Distance' }}</th>
                                <th scope="col" class="px-4 py-2.5 font-medium">Lasted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2">
                            @foreach ($spills as $spill)
                                @php
                                    $monitor = $spill->overflowMonitor;
                                    $started = $spill->started_at->copy()->timezone($timezone);
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-2.5 font-mono text-xs tabular-nums">{{ $started->format($started->isSameYear($today) ? 'D j M, H:i' : 'j M Y, H:i') }}</td>
                                    <td class="px-4 py-2.5">
                                        <x-overflow-link :monitor="$monitor" />
                                        <span class="block text-xs text-muted">{{ $monitor->intoName() }}</span>
                                    </td>
                                    <td class="hidden whitespace-nowrap px-4 py-2.5 font-mono tabular-nums sm:table-cell">{{ $history->distanceLabel($monitor) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2.5 font-mono tabular-nums"><x-spill-duration :spill="$spill" :monitor="$monitor" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($spills->hasPages())
                    <nav class="mt-3 flex items-center justify-between text-sm" aria-label="Spill pages">
                        @if ($spills->onFirstPage())
                            <span></span>
                        @else
                            <a href="{{ $spills->previousPageUrl() }}" class="font-medium text-accent hover:underline" rel="prev">Newer spills</a>
                        @endif
                        <span class="text-muted">Page {{ $spills->currentPage() }} of {{ $spills->lastPage() }}</span>
                        @if ($spills->hasMorePages())
                            <a href="{{ $spills->nextPageUrl() }}" class="font-medium text-accent hover:underline" rel="next">Older spills</a>
                        @else
                            <span></span>
                        @endif
                    </nav>
                @endif
            @endif
        </section>
    @endif

    <p class="mt-8 text-xs text-muted">Monitors report when an overflow is discharging, not how much or what it carries. Our record is built from the water companies' live feeds, read every 15 minutes, so a spill that starts and stops between two reads can be missed.</p>
</x-layouts.app>
