<?php

namespace App\Http\Resources;

use App\Data\BeachConditions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BeachConditions
 */
class BeachConditionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $beach = $this->bathingWater->bathingWater;

        return [
            'slug' => $beach->slug,
            'bathing_water' => new BathingWaterResource($this->bathingWater),
            'nearby_overflows' => new NearbyOverflowsResource($this->overflows),
        ];
    }
}
