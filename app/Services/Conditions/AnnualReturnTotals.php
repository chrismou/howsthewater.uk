<?php

namespace App\Services\Conditions;

use App\Data\AreaReturnTotal;
use App\Data\RiverReturnRank;
use App\Models\OverflowAnnualReturn;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Adds up the companies' annual returns by river and by region, for comparing them. A river counts its own overflows
 * and its tributaries' (as river pages do), not those of main rivers that flow into it, so no overflow counts twice; a
 * region counts every overflow its pages show, on its rivers and near its beaches and lakes, each once. Only returns
 * with hours count as a return. Regions whose overflow feed we do not read are left out.
 *
 * Figures are cached as plain values for a day under a generation number the annual returns import bumps
 * (GENERATION_KEY), so a new import shows at once and a release never reads another's objects.
 */
class AnnualReturnTotals
{
    public const GENERATION_KEY = 'annual-return-totals:generation';

    private const CACHE_HOURS = 24;

    public function __construct(private RiverOverviewService $overviews, private RegionOverflows $overflows)
    {
    }

    /**
     * The latest year any return is stored for.
     */
    public function latestYear(): ?int
    {
        $year = OverflowAnnualReturn::query()->max('year');

        return $year === null ? null : (int) $year;
    }

    /**
     * Each main river in the region for the year, most hours first, then those with no return.
     *
     * @return Collection<int, AreaReturnTotal>
     */
    public function riversIn(Region $region, int $year): Collection
    {
        if (! $region->hasOverflowData()) {
            return collect();
        }

        return $this->ranked(collect($this->cached("rivers:{$region->id}:{$year}", fn () => $this->riverFigures($region, $year)))
            ->map(fn (array $values) => AreaReturnTotal::fromArray($values)));
    }

    /**
     * Each region we read overflow data for, for the year, most hours first.
     *
     * @param  Collection<int, Region>  $regions
     * @return Collection<int, AreaReturnTotal>
     */
    public function regions(Collection $regions, int $year): Collection
    {
        return $this->ranked($regions
            ->filter(fn (Region $region) => $region->hasOverflowData())
            ->map(fn (Region $region) => AreaReturnTotal::fromArray($this->cached("region:{$region->id}:{$year}", fn () => $this->regionFigures($region, $year))))
            ->values());
    }

    /**
     * A main river's totals for each year it has returns, newest first, keyed by year.
     *
     * @return Collection<int, AreaReturnTotal>
     */
    public function riverByYear(River $river): Collection
    {
        if (! $river->region->hasOverflowData()) {
            return collect();
        }

        return collect($this->cached("river:{$river->id}", function () use ($river) {
            $riverIds = $this->overviews->riverFamilyIds($river);
            $overflows = OverflowMonitor::query()->whereIn('river_id', $riverIds)->count();

            return $this->aggregate(OverflowMonitor::query()->whereIn('river_id', $riverIds), 'overflow_annual_returns.year')
                ->sortKeysDesc()
                ->map(fn (array $sums) => [$river->name, $river->region->slug, $river->id, $river->slug, $overflows, ...$sums])
                ->all();
        }))->mapWithKeys(fn (array $values, int|string $year) => [(int) $year => AreaReturnTotal::fromArray($values)]);
    }

    /**
     * Where the river stood among the region's rivers with returns in the year, by hours; null when fewer than two
     * rivers have returns or this one has none.
     */
    public function riverRank(River $river, int $year): ?RiverReturnRank
    {
        $withReturns = $this->riversIn($river->region, $year)->filter(fn (AreaReturnTotal $total) => $total->hasReturns())->values();
        $own = $withReturns->first(fn (AreaReturnTotal $total) => $total->riverId === $river->id);

        if ($own === null || $withReturns->count() < 2) {
            return null;
        }

        return new RiverReturnRank(
            year: $year,
            position: $withReturns->filter(fn (AreaReturnTotal $total) => $total->hours > $own->hours)->count() + 1,
            of: $withReturns->count(),
            joint: $withReturns->filter(fn (AreaReturnTotal $total) => $total->hours === $own->hours)->count() > 1,
        );
    }

    /**
     * Whether main rivers flow into this one, whose overflows count on their own pages rather than in its totals.
     */
    public function hasInflowingMainRivers(River $river): bool
    {
        return River::query()->where('flows_into_river_id', $river->id)->exists();
    }

    /**
     * Every main river's figures, from one grouped query over the region's rivers, rolled up by family.
     *
     * @return list<array{0: string, 1: string, 2: int, 3: string, 4: int, 5: int, 6: int, 7: float}>
     */
    private function riverFigures(Region $region, int $year): array
    {
        $riverIds = $region->rivers()->pluck('id')->all();
        $overflowsByRiver = OverflowMonitor::query()->whereIn('river_id', $riverIds)->toBase()->selectRaw('river_id, count(*) as total')->groupBy('river_id')->pluck('total', 'river_id');
        $sumsByRiver = $this->aggregate(OverflowMonitor::query()->whereIn('river_id', $riverIds), 'overflow_monitors.river_id', $year);

        return $region->rivers()->main()->withoutCentreline()->orderBy('name')->get()
            ->map(function (River $river) use ($region, $overflowsByRiver, $sumsByRiver) {
                $family = $this->overviews->riverFamilyIds($river);
                $sums = collect($family)->map(fn (int $id) => $sumsByRiver->get($id, [0, 0, 0.0]));

                return [
                    $river->name, $region->slug, $river->id, $river->slug,
                    (int) collect($family)->sum(fn (int $id) => (int) $overflowsByRiver->get($id, 0)),
                    $sums->sum(fn (array $values) => $values[0]),
                    $sums->sum(fn (array $values) => $values[1]),
                    round($sums->sum(fn (array $values) => $values[2]), 1),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The region's figures over every overflow its pages show, each once.
     *
     * @return array{0: string, 1: string, 2: null, 3: null, 4: int, 5: int, 6: int, 7: float}
     */
    private function regionFigures(Region $region, int $year): array
    {
        $regions = collect([$region]);
        $monitors = fn () => OverflowMonitor::query()->where(fn ($query) => $query
            ->whereIn('river_id', $this->overflows->riverIdsQuery($regions))
            ->orWhereIn('overflow_monitors.id', $this->overflows->waterBodyMonitorIdsQuery($regions)));
        $sums = $this->aggregate($monitors(), null, $year)->first() ?? [0, 0, 0.0];

        return [$region->name, $region->slug, null, null, $monitors()->count(), ...$sums];
    }

    /**
     * Returns with hours for the given overflows, counted and summed, grouped by a column (or not at all).
     *
     * @param  Builder<OverflowMonitor>  $monitors
     * @return Collection<int|string, array{0: int, 1: int, 2: float}> Reporting overflows, counted spills, hours
     */
    private function aggregate(Builder $monitors, ?string $groupBy, ?int $year = null): Collection
    {
        $query = OverflowAnnualReturn::query()
            ->join('overflow_monitors', 'overflow_monitors.id', '=', 'overflow_annual_returns.overflow_monitor_id')
            ->whereIn('overflow_monitors.id', $monitors->select('overflow_monitors.id'))
            ->whereNotNull('overflow_annual_returns.total_hours')
            ->when($year !== null, fn ($query) => $query->where('overflow_annual_returns.year', $year))
            ->toBase()
            ->selectRaw(($groupBy !== null ? "{$groupBy} as grouping, " : '').'count(*) as reporting, coalesce(sum(overflow_annual_returns.counted_spills), 0) as spills, coalesce(sum(overflow_annual_returns.total_hours), 0) as hours');

        if ($groupBy !== null) {
            $query->groupBy($groupBy);
        }

        return $query->get()->mapWithKeys(fn (object $row) => [
            ($row->grouping ?? 0) => [(int) $row->reporting, (int) $row->spills, round((float) $row->hours, 1)],
        ]);
    }

    /**
     * @param  Closure(): array<mixed>  $build
     * @return array<mixed>
     */
    private function cached(string $key, Closure $build): array
    {
        $generation = (int) Cache::get(self::GENERATION_KEY, 0);

        return Cache::remember("annual-return-totals:{$generation}:{$key}", now()->addHours(self::CACHE_HOURS), $build);
    }

    /**
     * Most hours first, then those with no return.
     *
     * @param  Collection<int, AreaReturnTotal>  $totals
     * @return Collection<int, AreaReturnTotal>
     */
    private function ranked(Collection $totals): Collection
    {
        return $totals->sortBy([
            fn (AreaReturnTotal $a, AreaReturnTotal $b) => $b->hasReturns() <=> $a->hasReturns(),
            fn (AreaReturnTotal $a, AreaReturnTotal $b) => $b->hours <=> $a->hours,
        ])->values();
    }
}
