<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\River;
use App\Services\Conditions\RegionSummaries;
use App\Services\Conditions\RiverOverviewService;
use App\Services\Geo\Coordinates;
use Illuminate\View\View;

class RiverController extends Controller
{
    /**
     * A region's rivers, source to mouth, with every spot on them.
     */
    public function index(Region $region, RegionSummaries $summaries): View
    {
        $summary = $summaries->for($region);
        abort_if($summary->rivers->isEmpty(), 404);

        $centrelines = $region->rivers()->main()->get(['id', 'centreline'])
            ->map(fn ($river) => $river->hasCentreline() ? array_values(array_filter($river->centreline, fn ($point, $index) => $index % 4 === 0, ARRAY_FILTER_USE_BOTH)) : [])
            ->filter()
            ->values();

        return view('rivers.index', [
            'region' => $region,
            'summary' => $summary,
            'centrelines' => $centrelines,
            'mapIsTall' => Coordinates::isTallerThanWide($centrelines->flatten(1)),
        ]);
    }

    public function show(Region $region, River $river, RiverOverviewService $overview): View
    {
        abort_if($river->isTributary(), 404);

        return view('rivers.show', [
            'overview' => $overview->for($river->setRelation('region', $region)),
            'shareImage' => $river->shareImage,
        ]);
    }
}
