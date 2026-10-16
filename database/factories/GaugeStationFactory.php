<?php

namespace Database\Factories;

use App\Enums\GaugeNetwork;
use App\Models\GaugeStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GaugeStation>
 */
class GaugeStationFactory extends Factory
{
    /**
     * A gauge reporting metres above sea level rather than above its datum.
     */
    public function aboveOrdnanceDatum(): static
    {
        return $this->state(fn () => [
            'measure_id' => fake()->unique()->bothify('L####-level-stage-i-15_min-mAOD'),
            'unit' => 'mAOD',
            'typical_range_low' => 23.6,
            'typical_range_high' => 24.1,
        ]);
    }

    /**
     * A SEPA gauge, whose measure is a KiWIS time series id.
     */
    public function sepa(): static
    {
        return $this->state(fn () => [
            'external_id' => fake()->unique()->numerify('SEPA#####'),
            'network' => GaugeNetwork::Sepa,
            'measure_id' => fake()->unique()->numerify('5####010'),
            'unit' => 'm',
        ]);
    }

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
            'measure_id' => fake()->unique()->bothify('L####-level-stage-i-15_min-m'),
            'unit' => 'm',
            'typical_range_low' => 0.2,
            'typical_range_high' => 1.5,
            'is_active' => true,
        ];
    }
}
