<?php

namespace App\Services\Conditions;

use App\Data\FloodWarningEventSummary;
use App\Data\RiverOverview;
use App\Data\SpotConditions;
use App\Enums\OverflowStatus;
use App\Models\FloodArea;
use App\Models\FloodWarningEvent;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Summarises every published spot on a river, for listing pages and the API.
 */
class RiverOverviewService
{
    /** @var array<int, array<int, int>>|null Tributary ids keyed by parent river id */
    private ?array $childrenByParent = null;

    public function __construct(private SpotConditionsService $spotConditions, private SpillHistoryService $spillHistory, private DailyRainfall $rainfall)
    {
    }

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
        return $region->rivers()->main()->reorder('id')->get()
            ->map(fn (River $river) => $this->for($river->setRelation('region', $region)));
    }

    /**
     * The river plus all its tributaries, recursively, from a single query
     * over every river's parent. Each id is visited once, so a bad parent
     * loop cannot recurse forever.
     *
     * @return array<int, int>
     */
    public function riverFamilyIds(River $river): array
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
        $riverIds = $this->riverFamilyIds($river);
        // Where we do not read the overflow feed, monitors can still be positioned on the rivers, as every
        // company is recorded ahead of its region; they count for nothing until the region's feed is switched on.
        $countedRiverIds = $river->region->hasOverflowData() ? $riverIds : [];
        $monitorsByStatus = OverflowMonitor::query()->whereIn('river_id', $countedRiverIds)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $companies = OverflowMonitor::query()->whereIn('river_id', $countedRiverIds)->distinct()->orderBy('company')->pluck('company')
            ->map(fn (string $company) => preg_replace(['/\s+(Ltd|Limited|plc)\.?$/i', '/\s+Services$/i'], '', $company))->unique()->values()->all();
        // Counted as spot pages count them: spills still running or that ended within the last 48 hours.
        $spillsLast48Hours = SpillEvent::query()
            ->whereHas('overflowMonitor', fn ($query) => $query->whereIn('river_id', $countedRiverIds))
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', now()->subHours(48)))
            ->count();
        $rainSpot = $this->rainGaugeSpot($spots);

        return new RiverOverview(
            river: $river,
            spots: $spots,
            monitorCount: (int) $monitorsByStatus->sum(),
            dischargingNowCount: (int) ($monitorsByStatus[OverflowStatus::Discharging->value] ?? 0),
            offlineCount: (int) ($monitorsByStatus[OverflowStatus::Offline->value] ?? 0),
            hasTributaries: count($riverIds) > 1,
            spillsLast48HoursCount: $spillsLast48Hours,
            companies: $companies,
            dailyHistory: $this->dailyHistory($countedRiverIds),
            dailyRainfall: $rainSpot === null ? null : $this->rainfall->forStation($rainSpot->spot->rainfallStation, now()),
            rainGaugeLabel: $rainSpot?->spot->rainfallStation->nameInSentence(),
            floodAreaAgencies: $this->floodAreaAgencies($spots),
            lastFloodWarning: $this->lastFloodWarning($spots),
            monitorsUpdatedAt: $this->monitorsUpdatedAt($countedRiverIds),
        );
    }

    /**
     * When any monitor on these rivers last reported, so a river with monitors but none upstream of a spot still
     * has a data time.
     *
     * @param  array<int, int>  $riverIds
     */
    private function monitorsUpdatedAt(array $riverIds): ?Carbon
    {
        $latest = $riverIds === [] ? null : OverflowMonitor::query()->whereIn('river_id', $riverIds)->max('source_updated_at');

        return $latest === null ? null : Carbon::parse($latest);
    }

    /**
     * Spills per UK day over the history window for every overflow on these rivers, overlapping spills counted once,
     * with the days before the earliest overflow's record marked as no data.
     *
     * @param  array<int, int>  $riverIds
     * @return Collection<int, \App\Data\SpillHistoryDay>
     */
    private function dailyHistory(array $riverIds): Collection
    {
        if ($riverIds === []) {
            return collect();
        }

        $monitors = OverflowMonitor::query()->whereIn('river_id', $riverIds)->get(['id', 'company', 'created_at']);
        $events = $this->spillHistory->counted($this->spillHistory->recentEventsForMonitors($monitors->pluck('id')));

        return $this->spillHistory->dailyBreakdown($events, recordedFrom: $this->spillHistory->recordedFrom($monitors->toBase()));
    }

    /**
     * The spot whose rain gauge stands for the river: the one with a gauge nearest the river's midpoint.
     *
     * @param  Collection<int, SpotConditions>  $spots  Source to mouth
     */
    private function rainGaugeSpot(Collection $spots): ?SpotConditions
    {
        $withGauge = $spots->filter(fn (SpotConditions $conditions) => $conditions->spot->rainfallStation !== null)->values();

        return $withGauge->get(intdiv(max(0, $withGauge->count() - 1), 2));
    }

    /**
     * The agencies whose flood areas cover the river's spots, so a page knows whose feed an absence of warnings rests on.
     *
     * @param  Collection<int, SpotConditions>  $spots
     * @return list<\App\Enums\Agency>
     */
    private function floodAreaAgencies(Collection $spots): array
    {
        $spotIds = $spots->map(fn (SpotConditions $conditions) => $conditions->spot->id);

        return FloodArea::query()
            ->whereHas('spots', fn ($query) => $query->whereIn('spots.id', $spotIds))
            ->distinct()
            ->pluck('agency')
            ->sortBy(fn ($agency) => $agency->value)
            ->values()
            ->all();
    }

    /**
     * The flood alert or warning most recently lifted for an area covering one of the river's spots.
     *
     * @param  Collection<int, SpotConditions>  $spots
     */
    private function lastFloodWarning(Collection $spots): ?FloodWarningEventSummary
    {
        $spotIds = $spots->map(fn (SpotConditions $conditions) => $conditions->spot->id);

        $event = FloodWarningEvent::query()
            ->whereNotNull('ended_at')
            ->whereHas('floodArea.spots', fn ($query) => $query->whereIn('spots.id', $spotIds))
            ->with('floodArea')
            ->orderByDesc('ended_at')
            ->first();

        return $event === null ? null : FloodWarningEventSummary::fromEvent($event);
    }
}
