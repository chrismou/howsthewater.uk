<?php

namespace Database\Factories;

use App\Enums\FloodSeverity;
use App\Models\FloodArea;
use App\Models\FloodWarning;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FloodWarning>
 */
class FloodWarningFactory extends Factory
{
    public function definition(): array
    {
        return [
            'flood_area_id' => FloodArea::factory(),
            'severity_level' => FloodSeverity::FloodAlert,
            'message' => 'River levels are rising on the River Wharfe. Flooding of low lying land and roads is possible.',
            'raised_at' => now()->subHours(2),
            'message_changed_at' => now()->subHours(2),
            'checked_at' => now(),
        ];
    }

    public function severity(FloodSeverity $severity): static
    {
        return $this->state(fn () => ['severity_level' => $severity]);
    }
}
