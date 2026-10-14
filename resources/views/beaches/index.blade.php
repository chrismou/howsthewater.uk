<x-layouts.app :region="$region" :title="$region->name.' beaches'"
               :description="'Bathing water classifications, samples and nearby storm overflows for every designated beach in '.$region->name.'.'">
    <div class="mb-6">
        <p class="text-sm text-muted">
            <a href="{{ route('home') }}" class="hover:underline">All regions</a>
            <span class="mx-1">/</span>
            <a href="{{ route('regions.show', $region) }}" class="hover:underline">{{ $region->name }}</a>
        </p>
        <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $region->name }} beaches</h1>
        <p class="mt-2 text-ink-2">Every designated bathing water on the {{ $region->name }} coast, with its Environment Agency classification and the storm overflows nearby.</p>
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
                    @php($beach = $conditions->bathingWater->bathingWater)
                    <tr class="hover:bg-surface-2">
                        <td class="px-4 py-2.5">
                            <a href="{{ route('beaches.show', [$region, $beach]) }}" class="font-semibold hover:underline">{{ $beach->name }}</a>
                            <span class="block text-xs text-muted">{{ $beach->district }}</span>
                        </td>
                        <td class="px-4 py-2.5">{{ $beach->classification?->value ?? 'Not classified' }}{{ $beach->classification_year ? ' ('.$beach->classification_year.')' : '' }}</td>
                        <td class="hidden px-4 py-2.5 sm:table-cell"><x-spill-badge :recency="$conditions->overflows->spillRecency" :stale="$conditions->overflows->isStale()" nearby /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
