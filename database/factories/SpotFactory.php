<?php

namespace Database\Factories;

use App\Models\River;
use App\Models\Spot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Spot>
 */
class SpotFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city();

        return [
            'river_id' => River::factory(),
            'gauge_station_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'latitude' => fake()->latitude(53.7, 54.1),
            'longitude' => fake()->longitude(-2.0, -1.2),
            'river_metres' => fake()->numberBetween(1000, 90000),
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
