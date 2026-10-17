<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\NearbyPlaceFinder;
use App\Services\Conditions\SpotConditionsService;
use Illuminate\View\View;

class SpotController extends Controller
{
    /**
     * Rows the overflows table renders with the page; the rest load on request.
     */
    public const MONITORS_SHOWN = 10;

    public function show(Region $region, River $river, Spot $spot, SpotConditionsService $conditions, NearbyPlaceFinder $nearby): View
    {
        abort_unless($spot->is_published, 404);
        abort_if($river->isTributary(), 404);

        $spot->setRelation('river', $river->setRelation('region', $region));

        return view('spots.show', ['conditions' => $conditions->for($spot), 'river' => $river, 'nearby' => $nearby->forSpot($spot)]);
    }

    /**
     * The overflows table rows beyond those the spot page renders, fetched when a visitor asks to see them.
     */
    public function monitors(Region $region, River $river, Spot $spot, SpotConditionsService $conditions): View
    {
        abort_unless($spot->is_published, 404);
        abort_if($river->isTributary(), 404);

        $spot->setRelation('river', $river->setRelation('region', $region));
        $spotConditions = $conditions->for($spot);

        return view('spots.monitor-rows', [
            'monitors' => $spotConditions->upstreamMonitorsByActivity()->slice(self::MONITORS_SHOWN),
            'spillCounts' => $spotConditions->spillCountsByMonitor(),
        ]);
    }
}
