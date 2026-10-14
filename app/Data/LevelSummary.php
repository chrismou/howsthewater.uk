<?php

namespace App\Data;

use App\Enums\LevelTrend;
use App\Models\GaugeStation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class LevelSummary
{
    /**
     * A latest reading older than this is flagged as stale and its trend is
     * unknown. Environment Agency gauges report every 15 minutes and usually
     * publish within a couple of hours.
     */
    public const STALE_AFTER_HOURS = 6;

    /**
     * @param  Collection<int, array{measured_at: Carbon, value: float}>  $recentReadings  Oldest first, for charting
     */
    public function __construct(
        public GaugeStation $station,
        public ?float $latestValue,
        public ?Carbon $latestAt,
        public LevelTrend $trend,
        public ?float $changeLast3Hours,
        public ?string $relativeToTypical,
        public Collection $recentReadings,
    ) {}

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
