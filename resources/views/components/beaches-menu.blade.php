{{-- The Beaches menu: each region's beaches grouped by district. Regions without beaches are left out. --}}
@props(['regions', 'current' => null])

@php
    $entries = $regions->map(fn ($region) => [
        'region' => $region,
        'url' => route('beaches.index', $region),
        'blurb' => 'every beach, its classification and nearby overflows',
        'groups' => $region->beaches()->get()->groupBy(fn ($beach) => $beach->district ?? $region->name)->sortKeys()
            ->map(fn ($beaches) => $beaches->map(fn ($beach) => ['name' => $beach->name, 'url' => route('beaches.show', [$region, $beach])])->all())->all(),
    ])->filter(fn ($entry) => $entry['groups'] !== []);
@endphp

<x-nav-menu label="Beaches" :entries="$entries" :current="$current" />
