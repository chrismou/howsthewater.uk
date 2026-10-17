<?php

namespace App\Data;

use App\Models\OverflowMonitor;
use App\Models\OverflowMonitorPlace;
use Illuminate\Support\Collection;

/**
 * An overflow in a spills list: the monitor (with its river loaded, if it is on one) and the places whose pages show
 * it, nearest first.
 */
final readonly class OverflowListing
{
    /**
     * @param  Collection<int, OverflowMonitorPlace>  $places  With each place loaded
     */
    public function __construct(
        public OverflowMonitor $monitor,
        public Collection $places,
    ) {
    }
}
