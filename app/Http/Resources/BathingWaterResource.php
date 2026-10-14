<?php

namespace App\Http\Resources;

use App\Data\BathingWaterSummary;
use App\Models\BathingWaterClassificationRecord;
use App\Models\BathingWaterSample;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BathingWaterSummary
 */
class BathingWaterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $site = $this->bathingWater;

        return [
            'id' => $site->eubwid,
            'name' => $site->name,
            'kind' => $site->kind->value,
            'district' => $site->district,
            'sewerage_undertaker' => $site->sewerage_undertaker,
            'latitude' => $site->latitude,
            'longitude' => $site->longitude,
            'description' => $site->description,
            'classification' => $site->classification?->value,
            'classification_year' => $site->classification_year,
            'classifications' => $this->classifications->map(fn (BathingWaterClassificationRecord $record) => ['year' => $record->year, 'classification' => $record->classification->value])->values(),
            'in_season' => $site->isInSeason(),
            'season' => ['starts_on' => $site->season_starts_on?->toDateString(), 'ends_on' => $site->season_ends_on?->toDateString()],
            'pollution_risk' => $this->currentRisk()?->value,
            'affected_by_heavy_rain' => $site->affected_by_heavy_rain,
            'samples' => $this->recentSamples->map(fn (BathingWaterSample $sample) => [
                'sampled_at' => $sample->sampled_at->toIso8601String(),
                'e_coli_per_100ml' => $sample->e_coli,
                'e_coli_qualifier' => $sample->e_coli_qualifier,
                'enterococci_per_100ml' => $sample->enterococci,
                'enterococci_qualifier' => $sample->enterococci_qualifier,
            ])->values(),
            'profile_url' => $this->url(),
        ];
    }
}
