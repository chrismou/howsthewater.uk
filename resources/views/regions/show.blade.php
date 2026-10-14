@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $spots = $summary->spots();
    $aboveTypical = $summary->spotsAboveTypicalRange();
    $discharging = $summary->spotsDischargingNow();
    $wettest = $summary->wettestSpots();

    $mapMarkers = $spots->map(fn ($spot) => [
        'lat' => $spot->latitude,
        'lng' => $spot->longitude,
        'label' => $spot->name,
        'url' => route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]),
        'kind' => 'spot',
        'recency' => $spot->spillRecency->value,
    ])->values();

    $dotColour = fn ($spot) => match (true) {
        $spot->overflowDataIsStale() => 'bg-pill-grey-ring',
        default => match ($spot->spillRecency) {
            \App\Enums\SpillRecency::DischargingNow => 'bg-alert',
            \App\Enums\SpillRecency::Within24Hours, \App\Enums\SpillRecency::Within48Hours => 'bg-spill',
            \App\Enums\SpillRecency::None => 'bg-pill-green-text',
            default => 'bg-pill-grey-ring',
        },
    };
@endphp

<x-layouts.app :title="$summary->name.' rivers: spills, levels and rainfall'"
               :description="'Storm overflow spills, river levels and rainfall for every spot on '.$summary->name.'\'s rivers, listed source to mouth.'"
               :region="$region">
    <div class="mb-6">
        <p class="text-sm text-muted"><a href="{{ route('home') }}" class="hover:underline">All regions</a> <span class="mx-1">/</span> {{ $summary->name }}</p>
        <div class="mt-1 flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
            <div class="max-w-2xl">
                <h1 class="font-display text-4xl font-semibold tracking-tight">{{ $summary->name }}</h1>
                <p class="mt-2 text-ink-2">
                    {{ $summary->rivers->count() }} {{ \Illuminate\Support\Str::plural('river', $summary->rivers->count()) }} and {{ $spots->count() }} spots, listed source to mouth.
                    @if ($summary->dataUpdatedAt)
                        Monitor data updated {{ $summary->dataUpdatedAt->diffForHumans() }}.
                    @endif
                </p>
            </div>
            <dl class="flex flex-wrap gap-x-7 gap-y-3">
                <div>
                    <dd class="font-mono text-3xl font-semibold tabular-nums {{ $summary->dischargingNowCount() > 0 ? 'text-alert' : '' }}">{{ $summary->dischargingNowCount() }}</dd>
                    <dt class="text-xs text-muted">discharging now <x-help term="discharging_now" /></dt>
                </div>
                <div>
                    <dd class="font-mono text-3xl font-semibold tabular-nums {{ $summary->spotsWithSpillLast24HoursCount() > 0 ? 'text-spill' : '' }}">{{ $summary->spotsWithSpillLast24HoursCount() }}</dd>
                    <dt class="text-xs text-muted">spots, spill in 24h <x-help term="spill" /></dt>
                </div>
                <div>
                    <dd class="font-mono text-3xl font-semibold tabular-nums">{{ $aboveTypical->count() }}</dd>
                    <dt class="text-xs text-muted">gauges above typical <x-help term="typical_range" /></dt>
                </div>
                <div>
                    <dd class="font-mono text-3xl font-semibold tabular-nums">{{ number_format($summary->monitorCount) }}</dd>
                    <dt class="text-xs text-muted">monitors</dt>
                </div>
            </dl>
        </div>
    </div>

    @if ($summary->dataIsStale())
        <div class="mb-6 rounded-md border border-warn-line bg-warn-bg px-4 py-3 text-sm text-warn-text" role="status">
            <span class="font-medium">Monitor data is out of date.</span>
            The overflow feed has not updated for {{ $summary->dataUpdatedAt?->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) ?? 'an unknown time' }}, so spill statuses describe the data as it was then. <x-help term="stale" />
        </div>
    @endif

    <x-flood-warning-notice :summary="$summary" />

    <div class="relative mb-10">
        <div
            class="w-full rounded-lg border border-line {{ $mapIsTall ? 'h-96 sm:h-[640px]' : 'h-72 sm:h-[420px]' }}"
            data-map
            data-map-centrelines="{{ json_encode($centrelines) }}"
            data-map-markers="{{ json_encode($mapMarkers) }}"
        ></div>
        <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-label="Map key">
            <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#b91c1c"></span>Discharging now</li>
            <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#c2410c"></span>Spill in 48h</li>
            <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#047857"></span>No spills in 48h</li>
            <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#78716c"></span>No data or offline</li>
        </ul>
    </div>

    <div class="mb-12 grid gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2">
            <h2 class="font-display text-2xl font-semibold tracking-tight">Every river, source to mouth</h2>
            <p class="mt-1 text-sm text-muted">Each dot is a spot. Hover or tap for its name, click to open it.</p>
            <div class="mt-4 rounded-lg border border-line bg-surface px-5 py-2">
                @foreach ($summary->rivers as $river)
                    <div class="flex items-center gap-4 py-2.5 {{ $loop->first ? '' : 'border-t border-line-2' }}">
                        <a href="{{ route('rivers.show', [$summary->slug, $river->slug]) }}" class="w-24 shrink-0 text-sm font-semibold hover:underline">{{ \Illuminate\Support\Str::after($river->name, 'River ') }}</a>
                        <div class="relative h-0.5 flex-1 rounded-full bg-line">
                            @foreach ($river->spots as $spot)
                                @php($left = $river->lengthMetres && $spot->riverMetres !== null ? max(1, min(99, round($spot->riverMetres / $river->lengthMetres * 100))) : 50)
                                <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}"
                                   class="absolute -top-[5px] block h-3 w-3 -translate-x-1/2 rounded-full border-2 border-surface {{ $dotColour($spot) }} hover:scale-125"
                                   style="left: {{ $left }}%" title="{{ $spot->name }}: {{ $spot->spillRecency->label() }}"><span class="sr-only">{{ $spot->name }}</span></a>
                            @endforeach
                        </div>
                        <span class="w-14 shrink-0 text-right font-mono text-xs text-muted">{{ $river->lengthMetres ? number_format($river->lengthMetres / 1000).' km' : '' }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <aside>
            <h2 class="font-display text-2xl font-semibold tracking-tight">Right now</h2>
            <p class="mt-1 text-sm text-muted">
                @if ($summary->dataUpdatedAt)
                    Updated <span class="font-mono">{{ $summary->dataUpdatedAt->timezone($timezone)->format('H:i') }}</span>.
                @endif
            </p>
            <div class="mt-4 divide-y divide-line-2 rounded-lg border border-line bg-surface">
                <div class="px-4 py-4">
                    <div class="flex items-baseline justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide {{ $discharging->isNotEmpty() ? 'text-alert' : 'text-muted' }}">Discharging now</span>
                        <span class="font-mono text-lg font-semibold {{ $discharging->isNotEmpty() ? 'text-alert' : '' }}">{{ $discharging->count() }}</span>
                    </div>
                    @forelse ($discharging as $spot)
                        <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}" class="mt-2 flex justify-between gap-3 text-sm hover:underline">
                            <span class="font-medium">{{ $spot->name }}</span>
                            <span class="text-muted">{{ \Illuminate\Support\Str::after($spot->riverName, 'River ') }} · {{ $spot->dischargingNowCount }} {{ \Illuminate\Support\Str::plural('monitor', $spot->dischargingNowCount) }}</span>
                        </a>
                    @empty
                        <p class="mt-2 text-sm text-ink-2">No upstream overflow is discharging at any spot.</p>
                    @endforelse
                </div>
                <div class="px-4 py-4">
                    <div class="flex items-baseline justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted">Above typical range</span>
                        <span class="font-mono text-lg font-semibold">{{ $aboveTypical->count() }}</span>
                    </div>
                    @forelse ($aboveTypical->take(4) as $spot)
                        <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}" class="mt-2 flex justify-between gap-3 text-sm hover:underline">
                            <span class="font-medium">{{ \Illuminate\Support\Str::after($spot->riverName, 'River ') }} at {{ $spot->name }}</span>
                            <span class="font-mono text-muted">{{ number_format($spot->levelValue, 2) }} {{ $spot->levelUnit }}</span>
                        </a>
                    @empty
                        <p class="mt-2 text-sm text-ink-2">Every gauge is within or below its typical range.</p>
                    @endforelse
                </div>
                <div class="px-4 py-4">
                    <div class="flex items-baseline justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted">Most rain, last 24h</span>
                        <span class="font-mono text-xs text-muted">mm</span>
                    </div>
                    @forelse ($wettest as $spot)
                        <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}" class="mt-2 flex justify-between gap-3 text-sm hover:underline">
                            <span class="font-medium">{{ $spot->name }}</span>
                            <span class="font-mono text-muted">{{ number_format($spot->rainfallLast24HoursMillimetres, 1) }}</span>
                        </a>
                    @empty
                        <p class="mt-2 text-sm text-ink-2">No rain recorded in the last 24 hours.</p>
                    @endforelse
                </div>
                <div class="px-4 py-3 text-xs text-muted">
                    <span class="font-mono">{{ $summary->offlineMonitorCount }}</span> of {{ number_format($summary->monitorCount) }} monitors offline ·
                    <span class="font-mono">{{ $summary->silentGaugeCount() }}</span> {{ \Illuminate\Support\Str::plural('gauge', $summary->silentGaugeCount()) }} not reporting
                </div>
            </div>
        </aside>
    </div>

    @foreach ($summary->riversByCatchment() as $catchment => $rivers)
        <section class="mb-12">
            <div class="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-display text-2xl font-semibold tracking-tight">{{ $catchment }}</h2>
                <span class="text-sm text-muted">{{ $rivers->count() }} {{ \Illuminate\Support\Str::plural('river', $rivers->count()) }} · {{ $rivers->sum(fn ($river) => $river->spots->count()) }} spots</span>
            </div>
            <div class="grid gap-5 {{ $rivers->count() > 1 && $rivers->count() <= 2 ? 'lg:grid-cols-2' : '' }}">
                @foreach ($rivers as $river)
                    <div class="overflow-hidden rounded-lg border border-line bg-surface">
                        <div class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3">
                            <a href="{{ route('rivers.show', [$summary->slug, $river->slug]) }}" class="font-display text-lg font-semibold hover:underline">{{ $river->name }}</a>
                            <span class="text-xs text-muted">{{ $river->spots->count() }} spots · {{ $river->monitorCount }} monitors{{ $river->lengthMetres ? ' · '.number_format($river->lengthMetres / 1000).' km' : '' }}</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                                    <tr>
                                        <th class="px-4 py-2 font-medium">Spot</th>
                                        <th class="hidden px-4 py-2 font-medium sm:table-cell">Upstream spills</th>
                                        <th class="hidden px-4 py-2 font-medium sm:table-cell">Level</th>
                                        <th class="hidden px-4 py-2 font-medium sm:table-cell">Rain 24h</th>
                                        <th class="hidden px-4 py-2 font-medium sm:table-cell">Monitors</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line-2">
                                    @foreach ($river->spots->take(6) as $spot)
                                        <tr class="hover:bg-surface-2">
                                            {{-- On phones the other columns fold into this one, under the spot name. --}}
                                            <td class="px-4 py-2 sm:whitespace-nowrap">
                                                <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}" class="font-semibold hover:underline">{{ $spot->name }}</a>
                                                <div class="mt-1 flex flex-col items-start gap-1 sm:hidden">
                                                    <x-spill-badge :recency="$spot->spillRecency" :stale="$spot->overflowDataIsStale()" />
                                                    <span class="text-xs text-muted">
                                                        {{ $spot->levelValue === null ? 'No gauge' : number_format($spot->levelValue, 2).' '.$spot->levelUnit.' '.strtolower($spot->levelTrend->label()) }}
                                                        · {{ $spot->rainfallLast24HoursMillimetres === null ? 'no rain gauge' : number_format($spot->rainfallLast24HoursMillimetres, 1).' mm rain' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="hidden whitespace-nowrap px-4 py-2 sm:table-cell"><x-spill-badge :recency="$spot->spillRecency" :stale="$spot->overflowDataIsStale()" /></td>
                                            <td class="hidden whitespace-nowrap px-4 py-2 sm:table-cell">
                                                @if ($spot->levelValue === null)
                                                    <span class="text-muted">No gauge</span>
                                                @else
                                                    <span class="font-mono tabular-nums">{{ number_format($spot->levelValue, 2) }} {{ $spot->levelUnit }}</span>
                                                    <span class="text-muted">{{ strtolower($spot->levelTrend->label()) }}{{ $spot->levelRelativeToTypical ? ' · '.\Illuminate\Support\Str::before($spot->levelRelativeToTypical, ' typical').' typical' : '' }}</span>
                                                @endif
                                            </td>
                                            <td class="hidden whitespace-nowrap px-4 py-2 font-mono tabular-nums sm:table-cell">{{ $spot->rainfallLast24HoursMillimetres === null ? '-' : number_format($spot->rainfallLast24HoursMillimetres, 1).' mm' }}</td>
                                            <td class="hidden whitespace-nowrap px-4 py-2 font-mono tabular-nums text-muted sm:table-cell">{{ $spot->monitorCount }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($river->spots->count() > 6)
                            <a href="{{ route('rivers.show', [$summary->slug, $river->slug]) }}" class="block border-t border-line-2 px-4 py-2.5 text-center text-sm font-medium text-accent hover:underline">All {{ $river->spots->count() }} {{ \Illuminate\Support\Str::after($river->name, 'River ') }} spots</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach
</x-layouts.app>
