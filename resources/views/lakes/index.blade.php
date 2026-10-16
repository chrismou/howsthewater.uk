<x-layouts.app :region="$region" :title="$region->name.' lakes: swimming and water quality'"
               :description="'Bathing water results, lake levels and nearby storm overflows for swimming and paddling at lakes in '.$region->name.'.'"
               :breadcrumbs="[[$region->name, route('regions.show', $region)], ['Lakes', url()->current()]]"
               :structured-data="app(\App\Services\StructuredData::class)->itemList($region->name.' lakes', $lakes->map(fn ($conditions) => [$conditions->lake->name, route('lakes.show', [$region, $conditions->lake])]))">
    <div class="mb-6">
        <p class="text-sm text-muted">
            <a href="{{ route('hubs.lakes') }}" class="hover:underline">All lakes</a>
            <span class="mx-1">/</span>
            <a href="{{ route('regions.show', $region) }}" class="hover:underline">{{ $region->name }}</a>
        </p>
        <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $region->name }} lakes</h1>
    </div>

    @php
        $mapMarkers = $lakes->map(fn ($conditions) => [
            'lat' => $conditions->lake->latitude,
            'lng' => $conditions->lake->longitude,
            'label' => $conditions->lake->name,
            'url' => route('lakes.show', [$region, $conditions->lake]),
            'kind' => 'place',
        ])->values();
    @endphp

    <div class="mb-8 h-72 w-full rounded-lg border border-line" data-map data-map-markers="{{ json_encode($mapMarkers) }}"></div>

    <div class="overflow-x-auto rounded-lg border border-line bg-surface">
        <table class="w-full text-sm">
            <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-2.5 font-medium">Lake</th>
                    <th class="px-4 py-2.5 font-medium">Bathing sites</th>
                    <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Level</th>
                    <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Overflows nearby <x-help term="nearby_overflows" /></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line-2">
                @foreach ($lakes as $conditions)
                    <tr class="hover:bg-surface-2">
                        <td class="px-4 py-2.5"><a href="{{ route('lakes.show', [$region, $conditions->lake]) }}" class="font-semibold hover:underline">{{ $conditions->lake->name }}</a></td>
                        <td class="px-4 py-2.5">{{ $conditions->bathingWaters->isEmpty() ? 'No results yet' : $conditions->bathingWaters->map(fn ($summary) => $summary->bathingWater->classification?->value ?? 'Not classified')->join(', ') }}</td>
                        <td class="hidden px-4 py-2.5 sm:table-cell"><x-level-badge :level="$conditions->level" /></td>
                        <td class="hidden px-4 py-2.5 sm:table-cell"><x-spill-badge :recency="$conditions->overflows->spillRecency" :stale="$conditions->overflows->isStale()" nearby /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
