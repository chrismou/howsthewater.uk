<?php

namespace App\Http\Resources;

use App\Models\SpillEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SpillEvent
 */
class SpillEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'started_at' => $this->started_at->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'ongoing' => $this->isOngoing(),
            'duration_minutes' => $this->durationMinutes(),
        ];
    }
}
