<?php

namespace App\Data;

/**
 * Everything a beach page shows: the EA bathing water record and the storm
 * overflows near it.
 */
final readonly class BeachConditions
{
    public function __construct(
        public BathingWaterSummary $bathingWater,
        public NearbyOverflows $overflows,
    ) {
    }
}
