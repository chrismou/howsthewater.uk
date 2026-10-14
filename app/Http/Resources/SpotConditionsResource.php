<?php

namespace App\Http\Resources;

use App\Data\SpotConditions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SpotConditions
 */
class SpotConditionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'spot' => [
                'slug' => $this->spot->slug,
                'name' => $this->spot->name,
                'river' => ['slug' => $this->spot->river->slug, 'name' => $this->spot->river->name],
                'latitude' => $this->spot->latitude,
                'longitude' => $this->spot->longitude,
                'river_metres' => $this->spot->river_metres,
            ],
            'spills' => [
                'recency' => $this->spillRecency->value,
                'summary' => $this->spillRecency->label(),
                'discharging_now' => $this->dischargingNowCount,
                'last_48_hours' => $this->spillsLast48HoursCount,
                'last_30_days' => $this->spillsLast30DaysCount,
            ],
            'level' => $this->level === null ? null : [
                'station' => [
                    'id' => $this->level->station->external_id,
                    'label' => $this->level->station->label,
                    'unit' => $this->level->station->unitLabel(),
                    'typical_range_low' => $this->level->station->typical_range_low,
                    'typical_range_high' => $this->level->station->typical_range_high,
                ],
                'value' => $this->level->latestValue,
                'measured_at' => $this->level->latestAt?->toIso8601String(),
                'trend' => $this->level->trend->value,
                'change_last_3_hours' => $this->level->changeLast3Hours,
                'relative_to_typical' => $this->level->relativeToTypical,
                'stale' => $this->level->isStale(),
            ],
            'rainfall' => $this->rainfall === null ? null : [
                'station' => [
                    'id' => $this->rainfall->station->external_id,
                    'grid_reference' => $this->rainfall->station->grid_reference,
                    'latitude' => $this->rainfall->station->latitude,
                    'longitude' => $this->rainfall->station->longitude,
                    'distance_metres' => $this->rainfall->distanceMetres,
                ],
                'last_6_hours_mm' => $this->rainfall->last6HoursMillimetres,
                'last_24_hours_mm' => $this->rainfall->last24HoursMillimetres,
                'last_72_hours_mm' => $this->rainfall->last72HoursMillimetres,
                'measured_at' => $this->rainfall->latestAt?->toIso8601String(),
                'stale' => $this->rainfall->isStale(),
            ],
            'upstream_monitors' => OverflowMonitorResource::collection($this->upstreamMonitors),
            'history' => [
                'days' => $this->dailyHistory->map(fn ($day) => [
                    'date' => $day->date->toDateString(),
                    'spills' => $day->spillCount,
                    'minutes' => $day->spillMinutes,
                ]),
                'events' => $this->recentSpillEvents->map(fn ($event) => [
                    'monitor' => $event->overflowMonitor->external_id,
                    'river' => $event->overflowMonitor->river?->name,
                    'started_at' => $event->started_at->toIso8601String(),
                    'ended_at' => $event->ended_at?->toIso8601String(),
                    'ongoing' => $event->isOngoing(),
                    'duration_minutes' => $event->durationMinutes(),
                ]),
            ],
            'data_updated_at' => $this->dataUpdatedAt?->toIso8601String(),
            'data_stale' => $this->overflowDataIsStale(),
        ];
    }
}
