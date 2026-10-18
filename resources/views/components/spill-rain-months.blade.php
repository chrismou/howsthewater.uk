@props(['months', 'notes' => [], 'gauge' => null])

{{-- Hours spilling per month over the last year, with the rain gauge's monthly total below. Months we hold no spills
     for (neither the company's published log nor the live feed reaches them) are marked as having no data rather
     than drawn as empty, and the figures, the bar scale and the rain total cover only the months with data. A month
     the gauge sent nothing shows no rain bar. The notes say where the spills come from and which months are
     missing. --}}
@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $all = collect($months);
    $recorded = $all->where('recorded', true);
    $maxHours = max(1.0, (float) $recorded->max('hours'));
    $recordedRain = $recorded->filter(fn ($month) => $month['rain'] !== null);
    $silentMonths = $recorded->count() - $recordedRain->count();
    $maxRain = max(10.0, (float) $recordedRain->max('rain'));
    $hasRain = $gauge !== null && $recordedRain->isNotEmpty();
    $thisMonth = now($timezone)->startOfMonth();
    $label = fn ($month) => $month['month']->format('F Y').($month['month']->isSameMonth($thisMonth) ? ' so far' : '');
@endphp

<div class="rounded-lg border border-line bg-surface p-5">
    <p class="text-sm text-ink-2">
        @if ($recorded->count() < $all->count())
            {{ number_format($recorded->sum('hours'), 1) }} hours of spilling over the {{ $recorded->count() }} {{ \Illuminate\Support\Str::plural('month', $recorded->count()) }} with data
        @else
            {{ number_format($recorded->sum('hours'), 1) }} hours of spilling over the last {{ $all->count() }} months
        @endif
        @if ($hasRain)
            and {{ number_format($recordedRain->sum('rain')) }} mm of rain at the {{ $gauge }} gauge{{ $silentMonths > 0 ? ', which sent no readings in '.$silentMonths.' '.\Illuminate\Support\Str::plural('month', $silentMonths) : '' }}.
        @else
            .
        @endif
    </p>

    <div class="mt-4 flex h-24 items-end gap-1" aria-hidden="true">
        @foreach ($months as $month)
            {{-- Any spill gets a bar, however short, so a month with spills never looks like one without. --}}
            @php($height = $month['spills'] > 0 ? max(4, (int) round($month['hours'] / $maxHours * 100)) : 0)
            <div class="flex h-full flex-1 flex-col justify-end" title="{{ $label($month) }}: {{ $month['recorded'] ? $month['spills'].' '.\Illuminate\Support\Str::plural('spill', $month['spills']).', '.number_format($month['hours'], 1).' h' : 'no data' }}">
                @if (! $month['recorded'])
                    <div class="h-full w-full rounded-t-sm border border-dashed border-line"></div>
                @elseif ($height > 0)
                    <div class="w-full rounded-t-sm bg-spill" style="height: {{ $height }}%"></div>
                @else
                    <div class="w-full border-b border-line"></div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($hasRain)
        <div class="mt-px flex h-10 items-start gap-1" aria-hidden="true">
            @foreach ($months as $month)
                @php($height = $month['recorded'] && ($month['rain'] ?? 0) > 0 ? max(6, (int) round($month['rain'] / $maxRain * 100)) : 0)
                <div class="flex h-full flex-1 flex-col justify-start" title="{{ $label($month) }}: {{ $month['rain'] === null ? 'no rain readings' : number_format($month['rain']).' mm rain' }}">
                    @if ($month['recorded'] && $month['rain'] === null)
                        <div class="w-full border-t border-dashed border-line"></div>
                    @elseif ($height > 0)
                        <div class="w-full rounded-b-sm bg-accent opacity-60" style="height: {{ $height }}%"></div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-1 flex gap-1 text-[11px] text-faint" aria-hidden="true">
        @foreach ($months as $month)
            <span class="flex-1 text-center">{{ mb_substr($month['month']->format('M'), 0, 1) }}</span>
        @endforeach
    </div>
    <div class="flex justify-between text-[11px] text-faint" aria-hidden="true">
        <span>{{ $months[0]['month']->format('M Y') }}</span>
        <span>{{ $months[count($months) - 1]['month']->format('M Y') }} so far</span>
    </div>
    <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted" aria-hidden="true">
        <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-spill"></span>Hours spilling</li>
        @if ($recorded->count() < $all->count())
            <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm border border-dashed border-line"></span>No data</li>
        @endif
        @if ($hasRain)
            <li class="inline-flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-accent opacity-60"></span>Rain, {{ $gauge }} gauge</li>
        @endif
    </ul>

    @if ($notes !== [])
        <div class="mt-3 grid gap-1 border-t border-line-2 pt-3 text-xs text-muted">
            @foreach ($notes as $note)
                <p>{{ $note }}</p>
            @endforeach
        </div>
    @endif

    {{-- The same figures for screen readers, which skip the bars above. --}}
    <table class="sr-only">
        <caption>Spills and rain per month over the last {{ $all->count() }} months</caption>
        <thead>
            <tr>
                <th scope="col">Month</th>
                <th scope="col">Spills</th>
                <th scope="col">Hours spilling</th>
                @if ($hasRain)
                    <th scope="col">Rain</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($months as $month)
                <tr>
                    <th scope="row">{{ $label($month) }}</th>
                    <td>{{ $month['recorded'] ? $month['spills'] : 'no data' }}</td>
                    <td>{{ $month['recorded'] ? number_format($month['hours'], 1) : 'no data' }}</td>
                    @if ($hasRain)
                        <td>{{ ! $month['recorded'] ? 'no data' : ($month['rain'] === null ? 'no readings' : number_format($month['rain']).' mm') }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
