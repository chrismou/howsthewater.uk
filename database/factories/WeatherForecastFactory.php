<?php

namespace Database\Factories;

use App\Models\WeatherForecast;
use App\Services\Weather\WeatherCell;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeatherForecast>
 */
class WeatherForecastFactory extends Factory
{
    public function definition(): array
    {
        $cell = WeatherCell::containing(53.92, -1.82);
        $now = now()->startOfHour();

        return [
            'cell_key' => $cell->key,
            'latitude' => $cell->latitude,
            'longitude' => $cell->longitude,
            'data' => [
                'hours' => collect(range(0, 23))->map(fn (int $hour) => [
                    'at' => $now->copy()->addHours($hour)->getTimestamp(),
                    'temperature' => round(14.0 - $hour * 0.3, 1),
                    'apparent_temperature' => round(11.5 - $hour * 0.3, 1),
                    'precipitation_probability' => min(100, $hour * 5),
                    'precipitation' => $hour < 6 ? 0.0 : 0.4,
                    'weather_code' => $hour < 6 ? 2 : 61,
                    'wind_speed' => 12.4,
                    'wind_gusts' => 24.8,
                    'wind_direction' => 225,
                ])->all(),
                'uv_index_max' => [now('Europe/London')->toDateString() => 3.2],
            ],
            'forecast_at' => $now,
            'fetched_at' => now(),
        ];
    }

    /**
     * The forecast for the cell holding a point.
     */
    public function at(float $latitude, float $longitude): static
    {
        $cell = WeatherCell::containing($latitude, $longitude);

        return $this->state(fn () => ['cell_key' => $cell->key, 'latitude' => $cell->latitude, 'longitude' => $cell->longitude]);
    }
}
