<?php

namespace App\Services\Conditions;

use App\Data\FloodWarningSummary;
use App\Data\RegionSummary;
use App\Data\SpotSummary;
use App\Data\SwimmingSpot;
use App\Data\SwimmingSpotsNow;
use App\Data\WaterBodySummary;
use App\Enums\SpillRecency;
use Illuminate\Support\Collection;

/**
 * Places to swim with their live status, read from the cached region summaries, so it adds no queries: every river
 * spot, beach and lake for the swimming page (all()), and what is happening at popular ones for the home page
 * (popularNow()). Popular means a designated bathing water (sites where a large number of people bathe: every beach
 * and lake site, and river spots that are one) or a river spot the home page suggests as well known
 * (`SpotSeeder::FEATURED`).
 */
class SwimmingSpots
{
    public const SHOWN = 5;

    public const RANK_DISCHARGING = 0;

    public const RANK_POLLUTION_RISK = 1;

    public const RANK_FLOOD_WARNING = 2;

    public const RANK_RECENT_SPILL = 3;

    /**
     * Every river spot, beach and lake, by region (in the order given) and then by name.
     *
     * @param  Collection<int, RegionSummary>  $regions
     * @return Collection<int, SwimmingSpot>
     */
    public function all(Collection $regions): Collection
    {
        return $regions->flatMap(fn (RegionSummary $region) => $region->spots()
            ->map(fn (SpotSummary $spot) => $this->fromSpot($spot, $region))
            ->concat($region->beaches->map(fn (WaterBodySummary $beach) => $this->fromWaterBody($beach, $region, 'beach')))
            ->concat($region->lakes->map(fn (WaterBodySummary $lake) => $this->fromWaterBody($lake, $region, 'lake')))
            ->sortBy(fn (SwimmingSpot $spot) => mb_strtolower($spot->name))
            ->values())
            ->values();
    }

    /**
     * The popular spots where something is happening, most pressing first and spread across rivers, beaches and
     * lakes, then the well-known river spots as they are, up to $limit.
     *
     * @param  Collection<int, RegionSummary>  $regions
     * @param  Collection<int, SpotSummary>  $wellKnown  The river spots the home page suggests (HomeController)
     */
    public function popularNow(Collection $regions, Collection $wellKnown, int $limit = self::SHOWN): SwimmingSpotsNow
    {
        $wellKnownIds = $wellKnown->pluck('id')->flip();
        $spots = $regions->flatMap(fn (RegionSummary $region) => $region->spots()
            ->filter(fn (SpotSummary $spot) => $wellKnownIds->has($spot->id) || $spot->isBathingWater)
            ->map(fn (SpotSummary $spot) => ['spot' => $this->fromSpot($spot, $region), 'featured' => $wellKnownIds->has($spot->id)]));
        $waterBodies = $regions->flatMap(fn (RegionSummary $region) => $region->beaches
            ->map(fn (WaterBodySummary $beach) => ['spot' => $this->fromWaterBody($beach, $region, 'beach'), 'featured' => false])
            ->concat($region->lakes
                ->filter(fn (WaterBodySummary $lake) => $lake->bathingSiteCount > 0)
                ->map(fn (WaterBodySummary $lake) => ['spot' => $this->fromWaterBody($lake, $region, 'lake'), 'featured' => false])));
        $popular = $spots->concat($waterBodies);

        $happening = $popular
            ->filter(fn (array $candidate) => $candidate['spot']->isHappening())
            ->groupBy(fn (array $candidate) => $candidate['spot']->rank)
            ->sortKeys()
            ->flatMap(fn (Collection $candidates) => $this->interleaveKinds($candidates))
            ->take($limit);

        $quiet = $spots
            ->filter(fn (array $candidate) => $candidate['featured'] && ! $candidate['spot']->isHappening())
            ->pluck('spot')
            ->take($limit - $happening->count());

        return new SwimmingSpotsNow($happening->concat($quiet)->values(), $popular->count());
    }

    /**
     * Rivers, beaches and lakes in turn, so one kind never fills the list; well-known spots first within each.
     *
     * @param  Collection<int, array{spot: SwimmingSpot, featured: bool}>  $candidates
     * @return Collection<int, SwimmingSpot>
     */
    private function interleaveKinds(Collection $candidates): Collection
    {
        $byKind = $candidates
            ->sortBy([fn (array $a, array $b) => $b['featured'] <=> $a['featured'], fn (array $a, array $b) => strcmp($a['spot']->name, $b['spot']->name)])
            ->groupBy(fn (array $candidate) => $candidate['spot']->kind)
            ->map(fn (Collection $group) => $group->pluck('spot')->values())
            ->values();
        $interleaved = collect();

        for ($index = 0; $index < $byKind->max(fn (Collection $group) => $group->count()); $index++) {
            foreach ($byKind as $group) {
                if ($group->has($index)) {
                    $interleaved->push($group[$index]);
                }
            }
        }

        return $interleaved;
    }

    private function fromSpot(SpotSummary $spot, RegionSummary $region): SwimmingSpot
    {
        $stale = $spot->overflowDataIsStale();
        $floodWarning = $spot->floodWarnings->first(fn (FloodWarningSummary $warning) => ! $warning->isStale());

        return new SwimmingSpot(
            name: $spot->name,
            detail: $spot->riverName,
            url: route('spots.show', [$spot->regionSlug, $spot->riverSlug, $spot->slug]),
            latitude: $spot->latitude,
            longitude: $spot->longitude,
            regionSlug: (string) $region->slug,
            regionName: $region->name,
            kind: 'river',
            designated: $spot->isBathingWater,
            classification: null,
            spillRecency: $spot->spillRecency,
            stale: $stale,
            nearbyWording: false,
            note: $floodWarning?->severity->label(),
            rank: $this->rank($spot->spillRecency, $stale, false, $floodWarning !== null),
        );
    }

    private function fromWaterBody(WaterBodySummary $waterBody, RegionSummary $region, string $kind): SwimmingSpot
    {
        $stale = $waterBody->overflowDataIsStale();

        return new SwimmingSpot(
            name: $waterBody->name,
            detail: $kind === 'beach' ? 'Beach' : 'Lake',
            url: route($kind === 'beach' ? 'beaches.show' : 'lakes.show', [$region->slug, $waterBody->slug]),
            latitude: $waterBody->latitude,
            longitude: $waterBody->longitude,
            regionSlug: (string) $region->slug,
            regionName: $region->name,
            kind: $kind,
            designated: $waterBody->bathingSiteCount > 0,
            classification: $waterBody->classification,
            spillRecency: $waterBody->nearbySpillRecency,
            stale: $stale,
            nearbyWording: true,
            note: $waterBody->hasIncreasedRisk() ? 'Increased pollution risk forecast today' : null,
            rank: $this->rank($waterBody->nearbySpillRecency, $stale, $waterBody->hasIncreasedRisk(), false),
        );
    }

    /**
     * How pressing a spot's news is: an overflow discharging now, then a pollution risk forecast, a flood warning and
     * a spill in the last 24 hours. Out of date overflow data never counts as news either way.
     */
    private function rank(SpillRecency $recency, bool $stale, bool $increasedRisk, bool $floodWarning): ?int
    {
        return match (true) {
            ! $stale && $recency === SpillRecency::DischargingNow => self::RANK_DISCHARGING,
            $increasedRisk => self::RANK_POLLUTION_RISK,
            $floodWarning => self::RANK_FLOOD_WARNING,
            ! $stale && $recency === SpillRecency::Within24Hours => self::RANK_RECENT_SPILL,
            default => null,
        };
    }
}
