<?php

namespace Database\Factories;

use App\Enums\SpillLogSource;
use App\Models\OverflowSpillLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OverflowSpillLog>
 */
class OverflowSpillLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'source' => SpillLogSource::EnvironmentAgency,
            'key' => 'environment_agency:'.fake()->unique()->numerify('Yorkshire #### Detailed EDM Data.csv'),
            'name' => 'Yorkshire 2025 Detailed EDM Data.csv',
            'company' => 'Yorkshire Water',
            'year' => 2025,
            'fingerprint' => fake()->sha1(),
            'covers_from' => '2025-01-01 00:00:00',
            'covers_until' => '2026-01-01 00:00:00',
            'spills' => 0,
            'unmatched' => 0,
            'read_at' => now(),
        ];
    }
}
