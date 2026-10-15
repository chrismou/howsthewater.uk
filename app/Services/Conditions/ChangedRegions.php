<?php

namespace App\Services\Conditions;

use App\Models\Region;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Records which regions' data an ingest or positioning run changed, so one
 * scheduled refresh afterwards rebuilds just those regions, instead of every
 * command rebuilding every region as soon as it finishes. Marks are read and
 * written under a lock, as an ingest can mark while a refresh takes them.
 */
class ChangedRegions
{
    private const KEY = 'regions:changed';

    private const LOCK_SECONDS = 10;

    /**
     * Marks every region changed, e.g. after polling feeds that cover them all.
     * The scheduled regions:refresh then rebuilds what visitors read, once,
     * after every ingest in that pass has run.
     */
    public function markAll(): void
    {
        $this->locked(fn () => $this->store(['all' => true, 'ids' => []]));
    }

    /**
     * @param  iterable<int, int>  $regionIds
     */
    public function mark(iterable $regionIds): void
    {
        $this->locked(function () use ($regionIds) {
            $pending = $this->pending();
            $pending['ids'] = array_values(array_unique([...$pending['ids'], ...$regionIds]));

            $this->store($pending);
        });
    }

    /**
     * The regions marked since the last refresh, clearing the marks. A
     * refresh that fails should mark them again so the next one retries.
     *
     * @return list<int>
     */
    public function take(): array
    {
        $pending = $this->locked(function () {
            $pending = $this->pending();
            Cache::forget(self::KEY);

            return $pending;
        });

        if ($pending['all']) {
            return Region::query()->pluck('id')->all();
        }

        return $pending['ids'];
    }

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    private function locked(Closure $callback): mixed
    {
        return Cache::lock(self::KEY.':lock', self::LOCK_SECONDS)->block(self::LOCK_SECONDS, $callback);
    }

    /**
     * @return array{all: bool, ids: list<int>}
     */
    private function pending(): array
    {
        $pending = Cache::get(self::KEY);

        if (! is_array($pending)) {
            return ['all' => false, 'ids' => []];
        }

        return ['all' => (bool) ($pending['all'] ?? false), 'ids' => array_values(array_map('intval', $pending['ids'] ?? []))];
    }

    /**
     * @param  array{all: bool, ids: list<int>}  $pending
     */
    private function store(array $pending): void
    {
        Cache::forever(self::KEY, $pending);
    }
}
