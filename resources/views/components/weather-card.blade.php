{{--
    The forecast for the area around a place (now and the next twelve hours) and the day's daylight, as one band the
    width of the page: now, wind and UV, the coming hours, then daylight. Phones put now and wind side by side and the
    rest in rows below. weather: a WeatherSummary, or null before the first forecast is read; sun: the day's SunTimes;
    place: how to refer to the place ("this spot"). It reports the forecast, never whether conditions suit a swim.
--}}
@props(['weather', 'sun', 'place' => 'here'])

@php
    $timezone = config('app.display_timezone', 'Europe/London');
    $degrees = fn (?float $value) => $value === null ? '-' : number_format($value, 0).'°';
    $mph = fn (?float $value) => $value === null ? '-' : number_format($value, 0).' mph';
    $time = fn ($moment) => $moment?->copy()->timezone($timezone)->format('H:i') ?? '-';
    $outlook = $weather?->outlook(2, 6) ?? collect();
    $daylight = [['First light', $sun->firstLight], ['Sunrise', $sun->sunrise], ['Sunset', $sun->sunset], ['Last light', $sun->lastLight]];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-lg border border-line bg-surface']) }}>
    <div class="grid grid-cols-2 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)_minmax(0,2.2fr)_minmax(0,1fr)]">
        @if ($weather === null)
            <p class="col-span-2 p-5 text-sm text-ink-2 lg:col-span-3">No forecast for {{ $place }} yet.</p>
        @else
            <div class="p-5">
                <div class="flex items-center gap-3">
                    <x-weather-icon :icon="$weather->now->icon()" :dark="$sun->isDark(now())" class="h-10 w-10 text-ink-2" />
                    <p class="font-mono text-4xl font-semibold tabular-nums">{{ $degrees($weather->now->temperature) }}C</p>
                </div>
                <p class="mt-1 text-sm text-ink-2">{{ $weather->now->condition() ?? 'Forecast' }}</p>
                @if ($weather->now->apparentTemperature !== null)
                    <p class="text-sm text-ink-2">Feels like {{ $degrees($weather->now->apparentTemperature) }}C</p>
                @endif
            </div>

            <dl class="grid content-start gap-2 border-l border-line-2 p-5 text-sm">
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-xs uppercase tracking-wide text-muted">Wind</dt>
                    <dd class="font-mono font-medium tabular-nums">{{ $mph($weather->now->windSpeed) }} {{ $weather->now->windFrom() }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-xs uppercase tracking-wide text-muted">Gusts</dt>
                    <dd class="font-mono font-medium tabular-nums">{{ $mph($weather->now->windGusts) }}</dd>
                </div>
                <div class="flex items-baseline justify-between gap-3">
                    <dt class="text-xs uppercase tracking-wide text-muted">UV today</dt>
                    <dd class="font-mono font-medium tabular-nums">
                        @if ($weather->uvIndexMax === null)
                            -
                        @else
                            {{ number_format($weather->uvIndexMax, 0) }} <span class="font-sans text-xs font-normal text-ink-2">{{ $weather->uvBand() }}</span>
                        @endif
                    </dd>
                </div>
            </dl>

            <div class="col-span-2 border-t border-line-2 p-5 lg:col-span-1 lg:border-t-0 lg:border-l">
                @if ($outlook->isEmpty())
                    <p class="text-sm text-ink-2">No later hours in this forecast.</p>
                @else
                    <ol class="grid grid-cols-6 gap-1 text-center text-xs" aria-label="Forecast for the next hours">
                        @foreach ($outlook as $hour)
                            <li title="{{ $hour->condition() }}">
                                <p class="text-muted">{{ $time($hour->at) }}</p>
                                <x-weather-icon :icon="$hour->icon()" :dark="$sun->isDark($hour->at)" class="mx-auto mt-1 h-5 w-5 text-ink-2" />
                                <p class="mt-1 font-mono text-sm font-medium tabular-nums text-ink">{{ $degrees($hour->temperature) }}</p>
                                <p class="text-ink-2"><span class="sr-only">{{ $hour->condition() }}, rain chance </span>{{ $hour->precipitationProbability === null ? '-' : $hour->precipitationProbability.'%' }}</p>
                            </li>
                        @endforeach
                    </ol>
                    <p class="mt-2 text-center text-[11px] text-muted">Temperature and chance of rain</p>
                @endif
            </div>
        @endif

        <dl class="col-span-2 grid grid-cols-4 gap-1 border-t border-line-2 p-5 text-center text-xs lg:col-span-1 lg:grid-cols-1 lg:content-start lg:gap-1.5 lg:border-t-0 lg:border-l lg:text-left">
            @foreach ($daylight as [$label, $moment])
                <div class="lg:flex lg:items-baseline lg:justify-between lg:gap-3">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="font-mono text-sm font-medium tabular-nums text-ink">{{ $time($moment) }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    @if ($weather !== null)
        <p class="border-t border-line-2 px-5 py-2.5 text-xs text-muted">
            Forecast for the area around {{ $place }} from <a href="https://open-meteo.com/" rel="noopener" class="underline hover:text-ink">Open-Meteo</a>, updated {{ $time($weather->fetchedAt) }}.
        </p>
        @if ($weather->isStale())
            <p class="mx-5 mb-4 rounded-md border border-warn-line bg-warn-bg px-3 py-2 text-sm text-warn-text" role="status">
                This forecast was read {{ $weather->fetchedAt->diffForHumans() }}, so it may have changed since. <x-help term="stale" />
            </p>
        @endif
    @endif
</div>
