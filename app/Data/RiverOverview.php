<?php

namespace App\Data;

use App\Enums\Agency;
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
     * @param  Collection<int, SpillHistoryDay>  $dailyHistory  Spills on the river and its tributaries, oldest day first
     * @param  array<string, float|null>|null  $dailyRainfall  Rain per UK day at the river's rain gauge, null without one
     * @param  list<Agency>  $floodAreaAgencies  The agencies whose flood areas cover the river's spots
     * @param  Carbon|null  $monitorsUpdatedAt  When the river's own monitors last reported, spots or no spots
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
        public Collection $dailyHistory = new Collection(),
        public ?array $dailyRainfall = null,
        public ?string $rainGaugeLabel = null,
        public array $floodAreaAgencies = [],
        public ?FloodWarningEventSummary $lastFloodWarning = null,
        public ?Carbon $monitorsUpdatedAt = null,
    ) {
    }

    /**
     * When the river's monitor data last updated: the latest of its monitors' and its spots'.
     */
    public function dataUpdatedAt(): ?Carbon
    {
        return $this->spots->map(fn (SpotConditions $conditions) => $conditions->dataUpdatedAt)->push($this->monitorsUpdatedAt)->filter()->max();
    }

    public function overflowDataIsStale(): bool
    {
        $updatedAt = $this->dataUpdatedAt();

        return $this->monitorCount > 0
            && ($updatedAt === null || $updatedAt->lessThan(now()->subHours(SpotConditions::STALE_AFTER_HOURS)));
    }
}
