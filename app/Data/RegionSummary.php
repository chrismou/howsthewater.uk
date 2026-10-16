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
     * @param  string|null  $slug  Null for the combined summary of every region
     * @param  Collection<int, RiverSummary>  $rivers  In seed order, which runs through the catchments
     * @param  Collection<int, WaterBodySummary>  $beaches  By name
     * @param  Collection<int, WaterBodySummary>  $lakes  By name
     */
    public function __construct(
        public ?string $slug,
        public string $name,
        public Collection $rivers,
        public int $monitorCount,
        public int $offlineMonitorCount,
        public int $gaugeCount,
        public int $rainGaugeCount,
        public ?Carbon $dataUpdatedAt,
        public Carbon $generatedAt,
        public Collection $beaches = new Collection(),
        public Collection $lakes = new Collection(),
    ) {
    }

    /**
     * Every region's rivers and counts in one summary, for pages that cover
     * the whole site rather than one region.
     *
     * @param  Collection<int, RegionSummary>  $summaries
     */
    public static function combine(Collection $summaries, string $name = 'All regions'): self
    {
        return new self(
            slug: null,
            name: $name,
            rivers: $summaries->flatMap(fn (RegionSummary $summary) => $summary->rivers)->values(),
            monitorCount: $summaries->sum('monitorCount'),
            offlineMonitorCount: $summaries->sum('offlineMonitorCount'),
            gaugeCount: $summaries->sum('gaugeCount'),
            rainGaugeCount: $summaries->sum('rainGaugeCount'),
            dataUpdatedAt: $summaries->max('dataUpdatedAt'),
            generatedAt: $summaries->min('generatedAt') ?? now(),
            beaches: $summaries->flatMap(fn (RegionSummary $summary) => $summary->beaches)->values(),
            lakes: $summaries->flatMap(fn (RegionSummary $summary) => $summary->lakes)->values(),
        );
    }

    public function isCombined(): bool
    {
        return $this->slug === null;
    }

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

    /**
     * Each flood alert and warning in force across the region, most severe
     * first, with the spots it covers.
     *
     * @return Collection<int, array{warning: FloodWarningSummary, spots: Collection<int, SpotSummary>}>
     */
    public function floodWarnings(): Collection
    {
        $spots = $this->spots();

        return $spots
            ->flatMap(fn (SpotSummary $spot) => $spot->floodWarnings)
            ->unique('areaCode')
            ->sortBy(fn (FloodWarningSummary $warning) => $warning->severity->value)
            ->map(fn (FloodWarningSummary $warning) => [
                'warning' => $warning,
                'spots' => $spots->filter(fn (SpotSummary $spot) => $spot->floodWarnings->contains('areaCode', $warning->areaCode))->values(),
            ])
            ->values();
    }

    /**
     * The median 24 hour rainfall across spots with a rain gauge, so one
     * downpour does not stand for the whole region.
     */
    public function medianRainfallLast24HoursMillimetres(): ?float
    {
        $rain = $this->spots()
            ->map(fn (SpotSummary $spot) => $spot->rainfallLast24HoursMillimetres)
            ->filter(fn (?float $millimetres) => $millimetres !== null)
            ->sort()
            ->values();

        return $rain->isEmpty() ? null : $rain[intdiv($rain->count(), 2)];
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
