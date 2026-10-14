<?php

namespace App\Data;

use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use App\Models\River;
use App\Models\SpillEvent;
use Illuminate\Support\Carbon;

final readonly class UpstreamMonitorSummary
{
    public function __construct(
        public OverflowMonitor $monitor,
        public River $river,
        public int $distanceUpstreamMetres,
        public OverflowStatus $status,
        public ?SpillEvent $latestEvent,
    ) {
    }

    public function spilledSince(Carbon $threshold): bool
    {
        if ($this->status === OverflowStatus::Discharging) {
            return true;
        }

        if ($this->latestEvent === null) {
            return false;
        }

        $end = $this->latestEvent->ended_at ?? now();

        return $end->greaterThanOrEqualTo($threshold);
    }
}
