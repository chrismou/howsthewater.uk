@php
    // Only the kinds of place this region has, in a fixed order.
    $types = collect([
        'rivers' => $summary->rivers->isNotEmpty(),
        'beaches' => $summary->beaches->isNotEmpty(),
        'lakes' => $summary->lakes->isNotEmpty(),
    ])->filter()->keys();
    $typeList = $types->join(', ', ' and ');
    $counts = collect([
        'rivers' => $summary->rivers->count().' '.\Illuminate\Support\Str::plural('river', $summary->rivers->count()).' with '.$summary->spots()->count().' '.\Illuminate\Support\Str::plural('spot', $summary->spots()->count()),
        'beaches' => $summary->beaches->count().' '.\Illuminate\Support\Str::plural('beach', $summary->beaches->count()),
        'lakes' => $summary->lakes->count().' '.\Illuminate\Support\Str::plural('lake', $summary->lakes->count()),
    ])->only($types->all());

    // The title keeps "swimming" and as many kinds of place as fit in 60 characters.
    $title = 'Swimming in '.$region->nameInSentence().': '.$typeList;
    $title = mb_strlen($title) <= 60 ? $title : 'Swimming in '.$region->nameInSentence();

    // Featured spots where the region has some, otherwise the first spot on each river, source first.
    $featuredSpots = $summary->spots()->where('featured', true)->values();
    $spotsHeading = $featuredSpots->isNotEmpty() ? 'Popular spots' : 'A spot on each river';
    $spotList = ($featuredSpots->isNotEmpty() ? $featuredSpots : $summary->rivers->map(fn ($river) => $river->spots->first())->filter())->take(8)->values();

    // The page's trail: the visible breadcrumb and the BreadcrumbList are both built from it.
    $breadcrumbs = [['Home', route('home')], [$summary->name, route('regions.show', $region)]];
@endphp

<x-layouts.app :date-modified="$summary->dataUpdatedAt" :title="$title"
               :description="'Storm overflow spills, river levels, rainfall, flood warnings and bathing water results for '.$region->nameInSentence().'\'s '.$typeList.'.'"
               :breadcrumbs="$breadcrumbs"
               :region="$region"
               :structured-data="app(\App\Services\StructuredData::class)->regionPage($region, $types->map(fn ($type) => [$summary->name.' '.$type, route($type.'.index', $region)]))">
    <div class="mb-6">
        <x-breadcrumbs :items="$breadcrumbs" />
        <div class="mt-1 flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
            <div class="max-w-2xl">
                <h1 class="font-display text-4xl font-semibold tracking-tight">{{ $summary->name }}</h1>
                <p class="mt-2 text-ink-2">
                    Storm overflow spills, river levels, rainfall and bathing water results for swimming, paddling and fishing in {{ $region->nameInSentence() }}:
                    {{ $counts->join(', ', ' and ') }}.
                    @if ($summary->dataUpdatedAt)
                        Monitor data updated {{ $summary->dataUpdatedAt->diffForHumans() }}.
                    @endif
                </p>
            </div>
            <dl class="flex flex-wrap gap-x-7 gap-y-3">
                <div>
                    <dd class="font-mono text-3xl font-semibold tabular-nums {{ $summary->dischargingNowCount() > 0 ? 'text-alert' : '' }}">{{ $summary->hasOverflowData ? $summary->dischargingNowCount() : '-' }}</dd>
                    <dt class="text-xs text-muted">discharging now <x-help term="discharging_now" /></dt>
                </div>
                <div>
                    <dd class="font-mono text-3xl font-semibold tabular-nums">{{ $summary->spotsAboveTypicalRange()->count() }}</dd>
                    <dt class="text-xs text-muted">rivers higher than usual <x-help term="typical_range" /></dt>
                </div>
                <div>
                    <dd class="font-mono text-3xl font-semibold tabular-nums">{{ $summary->hasOverflowData ? number_format($summary->monitorCount) : '-' }}</dd>
                    <dt class="text-xs text-muted">monitors</dt>
                </div>
            </dl>
        </div>
    </div>

    @if ($summary->dataIsStale())
        <div class="mb-6 rounded-md border border-warn-line bg-warn-bg px-4 py-3 text-sm text-warn-text" role="status">
            <span class="font-medium">Monitor data is out of date.</span>
            The overflow feed has not updated for {{ $summary->dataUpdatedAt?->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) ?? 'an unknown time' }}, so spill statuses describe the data as it was then. <x-help term="stale" />
        </div>
    @endif

    <x-flood-warning-notice :summary="$summary" />

    @if ($spotList->isNotEmpty())
        <section class="mb-8">
            <h2 class="font-display text-2xl font-semibold tracking-tight">{{ $spotsHeading }}</h2>
            <ul class="mt-3 grid gap-x-6 divide-y divide-line-2 rounded-lg border border-line bg-surface px-4 sm:grid-cols-2 sm:divide-y-0">
                @foreach ($spotList as $spot)
                    <li class="flex items-center justify-between gap-3 py-2.5 sm:border-b sm:border-line-2">
                        <a href="{{ route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]) }}" class="min-w-0 hover:underline">
                            <span class="font-semibold">{{ $spot->name }}</span>
                            <span class="text-sm text-muted">{{ $spot->riverName }}</span>
                        </a>
                        <x-spill-badge :recency="$spot->spillRecency" :stale="$spot->overflowDataIsStale()" class="shrink-0" />
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="grid gap-5 {{ $types->count() >= 3 ? 'lg:grid-cols-3' : ($types->count() === 2 ? 'lg:grid-cols-2' : 'max-w-xl') }}">
        @foreach ($types as $type)
            <x-region-card :summary="$summary" :type="$type" in-region />
        @endforeach
    </div>
</x-layouts.app>
