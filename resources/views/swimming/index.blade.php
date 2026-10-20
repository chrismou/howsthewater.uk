@php
    $all = $regions->flatten(1);
    $counts = $all->countBy('kind');
    $placeCounts = collect([
        ($counts['river'] ?? 0).' river '.\Illuminate\Support\Str::plural('spot', $counts['river'] ?? 0) => $counts['river'] ?? 0,
        ($counts['beach'] ?? 0).' '.\Illuminate\Support\Str::plural('beach', $counts['beach'] ?? 0) => $counts['beach'] ?? 0,
        ($counts['lake'] ?? 0).' '.\Illuminate\Support\Str::plural('lake', $counts['lake'] ?? 0) => $counts['lake'] ?? 0,
    ])->filter()->keys()->join(', ', ' and ');
    $breadcrumbs = [['Home', route('home')], ['Swimming spots', route('swimming')]];
    $dischargingNow = fn ($spot) => ! $spot->stale && $spot->spillRecency === \App\Enums\SpillRecency::DischargingNow;

    // The "near me" rows are drawn by swimming.js, so their styles are set once on the list; pills take their colour
    // from data-pill (SpillRecency::tone(), as components/spill-badge does). The detail line wraps rather than being
    // cut short, so the distance at its end always shows.
    $listClasses = 'mt-3 divide-y divide-line-2 rounded-lg border border-line bg-surface [&_a]:flex [&_a]:flex-col [&_a]:items-start [&_a]:gap-1.5 [&_a]:px-4 [&_a]:py-2.5 [&_a:hover]:bg-surface-2 sm:[&_a]:flex-row sm:[&_a]:items-center sm:[&_a]:justify-between sm:[&_a]:gap-3 [&_b]:block [&_b]:truncate [&_b]:font-semibold [&_small]:block [&_small]:text-xs [&_small]:text-muted'
        .' [&_[data-pill]]:inline-flex [&_[data-pill]]:shrink-0 [&_[data-pill]]:items-center [&_[data-pill]]:gap-1.5 [&_[data-pill]]:whitespace-nowrap [&_[data-pill]]:rounded-full [&_[data-pill]]:px-2.5 [&_[data-pill]]:py-0.5 [&_[data-pill]]:text-xs [&_[data-pill]]:font-medium [&_[data-pill]]:ring-1 [&_[data-pill]]:ring-inset'
        .' [&_[data-pill=red]]:bg-pill-red-bg [&_[data-pill=red]]:text-pill-red-text [&_[data-pill=red]]:ring-pill-red-ring [&_[data-pill=amber]]:bg-pill-amber-bg [&_[data-pill=amber]]:text-pill-amber-text [&_[data-pill=amber]]:ring-pill-amber-ring'
        .' [&_[data-pill=green]]:bg-pill-green-bg [&_[data-pill=green]]:text-pill-green-text [&_[data-pill=green]]:ring-pill-green-ring [&_[data-pill=grey]]:bg-pill-grey-bg [&_[data-pill=grey]]:text-pill-grey-text [&_[data-pill=grey]]:ring-pill-grey-ring';
@endphp

<x-layouts.app title="Swimming spots near you: bathing water quality and spills"
               description="Every river spot, beach and lake we cover in England, Scotland and Wales on one map, with storm overflow spills and bathing water results now."
               :breadcrumbs="$breadcrumbs" :date-modified="$dataUpdatedAt" :live-summary="$liveSummary">
    <div class="mb-6 max-w-2xl">
        <x-breadcrumbs :items="$breadcrumbs" />
        <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">Swimming spots near you</h1>
        <p class="mt-2 text-ink-2">
            {{ $placeCounts }} in England, Scotland and Wales, with what the storm overflow monitors report upstream or nearby right now. It never says whether the water is safe: open a place for its levels, rainfall and bathing water results before you go.
        </p>
    </div>

    <div data-swimming data-places-url="{{ route('swimming.places') }}">
        {{-- The controls need JavaScript, so they stay hidden without it; the regions below still link to every place. --}}
        <div data-swimming-controls hidden class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-3">
            <button type="button" data-swimming-near class="inline-flex min-h-11 items-center rounded-lg bg-accent px-4 text-sm font-semibold text-surface hover:bg-accent-strong">Show spots near me</button>
            <div role="group" aria-label="Show" class="flex rounded-md border border-line text-sm">
                @foreach (['all' => 'All', 'river' => 'Rivers', 'beach' => 'Beaches', 'lake' => 'Lakes'] as $kind => $label)
                    <button type="button" data-swimming-kind="{{ $kind }}" aria-pressed="{{ $kind === 'all' ? 'true' : 'false' }}"
                            class="min-h-11 px-3 first:rounded-l-md last:rounded-r-md aria-pressed:bg-surface-2 aria-pressed:font-medium aria-pressed:text-ink text-muted hover:text-ink">{{ $label }}</button>
                @endforeach
            </div>
            <p data-swimming-status role="status" class="text-sm text-muted"></p>
        </div>

        <div class="mb-8">
            <div class="h-[28rem] w-full rounded-lg border border-line" role="region" aria-label="Map of every river spot, beach and lake, coloured by overflow activity upstream or nearby"
                 data-map data-swimming-map data-map-markers-url="{{ route('swimming.places') }}"></div>
            <x-map-key where="upstream or nearby" />
        </div>

        {{-- Filled by swimming.js once a visitor shares their location: the nearest places, a batch at a time. --}}
        <section data-swimming-nearest hidden class="mb-10">
            <h2 class="font-display text-2xl font-semibold tracking-tight">Nearest first</h2>
            <ol data-swimming-nearest-list class="{{ $listClasses }}"></ol>
            <button type="button" data-swimming-more hidden class="mt-3 text-sm font-medium text-accent hover:underline"></button>
        </section>

        <section data-swimming-regions aria-labelledby="swimming-regions">
            <h2 id="swimming-regions" class="font-display text-2xl font-semibold tracking-tight">By region</h2>
            <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($regions as $regionSpots)
                    @php
                        $region = $regionSpots->first();
                        $kinds = $regionSpots->countBy('kind');
                        $discharging = $regionSpots->filter($dischargingNow)->count();
                        $links = array_filter([
                            'Rivers' => isset($kinds['river']) ? route('rivers.index', $region->regionSlug) : null,
                            'Beaches' => isset($kinds['beach']) ? route('beaches.index', $region->regionSlug) : null,
                            'Lakes' => isset($kinds['lake']) ? route('lakes.index', $region->regionSlug) : null,
                        ]);
                    @endphp
                    <li class="flex flex-col gap-2 rounded-lg border border-line bg-surface p-4">
                        <h3 class="font-display text-lg font-semibold"><a href="{{ route('regions.show', $region->regionSlug) }}" class="hover:underline">{{ $region->regionName }}</a></h3>
                        <p class="text-sm text-ink-2">{{ collect(['river' => 'river spot', 'beach' => 'beach', 'lake' => 'lake'])->filter(fn ($noun, $kind) => isset($kinds[$kind]))->map(fn ($noun, $kind) => $kinds[$kind].' '.\Illuminate\Support\Str::plural($noun, $kinds[$kind]))->join(' · ') }}</p>
                        @if ($discharging > 0)
                            <p class="inline-flex items-center gap-1.5 text-sm font-medium text-alert"><x-live-dot />{{ $discharging }} with an overflow discharging upstream or nearby now</p>
                        @else
                            <p class="text-sm text-muted">No overflow discharging upstream or nearby now</p>
                        @endif
                        <p class="mt-auto flex flex-wrap gap-x-4 gap-y-1 pt-1 text-sm">
                            <a href="{{ route('regions.show', $region->regionSlug) }}" class="font-medium text-accent hover:underline">Full region</a>
                            @foreach ($links as $label => $url)
                                <a href="{{ $url }}" class="font-medium text-accent hover:underline">{{ $label }}</a>
                            @endforeach
                        </p>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</x-layouts.app>
