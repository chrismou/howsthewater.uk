{{-- The Rivers menu: each region's main rivers grouped by catchment. current: the region to open on. --}}
@props(['regions', 'current' => null])

@php
    $entries = $regions->map(fn ($region) => [
        'region' => $region,
        'url' => route('regions.show', $region),
        'blurb' => 'map, river strips and every spot',
        'groups' => $region->riversByCatchment()->map(fn ($rivers) => $rivers->map(fn ($river) => [
            'name' => $river->name,
            'url' => route('rivers.show', [$region, $river]),
        ])->all())->all(),
    ]);
@endphp

<x-nav-menu label="Rivers" :entries="$entries" :current="$current" />
