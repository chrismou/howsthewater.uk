<?php

namespace App\Data;

use App\Enums\SpillRecency;
use App\Models\Spot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class SpotConditions
{
    /**
     * @param  Collection<int, UpstreamMonitorSummary>  $upstreamMonitors  Nearest upstream first
     */
    public function __construct(
        public Spot $spot,
        public SpillRecency $spillRecency,
        public Collection $upstreamMonitors,
        public int $dischargingNowCount,
        public int $spillsLast48HoursCount,
        public int $spillsLast30DaysCount,
        public ?LevelSummary $level,
        public ?Carbon $dataUpdatedAt,
    ) {}
}
