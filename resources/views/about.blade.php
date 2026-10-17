@php
    $regions = \App\Models\Region::query()->orderBy('name')->get();
    $overflowFeeds = collect($regions->reduce(fn (array $feeds, $region) => $feeds + $region->overflowFeedConfig(), []));
    $streamCompanies = $overflowFeeds->where('driver', 'stream')->pluck('name')->unique()->sort()->join(', ', ' and ');
    $hasScottishWater = $overflowFeeds->contains('driver', 'scottish_water');
@endphp

<x-layouts.app title="About the data and how it works"
               description="Where How's the Water gets its storm overflow, river level, rainfall, flood and bathing water data, how often it updates, and what it does not claim.">
    <article class="max-w-2xl">
        <p class="text-sm text-muted">Last updated 16 October 2026</p>
        <h1 class="font-display mt-1 text-3xl font-semibold tracking-tight">About the data and how it works</h1>

        <div class="mt-6 grid gap-8 leading-relaxed text-ink-2">
            <section class="grid gap-3">
                <p>How's the Water brings together public monitoring data for rivers, beaches and lakes in {{ $regions->pluck('name')->join(', ', ' and ') }}, so you can check conditions before a swim, paddle or fishing trip. For each place it shows what the storm overflow monitors upstream or nearby are reporting, the river or lake level, recent rainfall, any flood alerts and, at designated bathing waters, the official results.</p>
                <p>It reports what the monitors say. It never says the water is safe or clean, because nobody measures that in real time.</p>
            </section>

            <section class="grid gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">Where the data comes from</h2>
                <ul class="grid gap-2">
                    <li class="flex gap-3"><span class="text-muted">-</span><span><strong class="font-semibold text-ink">Storm overflows.</strong>
                        @if ($streamCompanies !== '')
                            {{ $streamCompanies }} publish their event duration monitors through <a href="https://www.streamwaterdata.co.uk/" rel="noopener" class="text-accent hover:underline">Stream</a> (CC BY 4.0).
                        @endif
                        @if ($hasScottishWater)
                            Scottish Water publishes its own near real-time overflow feed, updated hourly.
                        @endif
                        Dŵr Cymru Welsh Water's overflows are not shown yet, so Welsh spots, beaches and lakes say that overflow data is not available rather than showing none. Each feed gives a monitor's current status and its latest spill, so the spill history here is built up from our own readings, starting from the day each monitor was first seen.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span><strong class="font-semibold text-ink">River and lake levels.</strong> The Environment Agency's real-time flood monitoring API in England, Natural Resources Wales's river level, rainfall and sea data API in Wales, and SEPA's time series service in Scotland (all Open Government Licence). Readings from Natural Resources Wales are kept for a year at most, as it asks.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span><strong class="font-semibold text-ink">Rainfall.</strong> Tipping bucket rain gauges from the same three services, summed from their 15 minute readings.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span><strong class="font-semibold text-ink">Flood alerts and warnings.</strong> The Environment Agency's flood warning service in England and Natural Resources Wales's in Wales, shown in their own words. Scottish flood warnings are not shown yet.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span><strong class="font-semibold text-ink">Bathing waters.</strong> The Environment Agency's bathing water register for England, and Natural Resources Wales's for Wales: yearly classifications, sample results and, in season, daily pollution risk forecasts. In Scotland, classifications from SEPA's bathing water map service, with a link to each site's results page on SEPA's site.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span><strong class="font-semibold text-ink">Rivers and maps.</strong> River centrelines from Ordnance Survey's OS Open Rivers, and map tiles from OpenStreetMap.</span></li>
                </ul>
            </section>

            <section class="grid gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">How often it updates</h2>
                <p>Overflows, river levels, rainfall and flood warnings are read every 15 minutes, and the pages are rebuilt straight after. Bathing water records are read every hour. Every page shows when its monitor data was last updated, and says so plainly when a feed has not updated for over six hours, so old figures are never shown as current.</p>
            </section>

            <section class="grid gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">How a place's figures are worked out</h2>
                <ul class="grid gap-2">
                    <li class="flex gap-3"><span class="text-muted">-</span><span><strong class="font-semibold text-ink">Upstream.</strong> For a river spot, every overflow monitor whose discharge flows past it: those higher up the same river, plus everything on the tributaries and rivers that join above it. Distances follow the water, measured along the river centreline.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span><strong class="font-semibold text-ink">Nearby.</strong> The sea and lakes have no upstream, so beaches and lakes list every overflow monitor within 3 km in a straight line. Overflows further away, or up rivers that flow in, can still affect the water.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span><strong class="font-semibold text-ink">Discharging now, spills and offline.</strong> A spill is one period an overflow released untreated sewage mixed with rainwater. A monitor that is not reporting counts as offline, never as "no spills", and a place whose monitors are all offline says so.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span><strong class="font-semibold text-ink">Level and rainfall.</strong> Each spot uses the nearest level gauge within 25 km along its river, and the nearest rain gauge within 20 km. The typical range is the band the gauge usually sits in, as published by the Environment Agency or SEPA. Natural Resources Wales does not publish one, so for its gauges it is the band the level sat in 90% of the time over the last year.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span><strong class="font-semibold text-ink">Flood warnings.</strong> Shown for a spot when a warning area naming its river comes within 1 km of it.</span></li>
                </ul>
            </section>

            <section class="grid gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">What it does not do</h2>
                <ul class="grid gap-2">
                    <li class="flex gap-3"><span class="text-muted">-</span><span>It does not say whether water is safe or clean.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span>It does not know about spills from monitors that are offline, or from anything the water companies do not monitor.</span></li>
                    <li class="flex gap-3"><span class="text-muted">-</span><span>It does not validate the data. The feeds are near real-time and unchecked, and the agencies and water companies may correct them later.</span></li>
                </ul>
            </section>

            <section class="grid gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">Who runs it</h2>
                <p>How's the Water is an independent site built by <a href="https://mou.me" rel="noopener" class="text-accent hover:underline">Chris Chrisostomou</a> in Leeds. It is not connected to any water company or agency. Seen a mistake, or want a river added? <a href="https://github.com/chrismou/howsthewater.uk/issues" rel="noopener" class="text-accent hover:underline">Let us know on GitHub</a>. The <a href="{{ route('terms') }}" class="text-accent hover:underline">terms of use</a> cover reusing the data.</p>
            </section>
        </div>
    </article>
</x-layouts.app>
