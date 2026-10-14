<?php

namespace App\Http\Resources;

use App\Models\Region;
use App\Models\River;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Region
 */
class RegionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'rivers' => $this->rivers->map(fn (River $river) => [
                'slug' => $river->slug,
                'name' => $river->name,
                'catchment' => $river->catchment,
                'url' => route('api.v1.rivers.show', [$this->resource, $river]),
            ]),
            'url' => route('api.v1.rivers.index', $this->resource),
        ];
    }
}
