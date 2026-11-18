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
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Rebuilds what visitors read for the given regions: every spot's, beach's
 * and lake's conditions (written over their cached entries, so nothing goes
 * cold) and the overflows each one links to, then each region's summary,
 * then the menus. Run once after the ingests rather than by each of them.
 *
 * A place or region that fails is reported and skipped, so a place that
 * cannot be built leaves its own region stale (its summary rebuilds it)
 * rather than every region, until it is fixed.
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
     * @return array{regions: int, spots: int, beaches: int, lakes: int, failedRegionIds: list<int>}
     */
    public function handle(array $regionIds): array
    {
        /** @var Collection<int, Region> $regions */
        $regions = Region::query()->whereKey($regionIds)->orderBy('name')->get();
        $counts = ['regions' => $regions->count(), 'spots' => 0, 'beaches' => 0, 'lakes' => 0, 'failedRegionIds' => []];
        $failed = function (int $regionId) use (&$counts): void {
            $counts['failedRegionIds'] = array_values(array_unique([...$counts['failedRegionIds'], $regionId]));
        };

        if ($regions->isEmpty()) {
            return $counts;
        }

        $linksChanged = false;

        Spot::query()
            ->published()
            ->onMainRiver()
            ->with(['river' => fn ($query) => $query->withoutCentreline(), 'river.region', 'gaugeStation', 'rainfallStation'])
            ->whereHas('river', fn ($query) => $query->whereIn('region_id', $regions->modelKeys()))
            ->each(function (Spot $spot) use (&$counts, &$linksChanged, $failed) {
                $this->attempt(function () use ($spot, &$counts, &$linksChanged) {
                    $linksChanged = $this->overflowPlaces->forSpot($spot, $this->spots->refresh($spot)) || $linksChanged;
                    $counts['spots']++;
                }, fn () => $failed($spot->river->region_id));
            });

        BathingWater::query()->beaches()->with(['rainfallStation', 'region'])->whereIn('region_id', $regions->modelKeys())->each(function (BathingWater $beach) use (&$counts, &$linksChanged, $failed) {
            $this->attempt(function () use ($beach, &$counts, &$linksChanged) {
                $linksChanged = $this->overflowPlaces->forBeach($beach, $this->waterBodies->refreshBeach($beach)->overflows) || $linksChanged;
                $counts['beaches']++;
            }, fn () => $failed($beach->region_id));
        });

        Lake::query()->with(['gaugeStation', 'rainfallStation', 'bathingWaters', 'region'])->whereIn('region_id', $regions->modelKeys())->each(function (Lake $lake) use (&$counts, &$linksChanged, $failed) {
            $this->attempt(function () use ($lake, &$counts, &$linksChanged) {
                $linksChanged = $this->overflowPlaces->forLake($lake, $this->waterBodies->refreshLake($lake)->overflows) || $linksChanged;
                $counts['lakes']++;
            }, fn () => $failed($lake->region_id));
        });

        $linksChanged = $this->overflowPlaces->prune() || $linksChanged;

        // River and region comparisons count the overflows each region's pages show, so a changed link (a region's
        // feed switched on, or overflows newly positioned) makes them work it out again rather than wait a day.
        if ($linksChanged) {
            AnnualReturnTotals::startNewGeneration();
        }

        // Summaries read the conditions just written, so they hit a warm cache.
        $regions->each(fn (Region $region) => $this->attempt(fn () => $this->summaries->refresh($region), fn () => $failed($region->id)));
        $this->menus->refresh();

        return $counts;
    }

    /**
     * Runs one place's or region's refresh, reporting a failure rather than
     * letting it stop the rest.
     *
     * @param  Closure(): mixed  $refresh
     * @param  Closure(): void  $onFailure
     */
    private function attempt(Closure $refresh, Closure $onFailure): void
    {
        try {
            $refresh();
        } catch (Throwable $exception) {
            report($exception);
            $onFailure();
        }
    }
}
