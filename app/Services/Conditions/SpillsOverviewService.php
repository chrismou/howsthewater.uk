<?php

namespace App\Services\Conditions;

use App\Data\OverflowListing;
use App\Data\RegionSummary;
use App\Data\SpillsOverview;
use App\Data\SpotSummary;
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
    /** Overflows listed per discharging list; a wet day across Britain has thousands. */
    public const DISCHARGING_SHOWN = 100;

    /** Recent spills listed; the overflow pages hold them all. */
    public const RECENT_SPILLS_SHOWN = 200;

    public function __construct(private RegionSummaries $summaries, private RegionOverflows $overflows)
    {
    }

    /**
     * @param  Collection<int, Region>  $regions  Only those with overflow data count
     */
    public function for(Collection $regions, string $name): SpillsOverview
    {
        $covered = $regions->filter(fn (Region $region) => $region->hasOverflowData())->values();
        $riverIds = $this->overflows->riverIdsQuery($covered);
        $waterBodyMonitorIds = $this->overflows->waterBodyMonitorIdsQuery($covered);

        $onRivers = OverflowMonitor::query()->whereIn('river_id', $riverIds);
        $nearWaterBodies = OverflowMonitor::query()->whereIn('id', $waterBodyMonitorIds)
            ->where(fn ($query) => $query->whereNull('river_id')->orWhereNotIn('river_id', $riverIds));
        $allMonitors = OverflowMonitor::query()->where(fn ($query) => $query->whereIn('river_id', $riverIds)->orWhereIn('id', $waterBodyMonitorIds));
        $allMonitorIds = (clone $allMonitors)->select('id');
        $updatedAt = (clone $allMonitors)->max('source_updated_at');

        $recent = SpillEvent::query()
            ->whereIn('overflow_monitor_id', $allMonitorIds)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', now()->subHours(24)));

        [$dischargingNow, $dischargingNowTotal] = $this->discharging($onRivers);
        [$dischargingNear, $dischargingNearTotal] = $this->discharging($nearWaterBodies);

        return new SpillsOverview(
            name: $name,
            dischargingNow: $dischargingNow,
            dischargingNowTotal: $dischargingNowTotal,
            dischargingNearWaterBodies: $dischargingNear,
            dischargingNearWaterBodiesTotal: $dischargingNearTotal,
            spotsWithSpill: $this->spotsWithSpill($covered),
            recentSpills: (clone $recent)
                ->with(['overflowMonitor.river' => fn ($query) => $query->withoutCentreline()])
                ->orderByDesc('started_at')
                ->limit(self::RECENT_SPILLS_SHOWN)
                ->get(),
            recentSpillsTotal: $recent->count(),
            dataUpdatedAt: $updatedAt === null ? null : Carbon::parse($updatedAt, 'UTC'),
        );
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
     * The first DISCHARGING_SHOWN overflows discharging now, longest running first (any without a start time last),
     * and how many there are in all.
     *
     * @param  Builder<OverflowMonitor>  $query
     * @return array{0: Collection<int, OverflowListing>, 1: int}
     */
    private function discharging(Builder $query): array
    {
        $query->where('status', OverflowStatus::Discharging);

        $monitors = (clone $query)
            ->with(['river' => fn ($rivers) => $rivers->withoutCentreline()])
            ->orderByRaw('status_started_at is null')
            ->orderBy('status_started_at')
            ->limit(self::DISCHARGING_SHOWN)
            ->get();

        $this->overflows->loadPlaces($monitors);

        return [
            $monitors->map(fn (OverflowMonitor $monitor) => new OverflowListing($monitor, $monitor->places->filter(fn ($link) => $link->place !== null)->values()))->values(),
            $query->count(),
        ];
    }
}
