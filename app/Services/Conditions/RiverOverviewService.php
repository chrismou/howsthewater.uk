<?php

namespace App\Services\Conditions;

use App\Data\RiverOverview;
use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use Illuminate\Support\Collection;

/**
 * Summarises every published spot on a river, for listing pages and the API.
 */
class RiverOverviewService
{
    /** @var array<int, array<int, int>>|null Tributary ids keyed by parent river id */
    private ?array $childrenByParent = null;

    public function __construct(private SpotConditionsService $spotConditions) {}

    /**
     * Every river with a page, across all regions.
     *
     * @return Collection<int, RiverOverview>
     */
    public function all(): Collection
    {
        return River::query()->main()->with('region')->orderBy('name')->get()->map(fn (River $river) => $this->for($river));
    }

    /**
     * @return Collection<int, RiverOverview>
     */
    public function forRegion(Region $region): Collection
    {
        return $region->rivers()->main()->get()
            ->map(fn (River $river) => $this->for($river->setRelation('region', $region)));
    }

    /**
     * The river plus all its tributaries, recursively, from a single query
     * over every river's parent. Each id is visited once, so a bad parent
     * loop cannot recurse forever.
     *
     * @return array<int, int>
     */
    private function riverFamilyIds(River $river): array
    {
        $this->childrenByParent ??= River::query()
            ->whereNotNull('parent_river_id')
            ->get(['id', 'parent_river_id'])
            ->groupBy('parent_river_id')
            ->map(fn (Collection $children) => $children->pluck('id')->all())
            ->all();

        $ids = [];
        $queue = [$river->id];

        while ($queue !== []) {
            $id = array_shift($queue);

            if (in_array($id, $ids, true)) {
                continue;
            }

            $ids[] = $id;
            $queue = [...$queue, ...($this->childrenByParent[$id] ?? [])];
        }

        return $ids;
    }

    public function for(River $river): RiverOverview
    {
        $river->loadMissing('region');

        $spots = $river->spots()
            ->published()
            ->with(['gaugeStation', 'rainfallStation'])
            ->get()
            ->each(fn (Spot $spot) => $spot->setRelation('river', $river));

        $spots = $this->spotConditions->forMany($spots);

        return new RiverOverview(
            river: $river,
            spots: $spots,
            monitorCount: OverflowMonitor::query()->whereIn('river_id', $riverIds = $this->riverFamilyIds($river))->count(),
            dischargingNowCount: OverflowMonitor::query()->whereIn('river_id', $riverIds)->where('status', OverflowStatus::Discharging)->count(),
        );
    }
}
