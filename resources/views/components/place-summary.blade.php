{{--
    The headline under a spot, beach or lake heading (from PlaceHeadline): the facts people scan for, with the key
    phrases in bold, any weather notes, then a small line saying where the figures come from and when, which keeps
    the page's dated, attributed statement. It never says whether a place is safe.
--}}
@props(['headline', 'region' => null])

@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $time = fn ($moment) => '<time datetime="'.$moment->copy()->utc()->toIso8601String().'">'
        .e($moment->copy()->timezone($timezone)->format($moment->isSameDay(now()) ? 'H:i T' : 'H:i T, j F Y')).'</time>';
    // Each run is escaped; bold runs are the headline facts.
    $render = fn (array $runs) => implode('', array_map(fn (array $run) => $run[1] ? '<strong class="font-semibold text-ink">'.e($run[0]).'</strong>' : e($run[0]), $runs));
    $sources = array_values(array_filter([
        $headline->overflowSource !== null && $headline->overflowAt !== null ? ucfirst(e($headline->overflowSource)).', data from '.$time($headline->overflowAt) : null,
        $headline->levelSource !== null && $headline->levelAt !== null ? 'Level from '.e($headline->levelSource).' at '.$time($headline->levelAt) : null,
        $headline->forecastAt !== null ? 'Forecast from Open-Meteo, read '.$time($headline->forecastAt) : null,
    ]));
@endphp

<div {{ $attributes->merge(['class' => 'max-w-2xl']) }}>
    @if ($headline->sentences !== [])
        <p class="text-ink-2">{!! implode(' ', array_map($render, $headline->sentences)) !!}</p>
    @endif
    @if ($headline->notes !== [])
        <ul class="mt-2 grid gap-1 text-sm text-ink-2">
            @foreach ($headline->notes as $runs)
                <li class="flex gap-2"><span class="text-muted" aria-hidden="true">-</span><span>{!! $render($runs) !!}</span></li>
            @endforeach
        </ul>
    @endif
    {{-- Every part is escaped above; the markup is only the bold runs and the <time> elements. --}}
    <p class="mt-2 text-xs text-muted">
        @if ($sources !== []){!! implode('. ', $sources) !!}. @endif
        <x-overflow-map-link :region="$region" />
        <a href="{{ route('about') }}#limits" class="underline decoration-line underline-offset-2 hover:decoration-ink">Monitor data is not a safety check.</a>
    </p>
</div>
