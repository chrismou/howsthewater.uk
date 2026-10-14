<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Services\Conditions\RegionSummaries;
use App\Services\Geo\Coordinates;
use Illuminate\View\View;

class RegionController extends Controller
{
    public function show(Region $region, RegionSummaries $summaries): View
    {
        $centrelines = $region->rivers()->main()->get(['id', 'centreline'])
            ->map(fn ($river) => $river->hasCentreline() ? array_values(array_filter($river->centreline, fn ($point, $index) => $index % 4 === 0, ARRAY_FILTER_USE_BOTH)) : [])
            ->filter()
            ->values();

        return view('regions.show', [
            'region' => $region,
            'summary' => $summaries->for($region),
            'centrelines' => $centrelines,
            'mapIsTall' => Coordinates::isTallerThanWide($centrelines->flatten(1)),
        ]);
    }
}
