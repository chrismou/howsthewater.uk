<?php

namespace App\Data;

use App\Enums\Agency;
use App\Enums\FloodSeverity;
use App\Models\FloodWarningEvent;
use Illuminate\Support\Carbon;

/**
 * A past flood alert or warning for an area covering one of a river's spots, as plain values for the river's
 * cached summary: the last one lifted, which tells a reader how recently the river was in flood.
 */
final readonly class FloodWarningEventSummary
{
    public function __construct(
        public FloodSeverity $severity,
        public string $areaLabel,
        public Carbon $startedAt,
        public ?Carbon $endedAt,
        public Agency $agency,
    ) {
    }

    public static function fromEvent(FloodWarningEvent $event): self
    {
        return new self(
            severity: $event->severity_level,
            areaLabel: $event->floodArea->label,
            startedAt: $event->started_at,
            endedAt: $event->ended_at,
            agency: $event->floodArea->agency,
        );
    }
}
