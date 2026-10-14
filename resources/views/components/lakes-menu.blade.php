{{-- The Lakes menu: each region's lakes. Regions without lakes are left out. --}}
@props(['regions', 'current' => null])

@php
    $entries = $regions->map(fn ($region) => [
        'region' => $region,
        'url' => route('lakes.index', $region),
        'blurb' => 'every lake, its bathing sites and level',
        'groups' => ($lakes = $region->lakes()->get())->isEmpty() ? [] : ['Lakes' => $lakes->map(fn ($lake) => ['name' => $lake->name, 'url' => route('lakes.show', [$region, $lake])])->all()],
    ])->filter(fn ($entry) => $entry['groups'] !== []);
@endphp

<x-nav-menu label="Lakes" :entries="$entries" :current="$current" />
