<?php

namespace App\Http\Controllers;

use App\Data\RegionSummary;
use App\Models\Region;
use App\Services\Conditions\RegionSummaries;
use Illuminate\View\View;

/**
 * The lists behind the "higher than usual" and "rising" counts: one spot per river whose gauge reads higher than
 * usual, and one per river whose gauge is rising, across every region or for one of them. Both come from the cached
 * region summaries, so the page reads nothing else.
 */
class LevelsController extends Controller
{
    public function index(RegionSummaries $summaries): View
    {
        $regions = Region::query()->orderBy('name')->get();

        return view('levels.index', [
            'summary' => RegionSummary::combine($regions->map(fn (Region $region) => $summaries->for($region))),
            'region' => null,
            'regionNames' => $regions->pluck('name', 'slug'),
        ]);
    }

    public function region(Region $region, RegionSummaries $summaries): View
    {
        return view('levels.index', [
            'summary' => $summaries->for($region),
            'region' => $region,
            'regionNames' => collect([$region->slug => $region->name]),
        ]);
    }
}
