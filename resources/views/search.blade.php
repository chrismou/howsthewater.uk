<x-layouts.app :title="$query === '' ? 'Search' : 'Search for '.$query" noindex>
    <div class="mb-6">
        <p class="text-sm text-muted"><a href="{{ route('home') }}" class="hover:underline">All rivers</a></p>
        <h1 class="font-display mt-1 text-3xl font-semibold tracking-tight">
            @if ($query === '')
                Find a spot or river
            @else
                Results for "{{ $query }}"
            @endif
        </h1>
    </div>

    @if ($query === '')
        <p class="text-ink-2">Type a spot, town or river name into the search box above.</p>
    @elseif ($spots->isEmpty() && $rivers->isEmpty() && $beaches->isEmpty() && $lakes->isEmpty())
        <p class="text-ink-2">Nothing matched "{{ $query }}". Try the name of a town on the river, or pick a river from the menu.</p>
    @else
        @if ($rivers->isNotEmpty())
            <h2 class="font-display mb-3 text-lg font-semibold">Rivers</h2>
            <ul class="mb-8 divide-y divide-line-2 rounded-lg border border-line bg-surface">
                @foreach ($rivers as $river)
                    <li>
                        <a href="{{ route('rivers.show', [$river->region, $river]) }}" class="flex items-baseline justify-between gap-3 px-4 py-3 hover:bg-surface-2">
                            <span class="font-medium">{{ $river->name }}</span>
                            <span class="text-sm text-muted">{{ $river->region->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        @foreach (['Beaches' => $beaches->map(fn ($beach) => ['name' => $beach->name, 'url' => route('beaches.show', [$beach->region, $beach]), 'where' => $beach->region->name]),
                   'Lakes' => $lakes->map(fn ($lake) => ['name' => $lake->name, 'url' => route('lakes.show', [$lake->region, $lake]), 'where' => $lake->region->name])] as $heading => $results)
            @if ($results->isNotEmpty())
                <h2 class="font-display mb-3 text-lg font-semibold">{{ $heading }}</h2>
                <ul class="mb-8 divide-y divide-line-2 rounded-lg border border-line bg-surface">
                    @foreach ($results as $result)
                        <li>
                            <a href="{{ $result['url'] }}" class="flex items-baseline justify-between gap-3 px-4 py-3 hover:bg-surface-2">
                                <span class="font-medium">{{ $result['name'] }}</span>
                                <span class="text-sm text-muted">{{ $result['where'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        @endforeach

        @if ($spots->isNotEmpty())
            <h2 class="font-display mb-3 text-lg font-semibold">Spots</h2>
            <ul class="divide-y divide-line-2 rounded-lg border border-line bg-surface">
                @foreach ($spots as $spot)
                    <li>
                        <a href="{{ route('spots.show', [$spot->river->region, $spot->river, $spot]) }}" class="flex items-baseline justify-between gap-3 px-4 py-3 hover:bg-surface-2">
                            <span class="font-medium">{{ $spot->name }}</span>
                            <span class="text-sm text-muted">{{ $spot->river->name }}, {{ $spot->river->region->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</x-layouts.app>
