<?php

namespace Database\Factories;

use App\Models\GaugeStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GaugeStation>
 */
class GaugeStationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => fake()->unique()->bothify('L####'),
            'river_id' => null,
            'label' => fake()->city(),
            'river_name' => 'River '.fake()->lastName(),
            'town' => fake()->city(),
            'latitude' => fake()->latitude(53.7, 54.1),
            'longitude' => fake()->longitude(-2.0, -1.2),
            'river_metres' => null,
            'measure_id' => fake()->unique()->bothify('L####-level-stage-i-15_min-mASD'),
            'typical_range_low' => 0.2,
            'typical_range_high' => 1.5,
            'is_active' => true,
        ];
    }
}
