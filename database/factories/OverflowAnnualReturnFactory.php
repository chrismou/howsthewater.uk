<?php

namespace Database\Factories;

use App\Models\OverflowAnnualReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OverflowAnnualReturn>
 */
class OverflowAnnualReturnFactory extends Factory
{
    public function definition(): array
    {
        return [
            'year' => 2025,
            'overflow_monitor_id' => null,
            'uid' => 'YWS'.fake()->unique()->numerify('#####'),
            'company' => 'Yorkshire Water',
            'site_name' => strtoupper(fake()->city()).' CSO',
            'permit_reference' => fake()->bothify('YW??##/##'),
            'counted_spills' => fake()->numberBetween(0, 120),
            'total_hours' => fake()->randomFloat(2, 0, 900),
            'monitor_uptime_percent' => fake()->randomFloat(2, 80, 100),
            'receiving_water' => 'River '.fake()->lastName(),
            'latitude' => fake()->latitude(53.7, 54.1),
            'longitude' => fake()->longitude(-2.0, -1.2),
        ];
    }
}
