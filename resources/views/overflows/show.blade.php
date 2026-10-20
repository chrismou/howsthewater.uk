@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $monitor = $page->monitor;
    $name = $page->name();
    $latestReturn = $page->annualReturns->first();
    $today = now($timezone);
    $breadcrumbs = [['Home', route('home')], [$page->region->name, route('regions.show', $page->region)], ['Spills', route('spills.region', $page->region)], [$name, route('overflows.show', $monitor)]];

    // Built here for the data attribute, as Blade's multi-line @json breaks inside attributes.
    $mapMarkers = collect([[
        'lat' => $monitor->latitude,
        'lng' => $monitor->longitude,
        'label' => $name,
        'kind' => 'monitor',
        'recency' => $page->spillRecency()->value,
        'note' => $page->mapNote(),
    ]])->concat($page->places->map(fn ($link) => [
        'lat' => $link->place->latitude,
        'lng' => $link->place->longitude,
        'label' => $link->placeLabel(),
        'url' => $link->placeUrl(),
        'note' => ucfirst($link->distanceLabel()),
        'link' => 'View '.$link->placeKind(),
        'kind' => 'place',
    ]))->values();

    $recordedFrom = $page->recordingSince->copy()->timezone($timezone);
    $hasPublishedLog = $page->coveredFrom->lessThan($page->recordingSince);
    $statusSince = $monitor->status_started_at?->copy()->timezone($timezone);
    $hours = fn (float $value) => $value >= 10 ? number_format($value) : number_format($value, 1);
@endphp

<x-layouts.app :title="$page->title()" :description="$page->description()" :breadcrumbs="$breadcrumbs" :region="$page->region"
               :noindex="! $page->hasContent() || $spills->currentPage() > 1"
               :date-modified="$monitor->latest_event_ended_at ?? $monitor->latest_event_started_at" :feed="route('overflows.feed', $monitor)" :share-image="$monitor->shareImage">
    <div class="mb-6">
        <x-breadcrumbs :items="$breadcrumbs" />
        <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $name }}</h1>
        <p class="mt-1 text-ink-2">
            {{ ucfirst($monitor->companyPhrase()) }}{{ $page->intoPhrase() ? ' '.$page->intoPhrase() : '' }}.
            <span class="text-muted">Monitor <span class="font-mono text-xs">{{ $monitor->external_id }}</span>@if ($latestReturn?->permit_reference), permit <span class="font-mono text-xs">{{ $latestReturn->permit_reference }}</span>@endif.</span>
            <a href="{{ route('overflows.feed', $monitor) }}" class="whitespace-nowrap text-accent hover:underline">Follow its spills by RSS</a>
        </p>
    </div>

    <section class="mb-8 rounded-lg border border-line bg-surface p-5">
        <p class="text-lg">
            <x-overflow-status :monitor="$monitor" /><span class="text-ink-2">.</span>
            @if ($monitor->isDischarging() && ! $monitor->isStale() && $statusSince)
                <span class="text-ink-2">Since {{ $statusSince->format($statusSince->isSameDay($today) ? 'H:i' : 'D j M, H:i') }}, {{ $statusSince->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }} so far.</span>
            @elseif (! $monitor->isDischarging() && $spills->currentPage() === 1 && $spills->first()?->ended_at)
                <span class="text-ink-2">Last spill ended {{ $spills->first()->ended_at->diffForHumans() }}.</span>
            @endif
        </p>
        @if ($monitor->source_updated_at)
            <p class="mt-1 text-sm text-muted">Monitor last reported {{ $monitor->source_updated_at->diffForHumans() }} · <span class="font-mono text-xs">{{ $monitor->source_updated_at->copy()->timezone($timezone)->format('j M, H:i T') }}</span></p>
        @endif

        <div class="mt-5 flex items-center gap-1.5">
            <h2 class="text-xs uppercase tracking-wide text-muted">Spills</h2>
            <x-help term="our_record" />
        </div>
        <dl class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <div>
                <dt class="text-xs text-muted">Last 7 days</dt>
                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums">{{ $page->record[7]['spills'] }} <span class="font-sans text-sm font-normal text-muted">{{ \Illuminate\Support\Str::plural('spill', $page->record[7]['spills']) }}, {{ $hours($page->record[7]['hours']) }} h</span></dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Last 30 days</dt>
                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums">{{ $page->record[30]['spills'] }} <span class="font-sans text-sm font-normal text-muted">{{ \Illuminate\Support\Str::plural('spill', $page->record[30]['spills']) }}, {{ $hours($page->record[30]['hours']) }} h</span></dd>
            </div>
            <div>
                <dt class="text-xs text-muted">{{ $page->yearLabel() }}</dt>
                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums">{{ $page->record['year']['spills'] }} <span class="font-sans text-sm font-normal text-muted">{{ \Illuminate\Support\Str::plural('spill', $page->record['year']['spills']) }}, {{ $hours($page->record['year']['hours']) }} h</span></dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Days with a spill, last 30</dt>
                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums">{{ $page->daysWithSpillLast30() }}</dd>
            </div>
            <div>
                <dt class="text-xs text-muted">Longest spill</dt>
                <dd class="mt-1 font-mono text-2xl font-semibold tabular-nums">{{ $page->longestSpillLabel() ?? '-' }}</dd>
            </div>
        </dl>
        @if ($page->uptimePercent !== null)
            <p class="mt-4 text-sm text-ink-2">Monitor reporting <span class="font-mono tabular-nums">{{ number_format($page->uptimePercent, 1) }}%</span> of the time since {{ $page->uptimeSince->copy()->timezone($timezone)->format('j F Y') }}. <x-help term="uptime" /></p>
        @endif
    </section>

    <div class="grid gap-8 lg:grid-cols-5">
        <div class="min-w-0 lg:col-span-3">
            <div class="mb-3 flex items-center gap-1.5">
                <h2 class="font-display text-lg font-semibold">Last 30 days</h2>
                <x-help term="history" />
            </div>
            <x-spill-rain-chart :days="$page->dailyHistory" :rainfall="$page->dailyRainfall" :gauge="$page->rainfallStation?->nameInSentence()" />

            <div class="mt-8 mb-3 flex items-center gap-1.5">
                <h2 class="font-display text-lg font-semibold">Last 12 months</h2>
                <x-help term="our_record" />
            </div>
            <x-spill-rain-months :months="$page->months" :notes="$page->notes" :gauge="$page->rainfallStation?->nameInSentence()" />

            <div class="mt-8 mb-3 flex items-center gap-1.5">
                <h2 class="font-display text-lg font-semibold">Year by year</h2>
                <x-help term="annual_return" />
            </div>
            @if ($page->annualReturns->isEmpty())
                <p class="rounded-lg border border-line bg-surface p-5 text-sm text-ink-2">No annual return has been matched to this overflow yet.@if ($monitor->company === 'Scottish Water') Scottish Water's yearly figures are not read yet.@endif</p>
            @else
                <div class="overflow-x-auto rounded-lg border border-line bg-surface">
                    <table class="w-full text-sm">
                        <caption class="sr-only">{{ $page->company() }}'s annual returns for this overflow, newest first</caption>
                        <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5 font-medium">Year</th>
                                <th scope="col" class="px-4 py-2.5 font-medium">Counted spills</th>
                                <th scope="col" class="px-4 py-2.5 font-medium">Hours</th>
                                <th scope="col" class="px-4 py-2.5 font-medium">Monitor working</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2 font-mono tabular-nums">
                            @foreach ($page->annualReturns as $return)
                                <tr>
                                    <td class="px-4 py-2.5 font-sans">{{ $return->year }}</td>
                                    <td class="px-4 py-2.5">{{ $return->counted_spills ?? '-' }}</td>
                                    <td class="px-4 py-2.5">{{ $return->total_hours !== null ? $hours((float) $return->total_hours) : '-' }}</td>
                                    <td class="px-4 py-2.5">{{ $return->monitor_uptime_percent !== null ? number_format((float) $return->monitor_uptime_percent, 1).'%' : '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-muted">From {{ $page->company() }}'s annual returns, which count spills in 12 and 24 hour blocks, so their counts run lower than the separate spills in our record. Produced by The Rivers Trust from Environment Agency and Natural Resources Wales data, Open Government Licence.</p>
            @endif
        </div>

        <aside class="lg:col-span-2">
            <div
                class="h-72 w-full rounded-lg border border-line"
                role="region" aria-label="Map of {{ $name }} and the places downstream"
                data-map
                data-map-centreline="{{ json_encode($page->centreline ?? []) }}"
                data-map-markers="{{ json_encode($mapMarkers) }}"
            ></div>
            <x-map-key :neutral="$page->places->isNotEmpty() ? 'Place it reaches' : null" />

            <h2 class="font-display mt-8 mb-3 text-lg font-semibold">Places it reaches</h2>
            @if ($page->places->isEmpty())
                <p class="text-sm text-ink-2">No spot, beach or lake on this site is downstream or within 3 km.</p>
            @else
                <ul class="divide-y divide-line-2 rounded-lg border border-line bg-surface text-sm">
                    @foreach ($page->places as $link)
                        <li class="flex items-baseline justify-between gap-3 px-4 py-2.5">
                            <a href="{{ $link->placeUrl() }}" class="font-medium hover:underline">{{ $link->placeLabel() }}</a>
                            <span class="whitespace-nowrap font-mono text-xs text-muted">{{ $link->distanceLabel() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </aside>
    </div>

    <section id="spills" class="mt-10 scroll-mt-24">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h2 class="font-display text-lg font-semibold">Every spill recorded<x-heading-count :count="$spills->total()" /></h2>
            @if ($hasPublishedLog)
                <span class="text-sm text-muted">Newest first. From its live feed since {{ $recordedFrom->format('j F Y') }}, and from {{ $page->company() }}'s published records before that.</span>
            @else
                <span class="text-sm text-muted">Since we first saw this overflow on {{ $recordedFrom->format('j F Y') }}. Any earlier spill is the latest the feed reported then.</span>
            @endif
        </div>
        @if ($spills->isEmpty())
            <p class="text-sm text-ink-2">No spill recorded yet.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-line bg-surface">
                <table class="w-full text-sm">
                    <caption class="sr-only">Every spill recorded from this overflow, newest first</caption>
                    <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">Started</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Ended</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Lasted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-2 font-mono text-xs tabular-nums">
                        @foreach ($spills as $spill)
                            @php
                                $started = $spill->started_at->copy()->timezone($timezone);
                                $ended = $spill->ended_at?->copy()->timezone($timezone);
                            @endphp
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2.5">{{ $started->format('D j M Y, H:i') }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5">
                                    @if ($ended === null)
                                        <x-spill-duration :spill="$spill" :monitor="$monitor" />
                                    @else
                                        {{ $ended->format($ended->isSameDay($started) ? 'H:i' : 'D j M, H:i') }}
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5">{{ $ended === null && $monitor->isStale() ? '-' : $spill->durationLabel() }}</td>
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

    <p class="mt-8 text-xs text-muted">
        Live status from {{ $page->company() }}'s storm overflow feed, read every 15 minutes. Monitors report when an overflow is discharging, not how much or what it carries.
        <a href="{{ route('spills.region', $page->region) }}" class="underline hover:text-ink">All spills in {{ $page->region->nameInSentence() }} now</a>.
    </p>
</x-layouts.app>
