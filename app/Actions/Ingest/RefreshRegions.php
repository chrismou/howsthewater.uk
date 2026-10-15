<?php

namespace App\Actions\Ingest;

use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Region;
use App\Models\Spot;
use App\Services\Conditions\NavigationMenus;
use App\Services\Conditions\RegionSummaries;
use App\Services\Conditions\SpotConditionsService;
use App\Services\Conditions\WaterBodyConditionsService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Rebuilds what visitors read for the given regions: every spot's, beach's
 * and lake's conditions (written over their cached entries, so nothing goes
 * cold), then each region's summary, then the menus. Run once after the
 * ingests rather than by each of them.
 */
class RefreshRegions
{
    public function __construct(
        private SpotConditionsService $spots,
        private WaterBodyConditionsService $waterBodies,
        private RegionSummaries $summaries,
        private NavigationMenus $menus,
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

        Spot::query()
            ->published()
            ->onMainRiver()
            ->with(['river' => fn ($query) => $query->withoutCentreline(), 'river.region', 'gaugeStation', 'rainfallStation'])
            ->whereHas('river', fn ($query) => $query->whereIn('region_id', $regions->modelKeys()))
            ->each(function (Spot $spot) use (&$counts) {
                $this->spots->refresh($spot);
                $counts['spots']++;
            });

        BathingWater::query()->beaches()->with('rainfallStation')->whereIn('region_id', $regions->modelKeys())->each(function (BathingWater $beach) use (&$counts) {
            $this->waterBodies->refreshBeach($beach);
            $counts['beaches']++;
        });

        Lake::query()->with(['gaugeStation', 'rainfallStation', 'bathingWaters'])->whereIn('region_id', $regions->modelKeys())->each(function (Lake $lake) use (&$counts) {
            $this->waterBodies->refreshLake($lake);
            $counts['lakes']++;
        });

        // Summaries read the conditions just written, so they hit a warm cache.
        $regions->each(fn (Region $region) => $this->summaries->refresh($region));
        $this->menus->refresh();

        return $counts;
    }
}
