<?php

namespace Database\Factories;

use App\Models\Lake;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lake>
 */
class LakeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'region_id' => Region::factory(),
            'name' => 'Windermere',
            'slug' => fake()->unique()->slug(2),
            'description' => "England's largest natural lake.",
            'latitude' => 54.36,
            'longitude' => -2.94,
            'gauge_reference' => null,
            'gauge_station_id' => null,
        ];
    }
}
