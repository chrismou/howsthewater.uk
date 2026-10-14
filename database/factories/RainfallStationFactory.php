<?php

namespace Database\Factories;

use App\Models\RainfallStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RainfallStation>
 */
class RainfallStationFactory extends Factory
{
    public function definition(): array
    {
        $reference = fake()->unique()->numerify('####');

        return [
            'external_id' => $reference,
            'label' => 'Rainfall station',
            'grid_reference' => fake()->bothify('SE######'),
            'latitude' => fake()->latitude(53.7, 54.1),
            'longitude' => fake()->longitude(-2.0, -1.2),
            'measure_id' => "{$reference}-rainfall-tipping_bucket_raingauge-t-15_min-mm",
            'is_active' => true,
        ];
    }
}
