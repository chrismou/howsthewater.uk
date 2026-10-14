<?php

namespace Database\Factories;

use App\Models\BathingWater;
use App\Models\BathingWaterSample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BathingWaterSample>
 */
class BathingWaterSampleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bathing_water_id' => BathingWater::factory(),
            'sampled_at' => now()->subWeek(),
            'e_coli' => 27,
            'e_coli_qualifier' => '=',
            'enterococci' => 10,
            'enterococci_qualifier' => '<',
        ];
    }
}
