<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use Illuminate\Http\Response;

/**
 * Every public page as a sitemap, so search engines find each spot without
 * crawling through the menus.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $regions = Region::query()->orderBy('name')->get();
        $rivers = River::query()->main()->withoutCentreline()->with('region')->orderBy('name')->get();
        $spots = Spot::query()->published()->onMainRiver()->with('river.region')->orderBy('name')->get();

        $urls = collect([['loc' => route('home'), 'changefreq' => 'hourly', 'priority' => '1.0', 'lastmod' => null]])
            ->concat($regions->map(fn (Region $region) => ['loc' => route('regions.show', $region), 'changefreq' => 'hourly', 'priority' => '0.9', 'lastmod' => $region->updated_at]))
            ->concat($rivers->map(fn (River $river) => ['loc' => route('rivers.show', [$river->region, $river]), 'changefreq' => 'hourly', 'priority' => '0.8', 'lastmod' => $river->updated_at]))
            ->concat($spots->map(fn (Spot $spot) => ['loc' => route('spots.show', [$spot->river->region, $spot->river, $spot]), 'changefreq' => 'hourly', 'priority' => '0.7', 'lastmod' => $spot->updated_at]));

        return response()->view('sitemap', ['urls' => $urls], 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
