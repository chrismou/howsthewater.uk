<?php

namespace App\Services\Conditions;

use App\Data\OverflowListing;
use App\Data\RegionSummary;
use App\Data\SpillsOverview;
use App\Data\SpotSummary;
use App\Enums\DischargingOrder;
use App\Enums\OverflowStatus;
use App\Enums\SpillRecency;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\SpillEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the spills lists behind the live bar's counts, for one region or all of them. "Discharging now" is the set
 * the live bar counts (overflows on the regions' rivers, RiverOverviewService) and the spots come from the cached
 * region summaries. The bar is rebuilt every 15 minutes by regions:refresh while these lists are read live, so the
 * two can differ for a few minutes after an ingest.
 */
class SpillsOverviewService
{
    /** Overflows listed per discharging list at first; a wet day across Britain has thousands. */
    public const DISCHARGING_SHOWN = 50;

    /** Recent spills listed at first; the overflow pages hold them all. */
    public const RECENT_SPILLS_SHOWN = 100;

    /** Rows each "show more" adds to a list. */
    public const MORE_ROWS = 200;

    public function __construct(private RegionSummaries $summaries, private RegionOverflows $overflows)
    {
    }

    /**
     * @param  Collection<int, Region>  $regions  Only those with overflow data count
     */
    public function for(Collection $regions, string $name, DischargingOrder $order = DischargingOrder::Newest): SpillsOverview
    {
        $covered = $this->covered($regions);
        [$onRivers, $nearWaterBodies, $allMonitors] = $this->monitorQueries($covered);
        $updatedAt = (clone $allMonitors)->max('source_updated_at');
        $recent = $this->recentSpillsQuery($allMonitors);

        return new SpillsOverview(
            name: $name,
            order: $order,
            dischargingNow: $this->discharging(clone $onRivers, $order, 0, self::DISCHARGING_SHOWN),
            dischargingNowTotal: (clone $onRivers)->where('status', OverflowStatus::Discharging)->count(),
            dischargingNearWaterBodies: $this->discharging(clone $nearWaterBodies, $order, 0, self::DISCHARGING_SHOWN),
            dischargingNearWaterBodiesTotal: (clone $nearWaterBodies)->where('status', OverflowStatus::Discharging)->count(),
            spotsWithSpill: $this->spotsWithSpill($covered),
            recentSpills: $this->recentSpills(clone $recent, 0, self::RECENT_SPILLS_SHOWN),
            recentSpillsTotal: $recent->count(),
            dataUpdatedAt: $updatedAt === null ? null : Carbon::parse($updatedAt, 'UTC'),
        );
    }

    /**
     * The next MORE_ROWS overflows discharging now after the first $offset, on the regions' rivers or, with
     * $nearWaterBodies, near their beaches and lakes, for the page's "show more" control.
     *
     * @param  Collection<int, Region>  $regions
     * @return Collection<int, OverflowListing>
     */
    public function moreDischarging(Collection $regions, bool $nearWaterBodies, DischargingOrder $order, int $offset): Collection
    {
        [$onRivers, $nearBeachesAndLakes] = $this->monitorQueries($this->covered($regions));

        return $this->discharging($nearWaterBodies ? $nearBeachesAndLakes : $onRivers, $order, $offset, self::MORE_ROWS);
    }

    /**
     * The next MORE_ROWS spills running or ended in the last 24 hours after the first $offset, most recent first.
     *
     * @param  Collection<int, Region>  $regions
     * @return Collection<int, SpillEvent>
     */
    public function moreRecentSpills(Collection $regions, int $offset): Collection
    {
        [, , $allMonitors] = $this->monitorQueries($this->covered($regions));

        return $this->recentSpills($this->recentSpillsQuery($allMonitors), $offset, self::MORE_ROWS);
    }

    /**
     * Every region's summary combined, for the home page's live bar and /spills.
     *
     * @param  Collection<int, Region>  $regions
     */
    public function combinedSummary(Collection $regions): RegionSummary
    {
        return RegionSummary::combine($regions->map(fn (Region $region) => $this->summaries->for($region)));
    }

    /**
     * The spots the live bar counts as having a spill upstream in the last 24 hours, discharging ones first.
     *
     * @param  Collection<int, Region>  $covered
     * @return Collection<int, SpotSummary>
     */
    private function spotsWithSpill(Collection $covered): Collection
    {
        return $covered
            ->flatMap(fn (Region $region) => $this->summaries->for($region)->spots())
            ->filter(fn (SpotSummary $spot) => in_array($spot->spillRecency, [SpillRecency::DischargingNow, SpillRecency::Within24Hours], true))
            ->sortBy([
                fn (SpotSummary $a, SpotSummary $b) => ($a->spillRecency !== SpillRecency::DischargingNow) <=> ($b->spillRecency !== SpillRecency::DischargingNow),
                fn (SpotSummary $a, SpotSummary $b) => strcmp($a->name, $b->name),
            ])
            ->values();
    }

    /**
     * @param  Collection<int, Region>  $regions
     * @return Collection<int, Region>
     */
    private function covered(Collection $regions): Collection
    {
        return $regions->filter(fn (Region $region) => $region->hasOverflowData())->values();
    }

    /**
     * Overflows on the regions' rivers (what the live bar counts), those near their beaches and lakes but on none of
     * those rivers, and both together.
     *
     * @param  Collection<int, Region>  $covered
     * @return array{0: Builder<OverflowMonitor>, 1: Builder<OverflowMonitor>, 2: Builder<OverflowMonitor>}
     */
    private function monitorQueries(Collection $covered): array
    {
        $riverIds = $this->overflows->riverIdsQuery($covered);
        $waterBodyMonitorIds = $this->overflows->waterBodyMonitorIdsQuery($covered);

        return [
            OverflowMonitor::query()->whereIn('river_id', $riverIds),
            OverflowMonitor::query()->whereIn('id', $waterBodyMonitorIds)
                ->where(fn ($query) => $query->whereNull('river_id')->orWhereNotIn('river_id', $riverIds)),
            OverflowMonitor::query()->where(fn ($query) => $query->whereIn('river_id', $riverIds)->orWhereIn('id', $waterBodyMonitorIds)),
        ];
    }

    /**
     * Spills from these overflows running or ended in the last 24 hours.
     *
     * @param  Builder<OverflowMonitor>  $monitors
     * @return Builder<SpillEvent>
     */
    private function recentSpillsQuery(Builder $monitors): Builder
    {
        return SpillEvent::query()
            ->whereIn('overflow_monitor_id', (clone $monitors)->select('id'))
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', now()->subHours(24)));
    }

    /**
     * $limit recent spills after the first $offset, most recent first, with each overflow and its river loaded.
     *
     * @param  Builder<SpillEvent>  $query
     * @return Collection<int, SpillEvent>
     */
    private function recentSpills(Builder $query, int $offset, int $limit): Collection
    {
        return $query
            ->with(['overflowMonitor.river' => fn ($rivers) => $rivers->withoutCentreline()])
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * $limit overflows discharging now after the first $offset, in the given order (any without a start time last),
     * with the places their discharge reaches.
     *
     * @param  Builder<OverflowMonitor>  $query
     * @return Collection<int, OverflowListing>
     */
    private function discharging(Builder $query, DischargingOrder $order, int $offset, int $limit): Collection
    {
        $monitors = $query
            ->where('status', OverflowStatus::Discharging)
            ->with(['river' => fn ($rivers) => $rivers->withoutCentreline()])
            ->orderByRaw('status_started_at is null')
            ->orderBy('status_started_at', $order === DischargingOrder::Newest ? 'desc' : 'asc')
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $this->overflows->loadPlaces($monitors);

        return $monitors->map(fn (OverflowMonitor $monitor) => new OverflowListing($monitor, $monitor->places->filter(fn ($link) => $link->place !== null)->values()))->values();
    }
}
