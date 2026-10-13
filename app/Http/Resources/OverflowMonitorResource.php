<?php

namespace App\Http\Resources;

use App\Data\UpstreamMonitorSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UpstreamMonitorSummary
 */
class OverflowMonitorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->monitor->external_id,
            'receiving_watercourse' => $this->monitor->receiving_watercourse,
            'latitude' => $this->monitor->latitude,
            'longitude' => $this->monitor->longitude,
            'distance_upstream_metres' => $this->distanceUpstreamMetres,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'latest_event' => $this->latestEvent === null ? null : new SpillEventResource($this->latestEvent),
        ];
    }
}
