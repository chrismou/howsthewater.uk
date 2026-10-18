<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Services\Conditions\RegionSummaries;
use Illuminate\View\View;

/**
 * A region's overview: its headline figures, any flood warnings in force,
 * a card each for its rivers, beaches and lakes, and links to other
 * regions' rivers that run through it.
 */
class RegionController extends Controller
{
    public function show(Region $region, RegionSummaries $summaries): View
    {
        return view('regions.show', [
            'region' => $region,
            'summary' => $summaries->for($region),
            'alsoRunningThrough' => $region->riversAlsoRunningThrough(),
        ]);
    }
}
