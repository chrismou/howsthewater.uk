<?php

namespace App\Http\Resources;

use App\Data\NearbyMonitorSummary;
use App\Data\NearbyOverflows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NearbyOverflows
 */
class NearbyOverflowsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'radius_metres' => $this->radiusMetres,
            'recency' => $this->spillRecency->value,
            'summary' => $this->spillRecency->nearbyLabel(),
            'discharging_now' => $this->dischargingNowCount,
            'offline' => $this->offlineCount,
            'last_48_hours' => $this->spillsLast48HoursCount,
            'last_30_days' => $this->spillsLast30DaysCount,
            'data_updated_at' => $this->dataUpdatedAt?->toIso8601String(),
            'data_stale' => $this->isStale(),
            'monitors' => $this->monitors->map(fn (NearbyMonitorSummary $summary) => [
                'id' => $summary->monitor->external_id,
                'company' => $summary->monitor->company,
                'receiving_watercourse' => $summary->monitor->receiving_watercourse,
                'distance_metres' => $summary->distanceMetres,
                'status' => $summary->status->name,
                'latest_event' => $summary->latestEvent === null ? null : [
                    'started_at' => $summary->latestEvent->started_at->toIso8601String(),
                    'ended_at' => $summary->latestEvent->ended_at?->toIso8601String(),
                ],
            ])->values(),
        ];
    }
}
