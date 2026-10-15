<?php

namespace App\Services\Conditions;

use App\Models\Region;
use Illuminate\Support\Facades\Cache;

/**
 * Records which regions' data an ingest or positioning run changed, so one
 * scheduled refresh afterwards rebuilds just those regions, instead of every
 * command rebuilding every region as soon as it finishes.
 */
class ChangedRegions
{
    private const KEY = 'regions:changed';

    /**
     * Marks every region changed, e.g. after polling feeds that cover them all.
     */
    public function markAll(): void
    {
        $this->store(['all' => true, 'ids' => []]);
    }

    /**
     * @param  iterable<int, int>  $regionIds
     */
    public function mark(iterable $regionIds): void
    {
        $pending = $this->pending();
        $pending['ids'] = array_values(array_unique([...$pending['ids'], ...$regionIds]));

        $this->store($pending);
    }

    /**
     * The regions marked since the last refresh, clearing the marks.
     *
     * @return list<int>
     */
    public function take(): array
    {
        $pending = $this->pending();
        Cache::forget(self::KEY);

        if ($pending['all']) {
            return Region::query()->pluck('id')->all();
        }

        return $pending['ids'];
    }

    /**
     * @return array{all: bool, ids: list<int>}
     */
    private function pending(): array
    {
        $pending = Cache::get(self::KEY);

        return is_array($pending) ? ['all' => (bool) ($pending['all'] ?? false), 'ids' => array_values(array_map('intval', $pending['ids'] ?? []))] : ['all' => false, 'ids' => []];
    }

    /**
     * @param  array{all: bool, ids: list<int>}  $pending
     */
    private function store(array $pending): void
    {
        Cache::forever(self::KEY, $pending);
    }
}
