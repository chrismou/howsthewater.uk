<?php

namespace App\Data;

use App\Enums\OverflowStatus;
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
     * @param  Collection<int, FloodWarningSummary>  $floodWarnings  Most severe first
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
        public Collection $floodWarnings,
        public ?BathingWaterSummary $bathingWater = null,
    ) {
    }

    /**
     * Spills each upstream monitor recorded in the history window, by monitor id.
     *
     * @return Collection<int, int>
     */
    public function spillCountsByMonitor(): Collection
    {
        return $this->recentSpillEvents->countBy('overflow_monitor_id');
    }

    /**
     * Upstream monitors for the overflows table: those discharging, offline or
     * with a spill in the history window first, then the quiet ones, each
     * nearest first.
     *
     * @return Collection<int, UpstreamMonitorSummary>
     */
    public function upstreamMonitorsByActivity(): Collection
    {
        [$active, $quiet] = $this->upstreamMonitors->partition($this->activeMonitorTest());

        return $active->concat($quiet)->values();
    }

    /**
     * How many upstream monitors are discharging, offline or spilled in the history window.
     */
    public function activeUpstreamMonitorCount(): int
    {
        return $this->upstreamMonitors->filter($this->activeMonitorTest())->count();
    }

    /**
     * @return callable(UpstreamMonitorSummary): bool
     */
    private function activeMonitorTest(): callable
    {
        $spillCounts = $this->spillCountsByMonitor();

        return fn (UpstreamMonitorSummary $summary): bool => $summary->status !== OverflowStatus::NotDischarging || $spillCounts->has($summary->monitor->id);
    }

    /**
     * The newest of the overflow, level and rain data behind the page, for its dateModified (SpotSummary::latestDataAt()).
     */
    public function latestDataAt(): ?Carbon
    {
        return collect([$this->dataUpdatedAt, $this->level?->latestAt, $this->rainfall?->latestAt])->filter()->max();
    }

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
