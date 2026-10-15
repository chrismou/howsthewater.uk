<?php

namespace App\Services\Conditions;

use App\Data\RegionSummary;
use App\Data\RiverSummary;
use App\Models\GaugeStation;
use App\Models\OverflowMonitor;
use App\Models\RainfallStation;
use App\Models\Region;
use Illuminate\Support\Facades\Cache;

/**
 * Builds and caches each region's summary. The scheduled regions:refresh
 * rewrites the summaries of regions an ingest changed, so pages only ever
 * read. A page that finds no summary builds one, so a fresh install still works.
 */
class RegionSummaries
{
    private const KEY_PREFIX = 'region-summary';

    public function __construct(private RiverOverviewService $overviews)
    {
    }

    public function for(Region $region): RegionSummary
    {
        $summary = Cache::get($this->keyFor($region));

        return $summary instanceof RegionSummary ? $summary : $this->refresh($region);
    }

    /**
     * Rebuilds a region's summary from fresh spot conditions and stores it
     * without expiry; the next refresh simply overwrites it.
     */
    public function refresh(Region $region): RegionSummary
    {
        $summary = $this->build($region);

        Cache::forever($this->keyFor($region), $summary);

        return $summary;
    }

    public function build(Region $region): RegionSummary
    {
        $rivers = $this->overviews->forRegion($region)->map(fn ($overview) => RiverSummary::fromOverview($overview))->values();
        $riverIds = $region->rivers()->pluck('id');

        return new RegionSummary(
            slug: $region->slug,
            name: $region->name,
            rivers: $rivers,
            monitorCount: OverflowMonitor::query()->whereIn('river_id', $riverIds)->count(),
            offlineMonitorCount: OverflowMonitor::query()->whereIn('river_id', $riverIds)->where('status', -1)->count(),
            gaugeCount: GaugeStation::query()->whereIn('river_id', $riverIds)->where('is_active', true)->count(),
            rainGaugeCount: RainfallStation::query()->where('is_active', true)->whereHas('spots', fn ($query) => $query->whereIn('river_id', $riverIds))->count(),
            dataUpdatedAt: $rivers->flatMap(fn (RiverSummary $river) => $river->spots)->max('dataUpdatedAt'),
            generatedAt: now(),
        );
    }

    /**
     * Keys carry the code hash, so a deploy that changes the summary's shape
     * rebuilds rather than reading an old object.
     */
    private function keyFor(Region $region): string
    {
        return sprintf('%s:%s:%s', self::KEY_PREFIX, ConditionsCache::codeVersion(), $region->slug);
    }
}
