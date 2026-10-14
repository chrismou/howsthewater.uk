@props(['days', 'events'])

@php
    $maxMinutes = max(1, $days->max(fn ($day) => $day->spillMinutes));
    $totalMinutes = $days->sum(fn ($day) => $day->spillMinutes);
    $activeDays = $days->filter(fn ($day) => $day->spillCount > 0)->count();
@endphp

<div class="rounded-lg border border-line bg-surface p-5">
    <p class="text-sm text-ink-2">
        Upstream overflows were discharging on
        <span class="font-medium text-ink">{{ $activeDays }} of the last {{ $days->count() }} days</span>,
        for {{ number_format($totalMinutes / 60, 1) }} hours in total.
    </p>

    <div class="mt-4 flex h-24 items-end gap-px" role="img" aria-label="Minutes of upstream spilling per day over the last {{ $days->count() }} days">
        @foreach ($days as $day)
            @php
                $height = $day->spillMinutes > 0 ? max(4, (int) round($day->spillMinutes / $maxMinutes * 100)) : 0;
            @endphp
            <div class="flex h-full flex-1 flex-col justify-end" title="{{ $day->date->format('D j M') }}: {{ $day->spillCount }} {{ \Illuminate\Support\Str::plural('spill', $day->spillCount) }}, {{ $day->spillMinutes }} min">
                @if ($height > 0)
                    <div class="w-full rounded-t-sm {{ $loop->last ? 'bg-alert' : 'bg-spill' }}" style="height: {{ $height }}%"></div>
                @else
                    <div class="w-full border-b border-line"></div>
                @endif
            </div>
        @endforeach
    </div>
    <div class="mt-1 flex justify-between text-[11px] text-faint">
        <span>{{ $days->first()->date->format('j M') }}</span>
        <span>Today</span>
    </div>

    @if ($events->isNotEmpty())
        <details class="mt-4 text-sm">
            <summary class="cursor-pointer text-ink-2 hover:text-ink">
                {{ $events->count() }} {{ \Illuminate\Support\Str::plural('spill', $events->count()) }} recorded
            </summary>
            <table class="mt-3 w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-muted">
                    <tr>
                        <th class="py-1 pr-4 font-medium">Started</th>
                        <th class="py-1 pr-4 font-medium">Duration</th>
                        <th class="py-1 pr-4 font-medium">Monitor</th>
                        <th class="py-1 font-medium">Watercourse</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-2">
                    @foreach ($events as $event)
                        <tr>
                            <td class="py-1.5 pr-4 whitespace-nowrap">{{ $event->started_at->timezone(config('app.display_timezone', 'Europe/London'))->format('D j M, H:i') }}</td>
                            <td class="py-1.5 pr-4 whitespace-nowrap">
                                @if ($event->isOngoing())
                                    <span class="font-medium text-alert">Ongoing</span>
                                @else
                                    {{ $event->durationMinutes() }} min
                                @endif
                            </td>
                            <td class="py-1.5 pr-4 font-mono text-xs">{{ $event->overflowMonitor->external_id }}</td>
                            <td class="py-1.5 text-ink-2">{{ $event->overflowMonitor->river?->name ?? $event->overflowMonitor->receiving_watercourse }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>
