<?php

namespace App\Http\Controllers;

use App\Enums\DischargingOrder;
use App\Models\Region;
use App\Services\Conditions\AnnualReturnTotals;
use App\Services\Conditions\RegionSummaries;
use App\Services\Conditions\SpillsOverviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The lists behind the live bar's spill counts: what is discharging now and which spots had a spill upstream in the
 * last 24 hours, across every region we read overflow data for, or for one of them. Long lists show their first rows
 * and load more on request (rows()).
 */
class SpillsController extends Controller
{
    public function index(Request $request, SpillsOverviewService $spills, AnnualReturnTotals $totals): View
    {
        $regions = Region::query()->orderBy('id')->get();
        $year = $totals->latestYear();

        return view('spills.index', [
            'overview' => $spills->for($regions, 'All regions', $this->order($request)),
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
    public function region(Request $request, Region $region, SpillsOverviewService $spills, RegionSummaries $summaries, AnnualReturnTotals $totals): View
    {
        abort_unless($region->hasOverflowData(), 404);

        $year = $totals->latestYear();

        return view('spills.index', [
            'overview' => $spills->for(collect([$region]), $region->name, $this->order($request)),
            'region' => $region,
            'regionLinks' => collect(),
            'liveSummary' => $summaries->for($region),
            'comparisonYear' => $year,
            'comparison' => $year === null ? collect() : $totals->riversIn($region, $year),
        ]);
    }

    /**
     * The next rows of one of the combined page's lists, after the first `offset`.
     */
    public function rows(Request $request, string $list, SpillsOverviewService $spills): View
    {
        return $this->moreRows($request, $list, Region::query()->orderBy('id')->get(), $spills);
    }

    /**
     * The next rows of one of a region's lists, after the first `offset`.
     */
    public function regionRows(Request $request, Region $region, string $list, SpillsOverviewService $spills): View
    {
        abort_unless($region->hasOverflowData(), 404);

        return $this->moreRows($request, $list, collect([$region]), $spills);
    }

    /**
     * @param  Collection<int, Region>  $regions
     */
    private function moreRows(Request $request, string $list, Collection $regions, SpillsOverviewService $spills): View
    {
        $offset = max(0, min(100000, (int) $request->query('offset', '0')));

        if ($list === 'recent') {
            return view('spills.recent-rows', ['spills' => $spills->moreRecentSpills($regions, $offset)]);
        }

        return view('spills.discharging-rows', ['listings' => $spills->moreDischarging($regions, $list === 'near', $this->order($request), $offset)]);
    }

    private function order(Request $request): DischargingOrder
    {
        return DischargingOrder::tryFrom((string) $request->query('order', '')) ?? DischargingOrder::Newest;
    }
}
