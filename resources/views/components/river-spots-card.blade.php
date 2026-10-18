{{--
    A main river as a card with its first six spots (spill status upstream, level, rain and monitors), for a region's
    rivers page. regionSlug is the river's own region, which may not be the page's (the Thames on London's); spotSlugs
    then picks its spots in the page's region.
--}}
@props(['river', 'regionSlug', 'spotSlugs' => []])

@php
    // A river's spots in another region are a short list picked for it, so they all show; otherwise its first six.
    $shownSpots = $spotSlugs === [] ? $river->spots->take(6) : $river->spots->whereIn('slug', $spotSlugs)->values();
@endphp

<div class="overflow-hidden rounded-lg border border-line bg-surface">
    <div class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-3">
        <a href="{{ route('rivers.show', [$regionSlug, $river->slug]) }}" class="font-display text-lg font-semibold hover:underline">{{ $river->name }}</a>
        <span class="text-xs text-muted">{{ $river->spots->count() }} {{ \Illuminate\Support\Str::plural('spot', $river->spots->count()) }} · {{ $river->monitorCount }} {{ \Illuminate\Support\Str::plural('monitor', $river->monitorCount) }}{{ $river->lengthMetres ? ' · '.number_format($river->lengthMetres / 1000).' km' : '' }}</span>
    </div>
    <div class="overflow-x-auto">
        {{-- Fixed column widths on wide screens, sized to the longest badge and reading, so every river's table lines up; the spot takes the rest. --}}
        <table class="w-full text-sm lg:table-fixed">
            <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-2 font-medium">Spot</th>
                    <th class="hidden px-4 py-2 font-medium sm:table-cell lg:w-66">Upstream spills</th>
                    <th class="hidden px-4 py-2 font-medium sm:table-cell lg:w-80">Level</th>
                    <th class="hidden px-4 py-2 font-medium sm:table-cell lg:w-24">Rain 24h</th>
                    <th class="hidden px-4 py-2 font-medium sm:table-cell lg:w-24">Monitors</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line-2">
                @foreach ($shownSpots as $spot)
                    <tr class="hover:bg-surface-2">
                        {{-- On phones the other columns fold into this one, under the spot name. --}}
                        <td class="px-4 py-2 sm:whitespace-nowrap lg:whitespace-normal">
                            <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}" class="font-semibold hover:underline">{{ $spot->name }}</a>
                            <div class="mt-1 flex flex-col items-start gap-1 sm:hidden">
                                <x-spill-badge :recency="$spot->spillRecency" :stale="$spot->overflowDataIsStale()" />
                                <span class="text-xs text-muted">
                                    {{ $spot->levelValue === null ? 'No gauge' : number_format($spot->levelValue, 2).' '.$spot->levelUnit.' '.strtolower($spot->levelTrend->label()) }}
                                    · {{ $spot->rainfallLast24HoursMillimetres === null ? 'no rain gauge' : number_format($spot->rainfallLast24HoursMillimetres, 1).' mm rain' }}
                                </span>
                            </div>
                        </td>
                        <td class="hidden whitespace-nowrap px-4 py-2 sm:table-cell"><x-spill-badge :recency="$spot->spillRecency" :stale="$spot->overflowDataIsStale()" /></td>
                        <td class="hidden whitespace-nowrap px-4 py-2 sm:table-cell">
                            @if ($spot->levelValue === null)
                                <span class="text-muted">No gauge</span>
                            @else
                                <span class="font-mono tabular-nums">{{ number_format($spot->levelValue, 2) }} {{ $spot->levelUnit }}</span>
                                <span class="text-muted">{{ strtolower($spot->levelTrend->label()) }}{{ $spot->levelRelativeToTypical ? ' · '.\App\Data\LevelSummary::usualPhrase($spot->levelRelativeToTypical) : '' }}</span>
                            @endif
                        </td>
                        <td class="hidden whitespace-nowrap px-4 py-2 font-mono tabular-nums sm:table-cell">{{ $spot->rainfallLast24HoursMillimetres === null ? '-' : number_format($spot->rainfallLast24HoursMillimetres, 1).' mm' }}</td>
                        <td class="hidden whitespace-nowrap px-4 py-2 font-mono tabular-nums text-muted sm:table-cell">{{ $spot->monitorCount }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($river->spots->count() > $shownSpots->count())
        <a href="{{ route('rivers.show', [$regionSlug, $river->slug]) }}" class="block border-t border-line-2 px-4 py-2.5 text-center text-sm font-medium text-accent hover:underline">All {{ $river->spots->count() }} {{ \Illuminate\Support\Str::after($river->name, 'River ') }} spots</a>
    @endif
</div>
