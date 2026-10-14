<x-layouts.app :title="$query === '' ? 'Search' : 'Search for '.$query">
    <div class="mb-6">
        <p class="text-sm text-stone-500"><a href="{{ route('home') }}" class="hover:underline">All rivers</a></p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight">
            @if ($query === '')
                Find a spot or river
            @else
                Results for "{{ $query }}"
            @endif
        </h1>
    </div>

    @if ($query === '')
        <p class="text-stone-600">Type a spot, town or river name into the search box above.</p>
    @elseif ($spots->isEmpty() && $rivers->isEmpty())
        <p class="text-stone-600">Nothing matched "{{ $query }}". Try the name of a town on the river, or pick a river from the menu.</p>
    @else
        @if ($rivers->isNotEmpty())
            <h2 class="mb-3 text-lg font-semibold">Rivers</h2>
            <ul class="mb-8 divide-y divide-stone-100 rounded-lg border border-stone-200 bg-white">
                @foreach ($rivers as $river)
                    <li>
                        <a href="{{ route('rivers.show', [$river->region, $river]) }}" class="flex items-baseline justify-between gap-3 px-4 py-3 hover:bg-stone-50">
                            <span class="font-medium">{{ $river->name }}</span>
                            <span class="text-sm text-stone-500">{{ $river->region->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($spots->isNotEmpty())
            <h2 class="mb-3 text-lg font-semibold">Spots</h2>
            <ul class="divide-y divide-stone-100 rounded-lg border border-stone-200 bg-white">
                @foreach ($spots as $spot)
                    <li>
                        <a href="{{ route('spots.show', [$spot->river->region, $spot->river, $spot]) }}" class="flex items-baseline justify-between gap-3 px-4 py-3 hover:bg-stone-50">
                            <span class="font-medium">{{ $spot->name }}</span>
                            <span class="text-sm text-stone-500">{{ $spot->river->name }}, {{ $spot->river->region->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</x-layouts.app>
