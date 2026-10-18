<?php

namespace App\Actions\Ingest;

use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Region;
use App\Models\Spot;
use App\Services\Conditions\AnnualReturnTotals;
use App\Services\Conditions\NavigationMenus;
use App\Services\Conditions\RegionSummaries;
use App\Services\Conditions\SpotConditionsService;
use App\Services\Conditions\WaterBodyConditionsService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rebuilds what visitors read for the given regions: every spot's, beach's
 * and lake's conditions (written over their cached entries, so nothing goes
 * cold) and the overflows each one links to, then each region's summary,
 * then the menus. Run once after the ingests rather than by each of them.
 */
class RefreshRegions
{
    public function __construct(
        private SpotConditionsService $spots,
        private WaterBodyConditionsService $waterBodies,
        private RegionSummaries $summaries,
        private NavigationMenus $menus,
        private SyncOverflowMonitorPlaces $overflowPlaces,
    ) {
    }

    /**
     * @param  list<int>  $regionIds
     * @return array{regions: int, spots: int, beaches: int, lakes: int}
     */
    public function handle(array $regionIds): array
    {
        /** @var Collection<int, Region> $regions */
        $regions = Region::query()->whereKey($regionIds)->orderBy('name')->get();
        $counts = ['regions' => $regions->count(), 'spots' => 0, 'beaches' => 0, 'lakes' => 0];

        if ($regions->isEmpty()) {
            return $counts;
        }

        $linksChanged = false;

        Spot::query()
            ->published()
            ->onMainRiver()
            ->with(['river' => fn ($query) => $query->withoutCentreline(), 'river.region', 'gaugeStation', 'rainfallStation'])
            ->whereHas('river', fn ($query) => $query->whereIn('region_id', $regions->modelKeys()))
            ->each(function (Spot $spot) use (&$counts, &$linksChanged) {
                $linksChanged = $this->overflowPlaces->forSpot($spot, $this->spots->refresh($spot)) || $linksChanged;
                $counts['spots']++;
            });

        BathingWater::query()->beaches()->with(['rainfallStation', 'region'])->whereIn('region_id', $regions->modelKeys())->each(function (BathingWater $beach) use (&$counts, &$linksChanged) {
            $linksChanged = $this->overflowPlaces->forBeach($beach, $this->waterBodies->refreshBeach($beach)->overflows) || $linksChanged;
            $counts['beaches']++;
        });

        Lake::query()->with(['gaugeStation', 'rainfallStation', 'bathingWaters', 'region'])->whereIn('region_id', $regions->modelKeys())->each(function (Lake $lake) use (&$counts, &$linksChanged) {
            $linksChanged = $this->overflowPlaces->forLake($lake, $this->waterBodies->refreshLake($lake)->overflows) || $linksChanged;
            $counts['lakes']++;
        });

        $linksChanged = $this->overflowPlaces->prune() || $linksChanged;

        // River and region comparisons count the overflows each region's pages show, so a changed link (a region's
        // feed switched on, or overflows newly positioned) makes them work it out again rather than wait a day.
        if ($linksChanged) {
            AnnualReturnTotals::startNewGeneration();
        }

        // Summaries read the conditions just written, so they hit a warm cache.
        $regions->each(fn (Region $region) => $this->summaries->refresh($region));
        $this->menus->refresh();

        return $counts;
    }
}
