<?php

namespace App\Services\Conditions;

use App\Data\UpstreamMonitorSummary;
use App\Models\OverflowMonitor;
use App\Models\River;
use App\Models\Spot;
use Illuminate\Support\Collection;

/**
 * Finds every storm overflow monitor whose discharge flows past a spot:
 * monitors higher up the same river, plus everything on tributaries and
 * other main rivers that join above the spot, recursively. Distances
 * follow the water.
 */
class UpstreamMonitorFinder
{
    /**
     * @return Collection<int, UpstreamMonitorSummary> Nearest upstream first
     */
    public function for(Spot $spot): Collection
    {
        if ($spot->river_metres === null) {
            return collect();
        }

        $spot->loadMissing('river');

        return $this->collect($spot->river, $spot->river_metres, 0)
            ->sortBy(fn (UpstreamMonitorSummary $summary) => $summary->distanceUpstreamMetres)
            ->values();
    }

    /**
     * @param  int  $belowMetres  Position on this river that everything must be upstream of
     * @param  int  $offsetMetres  Flow distance from this river's reference point down to the spot
     * @return Collection<int, UpstreamMonitorSummary>
     */
    private function collect(River $river, int $belowMetres, int $offsetMetres): Collection
    {
        $monitors = OverflowMonitor::query()
            ->where('river_id', $river->id)
            ->whereNotNull('river_metres')
            ->where('river_metres', '<=', $belowMetres)
            ->with(['spillEvents' => fn ($query) => $query->limit(1)])
            ->get()
            ->map(fn (OverflowMonitor $monitor) => new UpstreamMonitorSummary(
                monitor: $monitor->setRelation('river', $river),
                river: $river,
                distanceUpstreamMetres: $offsetMetres + max(0, $belowMetres - $monitor->river_metres),
                status: $monitor->status,
                latestEvent: $monitor->spillEvents->first(),
            ));

        $inflows = $river->inflowingRivers()
            ->withoutCentreline()
            ->whereNotNull('joins_at_metres')
            ->whereNotNull('length_metres')
            ->where('joins_at_metres', '<=', $belowMetres)
            ->get();

        foreach ($inflows as $inflow) {
            $monitors = $monitors->concat($this->collect(
                $inflow,
                $inflow->length_metres,
                $offsetMetres + ($belowMetres - $inflow->joins_at_metres),
            ));
        }

        return $monitors;
    }
}
