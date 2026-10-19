<?php

namespace App\Http\Controllers;

use App\Data\RegionSummary;
use App\Data\SwimmingSpot;
use App\Models\Region;
use App\Services\Conditions\RegionSummaries;
use App\Services\Conditions\SwimmingSpots;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Every river spot, beach and lake on one map, for finding somewhere to swim. The page itself stays light: a block per
 * region linking to its listings, while the places come from places() for the map and the "near me" list, which
 * swimming.js sorts by distance in the browser. Both are the same for every visitor, so both cache at the edge.
 */
class SwimmingController extends Controller
{
    public function __invoke(RegionSummaries $summaries, SwimmingSpots $swimmingSpots): View
    {
        $regions = $this->regions($summaries);

        return view('swimming.index', [
            'regions' => $swimmingSpots->all($regions)->groupBy('regionSlug'),
            'liveSummary' => RegionSummary::combine($regions),
            'dataUpdatedAt' => $regions->max('dataUpdatedAt'),
        ]);
    }

    public function places(RegionSummaries $summaries, SwimmingSpots $swimmingSpots): JsonResponse
    {
        $places = $swimmingSpots->all($this->regions($summaries))->map(fn (SwimmingSpot $spot) => $spot->toMapPlace())->values();

        return response()->json($places, options: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return Collection<int, RegionSummary>
     */
    private function regions(RegionSummaries $summaries): Collection
    {
        return Region::query()->orderBy('name')->get()->map(fn (Region $region) => $summaries->for($region));
    }
}
