<?php

namespace App\Data;

use App\Enums\SpillRecency;
use App\Models\SpillEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Storm overflow activity within a fixed distance of a beach or lake.
 */
final readonly class NearbyOverflows
{
    /**
     * @param  Collection<int, NearbyMonitorSummary>  $monitors  Nearest first
     * @param  Collection<int, SpillEvent>  $recentSpillEvents  Most recent first
     */
    public function __construct(
        public int $radiusMetres,
        public Collection $monitors,
        public SpillRecency $spillRecency,
        public int $dischargingNowCount,
        public int $offlineCount,
        public int $spillsLast48HoursCount,
        public int $spillsLast30DaysCount,
        public Collection $recentSpillEvents,
        public ?Carbon $dataUpdatedAt,
    ) {
    }

    /**
     * Mirrors SpotConditions::overflowDataIsStale(), evaluated at read time.
     */
    /**
     * Whether we read the water company's overflow feed here at all.
     */
    public function isCovered(): bool
    {
        return $this->spillRecency !== SpillRecency::NotCovered;
    }

    public function isStale(): bool
    {
        if ($this->monitors->isEmpty()) {
            return false;
        }

        return $this->dataUpdatedAt === null
            || $this->dataUpdatedAt->lessThan(now()->subHours(SpotConditions::STALE_AFTER_HOURS));
    }
}
