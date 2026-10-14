<?php

namespace App\Data;

use App\Enums\LevelTrend;
use App\Enums\SpillRecency;
use Illuminate\Support\Carbon;

/**
 * Everything a listing needs to say about a spot, as plain values. Built
 * from SpotConditions and cached inside a RegionSummary, so region and home
 * pages never unserialise full condition objects.
 */
final readonly class SpotSummary
{
    public function __construct(
        public int $id,
        public string $name,
        public string $slug,
        public string $riverSlug,
        public string $riverName,
        public string $regionSlug,
        public float $latitude,
        public float $longitude,
        public ?int $riverMetres,
        public bool $featured,
        public SpillRecency $spillRecency,
        public int $monitorCount,
        public int $dischargingNowCount,
        public int $offlineCount,
        public int $spillsLast48HoursCount,
        public int $spillsLast30DaysCount,
        public ?Carbon $dataUpdatedAt,
        public ?float $levelValue,
        public ?string $levelUnit,
        public LevelTrend $levelTrend,
        public ?string $levelRelativeToTypical,
        public ?Carbon $levelAt,
        public ?string $gaugeLabel,
        public ?float $rainfallLast24HoursMillimetres,
        public ?Carbon $rainfallAt,
    ) {
    }

    public static function fromConditions(SpotConditions $conditions): self
    {
        $spot = $conditions->spot;
        $level = $conditions->level;

        return new self(
            id: $spot->id,
            name: $spot->name,
            slug: $spot->slug,
            riverSlug: $spot->river->slug,
            riverName: $spot->river->name,
            regionSlug: $spot->river->region->slug,
            latitude: $spot->latitude,
            longitude: $spot->longitude,
            riverMetres: $spot->river_metres,
            featured: $spot->is_featured,
            spillRecency: $conditions->spillRecency,
            monitorCount: $conditions->upstreamMonitors->count(),
            dischargingNowCount: $conditions->dischargingNowCount,
            offlineCount: $conditions->offlineCount,
            spillsLast48HoursCount: $conditions->spillsLast48HoursCount,
            spillsLast30DaysCount: $conditions->spillsLast30DaysCount,
            dataUpdatedAt: $conditions->dataUpdatedAt,
            levelValue: $level?->latestValue,
            levelUnit: $level?->station->unitLabel(),
            levelTrend: $level === null ? LevelTrend::Unknown : $level->trend,
            levelRelativeToTypical: $level?->relativeToTypical,
            levelAt: $level?->latestAt,
            gaugeLabel: $level?->station->label,
            rainfallLast24HoursMillimetres: $conditions->rainfall?->hasReadings() ? $conditions->rainfall->last24HoursMillimetres : null,
            rainfallAt: $conditions->rainfall?->latestAt,
        );
    }

    /**
     * Mirrors SpotConditions::overflowDataIsStale(), evaluated at read time.
     */
    public function overflowDataIsStale(): bool
    {
        if ($this->monitorCount === 0) {
            return false;
        }

        return $this->dataUpdatedAt === null
            || $this->dataUpdatedAt->lessThan(now()->subHours(SpotConditions::STALE_AFTER_HOURS));
    }

    public function levelIsStale(): bool
    {
        return $this->levelAt !== null
            && $this->levelAt->lessThan(now()->subHours(LevelSummary::STALE_AFTER_HOURS));
    }

    public function isAboveTypicalRange(): bool
    {
        return $this->levelRelativeToTypical === 'above typical range';
    }
}
