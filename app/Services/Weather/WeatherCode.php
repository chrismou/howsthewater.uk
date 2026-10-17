<?php

namespace App\Services\Weather;

/**
 * Plain words and an icon for the WMO weather codes Open-Meteo reports.
 * "Clear" rather than "sunny", since the hour may be after dark.
 */
final class WeatherCode
{
    public static function label(?int $code): ?string
    {
        return match ($code) {
            0 => 'Clear',
            1 => 'Mostly clear',
            2 => 'Partly cloudy',
            3 => 'Overcast',
            45 => 'Fog',
            48 => 'Freezing fog',
            51 => 'Light drizzle',
            53 => 'Drizzle',
            55 => 'Heavy drizzle',
            56, 57 => 'Freezing drizzle',
            61 => 'Light rain',
            63 => 'Rain',
            65 => 'Heavy rain',
            66, 67 => 'Freezing rain',
            71 => 'Light snow',
            73 => 'Snow',
            75 => 'Heavy snow',
            77 => 'Snow grains',
            80 => 'Light showers',
            81 => 'Showers',
            82 => 'Heavy showers',
            85 => 'Snow showers',
            86 => 'Heavy snow showers',
            95 => 'Thunderstorm',
            96, 99 => 'Thunderstorm with hail',
            default => null,
        };
    }

    /**
     * Which of components/weather-icon's icons stands for a code; null for one we do not know, which shows no icon.
     */
    public static function icon(?int $code): ?string
    {
        return match ($code) {
            0, 1 => 'clear',
            2 => 'partly-cloudy',
            3 => 'overcast',
            45, 48 => 'fog',
            51, 53, 55 => 'drizzle',
            61, 63, 65 => 'rain',
            80, 81, 82 => 'showers',
            56, 57, 66, 67 => 'sleet',
            71, 73, 75, 77, 85, 86 => 'snow',
            95, 96, 99 => 'thunder',
            default => null,
        };
    }
}
