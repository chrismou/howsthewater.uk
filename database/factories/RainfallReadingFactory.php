<?php

namespace Database\Factories;

use App\Models\RainfallReading;
use App\Models\RainfallStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RainfallReading>
 */
class RainfallReadingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rainfall_station_id' => RainfallStation::factory(),
            'measured_at' => now()->subMinutes(fake()->numberBetween(0, 1440)),
            'millimetres' => fake()->randomFloat(2, 0, 2.0),
        ];
    }
}
