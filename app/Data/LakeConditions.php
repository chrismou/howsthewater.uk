<?php

namespace App\Data;

use App\Models\Lake;
use Illuminate\Support\Collection;

/**
 * Everything a lake page shows: its bathing water sites, level and the
 * storm overflows near it.
 */
final readonly class LakeConditions
{
    /**
     * @param  Collection<int, BathingWaterSummary>  $bathingWaters
     */
    public function __construct(
        public Lake $lake,
        public Collection $bathingWaters,
        public ?LevelSummary $level,
        public NearbyOverflows $overflows,
    ) {
    }
}
