<?php

namespace App\Data;

use App\Enums\SpillRecency;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final readonly class SpotConditions
{
    /**
     * @param  Collection<int, UpstreamMonitorSummary>  $upstreamMonitors  Nearest upstream first
     * @param  Collection<int, SpillEvent>  $recentSpillEvents  Most recent first, within the history window
     * @param  Collection<int, SpillHistoryDay>  $dailyHistory  Oldest day first
     */
    public function __construct(
        public Spot $spot,
        public SpillRecency $spillRecency,
        public Collection $upstreamMonitors,
        public int $dischargingNowCount,
        public int $spillsLast48HoursCount,
        public int $spillsLast30DaysCount,
        public Collection $recentSpillEvents,
        public Collection $dailyHistory,
        public ?LevelSummary $level,
        public ?Carbon $dataUpdatedAt,
    ) {}
}
