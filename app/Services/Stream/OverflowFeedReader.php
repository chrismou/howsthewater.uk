<?php

namespace App\Services\Stream;

use App\Data\OverflowMonitorData;
use Illuminate\Support\Collection;

/**
 * Reads every monitor from one water company's overflow feed into the shape
 * the sync stores, whatever fields the company publishes.
 */
interface OverflowFeedReader
{
    /**
     * @return Collection<int, OverflowMonitorData>
     */
    public function all(string $feedUrl): Collection;
}
