<?php

namespace App\Actions\Ingest;

use App\Data\NearbyMonitorSummary;
use App\Data\NearbyOverflows;
use App\Data\SpotConditions;
use App\Data\UpstreamMonitorSummary;
use App\Enums\SpillRecency;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitorPlace;
use App\Models\Spot;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps overflow_monitor_places in step with what each place's page shows: the overflows upstream of a spot (distance
 * along the water) and those near a beach or lake (straight-line distance). Called by RefreshRegions with the
 * conditions it has just built, so the links never disagree with the pages. Places where we do not read the overflow
 * feed get no links. Only changed rows are written.
 */
class SyncOverflowMonitorPlaces
{
    /**
     * Whether an overflow was linked or unlinked (distances alone do not count).
     */
    public function forSpot(Spot $spot, SpotConditions $conditions): bool
    {
        $distances = $conditions->spillRecency === SpillRecency::NotCovered
            ? []
            : $conditions->upstreamMonitors->mapWithKeys(fn (UpstreamMonitorSummary $summary) => [$summary->monitor->id => $summary->distanceUpstreamMetres])->all();

        return $this->sync($spot, $distances);
    }

    public function forBeach(BathingWater $beach, NearbyOverflows $overflows): bool
    {
        return $this->sync($beach, $this->nearbyDistances($overflows));
    }

    public function forLake(Lake $lake, NearbyOverflows $overflows): bool
    {
        return $this->sync($lake, $this->nearbyDistances($overflows));
    }

    /**
     * Drops links to places that no longer have a page: unpublished spots, spots on tributaries, beaches outside any
     * region, and deleted places. Returns whether any went.
     */
    public function prune(): bool
    {
        $pruned = $this->pruneType(Spot::class, Spot::query()->published()->onMainRiver()->pluck('id')->all());
        $pruned += $this->pruneType(BathingWater::class, BathingWater::query()->beaches()->whereNotNull('region_id')->pluck('id')->all());
        $pruned += $this->pruneType(Lake::class, Lake::query()->pluck('id')->all());

        return $pruned > 0;
    }

    /**
     * @param  class-string<Model>  $type
     * @param  list<int>  $keep
     */
    private function pruneType(string $type, array $keep): int
    {
        return OverflowMonitorPlace::query()
            ->where('place_type', (new $type())->getMorphClass())
            ->whereNotIn('place_id', $keep === [] ? [0] : $keep)
            ->delete();
    }

    /**
     * @return array<int, int>
     */
    private function nearbyDistances(NearbyOverflows $overflows): array
    {
        return $overflows->isCovered()
            ? $overflows->monitors->mapWithKeys(fn (NearbyMonitorSummary $summary) => [$summary->monitor->id => $summary->distanceMetres])->all()
            : [];
    }

    /**
     * @param  array<int, int>  $distances  Distance in metres by monitor id
     * @return bool Whether an overflow was linked or unlinked
     */
    private function sync(Model $place, array $distances): bool
    {
        $existing = OverflowMonitorPlace::query()
            ->where('place_type', $place->getMorphClass())
            ->where('place_id', $place->getKey())
            ->pluck('distance_metres', 'overflow_monitor_id')
            ->all();

        $gone = array_keys(array_diff_key($existing, $distances));

        if ($gone !== []) {
            OverflowMonitorPlace::query()
                ->where('place_type', $place->getMorphClass())
                ->where('place_id', $place->getKey())
                ->whereIn('overflow_monitor_id', $gone)
                ->delete();
        }

        $changed = array_filter($distances, fn (int $distance, int $monitorId) => ($existing[$monitorId] ?? null) !== $distance, ARRAY_FILTER_USE_BOTH);

        if ($changed !== []) {
            OverflowMonitorPlace::query()->upsert(
                collect($changed)->map(fn (int $distance, int $monitorId) => [
                    'overflow_monitor_id' => $monitorId,
                    'place_type' => $place->getMorphClass(),
                    'place_id' => $place->getKey(),
                    'distance_metres' => max(0, $distance),
                ])->values()->all(),
                ['place_type', 'place_id', 'overflow_monitor_id'],
                ['distance_metres'],
            );
        }

        return $gone !== [] || array_diff_key($distances, $existing) !== [];
    }
}
