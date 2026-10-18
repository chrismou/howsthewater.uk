<?php

namespace App\Http\Controllers;

use App\Data\FeedItem;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\ConditionsFeed;
use App\Services\Conditions\RegionOverflows;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class FeedController extends Controller
{
    public function spot(Region $region, River $river, Spot $spot, ConditionsFeed $feed): Response
    {
        abort_unless($spot->is_published, 404);
        abort_if($river->isTributary(), 404);

        $spot->setRelation('river', $river->setRelation('region', $region));

        return $this->rss(
            title: "{$spot->name}, {$river->name}",
            description: "Storm overflows discharging upstream of {$spot->name} and Environment Agency flood warnings, as the monitors report them. Not a statement that the water is safe or clean.",
            link: route('spots.show', [$region, $river, $spot]),
            items: $feed->forSpot($spot),
        );
    }

    public function river(Region $region, River $river, ConditionsFeed $feed): Response
    {
        abort_if($river->isTributary(), 404);

        $river->setRelation('region', $region);

        return $this->rss(
            title: $river->name,
            description: "Storm overflows discharging on the {$river->name} and its tributaries, and Environment Agency flood warnings, as the monitors report them. Not a statement that the water is safe or clean.",
            link: route('rivers.show', [$region, $river]),
            items: $feed->forRiver($river),
        );
    }

    public function beach(Region $region, BathingWater $beach, ConditionsFeed $feed): Response
    {
        $beach->setRelation('region', $region);

        return $this->rss(
            title: $beach->name,
            description: "Storm overflows discharging near {$beach->name}, and Environment Agency bathing water samples and pollution risk forecasts, as they are reported. Not a statement that the water is safe or clean.",
            link: route('beaches.show', [$region, $beach]),
            items: $feed->forBeach($beach),
        );
    }

    public function lake(Region $region, Lake $lake, ConditionsFeed $feed): Response
    {
        $lake->setRelation('region', $region);

        return $this->rss(
            title: $lake->name,
            description: "Storm overflows discharging near {$lake->name}, and Environment Agency bathing water samples and pollution risk forecasts for its bathing sites, as they are reported. Not a statement that the water is safe or clean.",
            link: route('lakes.show', [$region, $lake]),
            items: $feed->forLake($lake),
        );
    }

    /**
     * One overflow's spills. Only overflows with a page have a feed.
     */
    public function overflow(OverflowMonitor $monitor, RegionOverflows $overflows, ConditionsFeed $feed): Response
    {
        $region = $overflows->regionFor($monitor);

        abort_if($region === null, 404);

        $name = $monitor->titleName();

        return $this->rss(
            title: $name,
            description: ($monitor->displayName() === null ? "Each spill from {$monitor->companyName()} storm overflow {$monitor->external_id}" : "Each spill from {$name}, {$monitor->companyPhrase()},").' as its monitor reports it starting and stopping. Not a statement that the water is safe or clean.',
            link: route('overflows.show', $monitor),
            items: $feed->forOverflow($monitor),
        );
    }

    /**
     * @param  Collection<int, FeedItem>  $items
     */
    private function rss(string $title, string $description, string $link, Collection $items): Response
    {
        // Overflow names come from the water companies' feeds, so a stray control character must not break the XML.
        return response()->view('feeds.rss', [
            'title' => ConditionsFeed::xmlText($title.' - '.config('app.name')),
            'description' => ConditionsFeed::xmlText($description),
            'link' => $link,
            'items' => $items,
        ], 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
