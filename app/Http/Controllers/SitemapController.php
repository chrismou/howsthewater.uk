<?php

namespace App\Http\Controllers;

use App\Data\RegionSummary;
use App\Data\SpotSummary;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\OverflowMonitorPlace;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\RegionOverflows;
use App\Services\Conditions\RegionSummaries;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Every public page as a sitemap, so search engines find each spot without
 * crawling through the menus. Each page's lastmod is when its data last
 * changed: the monitor, level or rain data behind a spot, river or region
 * (from the cached summaries), the bathing water or lake record for beaches
 * and lakes, and the date written on the about and legal pages.
 * Search engines ignore changefreq and priority, so neither is given.
 */
class SitemapController extends Controller
{
    public function __invoke(RegionSummaries $regionSummaries, RegionOverflows $regionOverflows): Response
    {
        $regions = Region::query()->orderBy('name')->get();
        $rivers = River::query()->main()->withoutCentreline()->with('region')->orderBy('name')->get();
        $spots = Spot::query()->published()->onMainRiver()->with('river.region')->orderBy('name')->get();
        $beaches = BathingWater::query()->beaches()->whereNotNull('region_id')->with('region')->orderBy('name')->get();
        $lakes = Lake::query()->with('region')->orderBy('name')->get();

        /** @var Collection<int, RegionSummary> $summaries */
        $summaries = $regions->mapWithKeys(fn (Region $region) => [$region->id => $regionSummaries->for($region)]);
        $spotTimes = $summaries->flatMap(fn (RegionSummary $summary) => $summary->spots()->mapWithKeys(fn (SpotSummary $spot) => [$spot->id => $spot->latestDataAt()]));
        $regionTimes = $summaries->map(fn (RegionSummary $summary) => $summary->dataUpdatedAt ?? $summary->spots()->map(fn (SpotSummary $spot) => $spot->latestDataAt())->filter()->max());
        $latest = $regionTimes->filter()->max();
        $listing = fn (string $route, Collection $withPlaces) => $regions->whereIn('id', $withPlaces->pluck('region_id'))
            ->map(fn (Region $region) => $this->url(route($route, $region), $regionTimes[$region->id]));

        $urls = collect([$this->url(route('home'), $latest)])
            ->concat($regions->map(fn (Region $region) => $this->url(route('regions.show', $region), $regionTimes[$region->id])))
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
            ->concat(collect(['hubs.rivers', 'hubs.beaches', 'hubs.lakes', 'spills.index'])->map(fn (string $hub) => $this->url(route($hub), $latest)))
            ->concat($regions->filter(fn (Region $region) => $region->hasOverflowData())->map(fn (Region $region) => $this->url(route('spills.region', $region), $regionTimes[$region->id])))
            // Dated by the overflow's latest spill: feeds confirm every monitor each poll, which changes nothing on the page.
            ->concat($this->overflowPages($regions, $regionOverflows)->map(fn (OverflowMonitor $monitor) => $this->url(route('overflows.show', $monitor), $monitor->latest_event_ended_at ?? $monitor->latest_event_started_at)))
            ->concat(collect(['about', 'terms', 'privacy'])->map(fn (string $page) => $this->url(route($page), Carbon::parse(config("app.pages_updated.{$page}"), config('app.display_timezone', 'Europe/London')))));

        return response()->view('sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Every overflow some region's pages show, leaving out those whose page says nothing of its own yet (no name, no
     * annual return and no spill recorded), which are noindexed.
     *
     * @param  Collection<int, Region>  $regions
     * @return Collection<int, OverflowMonitor>
     */
    private function overflowPages(Collection $regions, RegionOverflows $regionOverflows): Collection
    {
        return OverflowMonitor::query()
            ->where(fn ($query) => $query->whereIn('river_id', $regionOverflows->riverIdsQuery($regions))->orWhereIn('id', OverflowMonitorPlace::query()->select('overflow_monitor_id')))
            ->where(fn ($query) => $query->whereNotNull('name')->orWhereNotNull('return_name')->orWhereHas('annualReturns')->orWhereHas('spillEvents'))
            ->orderBy('external_id')
            ->get(['id', 'external_id', 'latest_event_started_at', 'latest_event_ended_at']);
    }

    /**
     * @return array{loc: string, lastmod: Carbon|null}
     */
    private function url(string $loc, ?Carbon $lastmod): array
    {
        return ['loc' => $loc, 'lastmod' => $lastmod];
    }
}
