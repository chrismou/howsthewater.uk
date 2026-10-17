<?php

namespace App\Services\ShareImages;

use App\Data\ShareCard;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use Illuminate\Support\Collection;

/**
 * What each kind of page's share card says. Cards name the place and what the page covers, never its live status,
 * and never mention spills where the region has no overflow data.
 */
class ShareCards
{
    /**
     * @param  Collection<int, Spot>  $spotsOnRiver  The river's published spots, this one included
     */
    public function forSpot(Spot $spot, River $river, Region $region, Collection $spotsOnRiver): ShareCard
    {
        return new ShareCard(
            eyebrow: 'River spot',
            title: $spot->name,
            subtitle: "{$river->name}, {$region->name}",
            tagline: $this->riverTagline($region),
            alt: "{$spot->name} on the {$river->name}, {$region->name}, marked on a map of ".($river->hasCentreline() ? 'the river from source to mouth' : 'Britain'),
            riverLine: $river->hasCentreline() ? $river->centreline : null,
            riverSpots: $this->positions($spotsOnRiver->reject(fn (Spot $other) => $other->is($spot))),
            pin: [$spot->latitude, $spot->longitude],
        );
    }

    /**
     * @param  Collection<int, Spot>  $spotsOnRiver  The river's published spots
     */
    public function forRiver(River $river, Region $region, Collection $spotsOnRiver): ShareCard
    {
        $count = $spotsOnRiver->count();
        $spots = $count === 1 ? '1 spot' : "{$count} spots";

        return new ShareCard(
            eyebrow: 'River',
            title: $river->name,
            subtitle: $count > 0 ? "{$region->name} - {$spots}" : $region->name,
            tagline: $this->riverTagline($region),
            alt: $river->hasCentreline()
                ? "The {$river->name}, {$region->name}, drawn from source to mouth".($count > 0 ? " with its {$spots} marked" : '')
                : "The mouth of the {$river->name}, {$region->name}, marked on a map of Britain",
            riverLine: $river->hasCentreline() ? $river->centreline : null,
            riverSpots: $this->positions($spotsOnRiver),
            // Without a centreline yet, the card pins the river's mouth on Britain instead.
            pin: ! $river->hasCentreline() && $river->mouth_latitude !== null && $river->mouth_longitude !== null
                ? [$river->mouth_latitude, $river->mouth_longitude]
                : null,
        );
    }

    public function forBeach(BathingWater $beach, Region $region): ShareCard
    {
        $where = $beach->district ?? $region->name;

        return new ShareCard(
            eyebrow: 'Beach',
            title: $beach->name,
            // Some districts start lower case ("the Vale of Glamorgan").
            subtitle: ucfirst($where),
            tagline: $this->bathingTagline($region),
            alt: "{$beach->name}, {$where}, marked on a map of Britain",
            pin: [$beach->latitude, $beach->longitude],
        );
    }

    public function forLake(Lake $lake, Region $region): ShareCard
    {
        return new ShareCard(
            eyebrow: 'Lake',
            title: $lake->name,
            subtitle: $region->name,
            tagline: $this->bathingTagline($region),
            alt: "{$lake->name}, {$region->name}, marked on a map of Britain",
            pin: [$lake->latitude, $lake->longitude],
        );
    }

    private function riverTagline(Region $region): string
    {
        return $region->hasOverflowData()
            ? 'Live overflow spills, levels, rainfall and weather'
            : 'Live river levels, rainfall and weather';
    }

    private function bathingTagline(Region $region): string
    {
        return $region->hasOverflowData()
            ? 'Live spills, bathing water results and weather'
            : 'Bathing water results, rainfall and weather';
    }

    /**
     * @param  Collection<int, Spot>  $spots
     * @return list<array{0: float, 1: float}>
     */
    private function positions(Collection $spots): array
    {
        return $spots->map(fn (Spot $spot) => [$spot->latitude, $spot->longitude])->values()->all();
    }
}
