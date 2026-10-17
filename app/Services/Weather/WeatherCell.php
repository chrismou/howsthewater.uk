<?php

namespace App\Services\Weather;

/**
 * A grid square about 5.5 km across that places share one forecast for, so a
 * run asks Open-Meteo for 588 squares rather than 719 places. Its coordinates
 * are the square's centre, which is where the forecast is read.
 */
final readonly class WeatherCell
{
    public const LATITUDE_STEP = 0.05;

    public const LONGITUDE_STEP = 0.08;

    public function __construct(
        public string $key,
        public float $latitude,
        public float $longitude,
    ) {
    }

    public static function containing(float $latitude, float $longitude): self
    {
        $row = (int) round($latitude / self::LATITUDE_STEP);
        $column = (int) round($longitude / self::LONGITUDE_STEP);

        return new self(
            key: "{$row}:{$column}",
            latitude: round($row * self::LATITUDE_STEP, 5),
            longitude: round($column * self::LONGITUDE_STEP, 5),
        );
    }
}
