<?php

namespace App\Http\Resources;

use App\Data\FloodWarningSummary;
use App\Data\RegionSummary;
use App\Data\RiverSummary;
use App\Data\SpotSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RegionSummary
 */
class RegionSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'monitor_count' => $this->monitorCount,
            'offline_monitor_count' => $this->offlineMonitorCount,
            'gauge_count' => $this->gaugeCount,
            'rain_gauge_count' => $this->rainGaugeCount,
            'discharging_now' => $this->dischargingNowCount(),
            'spots_with_spill_last_24_hours' => $this->spotsWithSpillLast24HoursCount(),
            'data_updated_at' => $this->dataUpdatedAt?->toIso8601String(),
            'data_stale' => $this->dataIsStale(),
            'generated_at' => $this->generatedAt->toIso8601String(),
            'flood_warnings' => $this->floodWarnings()->map(fn (array $entry) => [
                ...(new FloodWarningResource($entry['warning']))->toArray($request),
                'spots' => $entry['spots']->map(fn (SpotSummary $spot) => ['slug' => $spot->slug, 'river' => $spot->riverSlug]),
            ]),
            'rivers' => $this->rivers->map(fn (RiverSummary $river) => [
                'slug' => $river->slug,
                'name' => $river->name,
                'catchment' => $river->catchment,
                'length_metres' => $river->lengthMetres,
                'monitor_count' => $river->monitorCount,
                'discharging_now' => $river->dischargingNowCount,
                'spots' => $river->spots->map(fn (SpotSummary $spot) => [
                    'slug' => $spot->slug,
                    'name' => $spot->name,
                    'latitude' => $spot->latitude,
                    'longitude' => $spot->longitude,
                    'river_metres' => $spot->riverMetres,
                    'spills' => [
                        'recency' => $spot->spillRecency->value,
                        'summary' => $spot->spillRecency->label(),
                        'discharging_now' => $spot->dischargingNowCount,
                        'offline' => $spot->offlineCount,
                        'last_48_hours' => $spot->spillsLast48HoursCount,
                        'last_30_days' => $spot->spillsLast30DaysCount,
                        'monitors' => $spot->monitorCount,
                    ],
                    'level' => $spot->levelValue === null ? null : [
                        'value' => $spot->levelValue,
                        'unit' => $spot->levelUnit,
                        'trend' => $spot->levelTrend->value,
                        'relative_to_typical' => $spot->levelRelativeToTypical,
                        'measured_at' => $spot->levelAt?->toIso8601String(),
                        'stale' => $spot->levelIsStale(),
                    ],
                    'rainfall_last_24_hours_mm' => $spot->rainfallLast24HoursMillimetres,
                    'flood_warnings' => $this->spotFloodWarnings($spot),
                    'data_updated_at' => $spot->dataUpdatedAt?->toIso8601String(),
                    'data_stale' => $spot->overflowDataIsStale(),
                    'url' => route('api.v1.spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]),
                ]),
            ]),
        ];
    }

    /**
     * @return array<int, array{area: string, severity_level: int, severity: string}>
     */
    private function spotFloodWarnings(SpotSummary $spot): array
    {
        return $spot->floodWarnings
            ->map(fn (FloodWarningSummary $warning) => ['area' => $warning->areaCode, 'severity_level' => $warning->severity->value, 'severity' => $warning->severity->label()])
            ->all();
    }
}
