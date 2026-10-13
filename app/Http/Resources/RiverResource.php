<?php

namespace App\Http\Resources;

use App\Data\RiverOverview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RiverOverview
 */
class RiverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->river->slug,
            'name' => $this->river->name,
            'catchment' => $this->river->catchment,
            'length_metres' => $this->river->length_metres,
            'monitor_count' => $this->monitorCount,
            'discharging_now' => $this->dischargingNowCount,
            'spots' => SpotConditionsResource::collection($this->spots),
        ];
    }
}
