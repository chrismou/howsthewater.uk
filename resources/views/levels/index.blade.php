@php
    // A region names itself; the combined page describes its coverage rather than naming every region.
    $where = $region ? $region->nameInSentence() : 'England, Scotland and Wales';
    $higher = $summary->spotsAboveTypicalRange()->sortBy('riverName')->values();
    $rising = $summary->spotsRising()->sortBy('riverName')->values();
    $title = $region
        ? collect(['River levels in '.$region->nameInSentence().' now: higher and rising', 'River levels in '.$region->nameInSentence().' now', $region->name.' river levels now'])->first(fn (string $candidate) => mb_strlen($candidate) <= 60, 'River levels now')
        : 'River levels now: higher than usual and rising';
    $description = 'Rivers in '.$where.' whose gauges read higher than usual or are rising now, with the latest reading at a spot on each and when it was taken.';
    $breadcrumbs = $region
        ? [['Home', route('home')], [$region->name, route('regions.show', $region)], ['River levels', route('levels.region', $region)]]
        : [['Home', route('home')], ['River levels', route('levels.index')]];

    // One marker per spot; a spot in both lists shows as higher than usual.
    $mapMarkers = $higher->map(fn ($spot) => [$spot, 'higher'])
        ->concat($rising->reject(fn ($spot) => $higher->contains('id', $spot->id))->map(fn ($spot) => [$spot, 'rising']))
        ->map(fn (array $entry) => [
            'lat' => $entry[0]->latitude,
            'lng' => $entry[0]->longitude,
            'label' => $entry[0]->riverName.' at '.$entry[0]->name,
            'url' => route('spots.show', [$entry[0]->regionSlug, $entry[0]->riverSlug, $entry[0]->slug]),
            'kind' => 'level',
            'state' => $entry[1],
        ])->values();
@endphp

<x-layouts.app :title="$title" :description="$description" :breadcrumbs="$breadcrumbs" :region="$region" :live-summary="$region ? null : $summary"
               :date-modified="$summary->spots()->pluck('levelAt')->filter()->max()">
    <div class="mb-8">
        <x-breadcrumbs :items="$breadcrumbs" />
        <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $region ? 'River levels in '.$region->nameInSentence() : 'River levels now' }}</h1>
        <p class="mt-2 max-w-2xl text-ink-2">
            The rivers in {{ $where }} whose gauges read higher than usual, and those rising now. Each river is listed once, at one of its spots, and gauges that have not reported for {{ \App\Data\LevelSummary::STALE_AFTER_HOURS }} hours are left out.
            A level is not a safety check: open a spot for its overflows, rainfall and any flood warning.
        </p>
        @if (! $region)
            <p class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-sm">
                <span class="text-muted">By region:</span>
                @foreach ($regionNames as $slug => $name)
                    <a href="{{ route('levels.region', $slug) }}" class="font-medium text-accent hover:underline">{{ $name }}</a>
                @endforeach
            </p>
        @endif
    </div>

    @if ($mapMarkers->isNotEmpty())
        <div class="mb-10">
            <div class="h-80 w-full rounded-lg border border-line sm:h-[420px]" role="region" aria-label="Map of the rivers higher than usual or rising in {{ $where }}"
                 data-map data-map-markers="{{ json_encode($mapMarkers) }}"></div>
            <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-label="Map key">
                <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#1f5f8b"></span>Higher than usual</li>
                <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#b45309"></span>Rising</li>
            </ul>
        </div>
    @endif

    <section id="higher" class="mb-10 scroll-mt-24">
        <div class="mb-3 flex items-center gap-1.5">
            <h2 class="font-display text-lg font-semibold">Higher than usual<x-heading-count :count="$higher->count()" /></h2>
            <x-help term="typical_range" />
        </div>
        @include('levels.list', ['spots' => $higher, 'regionNames' => $region ? collect() : $regionNames, 'empty' => 'No river is higher than usual right now.'])
    </section>

    <section id="rising" class="mb-10 scroll-mt-24">
        <div class="mb-3 flex items-center gap-1.5">
            <h2 class="font-display text-lg font-semibold">Rising now<x-heading-count :count="$rising->count()" /></h2>
            <x-help term="rivers_rising" />
        </div>
        <p class="mb-3 max-w-2xl text-sm text-ink-2">Up by 3 cm or more over the last three hours. Rain is the usual cause, but tidal stretches rise with every tide, and weirs and locks can move a level too.</p>
        @include('levels.list', ['spots' => $rising, 'regionNames' => $region ? collect() : $regionNames, 'empty' => 'No river is rising right now.'])
    </section>

    @if ($region)
        <p class="text-sm"><a href="{{ route('rivers.index', $region) }}" class="font-medium text-accent hover:underline">Every river in {{ $region->nameInSentence() }}</a> · <a href="{{ route('levels.index') }}" class="font-medium text-accent hover:underline">River levels in every region</a></p>
    @endif
</x-layouts.app>
