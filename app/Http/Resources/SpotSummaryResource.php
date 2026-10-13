<?php

namespace App\Http\Resources;

use App\Models\Spot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Spot
 */
class SpotSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'river' => $this->whenLoaded('river', fn () => ['slug' => $this->river->slug, 'name' => $this->river->name]),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'river_metres' => $this->river_metres,
            'url' => route('api.v1.spots.show', $this),
        ];
    }
}
