<?php

namespace App\Services\Conditions;

use App\Data\BathingWaterSummary;
use App\Data\FloodWarningSummary;
use App\Data\SpotConditions;
use App\Data\UpstreamMonitorSummary;
use App\Enums\OverflowStatus;
use App\Enums\SpillRecency;
use App\Models\BathingWater;
use App\Models\FloodWarning;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the full picture for a spot: upstream overflow activity, recent
 * spill history, the nearest gauge's level, nearby rainfall and any flood
 * warnings in force. Shared by web and API.
 */
class SpotConditionsService
{
    public function __construct(
        private UpstreamMonitorFinder $upstreamMonitors,
        private SpillHistoryService $spillHistory,
        private LevelSummariser $levelSummariser,
        private RainfallSummariser $rainfallSummariser,
        private WaterBodyConditionsService $waterBodies,
        private ConditionsCache $cache,
    ) {
    }

    public function for(Spot $spot): SpotConditions
    {
        return $this->cache->rememberSpot($spot, fn () => $this->build($spot));
    }

    /**
     * Conditions for several spots, read from the cache in one round trip.
     *
     * @param  Collection<int, Spot>  $spots
     * @return Collection<int, SpotConditions>
     */
    public function forMany(Collection $spots): Collection
    {
        return $this->cache->rememberSpots($spots, fn (Spot $spot) => $this->build($spot));
    }

    /**
     * Rebuilds conditions and writes them over the cached entry.
     */
    public function refresh(Spot $spot): SpotConditions
    {
        return tap($this->build($spot), fn (SpotConditions $conditions) => $this->cache->putSpot($spot, $conditions));
    }

    /**
     * Computes conditions from the database, bypassing the cache.
     */
    public function build(Spot $spot): SpotConditions
    {
        $spot->loadMissing(['river.region', 'gaugeStation', 'rainfallStation']);
        $spot->setRelation('river', $this->withoutCentreline($spot->river));

        // Where we do not read the overflow feed (Wales), monitors recorded ahead of the region are left out entirely.
        $monitors = $spot->river->region->hasOverflowData() ? $this->upstreamMonitors->for($spot) : collect();
        $recentEvents = $this->spillHistory->recentEvents($monitors);

        $byStatus = fn (OverflowStatus $status) => $monitors->filter(fn (UpstreamMonitorSummary $summary) => $summary->status === $status)->count();
        $dischargingNow = $byStatus(OverflowStatus::Discharging);
        $offline = $byStatus(OverflowStatus::Offline);

        return new SpotConditions(
            spot: $spot,
            spillRecency: $this->spillRecency($monitors, $dischargingNow, $offline, $spot->river->region->hasOverflowData()),
            upstreamMonitors: $monitors,
            dischargingNowCount: $dischargingNow,
            offlineCount: $offline,
            spillsLast48HoursCount: $this->countEventsSince($recentEvents, now()->subHours(48)),
            spillsLast30DaysCount: $recentEvents->count(),
            recentSpillEvents: $recentEvents,
            dailyHistory: $this->spillHistory->dailyBreakdown($recentEvents),
            level: $spot->gaugeStation !== null ? $this->levelSummariser->summarise($spot->gaugeStation) : null,
            rainfall: $spot->rainfallStation !== null ? $this->rainfallSummariser->summarise($spot, $spot->rainfallStation) : null,
            dataUpdatedAt: $monitors->max(fn (UpstreamMonitorSummary $summary) => $summary->monitor->source_updated_at),
            floodWarnings: $this->floodWarnings($spot),
            bathingWater: $this->bathingWater($spot),
        );
    }

    /**
     * The EA designated bathing water at this spot, if it is one.
     */
    private function bathingWater(Spot $spot): ?BathingWaterSummary
    {
        $bathingWater = BathingWater::query()->where('spot_id', $spot->id)->first();

        return $bathingWater === null ? null : $this->waterBodies->summarise($bathingWater);
    }

    /**
     * Warnings in force for the flood areas covering the spot, most severe
     * first. Queried rather than loaded onto the spot, which is cached.
     *
     * @return Collection<int, FloodWarningSummary>
     */
    private function floodWarnings(Spot $spot): Collection
    {
        return FloodWarning::query()
            ->whereHas('floodArea.spots', fn ($query) => $query->whereKey($spot->id))
            ->with('floodArea')
            ->orderBy('severity_level')
            ->get()
            ->map(fn (FloodWarning $warning) => FloodWarningSummary::fromWarning($warning))
            ->values()
            ->toBase();
    }

    /**
     * A copy of the river without its centreline, so the result stays small
     * enough to cache. The caller's own river instance is left intact, as
     * pages still need the centreline for their map.
     */
    private function withoutCentreline(River $river): River
    {
        $attributes = $river->getAttributes();
        unset($attributes['centreline']);

        // Sync the originals too, or the model keeps a second copy of the centreline there.
        return (clone $river)->setRawAttributes($attributes, sync: true);
    }

    /**
     * @param  Collection<int, SpillEvent>  $events
     */
    private function countEventsSince(Collection $events, Carbon $since): int
    {
        return $events
            ->filter(fn (SpillEvent $event) => $event->ended_at === null || $event->ended_at->greaterThanOrEqualTo($since))
            ->count();
    }

    /**
     * @param  Collection<int, UpstreamMonitorSummary>  $monitors
     */
    private function spillRecency(Collection $monitors, int $dischargingNow, int $offline, bool $covered): SpillRecency
    {
        return SpillRecency::assess(
            covered: $covered,
            monitorCount: $monitors->count(),
            dischargingNow: $dischargingNow,
            offline: $offline,
            spilledWithin24Hours: $monitors->contains(fn (UpstreamMonitorSummary $summary) => $summary->spilledSince(now()->subHours(24))),
            spilledWithin48Hours: $monitors->contains(fn (UpstreamMonitorSummary $summary) => $summary->spilledSince(now()->subHours(48))),
        );
    }
}
