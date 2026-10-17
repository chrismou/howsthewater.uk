<?php

namespace App\Services\Conditions;

use App\Data\BathingWaterSummary;
use App\Data\BeachConditions;
use App\Data\LakeConditions;
use App\Data\NearbyMonitorSummary;
use App\Data\NearbyOverflows;
use App\Data\RainfallSummary;
use App\Enums\OverflowStatus;
use App\Enums\SpillRecency;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\RainfallStation;
use App\Models\SpillEvent;
use Illuminate\Support\Collection;

/**
 * Builds the picture for beaches and lakes: the Environment Agency's
 * bathing water records plus storm overflows within a fixed distance.
 * Shared by web and API, and cached like spot conditions.
 */
class WaterBodyConditionsService
{
    /**
     * How far from a beach, lake or bathing site an overflow can be and
     * still be listed. The sea and lakes have no upstream, so distance is
     * the only measure; this is close enough to plausibly reach the water.
     */
    public const NEARBY_RADIUS_METRES = 3000;

    private const RECENT_SAMPLES = 10;

    public function __construct(
        private NearbyMonitorFinder $monitors,
        private SpillHistoryService $spillHistory,
        private LevelSummariser $levelSummariser,
        private RainfallSummariser $rainfallSummariser,
        private ConditionsCache $cache,
    ) {
    }

    public function forBeach(BathingWater $beach): BeachConditions
    {
        return $this->cache->remember("beach:{$beach->id}", fn () => $this->buildBeach($beach));
    }

    public function forLake(Lake $lake): LakeConditions
    {
        return $this->cache->remember("lake:{$lake->id}", fn () => $this->buildLake($lake));
    }

    /**
     * Conditions for several beaches, read from the cache in one round trip.
     *
     * @param  Collection<int, BathingWater>  $beaches
     * @return Collection<int, BeachConditions>
     */
    public function forBeaches(Collection $beaches): Collection
    {
        $builders = $beaches->mapWithKeys(fn (BathingWater $beach) => ["beach:{$beach->id}" => fn () => $this->buildBeach($beach)])->all();

        return collect(array_values($this->cache->rememberMany($builders)));
    }

    /**
     * Conditions for several lakes, read from the cache in one round trip.
     *
     * @param  Collection<int, Lake>  $lakes
     * @return Collection<int, LakeConditions>
     */
    public function forLakes(Collection $lakes): Collection
    {
        $builders = $lakes->mapWithKeys(fn (Lake $lake) => ["lake:{$lake->id}" => fn () => $this->buildLake($lake)])->all();

        return collect(array_values($this->cache->rememberMany($builders)));
    }

    /**
     * Rebuilds a beach's conditions and writes them over the cached entry.
     */
    public function refreshBeach(BathingWater $beach): BeachConditions
    {
        return tap($this->buildBeach($beach), fn (BeachConditions $conditions) => $this->cache->put("beach:{$beach->id}", $conditions));
    }

    /**
     * Rebuilds a lake's conditions and writes them over the cached entry.
     */
    public function refreshLake(Lake $lake): LakeConditions
    {
        return tap($this->buildLake($lake), fn (LakeConditions $conditions) => $this->cache->put("lake:{$lake->id}", $conditions));
    }

    /**
     * Computes beach conditions from the database, bypassing the cache.
     */
    public function buildBeach(BathingWater $beach): BeachConditions
    {
        return new BeachConditions(
            bathingWater: $this->summarise($beach),
            overflows: $this->overflowsNear([[$beach->latitude, $beach->longitude]], $beach->region?->hasOverflowData() ?? true),
            rainfall: $this->rainfallAt($beach->rainfallStation, $beach->latitude, $beach->longitude),
        );
    }

    /**
     * Computes lake conditions from the database, bypassing the cache.
     * Overflows are measured from the lake's centre and each bathing site,
     * so long lakes like Windermere are covered end to end.
     */
    public function buildLake(Lake $lake): LakeConditions
    {
        $lake->loadMissing(['gaugeStation', 'rainfallStation', 'bathingWaters']);

        $points = [[$lake->latitude, $lake->longitude], ...$lake->bathingWaters->map(fn (BathingWater $site) => [$site->latitude, $site->longitude])->all()];

        return new LakeConditions(
            lake: $lake,
            bathingWaters: $lake->bathingWaters->map(fn (BathingWater $site) => $this->summarise($site))->values(),
            level: $lake->gaugeStation !== null ? $this->levelSummariser->summarise($lake->gaugeStation) : null,
            overflows: $this->overflowsNear($points, $lake->region->hasOverflowData()),
            rainfall: $this->rainfallAt($lake->rainfallStation, $lake->latitude, $lake->longitude),
        );
    }

    /**
     * A bathing water's recent samples and classification history.
     */
    public function summarise(BathingWater $bathingWater): BathingWaterSummary
    {
        return new BathingWaterSummary(
            bathingWater: $bathingWater->withoutRelations(),
            recentSamples: $bathingWater->samples()->limit(self::RECENT_SAMPLES)->get(),
            classifications: $bathingWater->classifications()->get(),
        );
    }

    private function rainfallAt(?RainfallStation $station, float $latitude, float $longitude): ?RainfallSummary
    {
        return $station === null ? null : $this->rainfallSummariser->summariseAt($latitude, $longitude, $station);
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    private function overflowsNear(array $points, bool $covered): NearbyOverflows
    {
        // Where we do not read the overflow feed (Wales), monitors recorded ahead of the region are left out entirely,
        // as on spots: they would show without context and link to overflows that have no page.
        $monitors = $covered ? $this->monitors->near($points, self::NEARBY_RADIUS_METRES) : collect();
        $recentEvents = $this->spillHistory->recentEventsForMonitors($monitors->map(fn (NearbyMonitorSummary $summary) => $summary->monitor->id));

        $byStatus = fn (OverflowStatus $status) => $monitors->filter(fn (NearbyMonitorSummary $summary) => $summary->status === $status)->count();
        $dischargingNow = $byStatus(OverflowStatus::Discharging);
        $offline = $byStatus(OverflowStatus::Offline);

        return new NearbyOverflows(
            radiusMetres: self::NEARBY_RADIUS_METRES,
            monitors: $monitors,
            spillRecency: SpillRecency::assess(
                covered: $covered,
                monitorCount: $monitors->count(),
                dischargingNow: $dischargingNow,
                offline: $offline,
                spilledWithin24Hours: $monitors->contains(fn (NearbyMonitorSummary $summary) => $summary->spilledSince(now()->subHours(24))),
                spilledWithin48Hours: $monitors->contains(fn (NearbyMonitorSummary $summary) => $summary->spilledSince(now()->subHours(48))),
            ),
            dischargingNowCount: $dischargingNow,
            offlineCount: $offline,
            spillsLast48HoursCount: $recentEvents->filter(fn (SpillEvent $event) => $event->ended_at === null || $event->ended_at->greaterThanOrEqualTo(now()->subHours(48)))->count(),
            spillsLast30DaysCount: $recentEvents->count(),
            recentSpillEvents: $recentEvents,
            dataUpdatedAt: $monitors->max(fn (NearbyMonitorSummary $summary) => $summary->monitor->source_updated_at),
        );
    }
}
