@php
    $mapMarkers = $overview->spots->map(fn ($conditions) => [
        'lat' => $conditions->spot->latitude,
        'lng' => $conditions->spot->longitude,
        'label' => $conditions->spot->name,
        'url' => route('spots.show', [$conditions->spot->river->region, $conditions->spot->river, $conditions->spot]),
        'kind' => 'place',
        'recency' => $conditions->spillRecency->onMap($conditions->overflowDataIsStale()),
        'note' => $conditions->spillRecency->mapNote($conditions->overflowDataIsStale()),
        'link' => 'View spot',
    ])->values();

    // The page's trail: the visible breadcrumb and the BreadcrumbList are both built from it.
    $breadcrumbs = [['All rivers', route('hubs.rivers')], [$overview->river->region->name, route('regions.show', $overview->river->region)], ['Rivers', route('rivers.index', $overview->river->region)], [$overview->river->name, route('rivers.show', [$overview->river->region, $overview->river])]];

    // Leads with the river and "levels", as people search for them, stepping down to fit search results' 60 characters.
    // Rivers whose overflow data we do not read yet never promise spills.
    $title = collect($overview->river->region->hasOverflowData() ? [' levels, spills and swimming spots', ' levels and spills', ''] : [' levels and swimming spots', ' levels', ''])
        ->map(fn (string $topic) => $overview->river->name.$topic)
        ->first(fn (string $candidate) => mb_strlen($candidate) <= 60, $overview->river->name);
@endphp

<x-layouts.app :share-image="$shareImage" :date-modified="$overview->dataUpdatedAt()" :region="$overview->river->region" :feed="route('rivers.feed', [$overview->river->region, $overview->river])" :title="$title"
               :description="'Upstream storm overflow spills, river levels and rainfall for every swimming, paddling and fishing spot on the '.$overview->river->name.', source to mouth.'"
               :breadcrumbs="$breadcrumbs"
               :structured-data="app(\App\Services\StructuredData::class)->riverPage($overview->river, $overview->river->region)">
    <div class="mb-6">
        <x-breadcrumbs :items="$breadcrumbs" />
        <h1 class="font-display mt-1 text-3xl font-semibold tracking-tight">{{ $overview->river->name }}</h1>
        <x-dated-summary class="mt-2 max-w-2xl text-ink-2" :monitor-count="$overview->monitorCount" :offline-count="$overview->offlineCount" :companies="$overview->companies"
                         :where="'on the '.$overview->river->name.($overview->hasTributaries ? ' and its tributaries' : '')"
                         :discharging-now="$overview->dischargingNowCount" :spills-last48-hours="$overview->spillsLast48HoursCount" :updated-at="$overview->dataUpdatedAt()"
                         :covered="$overview->river->region->hasOverflowData()" :region="$overview->river->region" :stale="$overview->overflowDataIsStale()" />
        <p class="mt-2 text-sm text-ink-2">
            {{ $overview->spots->count() }} {{ \Illuminate\Support\Str::plural('spot', $overview->spots->count()) }}, listed source to mouth.
            <a href="{{ route('rivers.feed', [$overview->river->region, $overview->river]) }}" class="whitespace-nowrap text-accent hover:underline">Follow the {{ \Illuminate\Support\Str::after($overview->river->name, 'River ') }} by RSS</a>
        </p>
    </div>

    <div
        class="h-80 w-full rounded-lg border border-line"
        role="region" aria-label="Map of the {{ $overview->river->name }} and its spots"
        data-map
        data-map-centreline="{{ json_encode($overview->river->centreline ?? []) }}"
        data-map-markers="{{ json_encode($mapMarkers) }}"
    ></div>
    <x-map-key where="upstream" :spills="$overview->river->region->hasOverflowData()" class="mb-8" />

    @if ($returnYears->isNotEmpty())
        @php
            $shortName = \Illuminate\Support\Str::after($overview->river->name, 'River ');
            $family = $overview->hasTributaries ? ' and its tributaries' : '';
        @endphp
        <section class="mb-8">
            <div class="mb-3 flex items-center gap-1.5">
                <h2 id="year-by-year" class="font-display text-lg font-semibold">Year by year</h2>
                <x-help term="overflow_hours" />
            </div>
            @if ($returnRank)
                <p class="mb-3 text-sm text-ink-2">
                    In {{ $returnRank->year }} the {{ $shortName }}{{ $family }} had {{ $returnRank->phrase() }} of the {{ $returnRank->of }} {{ $overview->river->region->name }} rivers with annual returns.
                    <a href="{{ route('spills.region', $overview->river->region) }}#compare" class="text-accent hover:underline">How {{ $overview->river->region->nameInSentence() }}'s rivers compare</a>.
                </p>
            @endif
            <div class="overflow-x-auto rounded-lg border border-line bg-surface" role="region" aria-labelledby="year-by-year" tabindex="0">
                <table class="w-full text-sm">
                    <caption class="sr-only">The {{ $overview->river->name }}'s overflows each year, from the water companies' annual returns</caption>
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

    {{-- The spot cards' names are h3s, so the list has its own h2. --}}
    <h2 class="font-display mb-3 text-lg font-semibold">Spots on the {{ $overview->river->name }}</h2>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-2">
        @foreach ($overview->spots as $conditions)
            <x-spot-card :conditions="$conditions" />
        @endforeach
    </div>
</x-layouts.app>
