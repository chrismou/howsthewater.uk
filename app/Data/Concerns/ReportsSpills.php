<?php

namespace App\Data\Concerns;

use App\Enums\OverflowStatus;
use Illuminate\Support\Carbon;

/**
 * For monitor summaries carrying a status and latest spill event.
 *
 * @property-read OverflowStatus $status
 * @property-read \App\Models\SpillEvent|null $latestEvent
 */
trait ReportsSpills
{
    /**
     * Whether the monitor is discharging now or its latest spill ended after the threshold.
     */
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
