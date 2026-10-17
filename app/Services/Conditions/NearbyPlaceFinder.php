<?php

namespace App\Services\Conditions;

use App\Data\NearbyPlace;
use App\Data\NearbyPlaces;
use App\Data\RegionSummary;
use App\Data\RiverSummary;
use App\Data\SpotSummary;
use App\Data\WaterBodySummary;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use App\Services\Geo\Coordinates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Finds the places a spot, beach or lake page links to: the next spots up
 * and down the river (following it into the river it flows into, or up
 * main rivers that flow in), and the nearest beaches, lakes and spots.
 * Statuses come from the cached region summaries, so it never builds conditions.
 */
class NearbyPlaceFinder
{
    public const SPOT_WATER_BODY_RADIUS_METRES = 20000;

    public const WATER_BODY_RADIUS_METRES = 30000;

    public const RIVER_SPOT_RADIUS_METRES = 20000;

    private const METRES_PER_DEGREE_LATITUDE = 111320;

    /** @var array<int, RegionSummary> */
    private array $summaries = [];

    public function __construct(private RegionSummaries $regionSummaries)
    {
    }

    /**
     * The spot's neighbours on the river, then up to two beaches or lakes within 20 km.
     */
    public function forSpot(Spot $spot): NearbyPlaces
    {
        $river = $spot->river;
        $region = $river->region;
        $riverSpots = $this->spotsOn($river, $region);
        $places = collect();

        if ($spot->river_metres !== null) {
            $metres = $spot->river_metres;
            $above = $riverSpots->last(fn (SpotSummary $other) => $other->riverMetres < $metres);
            $below = $riverSpots->first(fn (SpotSummary $other) => $other->riverMetres > $metres);

            if ($above !== null) {
                $places->push($this->spotPlace($above, 'Upstream', $metres - $above->riverMetres, alongRiver: true));
            }

            $places = $places->concat($this->inflowingRiverPlaces($river, $above?->riverMetres, $metres));

            if ($below !== null) {
                $places->push($this->spotPlace($below, 'Downstream', $below->riverMetres - $metres, alongRiver: true));
            } elseif (($downstream = $this->spotBelowJoin($river, $metres)) !== null) {
                $places->push($downstream);
            }
        }

        $places = $places->concat($this->waterBodiesNear($spot->latitude, $spot->longitude, self::SPOT_WATER_BODY_RADIUS_METRES, 2));

        $links = [[
            $riverSpots->count() > 1 ? "All {$riverSpots->count()} spots on the {$river->name}" : "More about the {$river->name}",
            route('rivers.show', [$region, $river]),
        ], [
            'Rivers in '.$region->nameInSentence(),
            route('rivers.index', $region),
        ]];

        return new NearbyPlaces($places->values(), $links);
    }

    /**
     * The three nearest other beaches or lakes within 30 km, then the nearest river spot within 20 km.
     */
    public function forBeach(BathingWater $beach): NearbyPlaces
    {
        $region = $beach->region;

        return new NearbyPlaces(
            $this->waterBodiesNear($beach->latitude, $beach->longitude, self::WATER_BODY_RADIUS_METRES, 3, except: $beach)
                ->concat($this->spotsNear($beach->latitude, $beach->longitude))
                ->values(),
            $this->listingLinks($region, 'Beaches in '.$region->nameInSentence(), route('beaches.index', $region)),
        );
    }

    /**
     * The three nearest other lakes or beaches within 30 km, then the nearest river spot within 20 km.
     */
    public function forLake(Lake $lake): NearbyPlaces
    {
        $region = $lake->region;

        return new NearbyPlaces(
            $this->waterBodiesNear($lake->latitude, $lake->longitude, self::WATER_BODY_RADIUS_METRES, 3, except: $lake)
                ->concat($this->spotsNear($lake->latitude, $lake->longitude))
                ->values(),
            $this->listingLinks($region, 'Lakes in '.$region->nameInSentence(), route('lakes.index', $region)),
        );
    }

    /**
     * The lowest spot on each main river that flows in between the spot above and this one.
     *
     * @return Collection<int, NearbyPlace>
     */
    private function inflowingRiverPlaces(River $river, ?int $aboveMetres, int $metres): Collection
    {
        return River::query()
            ->main()
            ->withoutCentreline()
            ->with('region')
            ->where('flows_into_river_id', $river->id)
            ->whereNotNull('joins_at_metres')
            ->whereNotNull('length_metres')
            ->where('joins_at_metres', '<=', $metres)
            ->when($aboveMetres !== null, fn (Builder $query) => $query->where('joins_at_metres', '>', $aboveMetres))
            ->orderByDesc('joins_at_metres')
            ->get()
            ->map(function (River $inflow) use ($metres): ?NearbyPlace {
                $lowest = $this->spotsOn($inflow, $inflow->region)->last();

                return $lowest === null ? null : $this->spotPlace(
                    $lowest,
                    "Upstream on the {$inflow->name}",
                    ($metres - $inflow->joins_at_metres) + max(0, $inflow->length_metres - $lowest->riverMetres),
                    alongRiver: true,
                );
            })
            ->filter()
            ->values();
    }

    /**
     * For the lowest spot on a river that flows into another, the first spot below the join.
     */
    private function spotBelowJoin(River $river, int $metres): ?NearbyPlace
    {
        if ($river->flows_into_river_id === null || $river->joins_at_metres === null || $river->length_metres === null) {
            return null;
        }

        $into = River::query()->withoutCentreline()->with('region')->find($river->flows_into_river_id);
        $first = $into === null ? null : $this->spotsOn($into, $into->region)->first(fn (SpotSummary $spot) => $spot->riverMetres >= $river->joins_at_metres);

        return $first === null ? null : $this->spotPlace(
            $first,
            "Downstream on the {$into->name}",
            max(0, $river->length_metres - $metres) + ($first->riverMetres - $river->joins_at_metres),
            alongRiver: true,
        );
    }

    /**
     * A river's positioned spots, source to mouth.
     *
     * @return Collection<int, SpotSummary>
     */
    private function spotsOn(River $river, Region $region): Collection
    {
        $summary = $this->summaryFor($region)->rivers->first(fn (RiverSummary $summary) => $summary->id === $river->id);

        return ($summary === null ? collect() : $summary->spots)
            ->filter(fn (SpotSummary $spot) => $spot->riverMetres !== null)
            ->sortBy('riverMetres')
            ->values();
    }

    /**
     * The nearest beaches and lakes within a radius, in any region, nearest first.
     *
     * @return Collection<int, NearbyPlace>
     */
    private function waterBodiesNear(float $latitude, float $longitude, int $radiusMetres, int $limit, ?Model $except = null): Collection
    {
        $beaches = $this->within(BathingWater::query()->beaches()->whereNotNull('region_id'), $latitude, $longitude, $radiusMetres)
            ->with('region')
            ->when($except instanceof BathingWater, fn (Builder $query) => $query->whereKeyNot($except->getKey()))
            ->get()
            ->map(fn (BathingWater $beach) => ['place' => $beach, 'summaries' => $this->summaryFor($beach->region)->beaches, 'relation' => 'Beach', 'route' => 'beaches.show']);

        $lakes = $this->within(Lake::query(), $latitude, $longitude, $radiusMetres)
            ->with('region')
            ->when($except instanceof Lake, fn (Builder $query) => $query->whereKeyNot($except->getKey()))
            ->get()
            ->map(fn (Lake $lake) => ['place' => $lake, 'summaries' => $this->summaryFor($lake->region)->lakes, 'relation' => 'Lake', 'route' => 'lakes.show']);

        return $beaches->concat($lakes)
            ->map(fn (array $candidate) => [...$candidate, 'distance' => (int) round(Coordinates::distanceMetres($latitude, $longitude, $candidate['place']->latitude, $candidate['place']->longitude))])
            ->filter(fn (array $candidate) => $candidate['distance'] <= $radiusMetres)
            ->sortBy('distance')
            ->map(function (array $candidate): ?NearbyPlace {
                /** @var WaterBodySummary|null $summary */
                $summary = $candidate['summaries']->firstWhere('slug', $candidate['place']->slug);

                return $summary === null ? null : new NearbyPlace(
                    relation: $candidate['relation'],
                    name: $summary->name,
                    url: route($candidate['route'], [$candidate['place']->region, $candidate['place']]),
                    distanceMetres: $candidate['distance'],
                    alongRiver: false,
                    spillRecency: $summary->nearbySpillRecency,
                    stale: $summary->overflowDataIsStale(),
                    nearbyWording: true,
                );
            })
            ->filter()
            ->take($limit)
            ->values();
    }

    /**
     * The nearest published spot on a main river within 20 km, in any region.
     *
     * @return Collection<int, NearbyPlace>
     */
    private function spotsNear(float $latitude, float $longitude): Collection
    {
        return $this->within(Spot::query()->published()->onMainRiver(), $latitude, $longitude, self::RIVER_SPOT_RADIUS_METRES)
            ->with(['river' => fn ($query) => $query->withoutCentreline()->with('region')])
            ->get()
            ->map(fn (Spot $spot) => ['spot' => $spot, 'distance' => (int) round(Coordinates::distanceMetres($latitude, $longitude, $spot->latitude, $spot->longitude))])
            ->filter(fn (array $candidate) => $candidate['distance'] <= self::RIVER_SPOT_RADIUS_METRES)
            ->sortBy('distance')
            ->map(function (array $candidate): ?NearbyPlace {
                $summary = $this->summaryFor($candidate['spot']->river->region)->spots()->firstWhere('id', $candidate['spot']->id);

                return $summary === null ? null : $this->spotPlace($summary, "On the {$summary->riverName}", $candidate['distance'], alongRiver: false);
            })
            ->filter()
            ->take(1)
            ->values();
    }

    private function spotPlace(SpotSummary $spot, string $relation, int $distanceMetres, bool $alongRiver): NearbyPlace
    {
        return new NearbyPlace(
            relation: $relation,
            name: $spot->name,
            url: route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]),
            distanceMetres: $distanceMetres,
            alongRiver: $alongRiver,
            spillRecency: $spot->spillRecency,
            stale: $spot->overflowDataIsStale(),
            nearbyWording: false,
        );
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function listingLinks(Region $region, string $listingText, string $listingUrl): array
    {
        $links = [[$listingText, $listingUrl]];

        if ($this->summaryFor($region)->rivers->isNotEmpty()) {
            $links[] = ['Rivers in '.$region->nameInSentence(), route('rivers.index', $region)];
        }

        return $links;
    }

    /**
     * Narrows a query to the box around a point, for the distance check that follows.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function within(Builder $query, float $latitude, float $longitude, int $radiusMetres): Builder
    {
        $latitudePadding = $radiusMetres / self::METRES_PER_DEGREE_LATITUDE;
        $longitudePadding = $radiusMetres / (self::METRES_PER_DEGREE_LATITUDE * cos(deg2rad($latitude)));

        return $query
            ->whereBetween('latitude', [$latitude - $latitudePadding, $latitude + $latitudePadding])
            ->whereBetween('longitude', [$longitude - $longitudePadding, $longitude + $longitudePadding]);
    }

    private function summaryFor(Region $region): RegionSummary
    {
        return $this->summaries[$region->id] ??= $this->regionSummaries->for($region);
    }
}
