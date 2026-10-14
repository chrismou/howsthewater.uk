<?php

namespace App\Data;

use App\Models\River;
use Illuminate\Support\Collection;

final readonly class RiverOverview
{
    /**
     * Monitor counts cover the river and all its tributaries.
     *
     * @param  Collection<int, SpotConditions>  $spots  Source to mouth
     */
    public function __construct(
        public River $river,
        public Collection $spots,
        public int $monitorCount,
        public int $dischargingNowCount,
        public int $offlineCount,
        public bool $hasTributaries,
    ) {
    }
}
