@php
    $beach = $conditions->bathingWater->bathingWater;
@endphp

<x-layouts.app :region="$region" :title="$beach->name.': bathing water and nearby overflows'"
               :description="'Environment Agency bathing water classification, samples and pollution forecast for '.$beach->name.', plus storm overflows nearby.'">
    <div class="mb-6">
        <p class="text-sm text-muted">
            <a href="{{ route('home') }}" class="hover:underline">All regions</a>
            <span class="mx-1">/</span>
            <a href="{{ route('regions.show', $region) }}" class="hover:underline">{{ $region->name }}</a>
            <span class="mx-1">/</span>
            <a href="{{ route('beaches.index', $region) }}" class="hover:underline">Beaches</a>
        </p>
        <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $beach->name }}</h1>
        @if ($beach->district)
            <p class="mt-1 text-ink-2">{{ $beach->district }}{{ $beach->kind === \App\Enums\BathingWaterKind::Estuary ? ', estuary bathing water' : '' }}</p>
        @endif
    </div>

    <x-bathing-water :summary="$conditions->bathingWater" class="mb-8" />

    <x-nearby-overflows :overflows="$conditions->overflows" />
</x-layouts.app>
