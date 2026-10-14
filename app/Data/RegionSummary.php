<?php

namespace App\Data;

use App\Enums\SpillRecency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The precomputed picture of a region: every river and spot as plain
 * values plus the headline numbers, rebuilt after each ingest run and
 * cached, so the region and home pages read one small object.
 */
final readonly class RegionSummary
{
    /**
     * @param  Collection<int, RiverSummary>  $rivers  In seed order, which runs through the catchments
     */
    public function __construct(
        public string $slug,
        public string $name,
        public Collection $rivers,
        public int $monitorCount,
        public int $offlineMonitorCount,
        public int $gaugeCount,
        public int $rainGaugeCount,
        public ?Carbon $dataUpdatedAt,
        public Carbon $generatedAt,
    ) {}

    /**
     * @return Collection<int, SpotSummary>
     */
    public function spots(): Collection
    {
        return $this->rivers->flatMap(fn (RiverSummary $river) => $river->spots)->values();
    }

    /**
     * Rivers grouped by catchment, larger catchments first.
     *
     * @return Collection<string, Collection<int, RiverSummary>>
     */
    public function riversByCatchment(): Collection
    {
        return $this->rivers
            ->groupBy(fn (RiverSummary $river) => $river->catchment ?? $this->name)
            ->sortBy([
                fn (Collection $a, Collection $b) => $b->count() <=> $a->count(),
                fn (Collection $a, Collection $b) => $a->first()->id <=> $b->first()->id,
            ]);
    }

    public function dischargingNowCount(): int
    {
        return $this->rivers->sum(fn (RiverSummary $river) => $river->dischargingNowCount);
    }

    /**
     * @return Collection<int, SpotSummary>
     */
    public function spotsDischargingNow(): Collection
    {
        return $this->spots()->filter(fn (SpotSummary $spot) => $spot->spillRecency === SpillRecency::DischargingNow)->values();
    }

    public function spotsWithSpillLast24HoursCount(): int
    {
        return $this->spots()->filter(fn (SpotSummary $spot) => in_array($spot->spillRecency, [SpillRecency::DischargingNow, SpillRecency::Within24Hours], true))->count();
    }

    /**
     * One spot per river above its gauge's typical range, highest first.
     *
     * @return Collection<int, SpotSummary>
     */
    public function spotsAboveTypicalRange(): Collection
    {
        return $this->spots()
            ->filter(fn (SpotSummary $spot) => $spot->isAboveTypicalRange() && ! $spot->levelIsStale())
            ->unique('gaugeLabel')
            ->sortByDesc('levelValue')
            ->values();
    }

    /**
     * @return Collection<int, SpotSummary>
     */
    public function wettestSpots(int $limit = 3): Collection
    {
        return $this->spots()
            ->filter(fn (SpotSummary $spot) => ($spot->rainfallLast24HoursMillimetres ?? 0) > 0)
            ->sortByDesc('rainfallLast24HoursMillimetres')
            ->take($limit)
            ->values();
    }

    public function silentGaugeCount(): int
    {
        return $this->spots()->filter(fn (SpotSummary $spot) => $spot->levelIsStale())->unique('gaugeLabel')->count();
    }

    public function dataIsStale(): bool
    {
        return $this->dataUpdatedAt === null
            || $this->dataUpdatedAt->lessThan(now()->subHours(SpotConditions::STALE_AFTER_HOURS));
    }
}
