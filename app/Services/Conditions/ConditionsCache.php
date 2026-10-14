<?php

namespace App\Services\Conditions;

use App\Data\SpotConditions;
use App\Models\Spot;
use Closure;
use Illuminate\Support\Facades\Cache;
use ReflectionClass;

/**
 * Caches computed spot conditions between ingest runs. Every key carries a
 * version number, so flushing is a single increment that works on any
 * cache store; stale entries simply expire.
 *
 * Keys also carry a hash of the cached classes' source, so a deploy that
 * changes their shape never reads objects written by the previous release
 * (its scheduler keeps warming the cache until the symlink moves).
 */
class ConditionsCache
{
    private const TTL_MINUTES = 30;

    private const VERSION_KEY = 'conditions:version';

    private static ?string $codeVersion = null;

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

    /**
     * A short hash of every class that can end up in the cache, so entries
     * written by different code never collide.
     */
    public static function codeVersion(): string
    {
        if (self::$codeVersion !== null) {
            return self::$codeVersion;
        }

        $sources = array_map(
            fn (string $class) => md5_file((new ReflectionClass($class))->getFileName()) ?: $class,
            config('cache.serializable_classes', []),
        );

        return self::$codeVersion = substr(md5(implode('', $sources)), 0, 8);
    }

    private function keyFor(Spot $spot): string
    {
        return sprintf('conditions:v%d:%s:spot:%d', $this->version(), self::codeVersion(), $spot->id);
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
