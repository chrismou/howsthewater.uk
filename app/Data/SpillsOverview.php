<?php

namespace App\Data;

use App\Enums\DischargingOrder;
use App\Models\SpillEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What the live bar's spill counts are made of, for one region or every region: the overflows discharging now on
 * its rivers (the "discharging now" count), those discharging near its beaches and lakes, the spots with a spill
 * upstream in the last 24 hours (the "spots with a spill" count) and every spill running or ended in that time. Long
 * lists carry only their first rows, with the full count beside them.
 */
final readonly class SpillsOverview
{
    /**
     * @param  Collection<int, OverflowListing>  $dischargingNow  In $order
     * @param  Collection<int, OverflowListing>  $dischargingNearWaterBodies  In $order
     * @param  Collection<int, SpotSummary>  $spotsWithSpill  Discharging upstream first
     * @param  Collection<int, SpillEvent>  $recentSpills  Most recent first, with the monitor and its river loaded
     */
    public function __construct(
        public string $name,
        public DischargingOrder $order,
        public Collection $dischargingNow,
        public int $dischargingNowTotal,
        public Collection $dischargingNearWaterBodies,
        public int $dischargingNearWaterBodiesTotal,
        public Collection $spotsWithSpill,
        public Collection $recentSpills,
        public int $recentSpillsTotal,
        public ?Carbon $dataUpdatedAt,
    ) {
    }

    /**
     * Whether no overflow behind these lists has reported for STALE_AFTER_HOURS, so an empty list cannot be read as
     * "none right now".
     */
    public function dataIsStale(): bool
    {
        return $this->dataUpdatedAt === null || $this->dataUpdatedAt->lessThan(now()->subHours(SpotConditions::STALE_AFTER_HOURS));
    }
}
