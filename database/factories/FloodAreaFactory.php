<?php

namespace Database\Factories;

use App\Models\FloodArea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FloodArea>
 */
class FloodAreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('122WAF###'),
            'label' => 'Lower River Wharfe',
            'description' => 'The Lower River Wharfe and tributaries from Otley to upstream of Ulleskelf',
            'river_or_sea' => 'River Wharfe',
        ];
    }
}
