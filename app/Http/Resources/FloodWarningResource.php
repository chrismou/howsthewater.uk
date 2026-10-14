<?php

namespace App\Http\Resources;

use App\Data\FloodWarningSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FloodWarningSummary
 */
class FloodWarningResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'area' => [
                'code' => $this->areaCode,
                'label' => $this->areaLabel,
                'description' => $this->areaDescription,
            ],
            'severity_level' => $this->severity->value,
            'severity' => $this->severity->label(),
            'message' => $this->message,
            'raised_at' => $this->raisedAt?->toIso8601String(),
            'message_changed_at' => $this->messageChangedAt?->toIso8601String(),
            'checked_at' => $this->checkedAt->toIso8601String(),
            'stale' => $this->isStale(),
            'url' => $this->url(),
        ];
    }
}
