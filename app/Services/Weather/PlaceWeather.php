<?php

namespace App\Services\Weather;

use App\Data\SunTimes;
use App\Data\WeatherSummary;
use App\Models\WeatherForecast;

/**
 * The weather and daylight for a spot, beach or lake page: the stored forecast for the grid cell it sits in, and the
 * day's sun times worked out from its position. Pages read only what the scheduled ingest:weather stored.
 */
class PlaceWeather
{
    public function forecast(float $latitude, float $longitude): ?WeatherSummary
    {
        $forecast = WeatherForecast::query()->where('cell_key', WeatherCell::containing($latitude, $longitude)->key)->first();

        return $forecast === null ? null : WeatherSummary::fromForecast($forecast);
    }

    public function sunTimes(float $latitude, float $longitude): SunTimes
    {
        return SunTimes::at($latitude, $longitude);
    }
}
