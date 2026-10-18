<?php

namespace App\Data;

use Illuminate\Support\Carbon;

final readonly class SpillHistoryDay
{
    /**
     * @param  bool  $recorded  Whether we hold spills for any of the day, so a day before the record shows as no data
     */
    public function __construct(
        public Carbon $date,
        public int $spillCount,
        public int $spillMinutes,
        public bool $recorded = true,
    ) {
    }
}
