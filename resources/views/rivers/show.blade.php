@php
    $mapMarkers = $overview->spots->map(fn ($conditions) => [
        'lat' => $conditions->spot->latitude,
        'lng' => $conditions->spot->longitude,
        'label' => $conditions->spot->name,
        'url' => route('spots.show', [$conditions->spot->river->region, $conditions->spot->river, $conditions->spot]),
        'kind' => 'spot',
        'recency' => $conditions->spillRecency->value,
    ])->values();
@endphp

<x-layouts.app :region="$overview->river->region" :feed="route('rivers.feed', [$overview->river->region, $overview->river])" :title="$overview->river->name.' spills and levels'"
               :description="'Upstream storm overflow spills and river levels for spots along the '.$overview->river->name.', source to mouth.'">
    <div class="mb-6">
        <p class="text-sm text-muted"><a href="{{ route('home') }}" class="hover:underline">All regions</a> <span class="mx-1">/</span> <a href="{{ route('regions.show', $overview->river->region) }}" class="hover:underline">{{ $overview->river->region->name }}</a></p>
        <h1 class="font-display mt-1 text-3xl font-semibold tracking-tight">{{ $overview->river->name }}</h1>
        <p class="mt-2 text-ink-2">
            {{ $overview->monitorCount }} storm overflow {{ \Illuminate\Support\Str::plural('monitor', $overview->monitorCount) }} on the {{ $overview->river->name }}{{ $overview->hasTributaries ? ' and its tributaries' : '' }},
            {{ $overview->dischargingNowCount }} discharging right now{{ $overview->offlineCount > 0 ? ' and '.$overview->offlineCount.' offline' : '' }}. Spots are listed source to mouth.
            <a href="{{ route('rivers.feed', [$overview->river->region, $overview->river]) }}" class="whitespace-nowrap text-accent hover:underline">Follow the {{ \Illuminate\Support\Str::after($overview->river->name, 'River ') }} by RSS</a>
        </p>
    </div>

    <div
        class="mb-8 h-80 w-full rounded-lg border border-line"
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
