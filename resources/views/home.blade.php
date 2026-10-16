@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $allRegions = \App\Data\RegionSummary::combine($regions);
    $totalSpots = $allRegions->spots()->count();
    $medianRain = $allRegions->medianRainfallLast24HoursMillimetres();
    $regionNames = $regions->pluck('name')->join(', ', ' and ');
    $placeCounts = collect([
        $totalSpots.' river '.\Illuminate\Support\Str::plural('spot', $totalSpots) => $totalSpots,
        $totalBeaches.' '.\Illuminate\Support\Str::plural('beach', $totalBeaches) => $totalBeaches,
        $totalLakes.' '.\Illuminate\Support\Str::plural('lake', $totalLakes) => $totalLakes,
    ])->filter()->keys()->join(', ', ' and ');
    $companyNames = \App\Models\Region::query()->orderBy('name')->get()->flatMap->waterCompanies()->unique()->join(', ', ' and ') ?: 'Water company';
@endphp

<x-layouts.app title="Storm overflows, river levels and bathing water results"
               description="Live storm overflow spills, river levels and bathing water results for swimming, paddling and fishing on rivers, beaches and lakes in England and Scotland."
               :live-summary="$allRegions">
    <x-slot:hero>
        <section class="relative overflow-hidden border-b border-line bg-surface">
            <svg aria-hidden="true" class="pointer-events-none absolute inset-0 h-full w-full text-accent" viewBox="0 0 1280 520" preserveAspectRatio="none" fill="none" stroke="currentColor" stroke-opacity="0.12" stroke-width="1.2">
                <path d="M-20 120 C 200 60, 380 200, 620 140 S 1000 40, 1300 120"/>
                <path d="M-20 170 C 220 110, 400 250, 640 190 S 1020 90, 1300 170"/>
                <path d="M-20 220 C 240 160, 420 300, 660 240 S 1040 140, 1300 220"/>
                <path d="M-20 270 C 260 210, 440 350, 680 290 S 1060 190, 1300 270"/>
                <path d="M-20 320 C 280 260, 460 400, 700 340 S 1080 240, 1300 320"/>
                <path d="M-20 370 C 300 310, 480 450, 720 390 S 1100 290, 1300 370"/>
                <path d="M-20 420 C 320 360, 500 500, 740 440 S 1120 340, 1300 420"/>
                <path d="M-20 470 C 340 410, 520 550, 760 490 S 1140 390, 1300 470"/>
            </svg>
            <div class="relative mx-auto grid max-w-5xl gap-8 px-4 pt-8 pb-12 sm:py-14 lg:grid-cols-12 lg:py-16">
                <div class="flex flex-col gap-5 lg:col-span-7">
                    <p class="text-xs font-semibold uppercase tracking-[0.08em] text-accent">{{ $regionNames ?: 'Rivers' }}, updated every 15 minutes</p>
                    <h1 class="font-display text-4xl font-semibold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl">How's your local river, beach or lake looking today?</h1>
                    <p class="max-w-xl text-lg text-ink-2">
                        Storm overflow spills, river levels, rainfall, flood warnings and bathing water results for {{ $placeCounts }}. Pulled straight from the public monitors.
                    </p>
                    <form action="{{ route('search') }}" method="get" role="search" class="flex max-w-xl flex-col gap-2 sm:flex-row">
                        <label for="hero-search" class="sr-only">Search spots, rivers, beaches and lakes</label>
                        <input id="hero-search" type="search" name="q" placeholder="Try {{ $featured->pluck('name')->take(2)->join(' or ') }}"
                               class="min-h-12 flex-1 rounded-lg border border-line bg-surface px-4 text-base text-ink placeholder:text-faint focus:border-accent focus:outline-none focus:ring-1 focus:ring-accent">
                        <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-accent px-5 text-sm font-semibold text-surface hover:bg-accent-strong">Search</button>
                    </form>
                    @if ($featured->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-2 text-sm text-muted">
                            <span>Try:</span>
                            @foreach ($featured as $spot)
                                <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}" class="rounded-full border border-pill-grey-ring bg-pill-grey-bg px-2.5 py-0.5 text-[13px] font-medium text-pill-grey-text hover:border-muted">{{ $spot->name }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div class="rounded-lg border border-line bg-surface p-6 lg:col-span-5">
                    <div class="flex items-baseline justify-between">
                        <span class="text-xs font-semibold uppercase tracking-[0.08em] text-muted">Across all rivers now</span>
                        @if ($allRegions->dataUpdatedAt)
                            <span class="font-mono text-xs text-muted">{{ $allRegions->dataUpdatedAt->timezone($timezone)->format('H:i') }}</span>
                        @endif
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-5">
                        <div>
                            <dd class="font-mono text-4xl font-semibold leading-none tabular-nums {{ $allRegions->dischargingNowCount() > 0 ? 'text-alert' : '' }}">{{ $allRegions->dischargingNowCount() }}</dd>
                            <dt class="mt-1.5 text-sm text-ink-2">{{ \Illuminate\Support\Str::plural('overflow', $allRegions->dischargingNowCount()) }} discharging now <x-help term="discharging_now" /></dt>
                        </div>
                        <div>
                            <dd class="font-mono text-4xl font-semibold leading-none tabular-nums {{ $allRegions->spotsWithSpillLast24HoursCount() > 0 ? 'text-spill' : '' }}">{{ $allRegions->spotsWithSpillLast24HoursCount() }}</dd>
                            <dt class="mt-1.5 text-sm text-ink-2">spots with a spill upstream in the last 24h</dt>
                        </div>
                        <div>
                            <dd class="font-mono text-4xl font-semibold leading-none tabular-nums">{{ $allRegions->spotsAboveTypicalRange()->count() }}</dd>
                            <dt class="mt-1.5 text-sm text-ink-2">gauges above their typical range</dt>
                        </div>
                        <div>
                            <dd class="font-mono text-4xl font-semibold leading-none tabular-nums">{{ $medianRain === null ? '-' : number_format($medianRain, 1) }}<span class="text-lg"> mm</span></dd>
                            <dt class="mt-1.5 text-sm text-ink-2">median rainfall, last 24h</dt>
                        </div>
                    </dl>
                    <ul class="mt-5 divide-y divide-line-2 border-t border-line text-sm">
                        @foreach ($regions as $region)
                            <li>
                                <a href="{{ route('regions.show', $region->slug) }}" class="group flex items-baseline justify-between gap-3 py-2">
                                    <span class="font-semibold group-hover:underline">{{ $region->name }}</span>
                                    <span class="text-muted">
                                        <span class="font-mono font-semibold {{ $region->dischargingNowCount() > 0 ? 'text-alert' : 'text-ink' }}">{{ $region->dischargingNowCount() }}</span> discharging ·
                                        <span class="font-mono">{{ $region->spots()->count() }}</span> spots <span class="text-accent">→</span>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <p class="border-t border-line pt-3 text-xs text-muted">{{ number_format($allRegions->monitorCount) }} monitors · {{ $allRegions->gaugeCount }} gauges · {{ $allRegions->rainGaugeCount }} rain gauges</p>
                </div>
            </div>
        </section>
    </x-slot:hero>

    <div id="flood-warnings">
        @foreach ($regions as $region)
            <x-flood-warning-notice :summary="$region" :spot-limit="5" link-region />
        @endforeach
    </div>

    <section id="how" class="grid gap-8 py-12 lg:grid-cols-12 lg:grid-rows-[auto_1fr] lg:gap-x-12 lg:gap-y-7">
        <div class="flex flex-col gap-3 lg:col-span-4 lg:row-start-1">
            <div class="h-[3px] w-14 rounded bg-accent"></div>
            <h2 class="font-display text-3xl font-semibold tracking-tight">How to read it</h2>
            <p class="text-ink-2">River spot pages show all three readings, plus any flood warning in force. Together they tell you whether the river is likely to be carrying sewage, running high, or about to. Beach and lake pages show overflows nearby, rainfall and their bathing water results.</p>
        </div>
        <ol class="divide-y divide-line border-y border-line lg:col-span-8 lg:col-start-5 lg:row-span-2 lg:row-start-1">
            <li class="grid grid-cols-[2.75rem_1fr] gap-3 py-6 sm:grid-cols-[4.5rem_1fr] sm:gap-4">
                <span class="font-mono text-2xl text-accent sm:text-3xl">01</span>
                <div class="flex flex-col gap-2">
                    <h3 class="font-display text-xl font-semibold">Storm overflows upstream <x-help term="spill" /></h3>
                    <p class="text-ink-2">{{ $companyNames }} monitors report every overflow that is discharging. We trace every monitor upstream of a spot, tributaries included, and summarise the last 48 hours and 30 days.</p>
                    <p class="text-sm text-muted">The sea and lakes have no upstream, so beaches and lakes list every overflow within 3 km instead. <x-help term="nearby_overflows" /></p>
                    <div class="flex flex-wrap gap-1.5">
                        <x-spill-badge :recency="\App\Enums\SpillRecency::DischargingNow" />
                        <x-spill-badge :recency="\App\Enums\SpillRecency::Within24Hours" />
                        <x-spill-badge :recency="\App\Enums\SpillRecency::None" />
                        <x-spill-badge :recency="\App\Enums\SpillRecency::Offline" />
                    </div>
                </div>
            </li>
            <li class="grid grid-cols-[2.75rem_1fr] gap-3 py-6 sm:grid-cols-[4.5rem_1fr] sm:gap-4">
                <span class="font-mono text-2xl text-accent sm:text-3xl">02</span>
                <div class="flex flex-col gap-2">
                    <h3 class="font-display text-xl font-semibold">River level and trend <x-help term="level" /></h3>
                    <p class="text-ink-2">Environment Agency and SEPA gauges read the river every 15 minutes. The latest reading, the change over 3 hours and where it sits in the gauge's typical range. High and rising means fast, cold water.</p>
                    <p class="flex items-baseline gap-3"><span class="font-mono text-2xl font-semibold tabular-nums">0.43 m</span><span class="text-sm text-muted">↑ rising · within typical range <x-help term="typical_range" /></span></p>
                </div>
            </li>
            <li class="grid grid-cols-[2.75rem_1fr] gap-3 py-6 sm:grid-cols-[4.5rem_1fr] sm:gap-4">
                <span class="font-mono text-2xl text-accent sm:text-3xl">03</span>
                <div class="flex flex-col gap-2">
                    <h3 class="font-display text-xl font-semibold">Rainfall nearby <x-help term="rainfall" /></h3>
                    <p class="text-ink-2">Totals from the nearest rain gauge over 6 hours, 24 hours and 3 days. Rain is what triggers overflows and lifts levels, so it is the earliest signal you get.</p>
                    <p class="flex items-baseline gap-3"><span class="font-mono text-2xl font-semibold tabular-nums">12.4 mm</span><span class="text-sm text-muted">last 24h · gauge 2.2 km away</span></p>
                </div>
            </li>
        </ol>
        {{-- After the readings on phones; under the intro in the left column on wide screens. --}}
        <div class="flex flex-col divide-y divide-line-2 rounded-lg border border-line bg-surface lg:col-span-4 lg:row-start-2 lg:self-start">
            <div class="flex flex-col gap-2 p-5">
                <h3 class="font-display text-lg font-semibold">Flood alerts and warnings <x-help term="flood_warning" /></h3>
                <p class="text-sm text-ink-2">Shown in the Environment Agency's own words when one is in force for a spot's stretch of river.</p>
                <div class="flex flex-wrap gap-1.5">
                    <x-flood-severity-badge :severity="\App\Enums\FloodSeverity::SevereFloodWarning" class="py-0.5" />
                    <x-flood-severity-badge :severity="\App\Enums\FloodSeverity::FloodWarning" class="py-0.5" />
                    <x-flood-severity-badge :severity="\App\Enums\FloodSeverity::FloodAlert" class="py-0.5" />
                </div>
            </div>
            <div class="flex flex-col gap-2 p-5">
                <h3 class="font-display text-lg font-semibold">Bathing water results <x-help term="bathing_classification" /></h3>
                <p class="text-sm text-ink-2">For designated beaches, lakes and river sites: the yearly classification, recent sample counts and, in season, the daily pollution risk forecast.</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ([\App\Enums\BathingWaterClassification::Excellent, \App\Enums\BathingWaterClassification::Sufficient, \App\Enums\BathingWaterClassification::Poor] as $classification)
                        <x-classification-pill :site="(object) ['classification' => $classification, 'classification_year' => null]" />
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="py-12">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div class="flex flex-col gap-3">
                <div class="h-[3px] w-14 rounded bg-accent"></div>
                <h2 class="font-display text-3xl font-semibold tracking-tight">Regions</h2>
            </div>
            <p class="max-w-md text-sm text-muted">
                {{ $regions->count() === 1 ? 'One so far' : $regions->count().' so far' }}, with more on the way.
                <a href="https://github.com/chrismou/howsthewater.uk/issues" rel="noopener" class="whitespace-nowrap font-semibold text-accent hover:underline">Suggest a region/river →</a>
            </p>
        </div>
        <div class="grid gap-5 lg:grid-cols-2">
            @foreach ($regions as $region)
                @php
                    $markers = $region->spots()->map(fn ($spot) => ['lat' => $spot->latitude, 'lng' => $spot->longitude, 'label' => $spot->name, 'kind' => 'spot', 'recency' => $spot->spillRecency->value])->values();
                @endphp
                <a href="{{ route('regions.show', $region->slug) }}" class="flex flex-col overflow-hidden rounded-lg border border-line bg-surface transition hover:border-muted hover:shadow-sm">
                    <div class="h-56 border-b border-line bg-water" data-map data-map-static data-map-markers="{{ json_encode($markers) }}"></div>
                    <div class="flex flex-1 flex-col gap-4 p-6">
                        <span class="text-xs font-semibold uppercase tracking-[0.08em] text-accent">Region</span>
                        <h3 class="font-display text-3xl font-semibold tracking-tight">{{ $region->name }}</h3>
                        <p class="text-sm text-ink-2">{{ $region->riversByCatchment()->keys()->join(', ', ' and ') }}. {{ $region->rivers->count() }} {{ \Illuminate\Support\Str::plural('river', $region->rivers->count()) }}, source to mouth.</p>
                        <dl class="grid grid-cols-2 gap-x-5 gap-y-3 text-sm">
                            <div><dt class="text-muted">Rivers</dt><dd class="font-mono text-xl font-semibold tabular-nums">{{ $region->rivers->count() }}</dd></div>
                            <div><dt class="text-muted">Spots</dt><dd class="font-mono text-xl font-semibold tabular-nums">{{ $region->spots()->count() }}</dd></div>
                            <div><dt class="text-muted">Discharging now</dt><dd class="font-mono text-xl font-semibold tabular-nums {{ $region->dischargingNowCount() > 0 ? 'text-alert' : '' }}">{{ $region->dischargingNowCount() }}</dd></div>
                            <div><dt class="text-muted">Spill in last 24h</dt><dd class="font-mono text-xl font-semibold tabular-nums {{ $region->spotsWithSpillLast24HoursCount() > 0 ? 'text-spill' : '' }}">{{ $region->spotsWithSpillLast24HoursCount() }} <span class="font-sans text-sm font-normal text-muted">spots</span></dd></div>
                        </dl>
                        <span class="mt-auto inline-flex self-start rounded-lg border border-line px-4 py-2 text-sm font-semibold">View {{ $region->name }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>

    <x-slot:after>
        <section id="limits" class="bg-ink text-ground dark:bg-surface-2 dark:text-ink">
            <div class="mx-auto grid max-w-5xl gap-8 px-4 py-14 lg:grid-cols-12">
                <div class="flex flex-col gap-3 lg:col-span-5">
                    <div class="h-[3px] w-14 rounded bg-ground dark:bg-ink"></div>
                    <h2 class="font-display text-3xl font-semibold tracking-tight">What this site does not do</h2>
                </div>
                <ul class="grid gap-4 lg:col-span-7">
                    <li class="flex gap-3"><span class="text-spill">-</span><span>It does not say whether water is safe or clean. Nobody measures that in real time.</span></li>
                    <li class="flex gap-3"><span class="text-spill">-</span><span>It does not know about spills from monitors that are offline, or from anything the water company does not monitor.</span></li>
                    <li class="flex gap-3"><span class="text-spill">-</span><span>It does not validate the data. Feeds are near real-time and unchecked. When a feed goes quiet, the page says so.</span></li>
                </ul>
            </div>
        </section>
    </x-slot:after>
</x-layouts.app>
