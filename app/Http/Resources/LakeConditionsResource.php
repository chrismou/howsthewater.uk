<?php

namespace App\Http\Resources;

use App\Data\LakeConditions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LakeConditions
 */
class LakeConditionsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->lake->slug,
            'name' => $this->lake->name,
            'description' => $this->lake->description,
            'latitude' => $this->lake->latitude,
            'longitude' => $this->lake->longitude,
            'level' => $this->level === null ? null : [
                'station' => ['id' => $this->level->station->external_id, 'label' => $this->level->station->label, 'unit' => $this->level->station->unitLabel()],
                'value' => $this->level->latestValue,
                'measured_at' => $this->level->latestAt?->toIso8601String(),
                'trend' => $this->level->trend->value,
                'relative_to_typical' => $this->level->relativeToTypical,
                'stale' => $this->level->isStale(),
            ],
            'bathing_waters' => BathingWaterResource::collection($this->bathingWaters),
            'nearby_overflows' => new NearbyOverflowsResource($this->overflows),
        ];
    }
}
