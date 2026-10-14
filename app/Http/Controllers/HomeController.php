<?php

namespace App\Http\Controllers;

use App\Data\RegionSummary;
use App\Data\RiverSummary;
use App\Data\SpotSummary;
use App\Models\Region;
use App\Services\Conditions\RegionSummaries;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(RegionSummaries $summaries): View
    {
        $regions = Region::query()->orderBy('name')->get()->map(fn (Region $region) => $summaries->for($region));

        return view('home', [
            'regions' => $regions,
            'featured' => $this->featuredSpots($regions),
        ]);
    }

    /**
     * A few spots to try from the search box: the best monitored spot on
     * each of the first rivers, so the examples have something to show.
     *
     * @param  Collection<int, RegionSummary>  $regions
     * @return Collection<int, SpotSummary>
     */
    private function featuredSpots(Collection $regions): Collection
    {
        return $regions
            ->flatMap(fn (RegionSummary $region) => $region->rivers)
            ->map(fn (RiverSummary $river) => $river->spots->sortByDesc('monitorCount')->first())
            ->filter()
            ->take(5)
            ->values();
    }
}
