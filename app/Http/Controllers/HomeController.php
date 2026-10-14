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
        // The first region added leads the hero; the rest follow in the order they were added.
        $regions = Region::query()->orderBy('id')->get()->map(fn (Region $region) => $summaries->for($region));

        return view('home', [
            'regions' => $regions,
            'featured' => $this->featuredSpots($regions),
        ]);
    }

    /**
     * A few spots to try from the search box: the ones flagged as featured
     * in the seed data, or failing that the best monitored spot on each of
     * the first rivers so the examples have something to show.
     *
     * @param  Collection<int, RegionSummary>  $regions
     * @return Collection<int, SpotSummary>
     */
    private function featuredSpots(Collection $regions): Collection
    {
        $featured = $regions
            ->flatMap(fn (RegionSummary $region) => $region->spots())
            ->filter(fn (SpotSummary $spot) => $spot->featured)
            ->take(6)
            ->values();

        if ($featured->isNotEmpty()) {
            return $featured;
        }

        return $regions
            ->flatMap(fn (RegionSummary $region) => $region->rivers)
            ->map(fn (RiverSummary $river) => $river->spots->sortByDesc('monitorCount')->first())
            ->filter()
            ->take(5)
            ->values();
    }
}
