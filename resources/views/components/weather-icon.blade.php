{{--
    A line icon for a forecast hour, in the site's icon style (currentColor, 1.8 stroke), so it follows the text colour
    and dark mode. icon: a WeatherCode::icon() name, or null for none; dark: after sunset, when clear, partly cloudy
    and showers show the moon. Decorative: the condition is always written beside it.
--}}
@props(['icon', 'dark' => false])

@php
    // The cloud in front of a sun or moon is filled with the card's colour, so the rays behind it stay hidden.
    $sun = '<circle cx="8" cy="8" r="3"/><path d="M8 2v1.3M8 12.7V14M2 8h1.3M3.8 3.8l.9.9M12.2 3.8l-.9.9M3.8 12.2l.9-.9"/>';
    $moon = '<path d="M11.5 9.6A4.6 4.6 0 1 1 6.4 3.5a3.6 3.6 0 0 0 5.1 6.1z"/>';
    $frontCloud = '<path fill="var(--color-surface)" d="M9.5 20a3.5 3.5 0 0 1-.4-6.98 4.7 4.7 0 0 1 9.1-1A3.9 3.9 0 0 1 18 20z"/>';
    $rainCloud = '<path d="M7.5 15a4.2 4.2 0 0 1-.5-8.37 5.7 5.7 0 0 1 11.1-.5A4.4 4.4 0 0 1 17.5 15z"/>';
    $showerCloud = '<path fill="var(--color-surface)" d="M9.5 16a3.5 3.5 0 0 1-.4-6.98 4.7 4.7 0 0 1 9.1-1A3.9 3.9 0 0 1 18 16z"/>';

    $paths = match ($icon) {
        'clear' => $dark
            ? '<path d="M19 14.5A7.5 7.5 0 1 1 9.5 5a6 6 0 0 0 9.5 9.5z"/>'
            : '<circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M2.5 12h2M19.5 12h2M5.3 5.3l1.4 1.4M17.3 17.3l1.4 1.4M5.3 18.7l1.4-1.4M17.3 6.7l1.4-1.4"/>',
        'partly-cloudy' => ($dark ? $moon : $sun).$frontCloud,
        'overcast' => '<path d="M7 19a4.5 4.5 0 0 1-.5-8.97 6 6 0 0 1 11.6-.6A4.8 4.8 0 0 1 17.3 19z"/>',
        'fog' => '<path d="M7.5 11a3.8 3.8 0 0 1 .2-7.6 5.2 5.2 0 0 1 9.8 1.6 3 3 0 0 1-.5 6"/><path d="M3.5 14.5h17M5.5 18h13M8 21.5h8"/>',
        'drizzle' => $rainCloud.'<path d="M8.5 18.5v.01M12.5 18.5v.01M16.5 18.5v.01M10.5 21.5v.01M14.5 21.5v.01" stroke-width="2.4"/>',
        'rain' => $rainCloud.'<path d="M9 17.5l-1.2 3.5M13 17.5l-1.2 3.5M17 17.5l-1.2 3.5"/>',
        'showers' => ($dark ? $moon : $sun).$showerCloud.'<path d="M11.5 18l-1 3M15.5 18l-1 3"/>',
        'sleet' => $rainCloud.'<path d="M9 17.5l-1.2 3.5M15 17.5l-1.2 3.5"/><path d="M12.3 19.5v.01" stroke-width="2.4"/>',
        'snow' => $rainCloud.'<path d="M8.5 18v.01M12.5 18v.01M16.5 18v.01M10.5 21v.01M14.5 21v.01" stroke-width="2.6"/>',
        'thunder' => $rainCloud.'<path d="M13 14.5l-2.5 3.8h3.3L11.3 22"/>',
        default => null,
    };
@endphp

@if ($paths !== null)
    <svg {{ $attributes->merge(['class' => 'shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"
         aria-hidden="true" data-weather-icon="{{ $icon }}{{ $dark && in_array($icon, ['clear', 'partly-cloudy', 'showers'], true) ? '-night' : '' }}">{!! $paths !!}</svg>
@endif
