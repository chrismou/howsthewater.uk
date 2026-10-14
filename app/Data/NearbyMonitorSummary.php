<?php

namespace App\Data;

use App\Data\Concerns\ReportsSpills;
use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use App\Models\SpillEvent;

/**
 * An overflow monitor near a beach or lake, by straight-line distance,
 * since the sea and lakes have no upstream.
 */
final readonly class NearbyMonitorSummary
{
    use ReportsSpills;

    public function __construct(
        public OverflowMonitor $monitor,
        public int $distanceMetres,
        public OverflowStatus $status,
        public ?SpillEvent $latestEvent,
    ) {
    }
}
