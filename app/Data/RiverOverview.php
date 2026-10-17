<?php

namespace App\Data;

use App\Models\River;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class RiverOverview
{
    /**
     * Monitor counts cover the river and all its tributaries.
     *
     * @param  Collection<int, SpotConditions>  $spots  Source to mouth
     * @param  list<string>  $companies  The water companies whose monitors these are, by name
     */
    public function __construct(
        public River $river,
        public Collection $spots,
        public int $monitorCount,
        public int $dischargingNowCount,
        public int $offlineCount,
        public bool $hasTributaries,
        public int $spillsLast48HoursCount = 0,
        public array $companies = [],
    ) {
    }

    /**
     * When the river's monitor data last updated: the latest of its spots'.
     */
    public function dataUpdatedAt(): ?Carbon
    {
        return $this->spots->map(fn (SpotConditions $conditions) => $conditions->dataUpdatedAt)->filter()->max();
    }

    public function overflowDataIsStale(): bool
    {
        $updatedAt = $this->dataUpdatedAt();

        return $this->monitorCount > 0
            && ($updatedAt === null || $updatedAt->lessThan(now()->subHours(SpotConditions::STALE_AFTER_HOURS)));
    }
}
