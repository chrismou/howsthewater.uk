<?php

namespace App\Services\Conditions;

use App\Data\SpotConditions;
use App\Models\Spot;
use Closure;
use Illuminate\Support\Collection;
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
 *
 * Registered as a singleton, so the version is read once per request
 * rather than once per spot.
 */
class ConditionsCache
{
    private const TTL_MINUTES = 30;

    private const VERSION_KEY = 'conditions:version';

    private static ?string $codeVersion = null;

    private ?int $version = null;

    /**
     * @param  Closure(): SpotConditions  $build
     */
    public function rememberSpot(Spot $spot, Closure $build): SpotConditions
    {
        return Cache::remember($this->keyFor($spot), now()->addMinutes(self::TTL_MINUTES), $build);
    }

    /**
     * Like rememberSpot for a whole list, fetching every cached entry in one
     * round trip and building only the misses.
     *
     * @param  Collection<int, Spot>  $spots
     * @param  Closure(Spot): SpotConditions  $build
     * @return Collection<int, SpotConditions> In the same order as the spots
     */
    public function rememberSpots(Collection $spots, Closure $build): Collection
    {
        if ($spots->isEmpty()) {
            return collect();
        }

        $cached = Cache::many($spots->map(fn (Spot $spot) => $this->keyFor($spot))->all());

        return $spots->map(function (Spot $spot) use ($cached, $build) {
            $conditions = $cached[$this->keyFor($spot)] ?? null;

            if ($conditions instanceof SpotConditions) {
                return $conditions;
            }

            return tap($build($spot), fn (SpotConditions $built) => Cache::put($this->keyFor($spot), $built, now()->addMinutes(self::TTL_MINUTES)));
        })->values();
    }

    /**
     * Caches any conditions object under the same version and code hash as
     * spots, so ingest flushes and deploys invalidate it the same way.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $build
     * @return TValue
     */
    public function remember(string $name, Closure $build): mixed
    {
        return Cache::remember($this->keyForName($name), now()->addMinutes(self::TTL_MINUTES), $build);
    }

    /**
     * Like remember() for several entries, fetching every cached one in a
     * single round trip and building only the misses.
     *
     * @template TValue
     *
     * @param  array<string, Closure(): TValue>  $builders  Keyed by name
     * @return array<string, TValue> In the same order as the builders
     */
    public function rememberMany(array $builders): array
    {
        if ($builders === []) {
            return [];
        }

        $keys = array_map(fn (string $name) => $this->keyForName($name), array_combine(array_keys($builders), array_keys($builders)));
        $cached = Cache::many(array_values($keys));
        $results = [];

        foreach ($builders as $name => $build) {
            $results[$name] = $cached[$keys[$name]] ?? null;

            if ($results[$name] === null) {
                $results[$name] = $build();
                Cache::put($keys[$name], $results[$name], now()->addMinutes(self::TTL_MINUTES));
            }
        }

        return $results;
    }

    /**
     * Invalidates every cached spot at once by moving to a new version.
     */
    public function flush(): void
    {
        $this->version = $this->version() + 1;

        Cache::put(self::VERSION_KEY, $this->version);
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

    private function keyForName(string $name): string
    {
        return sprintf('conditions:v%d:%s:%s', $this->version(), self::codeVersion(), $name);
    }

    private function keyFor(Spot $spot): string
    {
        return sprintf('conditions:v%d:%s:spot:%d', $this->version(), self::codeVersion(), $spot->id);
    }

    private function version(): int
    {
        return $this->version ??= (int) Cache::get(self::VERSION_KEY, 1);
    }
}
