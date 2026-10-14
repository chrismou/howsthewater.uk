<?php

namespace App\Data;

use App\Models\RainfallStation;
use Illuminate\Support\Carbon;

/**
 * Rainfall totals at the rain gauge nearest a spot. Totals are sums of the
 * gauge's 15 minute readings, so a gap in readings under-counts rather
 * than estimates.
 */
final readonly class RainfallSummary
{
    /**
     * A latest reading older than this is flagged as stale.
     */
    public const STALE_AFTER_HOURS = 6;

    public function __construct(
        public RainfallStation $station,
        public int $distanceMetres,
        public ?Carbon $latestAt,
        public float $last6HoursMillimetres,
        public float $last24HoursMillimetres,
        public float $last72HoursMillimetres,
    ) {
    }

    public function hasReadings(): bool
    {
        return $this->latestAt !== null;
    }

    /**
     * Whether the latest reading is too old to rely on. Evaluated at read
     * time, so a cached result still goes stale on schedule.
     */
    public function isStale(): bool
    {
        return $this->latestAt !== null
            && $this->latestAt->lessThan(now()->subHours(self::STALE_AFTER_HOURS));
    }
}
