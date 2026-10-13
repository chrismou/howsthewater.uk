<?php

namespace App\Data;

use Illuminate\Support\Carbon;

final readonly class SpillHistoryDay
{
    public function __construct(
        public Carbon $date,
        public int $spillCount,
        public int $spillMinutes,
    ) {}
}
