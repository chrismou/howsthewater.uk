<?php

namespace Database\Factories;

use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OverflowMonitor>
 */
class OverflowMonitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'external_id' => 'YWS'.fake()->unique()->numerify('#####'),
            'river_id' => null,
            'company' => 'Yorkshire Water',
            'receiving_watercourse' => 'River '.fake()->lastName(),
            'latitude' => fake()->latitude(53.7, 54.1),
            'longitude' => fake()->longitude(-2.0, -1.2),
            'river_metres' => null,
            'status' => OverflowStatus::NotDischarging,
            'status_started_at' => now()->subDays(3),
            'latest_event_started_at' => now()->subDays(4),
            'latest_event_ended_at' => now()->subDays(3),
            'source_updated_at' => now(),
        ];
    }

    public function discharging(): static
    {
        return $this->state(fn () => [
            'status' => OverflowStatus::Discharging,
            'status_started_at' => now()->subHours(2),
            'latest_event_started_at' => now()->subHours(2),
            'latest_event_ended_at' => null,
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn () => ['status' => OverflowStatus::Offline]);
    }
}
