<?php

namespace App\Http\Controllers;

use App\Data\FeedItem;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use App\Services\Conditions\ConditionsFeed;
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
     * @param  Collection<int, FeedItem>  $items
     */
    private function rss(string $title, string $description, string $link, Collection $items): Response
    {
        return response()->view('feeds.rss', [
            'title' => $title.' - '.config('app.name'),
            'description' => $description,
            'link' => $link,
            'items' => $items,
        ], 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
