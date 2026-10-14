<?php

namespace App\Data;

use App\Enums\SpillRecency;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class SpotConditions
{
    /**
     * Overflow data older than this is flagged as stale. The feed republishes
     * every monitor at least hourly, so a gap this long means the feed or our
     * ingest has stopped and the status shown can no longer be trusted.
     */
    public const STALE_AFTER_HOURS = 6;

    /**
     * @param  Collection<int, UpstreamMonitorSummary>  $upstreamMonitors  Nearest upstream first
     * @param  Collection<int, SpillEvent>  $recentSpillEvents  Most recent first, within the history window
     * @param  Collection<int, SpillHistoryDay>  $dailyHistory  Oldest day first
     */
    public function __construct(
        public Spot $spot,
        public SpillRecency $spillRecency,
        public Collection $upstreamMonitors,
        public int $dischargingNowCount,
        public int $offlineCount,
        public int $spillsLast48HoursCount,
        public int $spillsLast30DaysCount,
        public Collection $recentSpillEvents,
        public Collection $dailyHistory,
        public ?LevelSummary $level,
        public ?RainfallSummary $rainfall,
        public ?Carbon $dataUpdatedAt,
    ) {}

    /**
     * Whether the upstream monitor data is too old to rely on. Evaluated at
     * read time, so a cached result still goes stale on schedule.
     */
    public function overflowDataIsStale(): bool
    {
        if ($this->upstreamMonitors->isEmpty()) {
            return false;
        }

        return $this->dataUpdatedAt === null
            || $this->dataUpdatedAt->lessThan(now()->subHours(self::STALE_AFTER_HOURS));
    }
}
