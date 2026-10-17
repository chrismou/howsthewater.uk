<?php

namespace App\Data;

use App\Services\Weather\WeatherCode;
use Illuminate\Support\Carbon;

/**
 * One hour of a place's forecast. Wind is in miles per hour, the direction it blows from in degrees.
 */
final readonly class WeatherHour
{
    private const COMPASS = ['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW'];

    public function __construct(
        public Carbon $at,
        public ?float $temperature,
        public ?float $apparentTemperature,
        public ?int $precipitationProbability,
        public ?int $weatherCode,
        public ?float $windSpeed,
        public ?float $windGusts,
        public ?int $windDirection,
    ) {
    }

    /**
     * @param  array{at: int, temperature: float|null, apparent_temperature: float|null, precipitation_probability: int|null, weather_code: int|null, wind_speed: float|null, wind_gusts: float|null, wind_direction: int|null}  $hour
     */
    public static function fromArray(array $hour): self
    {
        return new self(
            at: Carbon::createFromTimestamp($hour['at']),
            temperature: $hour['temperature'],
            apparentTemperature: $hour['apparent_temperature'],
            precipitationProbability: $hour['precipitation_probability'],
            weatherCode: $hour['weather_code'],
            windSpeed: $hour['wind_speed'],
            windGusts: $hour['wind_gusts'],
            windDirection: $hour['wind_direction'],
        );
    }

    public function condition(): ?string
    {
        return WeatherCode::label($this->weatherCode);
    }

    public function icon(): ?string
    {
        return WeatherCode::icon($this->weatherCode);
    }

    /**
     * Where the wind blows from, to eight points: "SW".
     */
    public function windFrom(): ?string
    {
        if ($this->windDirection === null) {
            return null;
        }

        return self::COMPASS[(int) round(($this->windDirection % 360) / 45) % 8];
    }
}
