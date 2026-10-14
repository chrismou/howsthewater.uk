<?php

namespace Database\Factories;

use App\Enums\BathingWaterClassification;
use App\Enums\BathingWaterKind;
use App\Enums\PollutionRisk;
use App\Models\BathingWater;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BathingWater>
 */
class BathingWaterFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->city().' Beach';
        $point = fake()->unique()->numerify('#####');

        return [
            'eubwid' => "ukc0000-{$point}",
            'sampling_point' => $point,
            'region_id' => Region::factory(),
            'kind' => BathingWaterKind::Coastal,
            'name' => $name,
            'slug' => Str::slug($name),
            'district' => 'North Yorkshire',
            'sewerage_undertaker' => 'Yorkshire Water',
            'latitude' => 54.28,
            'longitude' => -0.40,
            'description' => 'A sandy beach.',
            'classification' => BathingWaterClassification::Good,
            'classification_year' => 2025,
            'risk_level' => PollutionRisk::Normal,
            'risk_expires_at' => now()->addHours(12),
            'affected_by_heavy_rain' => true,
            'season_starts_on' => now()->startOfYear()->setDate((int) now()->format('Y'), 5, 15),
            'season_ends_on' => now()->startOfYear()->setDate((int) now()->format('Y'), 9, 30),
        ];
    }

    public function kind(BathingWaterKind $kind): static
    {
        return $this->state(fn () => ['kind' => $kind]);
    }
}
