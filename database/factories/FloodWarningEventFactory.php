<?php

namespace Database\Factories;

use App\Enums\FloodSeverity;
use App\Models\FloodArea;
use App\Models\FloodWarningEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FloodWarningEvent>
 */
class FloodWarningEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'flood_area_id' => FloodArea::factory(),
            'severity_level' => FloodSeverity::FloodAlert,
            'started_at' => now()->subHours(6),
            'ended_at' => null,
            'last_seen_at' => now()->subMinutes(15),
            'message' => 'River levels are rising on the River Wharfe. Flooding of low lying land and roads is possible.',
            'message_changed_at' => now()->subHours(6),
        ];
    }

    public function severity(FloodSeverity $severity): static
    {
        return $this->state(fn () => ['severity_level' => $severity]);
    }

    public function ended(): static
    {
        return $this->state(fn () => ['ended_at' => now()->subHour()]);
    }
}
