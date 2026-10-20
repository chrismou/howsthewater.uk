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
        'kind' => 'place',
        'recency' => $spot->spillRecency->onMap($spot->overflowDataIsStale()),
        'note' => $spot->spillRecency->mapNote($spot->overflowDataIsStale()),
        'link' => 'View spot',
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

    // Other regions' rivers that run through this one, named once with the regions they are listed under.
    $alsoNames = $alsoRunningThrough->map(fn ($entry) => $entry['river']->name)->join(', ', ' and ');
    $alsoHomes = $alsoRunningThrough->pluck('regionName')->unique()->join(', ', ' and ');
    $alsoIsOne = $alsoRunningThrough->count() === 1;

    // The page's trail: the visible breadcrumb and the BreadcrumbList are both built from it.
    $breadcrumbs = [['All rivers', route('hubs.rivers')], [$summary->name, route('regions.show', $region)], ['Rivers', route('rivers.index', $region)]];
@endphp

<x-layouts.app :date-modified="$summary->dataUpdatedAt" :title="$summary->name.' rivers: swimming, levels and spills'"
               :description="'Storm overflow spills, river levels and rainfall for swimming, paddling and fishing spots on '.$region->nameInSentence().'\'s rivers, listed source to mouth.'"
               :breadcrumbs="$breadcrumbs"
               :region="$region"
               :structured-data="app(\App\Services\StructuredData::class)->itemList($summary->name.' rivers', $summary->rivers->map(fn ($river) => [$river->name, route('rivers.show', [$summary->slug, $river->slug])]))">
    <div class="mb-6">
        <x-breadcrumbs :items="$breadcrumbs" />
        <div class="mt-1 flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
            <div class="max-w-2xl">
                <h1 class="font-display text-4xl font-semibold tracking-tight">{{ $summary->name }} rivers</h1>
                <p class="mt-2 text-ink-2">
                    {{ $summary->rivers->count() }} {{ \Illuminate\Support\Str::plural('river', $summary->rivers->count()) }} and {{ $spots->count() }} {{ \Illuminate\Support\Str::plural('spot', $spots->count()) }}, listed source to mouth.
                    @if ($summary->dataUpdatedAt)
                        Monitor data updated {{ $summary->dataUpdatedAt->diffForHumans() }}.
                    @endif
                </p>
                <x-region-data-links :region="$region" />
            </div>
            <dl class="flex flex-wrap gap-x-7 gap-y-3">
                <div>
                    <dd class="font-mono text-3xl font-semibold tabular-nums {{ $summary->dischargingNowCount() > 0 ? 'text-alert' : '' }}"><x-figure-link :url="$summary->hasOverflowData ? route('spills.region', $region).'#discharging' : null" figure>{{ $summary->hasOverflowData ? $summary->dischargingNowCount() : '-' }}</x-figure-link></dd>
                    <dt class="text-xs text-muted"><x-figure-link :url="$summary->hasOverflowData ? route('spills.region', $region).'#discharging' : null">overflows discharging now</x-figure-link> <x-help term="discharging_now" /></dt>
                </div>
                <div>
                    <dd class="font-mono text-3xl font-semibold tabular-nums {{ $summary->spotsWithSpillLast24HoursCount() > 0 ? 'text-spill' : '' }}"><x-figure-link :url="$summary->hasOverflowData ? route('spills.region', $region).'#spots' : null" figure>{{ $summary->hasOverflowData ? $summary->spotsWithSpillLast24HoursCount() : '-' }}</x-figure-link></dd>
                    <dt class="text-xs text-muted"><x-figure-link :url="$summary->hasOverflowData ? route('spills.region', $region).'#spots' : null">spots with an upstream spill in 24h</x-figure-link> <x-help term="spill" /></dt>
                </div>
                <div>
                    <dd class="font-mono text-3xl font-semibold tabular-nums"><x-figure-link :url="route('levels.region', $region).'#higher'" figure>{{ $aboveTypical->count() }}</x-figure-link></dd>
                    <dt class="text-xs text-muted"><x-figure-link :url="route('levels.region', $region).'#higher'">rivers higher than usual</x-figure-link> <x-help term="typical_range" /></dt>
                </div>
                <div>
                    <dd class="font-mono text-3xl font-semibold tabular-nums">{{ $summary->hasOverflowData ? number_format($summary->monitorCount) : '-' }}</dd>
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
            role="region" aria-label="Map of the rivers and spots in {{ $region->nameInSentence() }}"
            data-map
            data-map-centrelines="{{ json_encode($centrelines) }}"
            data-map-markers="{{ json_encode($mapMarkers) }}"
        ></div>
        <x-map-key where="upstream" :spills="$summary->hasOverflowData" />
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
                    <div class="flex items-baseline justify-between gap-3">
                        {{-- Counts spots, not overflows: one overflow is upstream of every spot below it. --}}
                        <span class="text-xs font-semibold uppercase tracking-wide {{ $discharging->isNotEmpty() ? 'text-alert' : 'text-muted' }}">Spots with sewage reported upstream now</span>
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
                        <span class="text-xs font-semibold uppercase tracking-wide text-muted">Higher than usual</span>
                        <span class="font-mono text-lg font-semibold">{{ $aboveTypical->count() }}</span>
                    </div>
                    @forelse ($aboveTypical->take(4) as $spot)
                        <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}" class="mt-2 flex justify-between gap-3 text-sm hover:underline">
                            <span class="font-medium">{{ \Illuminate\Support\Str::after($spot->riverName, 'River ') }} at {{ $spot->name }}</span>
                            <span class="font-mono text-muted">{{ number_format($spot->levelValue, 2) }} {{ $spot->levelUnit }}</span>
                        </a>
                    @empty
                        <p class="mt-2 text-sm text-ink-2">No river is higher than usual.</p>
                    @endforelse
                    @if ($aboveTypical->count() > 4)
                        <a href="{{ route('levels.region', $region) }}#higher" class="mt-2 inline-block text-sm font-medium text-accent hover:underline">All {{ $aboveTypical->count() }} rivers higher than usual</a>
                    @endif
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
                <span class="text-sm text-muted">{{ $rivers->count() }} {{ \Illuminate\Support\Str::plural('river', $rivers->count()) }} · {{ $rivers->sum(fn ($river) => $river->spots->count()) }} {{ \Illuminate\Support\Str::plural('spot', $rivers->sum(fn ($river) => $river->spots->count())) }}</span>
            </div>
            <div class="grid gap-5">
                @foreach ($rivers as $river)
                    <x-river-spots-card :river="$river" :region-slug="$summary->slug" />
                @endforeach
            </div>
        </section>
    @endforeach

    @if ($alsoRunningThrough->isNotEmpty())
        <section class="mb-12">
            <div class="mb-4">
                <h2 class="font-display text-2xl font-semibold tracking-tight">Also through {{ $region->nameInSentence() }}</h2>
                <p class="mt-1 text-sm text-ink-2">{{ $alsoNames }} {{ $alsoIsOne ? 'runs' : 'run' }} through {{ $region->nameInSentence() }} too. {{ $alsoIsOne ? 'Its page is' : 'Their pages are' }} listed under {{ $alsoHomes }}, so the figures above leave {{ $alsoIsOne ? 'it' : 'them' }} out. Shown here are {{ $alsoIsOne ? 'its spots' : 'their spots' }} in {{ $region->nameInSentence() }}.</p>
            </div>
            <div class="grid gap-5">
                @foreach ($alsoRunningThrough as $entry)
                    <x-river-spots-card :river="$entry['river']" :region-slug="$entry['regionSlug']" :spot-slugs="$entry['spotSlugs']" />
                @endforeach
            </div>
        </section>
    @endif
</x-layouts.app>
