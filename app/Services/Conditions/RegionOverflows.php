<?php

namespace App\Services\Conditions;

use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\OverflowMonitorPlace;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

/**
 * Which overflows belong to which regions' pages: those on a region's rivers (tributaries included, as river pages
 * count them) and those near its beaches and lakes (overflow_monitor_places). Regions without overflow data have
 * none. An overflow has a page of its own when some region shows it.
 */
class RegionOverflows
{
    /**
     * The ids of the regions' rivers, tributaries included, as a subquery.
     *
     * @param  Collection<int, Region>  $regions
     * @return Builder<River>
     */
    public function riverIdsQuery(Collection $regions): Builder
    {
        return River::query()->whereIn('region_id', $this->covered($regions)->modelKeys())->select('id');
    }

    /**
     * The ids of the overflows linked to the regions' beaches and lakes, as a subquery.
     *
     * @param  Collection<int, Region>  $regions
     * @return Builder<OverflowMonitorPlace>
     */
    public function waterBodyMonitorIdsQuery(Collection $regions): Builder
    {
        $regionIds = $this->covered($regions)->modelKeys();

        return OverflowMonitorPlace::query()
            ->where(fn ($query) => $query
                ->where(fn ($beaches) => $beaches->where('place_type', (new BathingWater())->getMorphClass())
                    ->whereIn('place_id', BathingWater::query()->beaches()->whereIn('region_id', $regionIds)->select('id')))
                ->orWhere(fn ($lakes) => $lakes->where('place_type', (new Lake())->getMorphClass())
                    ->whereIn('place_id', Lake::query()->whereIn('region_id', $regionIds)->select('id'))))
            ->select('overflow_monitor_id');
    }

    public function hasPage(OverflowMonitor $monitor): bool
    {
        return $this->regionFor($monitor) !== null;
    }

    /**
     * The region whose pages show the overflow: its river's, else the region of the nearest place linked to it.
     */
    public function regionFor(OverflowMonitor $monitor): ?Region
    {
        $monitor->loadMissing(['river' => fn ($query) => $query->withoutCentreline(), 'river.region']);

        if ($monitor->river?->region?->hasOverflowData()) {
            return $monitor->river->region;
        }

        // The nearest place that still exists, should one have been deleted since the last refresh.
        $place = $this->placesFor($monitor)->first()?->place;

        return match (true) {
            $place instanceof Spot => $place->river->region,
            $place instanceof BathingWater, $place instanceof Lake => $place->region,
            default => null,
        };
    }

    /**
     * The places whose pages show the overflow, nearest first, each with what its link needs loaded.
     *
     * @return Collection<int, OverflowMonitorPlace>
     */
    public function placesFor(OverflowMonitor $monitor): Collection
    {
        return $this->placesQuery($monitor)->get()->filter(fn (OverflowMonitorPlace $link) => $link->place !== null)->values();
    }

    /**
     * Eager loads each monitor's linked places for a list.
     *
     * @param  Collection<int, OverflowMonitor>  $monitors
     */
    public function loadPlaces(Collection $monitors): void
    {
        (new EloquentCollection($monitors->all()))->load(['places' => fn ($query) => $query->orderBy('distance_metres')->with(['place' => fn (MorphTo $morph) => $this->morphWith($morph)])]);
    }

    /**
     * @return Builder<OverflowMonitorPlace>
     */
    private function placesQuery(OverflowMonitor $monitor): Builder
    {
        return $monitor->places()->getQuery()
            ->orderBy('distance_metres')
            ->with(['place' => fn (MorphTo $morph) => $this->morphWith($morph)]);
    }

    /**
     * @param  MorphTo<Model, OverflowMonitorPlace>  $morph
     * @return MorphTo<Model, OverflowMonitorPlace>
     */
    private function morphWith(MorphTo $morph): MorphTo
    {
        return $morph->morphWith([
            Spot::class => ['river' => fn ($query) => $query->withoutCentreline(), 'river.region'],
            BathingWater::class => ['region'],
            Lake::class => ['region'],
        ]);
    }

    /**
     * @param  Collection<int, Region>  $regions
     * @return EloquentCollection<int, Region>
     */
    private function covered(Collection $regions): EloquentCollection
    {
        return new EloquentCollection($regions->filter(fn (Region $region) => $region->hasOverflowData())->values()->all());
    }
}
