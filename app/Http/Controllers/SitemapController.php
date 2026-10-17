<?php

namespace App\Http\Controllers;

use App\Data\RegionSummary;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\RegionSummaries;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every public page as a sitemap, so search engines find each spot without
 * crawling through the menus. Each page's lastmod is when its data last
 * changed: the monitor data behind a spot, river or region (from the cached
 * summaries), and the bathing water or lake record for beaches and lakes.
 * Search engines ignore changefreq and priority, so neither is given.
 */
class SitemapController extends Controller
{
    public function __invoke(RegionSummaries $regionSummaries): Response
    {
        $regions = Region::query()->orderBy('name')->get();
        $rivers = River::query()->main()->withoutCentreline()->with('region')->orderBy('name')->get();
        $spots = Spot::query()->published()->onMainRiver()->with('river.region')->orderBy('name')->get();
        $beaches = BathingWater::query()->beaches()->whereNotNull('region_id')->with('region')->orderBy('name')->get();
        $lakes = Lake::query()->with('region')->orderBy('name')->get();

        /** @var Collection<int, RegionSummary> $summaries */
        $summaries = $regions->mapWithKeys(fn (Region $region) => [$region->id => $regionSummaries->for($region)]);
        $spotTimes = $summaries->flatMap(fn (RegionSummary $summary) => $summary->spots()->mapWithKeys(fn ($spot) => [$spot->id => $spot->dataUpdatedAt]));
        $latest = $summaries->map(fn (RegionSummary $summary) => $summary->dataUpdatedAt)->filter()->max();
        $listing = fn (string $route, Collection $withPlaces) => $regions->whereIn('id', $withPlaces->pluck('region_id'))
            ->map(fn (Region $region) => $this->url(route($route, $region), $summaries[$region->id]->dataUpdatedAt));

        $urls = collect([$this->url(route('home'), $latest)])
            ->concat($regions->map(fn (Region $region) => $this->url(route('regions.show', $region), $summaries[$region->id]->dataUpdatedAt)))
            ->concat($listing('rivers.index', $rivers))
            ->concat($listing('beaches.index', $beaches))
            ->concat($listing('lakes.index', $lakes))
            ->concat($rivers->map(fn (River $river) => $this->url(
                route('rivers.show', [$river->region, $river]),
                $spots->where('river_id', $river->id)->map(fn (Spot $spot) => $spotTimes[$spot->id] ?? null)->filter()->max(),
            )))
            ->concat($spots->map(fn (Spot $spot) => $this->url(route('spots.show', [$spot->river->region, $spot->river, $spot]), $spotTimes[$spot->id] ?? null)))
            ->concat($beaches->map(fn (BathingWater $beach) => $this->url(route('beaches.show', [$beach->region, $beach]), $beach->updated_at)))
            ->concat($lakes->map(fn (Lake $lake) => $this->url(route('lakes.show', [$lake->region, $lake]), $lake->updated_at)))
            ->concat(collect(['hubs.rivers', 'hubs.beaches', 'hubs.lakes'])->map(fn (string $hub) => $this->url(route($hub), $latest)))
            ->concat(collect(['about', 'terms', 'privacy'])->map(fn (string $page) => $this->url(route($page), null)));

        return response()->view('sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * @return array{loc: string, lastmod: Carbon|null}
     */
    private function url(string $loc, ?Carbon $lastmod): array
    {
        return ['loc' => $loc, 'lastmod' => $lastmod];
    }
}
