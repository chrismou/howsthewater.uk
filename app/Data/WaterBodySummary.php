<?php

namespace App\Data;

use App\Enums\BathingWaterClassification;
use App\Enums\PollutionRisk;
use App\Enums\SpillRecency;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * What a listing needs to say about a beach or lake, as plain values. Built
 * from its cached conditions and kept inside a RegionSummary, so the
 * Beaches and Lakes pages never unserialise full condition objects.
 */
final readonly class WaterBodySummary
{
    public function __construct(
        public string $name,
        public string $slug,
        public float $latitude,
        public float $longitude,
        public ?BathingWaterClassification $classification,
        public int $bathingSiteCount,
        public SpillRecency $nearbySpillRecency,
        public int $dischargingNearbyCount,
        public int $nearbyMonitorCount,
        public ?Carbon $overflowDataUpdatedAt,
        public ?Carbon $increasedRiskUntil = null,
    ) {
    }

    public static function fromBeach(BeachConditions $conditions): self
    {
        $beach = $conditions->bathingWater->bathingWater;

        return new self(
            name: $beach->name,
            slug: $beach->slug,
            latitude: $beach->latitude,
            longitude: $beach->longitude,
            classification: $beach->classification,
            bathingSiteCount: 1,
            nearbySpillRecency: $conditions->overflows->spillRecency,
            dischargingNearbyCount: $conditions->overflows->dischargingNowCount,
            nearbyMonitorCount: $conditions->overflows->monitors->count(),
            overflowDataUpdatedAt: $conditions->overflows->dataUpdatedAt,
            increasedRiskUntil: self::increasedRiskUntil(collect([$conditions->bathingWater])),
        );
    }

    public static function fromLake(LakeConditions $conditions): self
    {
        return new self(
            name: $conditions->lake->name,
            slug: $conditions->lake->slug,
            latitude: $conditions->lake->latitude,
            longitude: $conditions->lake->longitude,
            classification: null,
            bathingSiteCount: $conditions->bathingWaters->count(),
            nearbySpillRecency: $conditions->overflows->spillRecency,
            dischargingNearbyCount: $conditions->overflows->dischargingNowCount,
            nearbyMonitorCount: $conditions->overflows->monitors->count(),
            overflowDataUpdatedAt: $conditions->overflows->dataUpdatedAt,
            increasedRiskUntil: self::increasedRiskUntil($conditions->bathingWaters),
        );
    }

    /**
     * Whether an increased pollution risk forecast is in force at any of its bathing sites, evaluated at read time
     * so it ends when the forecast expires.
     */
    public function hasIncreasedRisk(): bool
    {
        return $this->increasedRiskUntil?->isFuture() === true;
    }

    /**
     * When the latest increased pollution risk forecast in force at these sites expires, or null for none.
     *
     * @param  Collection<int, BathingWaterSummary>  $sites
     */
    private static function increasedRiskUntil(Collection $sites): ?Carbon
    {
        return $sites
            ->filter(fn (BathingWaterSummary $site) => $site->currentRisk() === PollutionRisk::Increased)
            ->map(fn (BathingWaterSummary $site) => $site->bathingWater->risk_expires_at)
            ->filter()
            ->max();
    }

    /**
     * Mirrors NearbyOverflows::isStale(), evaluated at read time so cached summaries still go stale on schedule.
     */
    public function overflowDataIsStale(): bool
    {
        if ($this->nearbyMonitorCount === 0) {
            return false;
        }

        return $this->overflowDataUpdatedAt === null
            || $this->overflowDataUpdatedAt->lessThan(now()->subHours(SpotConditions::STALE_AFTER_HOURS));
    }

    /**
     * Whether an overflow within the nearby radius spilled in the last 48 hours, or is spilling now.
     */
    public function spilledNearbyRecently(): bool
    {
        return in_array($this->nearbySpillRecency, [SpillRecency::DischargingNow, SpillRecency::Within24Hours, SpillRecency::Within48Hours], true);
    }
}
