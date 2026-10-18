<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Services\Conditions\AnnualReturnTotals;
use App\Services\Conditions\RegionSummaries;
use App\Services\Conditions\SpillsOverviewService;
use Illuminate\View\View;

/**
 * The lists behind the live bar's spill counts: what is discharging now and which spots had a spill upstream in the
 * last 24 hours, across every region we read overflow data for, or for one of them.
 */
class SpillsController extends Controller
{
    public function index(SpillsOverviewService $spills, AnnualReturnTotals $totals): View
    {
        $regions = Region::query()->orderBy('id')->get();
        $year = $totals->latestYear();

        return view('spills.index', [
            'overview' => $spills->for($regions, 'All regions'),
            'region' => null,
            'regionLinks' => $regions->filter(fn (Region $region) => $region->hasOverflowData())->sortBy('name')->values(),
            'liveSummary' => $spills->combinedSummary($regions),
            'comparisonYear' => $year,
            'comparison' => $year === null ? collect() : $totals->regions($regions, $year),
        ]);
    }

    /**
     * A region whose overflow feed we do not read has no spills page: it would have nothing to list.
     */
    public function region(Region $region, SpillsOverviewService $spills, RegionSummaries $summaries, AnnualReturnTotals $totals): View
    {
        abort_unless($region->hasOverflowData(), 404);

        $year = $totals->latestYear();

        return view('spills.index', [
            'overview' => $spills->for(collect([$region]), $region->name),
            'region' => $region,
            'regionLinks' => collect(),
            'liveSummary' => $summaries->for($region),
            'comparisonYear' => $year,
            'comparison' => $year === null ? collect() : $totals->riversIn($region, $year),
        ]);
    }
}
