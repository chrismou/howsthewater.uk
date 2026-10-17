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
@endphp

<x-layouts.app :title="$summary->name.': '.$typeList"
               :description="'Storm overflow spills, river levels, rainfall, flood warnings and bathing water results for '.$summary->name.'\'s '.$typeList.'.'"
               :breadcrumbs="[[$summary->name, url()->current()]]"
               :region="$region"
               :structured-data="app(\App\Services\StructuredData::class)->itemList($summary->name, $types->map(fn ($type) => [$summary->name.' '.$type, route($type.'.index', $region)]))">
    <div class="mb-6">
        <p class="text-sm text-muted"><a href="{{ route('home') }}" class="hover:underline">Home</a></p>
        <div class="mt-1 flex flex-wrap items-end justify-between gap-x-8 gap-y-4">
            <div class="max-w-2xl">
                <h1 class="font-display text-4xl font-semibold tracking-tight">{{ $summary->name }}</h1>
                <p class="mt-2 text-ink-2">
                    {{ \Illuminate\Support\Str::ucfirst($counts->join(', ', ' and ')) }}.
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
                    <dt class="text-xs text-muted">gauges above typical <x-help term="typical_range" /></dt>
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

    <div class="grid gap-5 {{ $types->count() >= 3 ? 'lg:grid-cols-3' : ($types->count() === 2 ? 'lg:grid-cols-2' : 'max-w-xl') }}">
        @foreach ($types as $type)
            <x-region-card :summary="$summary" :type="$type" in-region />
        @endforeach
    </div>
</x-layouts.app>
