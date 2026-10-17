{{--
    A region as a card with a small map and four figures, for one kind of place: rivers (spots coloured by spill
    status upstream), beaches (coloured by classification) or lakes (coloured by spill status nearby). Used by the
    home page, the Rivers, Beaches and Lakes pages and each region's overview, where inRegion titles the card by kind
    of place instead of by region. summary: a RegionSummary.
--}}
@props(['summary', 'type' => 'rivers', 'inRegion' => false])

@php
    $place = fn ($item) => ['lat' => $item->latitude, 'lng' => $item->longitude, 'label' => $item->name];
    $classifications = $summary->beaches->countBy(fn ($beach) => $beach->classification?->value);

    // Overflow figures for a region whose feed we do not read yet show as a dash, never as zero.
    $overflowFigure = fn (int $value) => $summary->hasOverflowData ? $value : '-';

    [$url, $markers, $blurb, $figures] = match ($type) {
        'beaches' => [
            route('beaches.index', $summary->slug),
            $summary->beaches->map(fn ($beach) => [...$place($beach), 'kind' => 'bathing', 'classification' => $beach->classification?->value]),
            $summary->beaches->count().' designated '.\Illuminate\Support\Str::plural('beach', $summary->beaches->count()).', with their classification and the storm overflows within 3 km.',
            [
                ['Beaches', $summary->beaches->count(), null],
                ['Excellent or good', $classifications->get('Excellent', 0) + $classifications->get('Good', 0), null],
                ['Overflow discharging nearby', $overflowFigure($summary->beaches->where('dischargingNearbyCount', '>', 0)->count()), 'text-alert', 'beaches'],
                ['Classified poor', $classifications->get('Poor', 0), 'text-alert'],
            ],
        ],
        'lakes' => [
            route('lakes.index', $summary->slug),
            $summary->lakes->map(fn ($lake) => [...$place($lake), 'kind' => 'spot', 'recency' => $lake->nearbySpillRecency->value]),
            $summary->lakes->pluck('name')->join(', ', ' and ').'.',
            [
                ['Lakes', $summary->lakes->count(), null],
                ['Bathing sites', $summary->lakes->sum('bathingSiteCount'), null],
                ['Overflow discharging nearby', $overflowFigure($summary->lakes->where('dischargingNearbyCount', '>', 0)->count()), 'text-alert', 'lakes'],
                ['Spill nearby in last 48h', $overflowFigure($summary->lakes->filter->spilledNearbyRecently()->count()), 'text-spill', 'lakes'],
            ],
        ],
        default => [
            route('rivers.index', $summary->slug),
            $summary->spots()->map(fn ($spot) => [...$place($spot), 'kind' => 'spot', 'recency' => $spot->spillRecency->value]),
            $summary->riversByCatchment()->keys()->join(', ', ' and ').'. '.$summary->rivers->count().' '.\Illuminate\Support\Str::plural('river', $summary->rivers->count()).', source to mouth.',
            [
                ['Rivers', $summary->rivers->count(), null],
                ['Spots', $summary->spots()->count(), null],
                ['Discharging now', $overflowFigure($summary->dischargingNowCount()), 'text-alert'],
                ['Spill in last 24h', $overflowFigure($summary->spotsWithSpillLast24HoursCount()), 'text-spill', 'spots'],
            ],
        ],
    };
@endphp

<a href="{{ $url }}" {{ $attributes->merge(['class' => 'flex flex-col overflow-hidden rounded-lg border border-line bg-surface transition hover:border-muted hover:shadow-sm']) }}>
    <div class="h-56 border-b border-line bg-water" data-map data-map-static data-map-markers="{{ json_encode($markers->values()) }}"></div>
    <div class="flex flex-1 flex-col gap-4 p-6">
        <span class="text-xs font-semibold uppercase tracking-[0.08em] text-accent">{{ $inRegion ? $summary->name : 'Region' }}</span>
        <h3 class="font-display text-3xl font-semibold tracking-tight">{{ $inRegion ? \Illuminate\Support\Str::ucfirst($type) : $summary->name }}</h3>
        <p class="text-sm text-ink-2">{{ $blurb }}</p>
        <dl class="grid grid-cols-2 gap-x-5 gap-y-3 text-sm">
            @foreach ($figures as $figure)
                <div>
                    <dt class="text-muted">{{ $figure[0] }}</dt>
                    <dd class="font-mono text-xl font-semibold tabular-nums {{ $figure[2] !== null && is_int($figure[1]) && $figure[1] > 0 ? $figure[2] : '' }}">
                        {{ $figure[1] }}
                        @if (isset($figure[3]) && is_int($figure[1]))
                            <span class="font-sans text-sm font-normal text-muted">{{ $figure[3] }}</span>
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
        <span class="mt-auto inline-flex self-start rounded-lg border border-line px-4 py-2 text-sm font-semibold">View {{ $summary->name }} {{ $type }}</span>
    </div>
</a>
