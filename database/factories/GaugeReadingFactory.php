<?php

namespace Database\Factories;

use App\Models\GaugeReading;
use App\Models\GaugeStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GaugeReading>
 */
class GaugeReadingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'gauge_station_id' => GaugeStation::factory(),
            'measured_at' => now()->subMinutes(fake()->numberBetween(0, 1440)),
            'value' => fake()->randomFloat(3, 0.1, 2.0),
        ];
    }
}
