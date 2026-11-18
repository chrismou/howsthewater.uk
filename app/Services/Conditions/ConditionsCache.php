<?php

namespace App\Services\Conditions;

use App\Data\SpotConditions;
use App\Models\Spot;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ReflectionClass;
use Throwable;

/**
 * Caches computed spot, beach and lake conditions between ingest runs. The
 * scheduled regions:refresh writes over each entry, so visitors never wait
 * on a rebuild. Every key carries a version number, so flushing everything is
 * a single increment that works on any cache store; stale entries simply expire.
 *
 * Keys also carry a hash of the cached classes' source, so a deploy that
 * changes their shape never reads objects written by the previous release
 * (its scheduler keeps warming the cache until the symlink moves).
 *
 * Registered as a singleton, so the version is read once per request
 * rather than once per spot.
 *
 * A write that fails is logged and skipped rather than thrown, so one entry
 * the store refuses (Goole's outgrew its column) costs that page a rebuild
 * per visit, not the page itself or every region's refresh.
 */
class ConditionsCache
{
    private const TTL_MINUTES = 30;

    private const VERSION_KEY = 'conditions:version';

    /**
     * Entries past this size are logged as errors: a page takes about ten times an entry's size in memory to read
     * it, so one this big is a long way towards the request's memory limit.
     */
    public const LARGE_ENTRY_BYTES = 8 * 1024 * 1024;

    private static ?string $codeVersion = null;

    private ?int $version = null;

    /**
     * @param  Closure(): SpotConditions  $build
     */
    public function rememberSpot(Spot $spot, Closure $build): SpotConditions
    {
        return $this->rememberKey($this->keyFor($spot), $build);
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

            return tap($build($spot), fn (SpotConditions $built) => $this->store($this->keyFor($spot), $built));
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
        return $this->rememberKey($this->keyForName($name), $build);
    }

    /**
     * Overwrites a spot's cached conditions in place, so a refresh replaces
     * entries without visitors ever meeting an empty cache.
     */
    public function putSpot(Spot $spot, SpotConditions $conditions): void
    {
        $this->store($this->keyFor($spot), $conditions);
    }

    /**
     * Overwrites a named entry, as putSpot() does for spots.
     */
    public function put(string $name, mixed $value): void
    {
        $this->store($this->keyForName($name), $value);
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
                $this->store($keys[$name], $results[$name]);
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

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $build
     * @return TValue
     */
    private function rememberKey(string $key, Closure $build): mixed
    {
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $cached;
        }

        return tap($build(), fn (mixed $value) => $this->store($key, $value));
    }

    /**
     * Writes an entry, logging (never throwing) when the store refuses it, and
     * flagging one large enough to put the pages that read it at risk. Logged
     * without the exception, whose SQL carries the whole entry.
     */
    private function store(string $key, mixed $value): void
    {
        $bytes = strlen(serialize($value));

        if ($bytes > self::LARGE_ENTRY_BYTES) {
            Log::error(sprintf('Cached conditions %s are %s MB, large enough to risk the memory limit of pages that read them.', $key, number_format($bytes / 1048576, 1)));
        }

        try {
            Cache::put($key, $value, now()->addMinutes(self::TTL_MINUTES));
        } catch (Throwable $exception) {
            Log::error(sprintf('Could not cache conditions %s (%s MB): %s', $key, number_format($bytes / 1048576, 1), Str::limit($exception->getMessage(), 300)));
        }
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
