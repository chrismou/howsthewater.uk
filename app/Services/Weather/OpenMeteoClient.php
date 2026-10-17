<?php

namespace App\Services\Weather;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Forecasts from Open-Meteo's free, keyless API (non-commercial use; CC BY 4.0). It counts one call per location and
 * reportedly allows 600 a minute, 5,000 an hour and 10,000 a day, so calls are counted in the cache and every run
 * stays well under all three. Up to ten variables per location keep each location to a single call; this asks for eight.
 */
class OpenMeteoClient
{
    /** Locations per request; each counts as one call. 50 sometimes took close to a minute to answer. */
    public const BATCH_SIZE = 25;

    private const PER_MINUTE = 400;

    private const PER_HOUR = 4000;

    private const PER_DAY = 8000;

    /**
     * Whether this many more calls fit within every allowance.
     */
    public function hasBudget(int $calls): bool
    {
        foreach (['minute' => self::PER_MINUTE, 'hour' => self::PER_HOUR, 'day' => self::PER_DAY] as $window => $limit) {
            if (RateLimiter::attempts($this->limiterKey($window)) + $calls > $limit) {
                return false;
            }
        }

        return true;
    }

    /**
     * The next 24 hours, hour by hour from the current one, and the highest UV index today and tomorrow, for each
     * cell in the order given. Pages show whichever hour is current when they are viewed, so a forecast read up to
     * three hours ago still shows the weather now.
     *
     * @param  list<WeatherCell>  $cells  At most BATCH_SIZE
     * @return list<array{hours: list<array{at: int, temperature: float|null, apparent_temperature: float|null, precipitation_probability: int|null, weather_code: int|null, wind_speed: float|null, wind_gusts: float|null, wind_direction: int|null}>, uv_index_max: array<string, float>}>
     */
    public function forecasts(array $cells): array
    {
        if (! $this->hasBudget(count($cells))) {
            throw new RuntimeException('Open-Meteo: call allowance used up');
        }

        foreach (['minute' => 60, 'hour' => 3600, 'day' => 86400] as $window => $seconds) {
            RateLimiter::increment($this->limiterKey($window), $seconds, count($cells));
        }

        // No retries: each attempt counts against the allowance, and the next scheduled run tries again.
        $response = Http::acceptJson()
            ->timeout(120)
            ->withUserAgent(config('services.user_agent'))
            ->get(config('services.open_meteo.url'), [
                'latitude' => implode(',', array_map(fn (WeatherCell $cell) => $cell->latitude, $cells)),
                'longitude' => implode(',', array_map(fn (WeatherCell $cell) => $cell->longitude, $cells)),
                'hourly' => 'temperature_2m,apparent_temperature,precipitation_probability,weather_code,wind_speed_10m,wind_gusts_10m,wind_direction_10m',
                'daily' => 'uv_index_max',
                'forecast_hours' => 24,
                'forecast_days' => 2,
                'wind_speed_unit' => 'mph',
                'timeformat' => 'unixtime',
                'timezone' => 'Europe/London',
            ])
            ->throw()
            ->json();

        // One location comes back as an object, several as a list in the order asked.
        $locations = array_is_list($response) ? $response : [$response];

        if (count($locations) !== count($cells)) {
            throw new RuntimeException(sprintf('Open-Meteo: asked for %d locations, got %d', count($cells), count($locations)));
        }

        return array_map(fn (array $location) => $this->parse($location), $locations);
    }

    /**
     * @param  array<string, mixed>  $location
     * @return array{hours: list<array{at: int, temperature: float|null, apparent_temperature: float|null, precipitation_probability: int|null, weather_code: int|null, wind_speed: float|null, wind_gusts: float|null, wind_direction: int|null}>, uv_index_max: array<string, float>}
     */
    private function parse(array $location): array
    {
        $hourly = $location['hourly'] ?? [];
        $daily = $location['daily'] ?? [];
        $number = fn (mixed $value): ?float => is_numeric($value) ? (float) $value : null;
        $whole = fn (mixed $value): ?int => is_numeric($value) ? (int) round((float) $value) : null;
        $value = fn (string $variable, int $index): mixed => $hourly[$variable][$index] ?? null;

        $uvIndexMax = [];

        foreach ($daily['time'] ?? [] as $index => $time) {
            $highest = $number($daily['uv_index_max'][$index] ?? null);

            if ($highest !== null) {
                $uvIndexMax[now('Europe/London')->setTimestamp((int) $time)->toDateString()] = $highest;
            }
        }

        return [
            'hours' => array_map(fn (int $index, mixed $time) => [
                'at' => (int) $time,
                'temperature' => $number($value('temperature_2m', $index)),
                'apparent_temperature' => $number($value('apparent_temperature', $index)),
                'precipitation_probability' => $whole($value('precipitation_probability', $index)),
                'weather_code' => $whole($value('weather_code', $index)),
                'wind_speed' => $number($value('wind_speed_10m', $index)),
                'wind_gusts' => $number($value('wind_gusts_10m', $index)),
                'wind_direction' => $whole($value('wind_direction_10m', $index)),
            ], array_keys($hourly['time'] ?? []), $hourly['time'] ?? []),
            'uv_index_max' => $uvIndexMax,
        ];
    }

    private function limiterKey(string $window): string
    {
        return "open-meteo:{$window}";
    }
}
