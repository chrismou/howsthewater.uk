<?php

namespace App\Services\Conditions;

use App\Data\RiverOverview;
use App\Enums\OverflowStatus;
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
     * @return Collection<int, RiverOverview>
     */
    public function all(): Collection
    {
        return River::query()->orderBy('name')->get()->map(fn (River $river) => $this->for($river));
    }

    public function for(River $river): RiverOverview
    {
        $spots = $river->spots()
            ->published()
            ->with('gaugeStation')
            ->get()
            ->map(fn (Spot $spot) => $this->spotConditions->for($spot->setRelation('river', $river)));

        return new RiverOverview(
            river: $river,
            spots: $spots,
            monitorCount: $river->overflowMonitors()->count(),
            dischargingNowCount: $river->overflowMonitors()->where('status', OverflowStatus::Discharging)->count(),
        );
    }
}
