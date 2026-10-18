@props(['days', 'events', 'allUrl' => null, 'recency' => null])

@php
    $maxMinutes = max(1, $days->max(fn ($day) => $day->spillMinutes));
    $totalMinutes = $days->sum(fn ($day) => $day->spillMinutes);
    $activeDays = $days->filter(fn ($day) => $day->spillCount > 0)->count();
    $listedEvents = $events->take(10);
    // Days before the record starts are no data, not quiet days; the list below can still show the feed's earlier spills.
    $firstRecorded = $days->first(fn ($day) => $day->recorded);
    $unrecordedDays = $days->reject(fn ($day) => $day->recorded)->count();
@endphp

<div class="rounded-lg border border-line bg-surface p-5">
    <p class="text-sm text-ink-2">
        Upstream overflows were discharging on
        <span class="font-medium text-ink">{{ $activeDays }} of the last {{ $days->count() }} days</span>,
        for {{ number_format($totalMinutes / 60, 1) }} hours in total.
        @if ($unrecordedDays > 0 && $firstRecorded !== null)
            We hold no spills before {{ $firstRecorded->date->format('j M') }}.
        @endif
    </p>

    <div class="mt-4 flex h-24 items-end gap-px" role="img" aria-label="Minutes of upstream spilling per day over the last {{ $days->count() }} days">
        @foreach ($days as $day)
            @php
                $height = $day->spillCount > 0 ? max(4, (int) round($day->spillMinutes / $maxMinutes * 100)) : 0;
            @endphp
            <div class="flex h-full flex-1 flex-col justify-end" title="{{ $day->date->format('D j M') }}: {{ $day->recorded ? $day->spillCount.' '.\Illuminate\Support\Str::plural('spill', $day->spillCount).', '.$day->spillMinutes.' min' : 'no spill data' }}">
                @if (! $day->recorded)
                    <div class="w-full border-b border-dashed border-line"></div>
                @elseif ($height > 0)
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

    <div class="mt-5 flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="text-sm font-semibold">Latest spills upstream</h3>
        @if ($allUrl)
            <a href="{{ $allUrl }}" class="text-sm font-medium text-accent hover:underline">All spills upstream</a>
        @endif
    </div>
    @if ($events->isEmpty())
        <p class="mt-2 text-sm text-ink-2">{{ $recency === \App\Enums\SpillRecency::Offline ? 'No spill recorded upstream in the last '.$days->count().' days, but the monitors upstream are offline.' : 'No spill recorded upstream in the last '.$days->count().' days.' }}</p>
    @else
        {{-- Scrolls on its own on phones, so the table never widens the page. --}}
        <div class="mt-2 overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="sr-only">The latest spills from overflows upstream, newest first</caption>
                <thead class="text-left text-xs uppercase tracking-wide text-muted">
                    <tr>
                        <th scope="col" class="py-1 pr-4 font-medium">Started</th>
                        <th scope="col" class="py-1 pr-4 font-medium">Overflow</th>
                        <th scope="col" class="py-1 font-medium">Lasted</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line-2">
                    @foreach ($listedEvents as $event)
                        <tr>
                            <td class="py-1.5 pr-4 whitespace-nowrap font-mono text-xs tabular-nums">{{ $event->started_at->copy()->timezone(config('app.display_timezone', 'Europe/London'))->format('D j M, H:i') }}</td>
                            <td class="py-1.5 pr-4">
                                <x-overflow-link :monitor="$event->overflowMonitor" />
                                <span class="block text-xs text-muted">{{ $event->overflowMonitor->intoName() }}</span>
                            </td>
                            <td class="py-1.5 whitespace-nowrap font-mono tabular-nums"><x-spill-duration :spill="$event" :monitor="$event->overflowMonitor" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($events->count() > $listedEvents->count())
            <p class="mt-2 text-xs text-muted">The latest {{ $listedEvents->count() }} of {{ $events->count() }} in the last {{ $days->count() }} days.@if ($allUrl) <a href="{{ $allUrl }}" class="font-medium text-accent hover:underline">See them all</a>.@endif</p>
        @endif
    @endif
</div>
