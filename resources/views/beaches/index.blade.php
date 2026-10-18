@php
    // The page's trail: the visible breadcrumb and the BreadcrumbList are both built from it.
    $breadcrumbs = [['All beaches', route('hubs.beaches')], [$region->name, route('regions.show', $region)], ['Beaches', route('beaches.index', $region)]];
@endphp

<x-layouts.app :date-modified="$beaches->map(fn ($conditions) => $conditions->overflows->dataUpdatedAt)->filter()->max()" :region="$region" :title="$region->name.' beaches: water quality and swimming'"
               :description="'Bathing water classifications, sample results and nearby storm overflows for swimming at every designated beach in '.$region->nameInSentence().'.'"
               :breadcrumbs="$breadcrumbs"
               :structured-data="app(\App\Services\StructuredData::class)->itemList($region->name.' beaches', $beaches->map(fn ($conditions) => [$conditions->bathingWater->bathingWater->name, route('beaches.show', [$region, $conditions->bathingWater->bathingWater])]))">
    <div class="mb-6">
        <x-breadcrumbs :items="$breadcrumbs" />
        <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $region->name }} beaches</h1>
        <p class="mt-2 text-ink-2">Every designated bathing water on the {{ $region->name }} coast, with its classification and the storm overflows nearby.</p>
    </div>

    @php
        $mapMarkers = $beaches->map(fn ($conditions) => [
            'lat' => $conditions->bathingWater->bathingWater->latitude,
            'lng' => $conditions->bathingWater->bathingWater->longitude,
            'label' => $conditions->bathingWater->bathingWater->name,
            'url' => route('beaches.show', [$region, $conditions->bathingWater->bathingWater]),
            'kind' => 'bathing',
            'classification' => $conditions->bathingWater->bathingWater->classification?->value,
        ])->values();
    @endphp

    <div class="mb-8">
        <div class="h-80 w-full rounded-lg border border-line sm:h-[420px]" role="region" aria-label="Map of the beaches in {{ $region->nameInSentence() }}" data-map data-map-markers="{{ json_encode($mapMarkers) }}"></div>
        <x-bathing-map-key :overflows="false" />
    </div>

    <div class="overflow-x-auto rounded-lg border border-line bg-surface">
        <table class="w-full text-sm">
            <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-2.5 font-medium">Beach</th>
                    <th class="px-4 py-2.5 font-medium">Classification <x-help term="bathing_classification" /></th>
                    <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Overflows nearby <x-help term="nearby_overflows" /></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line-2">
                @foreach ($beaches as $conditions)
                    @php
                        $beach = $conditions->bathingWater->bathingWater;
                    @endphp
                    <tr class="hover:bg-surface-2">
                        <td class="px-4 py-2.5">
                            <a href="{{ route('beaches.show', [$region, $beach]) }}" class="font-semibold hover:underline">{{ $beach->name }}</a>
                            <span class="block text-xs text-muted">{{ $beach->district }}</span>
                        </td>
                        <td class="px-4 py-2.5"><x-classification-pill :site="$beach" /></td>
                        <td class="hidden px-4 py-2.5 sm:table-cell"><x-spill-badge :recency="$conditions->overflows->spillRecency" :stale="$conditions->overflows->isStale()" nearby /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
