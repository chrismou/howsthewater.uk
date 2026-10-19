@props(['days', 'rainfall' => null, 'gauge' => null])

{{-- Hours spilling per UK day over the history window, with the rain gauge's daily total hanging below each day, so
     spills that came without rain stand out. A day the gauge sent nothing is shown as no data, never as dry. --}}
@php
    // Millimetres by UK date, with null for a day the gauge sent nothing.
    $rainfall = $rainfall === null ? null : collect($rainfall);
    $maxMinutes = max(60, $days->max(fn ($day) => $day->spillMinutes));
    $reportedDays = $rainfall?->filter(fn ($millimetres) => $millimetres !== null) ?? collect();
    $hasRain = $reportedDays->isNotEmpty();
    $maxRain = max(5.0, (float) $reportedDays->max());
    $silentDays = $rainfall === null ? 0 : $rainfall->count() - $reportedDays->count();
    $totalHours = $days->sum(fn ($day) => $day->spillMinutes) / 60;
    $activeDays = $days->filter(fn ($day) => $day->spillCount > 0)->count();
    // Days before the record starts (no company log and before we first saw the overflows) are no data, not quiet days.
    $firstRecorded = $days->first(fn ($day) => $day->recorded);
    $unrecordedDays = $days->reject(fn ($day) => $day->recorded)->count();
@endphp

<div class="rounded-lg border border-line bg-surface p-5">
    <p class="text-sm text-ink-2">
        Spilling on <span class="font-medium text-ink">{{ $activeDays }} of the last {{ $days->count() }} days</span>,
        for {{ number_format($totalHours, 1) }} hours in total.
        @if ($unrecordedDays > 0 && $firstRecorded !== null)
            We hold no spills before {{ $firstRecorded->date->format('j M') }}.
        @endif
        @if ($rainfall !== null && ! $hasRain)
            The {{ $gauge }} rain gauge sent no readings in this time.
        @elseif ($hasRain)
            {{ number_format($reportedDays->sum(), 1) }} mm of rain at the {{ $gauge }} gauge{{ $silentDays > 0 ? ', which sent no readings on '.$silentDays.' '.\Illuminate\Support\Str::plural('day', $silentDays) : '' }}.
        @endif
    </p>

    <div class="mt-4 flex h-24 items-end gap-px" aria-hidden="true">
        @foreach ($days as $day)
            {{-- Any spill gets a bar, however short, so a day with spills never looks like one without. --}}
            @php($height = $day->spillCount > 0 ? max(4, (int) round($day->spillMinutes / $maxMinutes * 100)) : 0)
            <div class="flex h-full flex-1 flex-col justify-end" title="{{ $day->date->format('D j M') }}: {{ $day->recorded ? $day->spillCount.' '.\Illuminate\Support\Str::plural('spill', $day->spillCount).', '.number_format($day->spillMinutes / 60, 1).' h' : 'no spill data' }}">
                @if (! $day->recorded)
                    <div class="w-full border-b border-dashed border-line"></div>
                @elseif ($height > 0)
                    <div class="w-full rounded-t-sm bg-spill" style="height: {{ $height }}%"></div>
                @else
                    <div class="w-full border-b border-line"></div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($hasRain)
        <div class="mt-px flex h-10 items-start gap-px" aria-hidden="true">
            @foreach ($days as $day)
                @php($millimetres = $rainfall->get($day->date->toDateString()))
                @php($height = $millimetres > 0 ? max(6, (int) round($millimetres / $maxRain * 100)) : 0)
                <div class="flex h-full flex-1 flex-col justify-start" title="{{ $day->date->format('D j M') }}: {{ $millimetres === null ? 'no rain readings' : number_format($millimetres, 1).' mm rain' }}">
                    @if ($millimetres === null)
                        <div class="w-full border-t border-dashed border-line"></div>
                    @elseif ($height > 0)
                        <div class="w-full rounded-b-sm bg-accent opacity-60" style="height: {{ $height }}%"></div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-1 flex justify-between text-[11px] text-faint" aria-hidden="true">
        <span>{{ $days->first()->date->format('j M') }}</span>
        <span>Today</span>
    </div>
    <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-hidden="true">
        <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-spill"></span>Hours spilling</li>
        @if ($hasRain)
            <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-accent opacity-60"></span>Rain, {{ $gauge }} gauge</li>
        @endif
    </ul>

    {{-- The same figures for screen readers, which skip the bars above. A table will not shrink to sr-only's 1px, so a
         wrapper hides it; on the table itself it widened the page on phones. --}}
    <div class="sr-only">
        <table>
            <caption>Spills and rain per day over the last {{ $days->count() }} days</caption>
            <thead>
                <tr>
                    <th scope="col">Day</th>
                    <th scope="col">Spills</th>
                    <th scope="col">Hours spilling</th>
                    @if ($rainfall !== null)
                        <th scope="col">Rain</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach ($days as $day)
                    <tr>
                        <th scope="row">{{ $day->date->format('D j M') }}</th>
                        <td>{{ $day->recorded ? $day->spillCount : 'no data' }}</td>
                        <td>{{ $day->recorded ? number_format($day->spillMinutes / 60, 1) : 'no data' }}</td>
                        @if ($rainfall !== null)
                            @php($millimetres = $rainfall->get($day->date->toDateString()))
                            <td>{{ $millimetres === null ? 'no readings' : number_format($millimetres, 1).' mm' }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
