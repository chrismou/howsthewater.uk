<?php

namespace App\Models;

use Database\Factories\WeatherForecastFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Open-Meteo's forecast for one grid cell (see WeatherCell), shared by every
 * spot, beach and lake inside it: 24 hours from when it was read, and the
 * highest UV index by date. forecast_at is the first of those hours.
 *
 * @property int $id
 * @property string $cell_key
 * @property float $latitude
 * @property float $longitude
 * @property array{hours: list<array{at: int, temperature: float|null, apparent_temperature: float|null, precipitation_probability: int|null, precipitation: float|null, weather_code: int|null, wind_speed: float|null, wind_gusts: float|null, wind_direction: int|null}>, uv_index_max: array<string, float>} $data
 * @property Carbon $forecast_at
 * @property Carbon $fetched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WeatherForecast extends Model
{
    /** @use HasFactory<WeatherForecastFactory> */
    use HasFactory;

    protected $fillable = [
        'cell_key',
        'latitude',
        'longitude',
        'data',
        'forecast_at',
        'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'data' => 'array',
            'forecast_at' => 'datetime',
            'fetched_at' => 'datetime',
        ];
    }
}
