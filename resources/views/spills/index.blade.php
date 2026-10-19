@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $stale = $overview->dataIsStale();
    // A region names itself; the combined page describes its coverage rather than naming countries it may not cover.
    $where = $region ? $region->nameInSentence() : 'the regions we cover';
    $title = $region
        ? collect(['Storm overflow spills in '.$region->nameInSentence().' now', 'Spills in '.$region->nameInSentence().' now', $region->name.' spills now'])->first(fn (string $candidate) => mb_strlen($candidate) <= 60, 'Storm overflow spills now')
        : 'Storm overflow spills now, region by region';
    $description = $region
        ? 'Storm overflows discharging now in '.$region->nameInSentence().', the spots with a spill upstream in the last 24 hours and every spill in that time.'
        : 'Storm overflows discharging now, the river spots with a spill upstream in the last 24 hours and every spill in that time, region by region.';
    // Long lists load their next rows from here (?offset=, plus the chosen order), and the order switch is a plain link.
    $rowsUrl = fn (string $list) => $region
        ? route('spills.region.rows', array_filter([$region, $list, 'order' => $overview->order === \App\Enums\DischargingOrder::Newest ? null : $overview->order->value]))
        : route('spills.rows', array_filter([$list, 'order' => $overview->order === \App\Enums\DischargingOrder::Newest ? null : $overview->order->value]));
    $orderUrl = fn (\App\Enums\DischargingOrder $order) => ($region ? route('spills.region', array_filter([$region, 'order' => $order === \App\Enums\DischargingOrder::Newest ? null : $order->value])) : route('spills.index', array_filter(['order' => $order === \App\Enums\DischargingOrder::Newest ? null : $order->value]))).'#discharging';
    $breadcrumbs = $region
        ? [['Home', route('home')], [$region->name, route('regions.show', $region)], ['Spills', route('spills.region', $region)]]
        : [['Home', route('home')], ['Spills', route('spills.index')]];
@endphp

<x-layouts.app :title="$title" :description="$description" :breadcrumbs="$breadcrumbs" :region="$region" :live-summary="$liveSummary" :date-modified="$overview->dataUpdatedAt">
    <div class="mb-8">
        <x-breadcrumbs :items="$breadcrumbs" />
        <h1 class="font-display mt-1 text-4xl font-semibold tracking-tight">{{ $region ? $overview->name.' spills now' : 'Spills now' }}</h1>
        <p class="mt-2 max-w-2xl text-ink-2">
            What the storm overflow monitors in {{ $where }} report: those discharging now, the spots with a spill upstream in the last 24 hours, and every spill in that time. Monitors report when an overflow is discharging, not how much or what it carries.
        </p>
        @if ($regionLinks->isNotEmpty())
            <p class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-sm">
                <span class="text-muted">By region:</span>
                @foreach ($regionLinks as $each)
                    <a href="{{ route('spills.region', $each) }}" class="font-medium text-accent hover:underline">{{ $each->name }}</a>
                @endforeach
            </p>
        @endif
    </div>

    @if ($stale)
        <div class="mb-8 rounded-md border border-warn-line bg-warn-bg px-4 py-3 text-sm text-warn-text" role="status">
            <p class="font-medium">Monitor data is out of date. <x-help term="stale" /></p>
            <p class="mt-1">
                @if ($overview->dataUpdatedAt)
                    No overflow feed here has updated for {{ $overview->dataUpdatedAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }} (last update {{ $overview->dataUpdatedAt->timezone($timezone)->format('j M, H:i T') }}).
                @else
                    The overflow feeds here gave no update time.
                @endif
                These lists describe the data as it was then, not what is happening now.
            </p>
        </div>
    @endif

    <section id="discharging" class="mb-10 scroll-mt-24">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <div class="flex items-center gap-1.5">
                <h2 class="font-display text-lg font-semibold">Overflows discharging right now<x-heading-count :count="$overview->dischargingNowTotal" :tone="$overview->dischargingNowTotal > 0 ? 'red' : 'grey'" /></h2>
                <x-help term="discharging_now" />
            </div>
            {{-- Newest first by default, so each visit shows what has just started; the longest running are a click away. --}}
            @if ($overview->dischargingNowTotal > 1 || $overview->dischargingNearWaterBodiesTotal > 1)
                <nav aria-label="Order of overflows discharging" class="flex rounded-md border border-line text-sm">
                    @foreach (\App\Enums\DischargingOrder::cases() as $order)
                        <a href="{{ $orderUrl($order) }}" @if ($order === $overview->order) aria-current="true" @endif
                           class="px-3 py-1.5 first:rounded-l-md last:rounded-r-md {{ $order === $overview->order ? 'bg-surface-2 font-medium text-ink' : 'text-muted hover:text-ink' }}">{{ $order->label() }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
        @if ($overview->dischargingNow->isEmpty())
            <p class="text-sm text-ink-2">{{ $stale ? 'No storm overflow on these rivers was reporting a discharge at the last update.' : 'No storm overflow on these rivers is reporting a discharge right now.' }}</p>
        @else
            @include('spills.discharging-table', ['listings' => $overview->dischargingNow, 'total' => $overview->dischargingNowTotal, 'placesHeading' => 'Spots downstream', 'label' => 'Storm overflows discharging now', 'order' => $overview->order, 'moreUrl' => $rowsUrl('discharging')])
        @endif
    </section>

    @if ($overview->dischargingNearWaterBodies->isNotEmpty())
        <section id="near-beaches-and-lakes" class="mb-10 scroll-mt-24">
            <h2 class="font-display mb-3 text-lg font-semibold">Discharging near beaches and lakes<x-heading-count :count="$overview->dischargingNearWaterBodiesTotal" tone="red" /></h2>
            @include('spills.discharging-table', ['listings' => $overview->dischargingNearWaterBodies, 'total' => $overview->dischargingNearWaterBodiesTotal, 'placesHeading' => 'Within 3 km', 'label' => 'Storm overflows discharging near beaches and lakes', 'order' => $overview->order, 'moreUrl' => $rowsUrl('near')])
        </section>
    @endif

    <section id="spots" class="mb-10 scroll-mt-24">
        <div class="mb-3 flex items-center gap-1.5">
            <h2 class="font-display text-lg font-semibold">Spots with an overflow spilling upstream in the last 24 hours<x-heading-count :count="$overview->spotsWithSpill->count()" :tone="$overview->spotsWithSpill->isNotEmpty() ? 'amber' : 'grey'" /></h2>
            <x-help term="upstream" />
        </div>
        @if ($overview->spotsWithSpill->isEmpty())
            <p class="text-sm text-ink-2">{{ $stale ? 'No spot had a spill upstream in the 24 hours before the last update.' : 'No spot had a spill reported upstream in the last 24 hours.' }}</p>
        @else
            <ul class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($overview->spotsWithSpill as $spot)
                    <li>
                        <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}" title="{{ $spot->name }}, {{ $spot->riverName }}" class="flex items-start justify-between gap-3 rounded-lg border border-line bg-surface px-4 py-3 transition hover:border-muted">
                            {{-- One line each, cut short with an ellipsis, so a long name never pushes the pill down. --}}
                            <span class="min-w-0">
                                <span class="block truncate font-semibold">{{ $spot->name }}</span>
                                <span class="block truncate text-xs text-muted">{{ $spot->riverName }}</span>
                            </span>
                            {{-- The heading says "upstream" and "last 24 hours"; the pill says whether that spill is happening now or ended earlier, or the data is out of date. --}}
                            @if ($spot->overflowDataIsStale())
                                <span class="shrink-0 whitespace-nowrap rounded-full bg-pill-grey-bg px-2.5 py-0.5 text-xs font-medium text-pill-grey-text ring-1 ring-inset ring-pill-grey-ring">Out of date</span>
                            @elseif ($spot->spillRecency === \App\Enums\SpillRecency::DischargingNow)
                                <span class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full bg-pill-red-bg px-2.5 py-0.5 text-xs font-medium text-pill-red-text ring-1 ring-inset ring-pill-red-ring"><x-live-dot />Happening now</span>
                            @else
                                <span class="shrink-0 whitespace-nowrap rounded-full bg-pill-amber-bg px-2.5 py-0.5 text-xs font-medium text-pill-amber-text ring-1 ring-inset ring-pill-amber-ring">Earlier</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- A comparison needs two places with returns: a one-river region, or Scotland, whose company publishes none, has nothing to set side by side. --}}
    @if ($comparisonYear !== null && $comparison->filter(fn ($total) => $total->hasReturns())->count() >= 2)
        @include('spills.comparison', [
            'totals' => $comparison,
            'year' => $comparisonYear,
            'heading' => $region ? 'How '.$region->nameInSentence().'\'s rivers compare' : 'How the regions compare',
            'nameHeading' => $region ? 'River' : 'Region',
        ])
    @elseif ($region && $comparison->isNotEmpty() && ! $comparison->contains(fn ($total) => $total->hasReturns()))
        <p class="mb-10 text-sm text-ink-2">No yearly figures to compare {{ $region->nameInSentence() }}'s rivers with: Scottish Water's annual overflow figures are not read.</p>
    @endif

    <section id="recent" class="mb-10 scroll-mt-24">
        <div class="mb-3 flex items-center gap-1.5">
            <h2 class="font-display text-lg font-semibold">Spills in the last 24 hours<x-heading-count :count="$overview->recentSpillsTotal" /></h2>
            <x-help term="spill" />
        </div>
        @if ($overview->recentSpills->isEmpty())
            <p class="text-sm text-ink-2">{{ $stale ? 'No spill was reported in the 24 hours before the last update.' : 'No spill was reported in the last 24 hours.' }}</p>
        @else
            <div data-more-rows-list>
                <div class="overflow-x-auto rounded-lg border border-line bg-surface">
                    <table class="w-full text-sm">
                        <caption class="sr-only">Storm overflow spills in the last 24 hours, most recent first</caption>
                        <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                            <tr>
                                <th scope="col" class="px-4 py-2.5 font-medium">Overflow</th>
                                <th scope="col" class="hidden px-4 py-2.5 font-medium sm:table-cell">Into</th>
                                <th scope="col" class="px-4 py-2.5 font-medium">Started</th>
                                <th scope="col" class="hidden px-4 py-2.5 font-medium sm:table-cell">Lasted</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line-2" data-more-rows-body>
                            @include('spills.recent-rows', ['spills' => $overview->recentSpills])
                        </tbody>
                    </table>
                </div>
                @include('spills.more-rows', ['url' => $rowsUrl('recent'), 'shown' => $overview->recentSpills->count(), 'total' => $overview->recentSpillsTotal, 'shownPhrase' => ['the latest', '']])
            </div>
        @endif
    </section>
</x-layouts.app>
