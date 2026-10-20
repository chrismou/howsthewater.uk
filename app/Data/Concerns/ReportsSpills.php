<?php

namespace App\Data\Concerns;

use App\Enums\OverflowStatus;
use App\Enums\SpillRecency;
use Illuminate\Support\Carbon;

/**
 * For monitor summaries carrying a status and latest spill event.
 *
 * @property-read \App\Models\OverflowMonitor $monitor
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

    /**
     * The monitor's own recency, for map markers coloured like the places around them.
     */
    public function spillRecency(): SpillRecency
    {
        return SpillRecency::forMonitor($this->status, $this->latestSpillEnd(), $this->monitor->isStale());
    }

    /**
     * The line under its name in a map popup (SpillRecency::monitorNote()).
     */
    public function mapNote(): ?string
    {
        return SpillRecency::monitorNote($this->status, $this->latestSpillEnd(), $this->monitor->source_updated_at, $this->monitor->isStale());
    }

    /**
     * When its latest spill ended, or now while one is open.
     */
    private function latestSpillEnd(): ?Carbon
    {
        return $this->latestEvent === null ? null : ($this->latestEvent->ended_at ?? now());
    }
}
