<?php

namespace App\Http\Resources;

use App\Data\RainfallSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RainfallSummary
 */
class RainfallResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'station' => [
                'id' => $this->station->external_id,
                'grid_reference' => $this->station->grid_reference,
                'latitude' => $this->station->latitude,
                'longitude' => $this->station->longitude,
                'distance_metres' => $this->distanceMetres,
            ],
            'last_6_hours_mm' => $this->last6HoursMillimetres,
            'last_24_hours_mm' => $this->last24HoursMillimetres,
            'last_72_hours_mm' => $this->last72HoursMillimetres,
            'measured_at' => $this->latestAt?->toIso8601String(),
            'stale' => $this->isStale(),
        ];
    }
}
