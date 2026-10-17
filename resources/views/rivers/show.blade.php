@php
    $mapMarkers = $overview->spots->map(fn ($conditions) => [
        'lat' => $conditions->spot->latitude,
        'lng' => $conditions->spot->longitude,
        'label' => $conditions->spot->name,
        'url' => route('spots.show', [$conditions->spot->river->region, $conditions->spot->river, $conditions->spot]),
        'kind' => 'spot',
        'recency' => $conditions->spillRecency->value,
    ])->values();

    // The page's trail: the visible breadcrumb and the BreadcrumbList are both built from it.
    $breadcrumbs = [['All rivers', route('hubs.rivers')], [$overview->river->region->name, route('regions.show', $overview->river->region)], ['Rivers', route('rivers.index', $overview->river->region)], [$overview->river->name, route('rivers.show', [$overview->river->region, $overview->river])]];

    // Leads with the river and "levels", as people search for them, stepping down to fit search results' 60 characters.
    // Rivers whose overflow data we do not read yet never promise spills.
    $title = collect($overview->river->region->hasOverflowData() ? [' levels, spills and swimming spots', ' levels and spills', ''] : [' levels and swimming spots', ' levels', ''])
        ->map(fn (string $topic) => $overview->river->name.$topic)
        ->first(fn (string $candidate) => mb_strlen($candidate) <= 60, $overview->river->name);
@endphp

<x-layouts.app :date-modified="$overview->dataUpdatedAt()" :region="$overview->river->region" :feed="route('rivers.feed', [$overview->river->region, $overview->river])" :title="$title"
               :description="'Upstream storm overflow spills, river levels and rainfall for every swimming, paddling and fishing spot on the '.$overview->river->name.', source to mouth.'"
               :breadcrumbs="$breadcrumbs"
               :structured-data="app(\App\Services\StructuredData::class)->riverPage($overview->river, $overview->river->region)">
    <div class="mb-6">
        <x-breadcrumbs :items="$breadcrumbs" />
        <h1 class="font-display mt-1 text-3xl font-semibold tracking-tight">{{ $overview->river->name }}</h1>
        <x-dated-summary class="mt-2 max-w-2xl text-ink-2" :monitor-count="$overview->monitorCount" :offline-count="$overview->offlineCount" :companies="$overview->companies"
                         :where="'on the '.$overview->river->name.($overview->hasTributaries ? ' and its tributaries' : '')"
                         :discharging-now="$overview->dischargingNowCount" :spills-last48-hours="$overview->spillsLast48HoursCount" :updated-at="$overview->dataUpdatedAt()"
                         :covered="$overview->river->region->hasOverflowData()" :stale="$overview->overflowDataIsStale()" />
        <p class="mt-2 text-sm text-ink-2">
            {{ $overview->spots->count() }} {{ \Illuminate\Support\Str::plural('spot', $overview->spots->count()) }}, listed source to mouth.
            <a href="{{ route('rivers.feed', [$overview->river->region, $overview->river]) }}" class="whitespace-nowrap text-accent hover:underline">Follow the {{ \Illuminate\Support\Str::after($overview->river->name, 'River ') }} by RSS</a>
        </p>
    </div>

    <div
        class="mb-8 h-80 w-full rounded-lg border border-line"
        role="region" aria-label="Map of the {{ $overview->river->name }}, its spots and overflow monitors"
        data-map
        data-map-centreline="{{ json_encode($overview->river->centreline ?? []) }}"
        data-map-markers="{{ json_encode($mapMarkers) }}"
    ></div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-2">
        @foreach ($overview->spots as $conditions)
            <x-spot-card :conditions="$conditions" />
        @endforeach
    </div>
</x-layouts.app>
