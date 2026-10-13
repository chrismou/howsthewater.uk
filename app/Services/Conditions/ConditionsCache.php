<?php

namespace App\Services\Conditions;

use App\Data\SpotConditions;
use App\Models\Spot;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches computed spot conditions between ingest runs. Every key carries a
 * version number, so flushing is a single increment that works on any
 * cache store; stale entries simply expire.
 */
class ConditionsCache
{
    private const TTL_MINUTES = 30;

    private const VERSION_KEY = 'conditions:version';

    /**
     * @param  Closure(): SpotConditions  $build
     */
    public function rememberSpot(Spot $spot, Closure $build): SpotConditions
    {
        return Cache::remember($this->keyFor($spot), now()->addMinutes(self::TTL_MINUTES), $build);
    }

    /**
     * Invalidates every cached spot at once by moving to a new version.
     */
    public function flush(): void
    {
        Cache::put(self::VERSION_KEY, $this->version() + 1);
    }

    private function keyFor(Spot $spot): string
    {
        return sprintf('conditions:v%d:spot:%d', $this->version(), $spot->id);
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
