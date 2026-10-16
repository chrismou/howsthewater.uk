<?php

namespace App\Data;

use App\Enums\BathingWaterClassification;
use App\Enums\SpillRecency;

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
        );
    }

    /**
     * Whether an overflow within the nearby radius spilled in the last 48 hours, or is spilling now.
     */
    public function spilledNearbyRecently(): bool
    {
        return in_array($this->nearbySpillRecency, [SpillRecency::DischargingNow, SpillRecency::Within24Hours, SpillRecency::Within48Hours], true);
    }
}
