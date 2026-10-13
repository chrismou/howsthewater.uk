<?php

namespace App\Data;

use App\Enums\LevelTrend;
use App\Models\GaugeStation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class LevelSummary
{
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
}
