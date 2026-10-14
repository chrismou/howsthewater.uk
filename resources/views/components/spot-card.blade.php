@props(['conditions'])

<a href="{{ route('spots.show', $conditions->spot) }}"
   class="block rounded-lg border border-stone-200 bg-white p-4 transition hover:border-stone-300 hover:shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h3 class="font-semibold">{{ $conditions->spot->name }}</h3>
            <p class="mt-0.5 text-xs text-stone-500">{{ $conditions->spot->river->name }}</p>
        </div>
        <x-spill-badge :recency="$conditions->spillRecency" :stale="$conditions->overflowDataIsStale()" />
    </div>
    <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-stone-600">
        @if ($conditions->overflowDataIsStale())
            <div class="basis-full">
                <dt class="sr-only">Data freshness</dt>
                <dd class="font-medium text-amber-700">
                    @if ($conditions->dataUpdatedAt)
                        Monitor data is {{ $conditions->dataUpdatedAt->diffForHumans(null, \Illuminate\Support\Carbon::DIFF_ABSOLUTE) }} old
                    @else
                        Monitor data has no update time
                    @endif
                </dd>
            </div>
        @endif
        <div>
            <dt class="sr-only">Upstream monitors</dt>
            <dd>{{ $conditions->upstreamMonitors->count() }} upstream {{ \Illuminate\Support\Str::plural('monitor', $conditions->upstreamMonitors->count()) }}</dd>
        </div>
        <div>
            <dt class="sr-only">Spills in last 30 days</dt>
            <dd>{{ $conditions->spillsLast30DaysCount }} {{ \Illuminate\Support\Str::plural('spill', $conditions->spillsLast30DaysCount) }} in 30 days</dd>
        </div>
        <div>
            <dt class="sr-only">River level</dt>
            <dd><x-level-badge :level="$conditions->level" /></dd>
        </div>
        @if ($conditions->rainfall?->hasReadings())
            <div>
                <dt class="sr-only">Rainfall in last 24 hours</dt>
                <dd>{{ number_format($conditions->rainfall->last24HoursMillimetres, 1) }} mm rain in 24h</dd>
            </div>
        @endif
    </dl>
</a>
