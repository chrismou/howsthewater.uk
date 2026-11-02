<?php

namespace App\Http\Controllers;

use App\Models\GaugeStation;
use App\Models\Region;
use App\Models\River;
use App\Services\Conditions\AnnualReturnTotals;
use App\Services\Conditions\GaugeLevelHistory;
use App\Services\Conditions\PlaceHeadline;
use App\Services\Conditions\RegionSummaries;
use App\Services\Geo\Coordinates;
use App\Services\Weather\PlaceWeather;
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

        // Other regions' rivers running through this one, read from their own region's summary, with their spots here.
        $alsoRunningThrough = $region->riversAlsoRunningThrough()
            ->map(fn (River $river) => [
                'river' => $summaries->for($river->region)->rivers->firstWhere('id', $river->id),
                'spotSlugs' => $river->also_runs_through[$region->slug] ?? [],
                'regionSlug' => $river->region->slug,
                'regionName' => $river->region->nameInSentence(),
            ])
            ->filter(fn (array $entry) => $entry['river'] !== null)
            ->values();

        return view('rivers.index', [
            'region' => $region,
            'summary' => $summary,
            'alsoRunningThrough' => $alsoRunningThrough,
            'centrelines' => $centrelines,
            'mapIsTall' => Coordinates::isTallerThanWide($centrelines->flatten(1)),
        ]);
    }

    /**
     * A river and its spots, read from the region's cached summary: the full conditions of every spot on a long
     * river (hundreds of upstream overflows each, low on the Trent) do not fit in a request's memory. The river's few
     * gauges, with a week of readings, and its weather are read here, as the page is cached at the edge.
     */
    public function show(Region $region, River $river, RegionSummaries $summaries, AnnualReturnTotals $totals, GaugeLevelHistory $levelHistory, PlaceWeather $weather, PlaceHeadline $headline): View
    {
        abort_if($river->isTributary(), 404);

        $river->setRelation('region', $region);
        $summary = $summaries->for($region)->rivers->firstWhere('id', $river->id);
        abort_if($summary === null, 404);
        $latestYear = $totals->latestYear();

        $gaugeIds = $summary->spotsWithOwnGauge()->pluck('gaugeId');
        $middle = $summary->middleSpot();
        $forecast = $middle === null ? null : $weather->forecast($middle->latitude, $middle->longitude);

        return view('rivers.show', [
            'river' => $river,
            'summary' => $summary,
            'headline' => $headline->forRiver($summary, $forecast, $region->hasOverflowData()),
            'gauges' => GaugeStation::query()->whereIn('id', $gaugeIds)->get()->keyBy('id'),
            'levelHistories' => $levelHistory->forStations($gaugeIds),
            'weather' => $forecast,
            'sun' => $middle === null ? null : $weather->sunTimes($middle->latitude, $middle->longitude),
            'flowsInto' => $river->flows_into_river_id === null ? null : $river->flowsInto()->with('region')->first(),
            'tributaryNames' => $river->tributaries()->pluck('name')->all(),
            'shareImage' => $river->shareImage,
            'returnYears' => $totals->riverByYear($river),
            'returnRank' => $latestYear === null ? null : $totals->riverRank($river, $latestYear),
            'hasInflowingMainRivers' => $totals->hasInflowingMainRivers($river),
        ]);
    }
}
