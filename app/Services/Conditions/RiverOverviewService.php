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
     * The river plus all its tributaries, recursively.
     *
     * @return array<int, int>
     */
    private function riverFamilyIds(River $river): array
    {
        $ids = [$river->id];

        foreach ($river->tributaries as $tributary) {
            $ids = [...$ids, ...$this->riverFamilyIds($tributary)];
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
            ->map(fn (Spot $spot) => $this->spotConditions->for($spot->setRelation('river', $river)));

        return new RiverOverview(
            river: $river,
            spots: $spots,
            monitorCount: OverflowMonitor::query()->whereIn('river_id', $riverIds = $this->riverFamilyIds($river))->count(),
            dischargingNowCount: OverflowMonitor::query()->whereIn('river_id', $riverIds)->where('status', OverflowStatus::Discharging)->count(),
        );
    }
}
