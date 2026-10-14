<?php

namespace Database\Factories;

use App\Models\Region;
use App\Models\River;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<River>
 */
class RiverFactory extends Factory
{
    public function definition(): array
    {
        $name = 'River '.fake()->unique()->lastName();

        return [
            'region_id' => Region::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'catchment' => 'Yorkshire Ouse',
            'aliases' => [$name],
            'centreline' => null,
            'length_metres' => null,
        ];
    }

    public function tributaryOf(River $parent, int $joinsAtMetres): static
    {
        return $this->state(fn () => [
            'parent_river_id' => $parent->id,
            'joins_at_metres' => $joinsAtMetres,
        ]);
    }

    /**
     * A straight south-east running centreline roughly 20km long, so river
     * positions can be computed in tests.
     */
    public function withCentreline(): static
    {
        return $this->state(fn () => [
            'centreline' => [
                [53.950, -1.900],
                [53.900, -1.800],
                [53.850, -1.700],
                [53.800, -1.600],
            ],
            'length_metres' => 30000,
        ]);
    }
}
