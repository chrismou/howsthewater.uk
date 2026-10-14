{{-- Storm overflow monitors within a fixed distance of a beach or lake, nearest first. --}}
@props(['overflows'])

@php($timezone = config('app.display_timezone', 'Europe/London'))

<section {{ $attributes }}>
    <h2 class="font-display mb-3 text-lg font-semibold">Storm overflows within {{ $overflows->radiusMetres / 1000 }} km <x-help term="nearby_overflows" /></h2>
    <div class="mb-3 flex flex-wrap items-center gap-3">
        <x-spill-badge :recency="$overflows->spillRecency" :stale="$overflows->isStale()" nearby />
        <span class="text-sm text-muted">{{ $overflows->spillsLast30DaysCount }} {{ \Illuminate\Support\Str::plural('spill', $overflows->spillsLast30DaysCount) }} in the last 30 days</span>
    </div>

    @if ($overflows->isStale())
        <p class="mb-3 rounded-md border border-warn-line bg-warn-bg px-3 py-2 text-sm text-warn-text" role="status">Monitor data is out of date, so this describes the data as it was, not now. <x-help term="stale" /></p>
    @endif

    @if ($overflows->monitors->isNotEmpty())
        <div class="overflow-x-auto rounded-lg border border-line bg-surface">
            <table class="w-full text-sm">
                <thead class="bg-surface-2 text-left text-xs uppercase tracking-wide text-muted">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Monitor</th>
                        <th class="px-4 py-2.5 font-medium">Distance</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Last spill</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-2">
                    @foreach ($overflows->monitors as $summary)
                        <tr>
                            <td class="px-4 py-2.5">
                                <span class="font-mono text-xs">{{ $summary->monitor->external_id }}</span>
                                <span class="block text-xs text-muted">{{ $summary->monitor->receiving_watercourse }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 font-mono tabular-nums">{{ number_format($summary->distanceMetres / 1000, 1) }} km</td>
                            <td class="px-4 py-2.5">{{ $summary->status->label() }}</td>
                            <td class="hidden whitespace-nowrap px-4 py-2.5 text-ink-2 sm:table-cell">{{ $summary->latestEvent ? $summary->latestEvent->started_at->diffForHumans() : 'None recorded' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
