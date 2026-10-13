<?php

namespace Database\Factories;

use App\Models\OverflowMonitor;
use App\Models\SpillEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpillEvent>
 */
class SpillEventFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = fake()->dateTimeBetween('-30 days', '-1 day');

        return [
            'overflow_monitor_id' => OverflowMonitor::factory(),
            'started_at' => $startedAt,
            'ended_at' => (clone $startedAt)->modify('+'.fake()->numberBetween(10, 600).' minutes'),
        ];
    }

    public function ongoing(): static
    {
        return $this->state(fn () => [
            'started_at' => now()->subHours(2),
            'ended_at' => null,
        ]);
    }
}
