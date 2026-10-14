<?php

namespace App\Data;

use App\Data\Concerns\ReportsSpills;
use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use App\Models\River;
use App\Models\SpillEvent;

final readonly class UpstreamMonitorSummary
{
    use ReportsSpills;

    public function __construct(
        public OverflowMonitor $monitor,
        public River $river,
        public int $distanceUpstreamMetres,
        public OverflowStatus $status,
        public ?SpillEvent $latestEvent,
    ) {
    }
}
