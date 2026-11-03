@php
    $covered = $river->region->hasOverflowData();
    $shortName = $river->shortName();
    $family = $summary->hasTributaries ? ' and its tributaries' : '';
    $gaugedSpots = $summary->spotsWithOwnGauge();
    $hasMonitors = $covered && $summary->monitorCount > 0;
    $hasFloodData = $summary->floodAreaAgencies !== [];
    $middle = $summary->middleSpot();

    // Spots coloured by their upstream spills, and every gauge with a current reading in blue, as the levels page draws them.
    $mapMarkers = $summary->spots->map(fn ($spot) => [
        'lat' => $spot->latitude,
        'lng' => $spot->longitude,
        'label' => $spot->name,
        'url' => route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]),
        'kind' => 'place',
        'recency' => $spot->spillRecency->onMap($spot->overflowDataIsStale()),
        'note' => $spot->spillRecency->mapNote($spot->overflowDataIsStale()),
        'link' => 'View spot',
    ])->concat($gaugedSpots
        ->filter(fn ($spot) => isset($gauges[$spot->gaugeId]) && $spot->levelValue !== null && ! $spot->levelIsStale())
        ->map(fn ($spot) => [
            'lat' => $gauges[$spot->gaugeId]->latitude,
            'lng' => $gauges[$spot->gaugeId]->longitude,
            'label' => $gauges[$spot->gaugeId]->label.' gauge',
            'url' => route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]),
            'kind' => 'level',
            'state' => $spot->isAboveTypicalRange() ? 'higher' : ($spot->levelTrend === \App\Enums\LevelTrend::Rising ? 'rising' : 'usual'),
            'note' => collect([number_format($spot->levelValue, 2).' '.$spot->levelUnit, \App\Data\LevelSummary::usualPhrase($spot->levelRelativeToTypical), $spot->levelTrend === \App\Enums\LevelTrend::Unknown ? null : strtolower($spot->levelTrend->label())])->filter()->join(', '),
            'link' => 'View '.$spot->name,
        ]))->values();

    // The page's trail: the visible breadcrumb and the BreadcrumbList are both built from it.
    $breadcrumbs = [['All rivers', route('hubs.rivers')], [$river->region->name, route('regions.show', $river->region)], ['Rivers', route('rivers.index', $river->region)], [$river->name, route('rivers.show', [$river->region, $river])]];

    // Leads with the river and "levels", as people search for them when they fear a flood, stepping down to fit
    // search results' 60 characters. Flood alerts are promised only where flood areas cover the river, and spills
    // only where we read the overflow data.
    $topics = array_values(array_filter([
        $hasFloodData && $covered ? ' levels, flood alerts and spills' : null,
        $hasFloodData ? ' levels and flood alerts' : null,
        $covered ? ' levels and spills' : null,
        ' levels and swimming spots',
        ' levels',
        '',
    ]));
    $title = collect($topics)
        ->map(fn (string $topic) => $river->name.$topic)
        ->first(fn (string $candidate) => mb_strlen($candidate) <= 60, $river->name);

    $gaugeCount = $gaugedSpots->count();
    $descriptionFacts = collect([
        $gaugeCount > 0 ? "river levels at {$gaugeCount} ".\Illuminate\Support\Str::plural('gauge', $gaugeCount).' against '.($gaugeCount === 1 ? 'its' : 'their').' usual range' : 'river levels',
        $hasFloodData ? 'flood alerts and warnings' : null,
        'rainfall',
        $covered ? 'storm overflow spills' : null,
    ])->filter()->join(', ', ' and ');
    $description = collect(["The {$shortName} today: {$descriptionFacts} for every swimming spot, source to mouth.", "The {$shortName} today: {$descriptionFacts}."])
        ->first(fn (string $candidate) => mb_strlen($candidate) <= 160, \Illuminate\Support\Str::limit("The {$shortName} today: {$descriptionFacts}.", 157));

    // What the river is, in a sentence: its length, where it goes, what joins it and what we read on it.
    $facts = collect([
        $river->length_metres ? number_format($river->length_metres / 1000).' km from source to '.($flowsInto ? 'the '.$flowsInto->shortName() : 'mouth') : null,
        $flowsInto && ! $river->length_metres ? 'flows into the '.$flowsInto->shortName() : null,
        $tributaryNames !== [] ? 'with the '.collect($tributaryNames)->map(fn (string $name) => \Illuminate\Support\Str::after($name, 'River '))->join(', ', ' and ').' flowing into it' : null,
    ])->filter()->join(', ');
    $reads = collect([
        $summary->spots->count().' swimming '.\Illuminate\Support\Str::plural('spot', $summary->spots->count()),
        $gaugeCount.' level '.\Illuminate\Support\Str::plural('gauge', $gaugeCount),
        $covered ? $summary->monitorCount.' storm overflow '.\Illuminate\Support\Str::plural('monitor', $summary->monitorCount) : null,
    ])->filter()->join(', ', ' and ');
    $lastFlood = $summary->lastFloodWarning;
    // Who issues flood warnings for this river's stretch ("the Environment Agency", "SEPA or the Environment Agency" on the Tweed).
    $floodAgencies = collect($summary->floodAreaAgencies)
        ->map(fn (\App\Enums\Agency $agency) => $agency === \App\Enums\Agency::EnvironmentAgency ? 'the Environment Agency' : $agency->label())
        ->join(', ', ' or ');
@endphp

<x-layouts.app :share-image="$shareImage" :date-modified="$summary->dataUpdatedAt()" :region="$river->region" :feed="route('rivers.feed', [$river->region, $river])" :title="$title"
               :description="$description"
               :breadcrumbs="$breadcrumbs"
               :structured-data="app(\App\Services\StructuredData::class)->riverPage($river, $river->region)">
    <div class="mb-8">
        <x-breadcrumbs :items="$breadcrumbs" />
        <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $river->name }}</h1>
        <p class="mt-2 max-w-2xl text-ink-2">
            @if ($facts !== ''){{ ucfirst($facts) }}. @endif
            {{ ucfirst($reads) }} on the {{ $shortName }}{{ $family }}, listed source to mouth.
            <a href="{{ route('rivers.feed', [$river->region, $river]) }}" class="whitespace-nowrap text-accent hover:underline">Follow the {{ $shortName }} by RSS</a>
        </p>
        {{-- The headline sits in its own card, as the status card does on a spot page, so it reads as the river's state now. --}}
        <div class="mt-5 rounded-lg border border-line bg-surface p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-muted">Right now</p>
            <x-place-summary class="mt-2" :headline="$headline" :region="$river->region" />
        </div>
    </div>

    @if ($summary->floodWarnings()->isNotEmpty())
        <x-flood-warnings :warnings="$summary->floodWarnings()" />
    @endif

    <section id="levels" class="mb-10 scroll-mt-24">
        <div class="mb-1 flex items-center gap-1.5">
            <h2 class="font-display text-2xl font-semibold tracking-tight">{{ $shortName }} river levels</h2>
            <x-help term="river_gauges" />
        </div>
        <p class="mb-4 max-w-2xl text-sm text-ink-2">
            @if ($gaugeCount > 0)
                {{ $gaugeCount === 1 ? 'There is one gauge' : "There are {$gaugeCount} gauges" }} measuring the water level on the {{ $river->name }}, listed source to mouth.
                The live reading at each is shown below, against its usual range and the highest level on record.
            @endif
            A high level does not on its own mean flooding is imminent{{ $floodAgencies !== '' ? ": when {$floodAgencies} issues a flood alert or warning for this stretch, it is shown at the top of this page" : '' }}.
            @if ($lastFlood !== null && $lastFlood->endedAt !== null)
                The last {{ strtolower($lastFlood->severity->label()) }} here, for {{ $lastFlood->areaLabel }}, ended on <time datetime="{{ $lastFlood->endedAt->toIso8601String() }}">{{ $lastFlood->endedAt->timezone(config('app.display_timezone', 'Europe/London'))->format('j F Y') }}</time>.
            @endif
        </p>
        @if ($gaugeCount === 0)
            <p class="rounded-lg border border-line bg-surface p-5 text-sm text-ink-2">No level gauge is linked to the {{ $shortName }}'s swimming spots yet. Gauges are matched to spots within 25 km along the river, so this fills in once one is found.</p>
        @else
            <div class="grid gap-4 md:grid-cols-2 {{ $gaugeCount >= 3 ? 'xl:grid-cols-3' : '' }}">
                @foreach ($gaugedSpots as $spot)
                    @if (isset($gauges[$spot->gaugeId]))
                        <x-river-gauge-card :spot="$spot" :station="$gauges[$spot->gaugeId]" :points="$levelHistories[$spot->gaugeId] ?? []" :water="'the '.$shortName" />
                    @endif
                @endforeach
            </div>
        @endif
    </section>

    @if ($middle !== null && $sun !== null)
        <section class="mb-10">
            <div class="mb-3 flex items-center gap-1.5">
                <h2 class="font-display text-lg font-semibold">Weather on the {{ $shortName }}</h2>
                <x-help term="weather" />
            </div>
            <x-weather-card :weather="$weather" :sun="$sun" :place="$middle->name" />
        </section>
    @endif

    <section class="mb-10">
        <h2 class="font-display mb-3 text-2xl font-semibold tracking-tight">Along the {{ $shortName }}, source to mouth</h2>
        <div
            class="h-80 w-full rounded-lg border border-line"
            role="region" aria-label="Map of the {{ $river->name }}, its swimming spots and level gauges"
            data-map
            data-map-centreline="{{ json_encode($river->centreline ?? []) }}"
            data-map-markers="{{ json_encode($mapMarkers) }}"
        ></div>
        <x-map-key where="upstream" :spills="$covered" :gauges="$mapMarkers->contains('kind', 'level')" />
    </section>

    <section class="mb-10">
        <h2 class="font-display mb-1 text-2xl font-semibold tracking-tight">Where people swim, paddle and fish on the {{ $shortName }}</h2>
        <p class="mb-4 max-w-2xl text-sm text-ink-2">Each place has its own page with the overflows upstream of it, its gauge's level chart, the last 30 days of spills, the weather and daylight, and nearby places.</p>
        @if ($summary->spots->isEmpty())
            <p class="rounded-lg border border-line bg-surface p-5 text-sm text-ink-2">No swimming spots are listed on the {{ $shortName }} yet.</p>
        @else
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($summary->spots as $spot)
                    <x-spot-card :spot="$spot" />
                @endforeach
            </div>
        @endif
    </section>

    @if ($hasMonitors && $summary->dailyHistory->isNotEmpty())
        <section class="mb-10">
            <div class="mb-3 flex items-center gap-1.5">
                <h2 class="font-display text-lg font-semibold">Storm overflow spills on the {{ $shortName }}{{ $family }}, last 30 days</h2>
                <x-help term="history" />
            </div>
            <x-spill-rain-chart :days="$summary->dailyHistory" :rainfall="$summary->dailyRainfall" :gauge="$summary->rainGaugeLabel" />
            <p class="mt-2 text-xs text-muted">Every monitored overflow on the {{ $shortName }}{{ $family }}; overflows spilling at the same time count once. Each spot's own page counts only the overflows upstream of it.</p>
        </section>
    @endif

    @if ($returnYears->isNotEmpty())
        <section class="mb-8">
            <div class="mb-3 flex items-center gap-1.5">
                <h2 id="year-by-year" class="font-display text-lg font-semibold">Year by year, from the water companies' returns</h2>
                <x-help term="overflow_hours" />
            </div>
            @if ($returnRank)
                <p class="mb-3 text-sm text-ink-2">
                    In {{ $returnRank->year }} the {{ $shortName }}{{ $family }} had {{ $returnRank->phrase() }} of the {{ $returnRank->of }} {{ $river->region->name }} rivers with annual returns.
                    <a href="{{ route('spills.region', $river->region) }}#compare" class="text-accent hover:underline">How {{ $river->region->nameInSentence() }}'s rivers compare</a>.
                </p>
            @endif
            <div class="overflow-x-auto rounded-lg border border-line bg-surface" role="region" aria-labelledby="year-by-year" tabindex="0">
                <table class="w-full text-sm">
                    <caption class="sr-only">The {{ $river->name }}'s overflows each year, from the water companies' annual returns</caption>
                    <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                        <tr>
                            <th scope="col" class="px-4 py-2.5 font-medium">Year</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Overflows with a return</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Counted spills</th>
                            <th scope="col" class="px-4 py-2.5 font-medium">Hours</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line-2 font-mono tabular-nums">
                        @foreach ($returnYears as $year => $total)
                            <tr>
                                <th scope="row" class="px-4 py-2.5 text-left font-sans font-normal">{{ $year }}</th>
                                <td class="px-4 py-2.5">{{ number_format($total->reporting) }}</td>
                                <td class="px-4 py-2.5">{{ number_format($total->countedSpills) }}</td>
                                <td class="px-4 py-2.5">{{ number_format($total->hours) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-muted">
                From the water companies' annual returns, which count spills in 12 and 24 hour blocks, as published by The Rivers Trust (Open Government Licence).
                Every overflow on the river{{ $family }}, each adding its own hours.@if ($hasInflowingMainRivers) Main rivers that flow into the {{ $shortName }} count on their own pages.@endif
            </p>
        </section>
    @endif
</x-layouts.app>
