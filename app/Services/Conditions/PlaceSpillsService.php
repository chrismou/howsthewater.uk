<?php

namespace App\Services\Conditions;

use App\Data\PlaceSpills;
use App\Data\SpillHistoryDay;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\OverflowMonitorPlace;
use App\Models\RainfallStation;
use App\Models\Region;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Builds the spill history of the overflows a spot, beach or lake page shows (overflow_monitor_places, rebuilt by
 * regions:refresh): from their live feeds since we started reading them and, before that, from their companies'
 * published logs (SpillCoverage), counting only spills that start inside that time. Hours count time when at least
 * one overflow was spilling
 * (SpillIntervals), so many spilling together (hundreds sit upstream of Goole) never add up to more hours than passed.
 * Only spills inside the windows shown are read, as plain timestamps, and the figures are cached for CACHE_MINUTES per
 * place, as they only change with the 15 minute ingests.
 */
class PlaceSpillsService
{
    public const SPILLS_PER_PAGE = 50;

    private const BUSIEST_SHOWN = 10;

    private const MONTHS_SHOWN = 12;

    private const CACHE_MINUTES = 10;

    /** Bump whenever the cached figures change shape, so a deploy never reads the old release's. */
    private const CACHE_VERSION = 3;

    public function __construct(private DailyRainfall $rainfall, private SpillCoverage $coverage)
    {
    }

    /**
     * Null where the place's region has no overflow data, so its page has nothing to show.
     */
    public function for(Spot|BathingWater|Lake $place, Region $region): ?PlaceSpills
    {
        if (! $region->hasOverflowData()) {
            return null;
        }

        $figures = Cache::remember(
            "place-spills:".self::CACHE_VERSION.":{$place->getMorphClass()}:{$place->getKey()}",
            now()->addMinutes(self::CACHE_MINUTES),
            fn () => $this->figures($place),
        );
        $timezone = $this->timezone();
        $at = fn (?int $timestamp) => $timestamp === null ? null : Carbon::createFromTimestamp($timestamp, 'UTC');
        $busiestMonitors = OverflowMonitor::query()
            ->whereKey(array_column($figures['busiest'], 0))
            ->with(['river' => fn ($query) => $query->withoutCentreline()])
            ->get()
            ->keyBy('id');

        return new PlaceSpills(
            place: $place,
            region: $region,
            distances: $figures['distances'],
            coveredFrom: $at($figures['coveredFrom']),
            dataUpdatedAt: $at($figures['dataUpdatedAt']),
            record: $figures['record'],
            yearFrom: $at($figures['yearFrom']),
            daysWithSpillLast30: count(array_filter($figures['daily'], fn (array $day) => $day[1] > 0)),
            dailyHistory: collect($figures['daily'])->map(fn (array $day) => new SpillHistoryDay(Carbon::createFromTimestamp($day[0], $timezone), $day[1], $day[2], $day[3])),
            rainfallStation: $figures['rainfallStationId'] === null ? null : RainfallStation::query()->find($figures['rainfallStationId']),
            dailyRainfall: $figures['rainfall'],
            months: array_map(fn (array $month) => ['month' => Carbon::createFromTimestamp($month[0], $timezone), 'spills' => $month[1], 'hours' => $month[2], 'recorded' => $month[3]], $figures['months']),
            notes: $figures['notes'],
            busiest: array_values(array_filter(array_map(fn (array $row) => $busiestMonitors->has($row[0]) ? ['monitor' => $busiestMonitors->get($row[0]), 'spills' => $row[1], 'hours' => $row[2]] : null, $figures['busiest']))),
        );
    }

    /**
     * Every spill from the place's overflows, newest first, a page at a time, with each overflow and its river.
     * Null when the page asked for is past the last.
     *
     * @return LengthAwarePaginator<int, SpillEvent>|null
     */
    public function spillLog(Spot|BathingWater|Lake $place, string $path): ?LengthAwarePaginator
    {
        $spills = SpillEvent::query()
            ->whereIn('overflow_monitor_id', $this->links($place)->select('overflow_monitor_id'))
            ->with(['overflowMonitor.river' => fn ($query) => $query->withoutCentreline()])
            ->orderByDesc('started_at')
            ->paginate(self::SPILLS_PER_PAGE)
            ->withPath($path)
            ->fragment('spills');

        return $spills->currentPage() > max(1, $spills->lastPage()) ? null : $spills;
    }

    /**
     * The figures behind the page as plain values, so they cache cheaply.
     *
     * @return array{distances: array<int, int>, coveredFrom: int|null, dataUpdatedAt: int|null, record: array{7: array{spills: int, hours: float}, 30: array{spills: int, hours: float}, year: array{spills: int, hours: float}}, yearFrom: int, daily: list<array{0: int, 1: int, 2: int, 3: bool}>, months: list<array{0: int, 1: int, 2: float, 3: bool}>, notes: list<string>, busiest: list<array{0: int, 1: int, 2: float}>, rainfallStationId: int|null, rainfall: array<string, float|null>|null}
     */
    private function figures(Spot|BathingWater|Lake $place): array
    {
        $now = now();
        $timezone = $this->timezone();
        $distances = array_map('intval', $this->links($place)->pluck('distance_metres', 'overflow_monitor_id')->all());
        $monitors = OverflowMonitor::query()->whereKey(array_keys($distances))->get(['id', 'company', 'created_at', 'source_updated_at']);
        $nowTs = $now->getTimestamp();
        // The time each overflow's spills are held for: its company's published log, then its live feed.
        $coverage = $monitors->mapWithKeys(fn (OverflowMonitor $monitor) => [$monitor->id => $this->coverage->intervals($monitor->company, ($monitor->created_at ?? $now)->getTimestamp())])->all();
        $coveredFrom = $coverage === [] ? null : Carbon::createFromTimestamp(min(array_map(fn (array $intervals) => $intervals[0][0], $coverage)), 'UTC');
        $days = SpillIntervals::dayBoundaries($now, SpillHistoryService::HISTORY_DAYS);
        $yearFrom = $now->copy()->timezone($timezone)->startOfYear()->utc()->max($coveredFrom ?? $now);
        $monthsFrom = $now->copy()->timezone($timezone)->startOfMonth()->subMonths(self::MONTHS_SHOWN - 1)
            ->max(($coveredFrom ?? $now)->copy()->timezone($timezone)->startOfMonth());
        // A spill the feed showed when we first saw an overflow, from before any published log, stays in the spill
        // log but not in the figures.
        $spills = $this->spans($place, $monitors, $coverage, $now, min($days[0], $yearFrom->getTimestamp(), $monthsFrom->getTimestamp()));
        $station = $place->rainfall_station_id === null ? null : RainfallStation::query()->find($place->rainfall_station_id);

        $daily = [];

        for ($day = 0; $day < SpillHistoryService::HISTORY_DAYS; $day++) {
            [$count, $seconds] = $this->overlap($spills, $days[$day], $days[$day + 1]);
            // A day before every overflow's record starts shows as no data, never as a day without spills.
            $daily[] = [$days[$day], $count, (int) round($seconds / 60), $coveredFrom === null || $days[$day + 1] > $coveredFrom->getTimestamp()];
        }

        $months = [];

        for ($month = $monthsFrom->copy(); $month->getTimestamp() <= $nowTs; $month = $month->copy()->addMonth()) {
            $monthUntil = min($month->copy()->addMonth()->getTimestamp(), $nowTs);
            $window = $this->window($spills, $month->getTimestamp(), $monthUntil);
            $recorded = collect($coverage)->contains(fn (array $intervals) => SpillCoverage::overlaps($intervals, $month->getTimestamp(), $monthUntil));
            $months[] = [$month->getTimestamp(), $window['spills'], $window['hours'], $recorded];
        }

        return [
            'distances' => $distances,
            'coveredFrom' => $coveredFrom?->getTimestamp(),
            'dataUpdatedAt' => $monitors->max('source_updated_at')?->getTimestamp(),
            'record' => [
                7 => $this->window($spills, $days[SpillHistoryService::HISTORY_DAYS - 7], $nowTs),
                30 => $this->window($spills, $days[0], $nowTs),
                'year' => $this->window($spills, $yearFrom->getTimestamp(), $nowTs),
            ],
            'yearFrom' => $yearFrom->getTimestamp(),
            'daily' => $daily,
            'months' => $months,
            'notes' => $this->coverage->notes(
                $monitors->groupBy('company')->map(fn ($group) => (int) $group->min(fn (OverflowMonitor $monitor) => ($monitor->created_at ?? $now)->getTimestamp()))->all(),
                $monthsFrom->getTimestamp(),
                $nowTs,
            ),
            'busiest' => $this->busiest($spills, $yearFrom->getTimestamp(), $nowTs),
            'rainfallStationId' => $station?->id,
            'rainfall' => $station === null ? null : $this->rainfall->forStation($station, $now),
        ];
    }

    /**
     * @return Builder<OverflowMonitorPlace>
     */
    private function links(Spot|BathingWater|Lake $place): Builder
    {
        return OverflowMonitorPlace::query()->where('place_type', $place->getMorphClass())->where('place_id', $place->getKey());
    }

    /**
     * The spills that reach into [from, now] and count for their overflow's coverage, as plain timestamps in
     * parallel lists sorted by start (a year of a few hundred overflows is a hundred thousand or more, so no array or
     * object per spill), plus the stretches when at least one was spilling. An open spill ends now, or at its
     * monitor's last report if that is old.
     *
     * @param  EloquentCollection<int, OverflowMonitor>  $monitors
     * @param  array<int, list<array{0: int, 1: int}>>  $coverage  By monitor id
     * @return array{monitors: list<int>, starts: list<int>, ends: list<int>, spilling: list<array{0: int, 1: int}>}
     */
    private function spans(Spot|BathingWater|Lake $place, EloquentCollection $monitors, array $coverage, Carbon $now, int $from): array
    {
        $openUntil = $monitors->mapWithKeys(fn (OverflowMonitor $monitor) => [$monitor->id => SpillIntervals::openUntil($monitor, $now)->getTimestamp()])->all();
        $monitorIds = [];
        $starts = [];
        $ends = [];

        // A hundred overflows at a time, so each read uses the overflow and start index and no result is too big.
        foreach (array_chunk($monitors->modelKeys(), 100) as $group) {
            $rows = SpillEvent::query()
                ->whereIn('overflow_monitor_id', $group)
                ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', Carbon::createFromTimestamp($from, 'UTC')))
                ->toBase()
                ->get(['overflow_monitor_id', 'started_at', 'ended_at']);

            foreach ($rows as $row) {
                $monitorId = (int) $row->overflow_monitor_id;
                $start = (int) strtotime($row->started_at.' UTC');
                $end = $row->ended_at === null ? max($start, $openUntil[$monitorId] ?? $now->getTimestamp()) : (int) strtotime($row->ended_at.' UTC');

                if (! SpillCoverage::counts($coverage[$monitorId] ?? [], $start, $end)) {
                    continue;
                }

                $monitorIds[] = $monitorId;
                $starts[] = $start;
                $ends[] = $end;
            }
        }

        array_multisort($starts, $ends, $monitorIds);

        $spilling = [];

        foreach ($starts as $index => $start) {
            $last = count($spilling) - 1;

            if ($last >= 0 && $start <= $spilling[$last][1]) {
                $spilling[$last][1] = max($spilling[$last][1], $ends[$index]);
            } else {
                $spilling[] = [$start, $ends[$index]];
            }
        }

        return ['monitors' => $monitorIds, 'starts' => $starts, 'ends' => $ends, 'spilling' => $spilling];
    }

    /**
     * Spills overlapping [from, until) and the hours when at least one was spilling.
     *
     * @param  array{monitors: list<int>, starts: list<int>, ends: list<int>, spilling: list<array{0: int, 1: int}>}  $spills
     * @return array{spills: int, hours: float}
     */
    private function window(array $spills, int $from, int $until): array
    {
        [$count, $seconds] = $this->overlap($spills, $from, $until);

        return ['spills' => $count, 'hours' => round($seconds / 3600, 1)];
    }

    /**
     * How many spills overlap [from, until) and the seconds when at least one was spilling.
     *
     * @param  array{monitors: list<int>, starts: list<int>, ends: list<int>, spilling: list<array{0: int, 1: int}>}  $spills
     * @return array{0: int, 1: int}
     */
    private function overlap(array $spills, int $from, int $until): array
    {
        $count = 0;

        // Sorted by start, so nothing after the first that starts at or after $until can overlap.
        foreach ($spills['starts'] as $index => $start) {
            if ($start >= $until) {
                break;
            }

            if ($spills['ends'][$index] > $from) {
                $count++;
            }
        }

        $seconds = 0;

        foreach ($spills['spilling'] as [$start, $end]) {
            $seconds += max(0, min($end, $until) - max($start, $from));
        }

        return [$count, $seconds];
    }

    /**
     * The overflows with the most hours spilling in the window, each with its own spills and hours (its own
     * overlapping spills counted once).
     *
     * @param  array{monitors: list<int>, starts: list<int>, ends: list<int>, spilling: list<array{0: int, 1: int}>}  $spills
     * @return list<array{0: int, 1: int, 2: float}>
     */
    private function busiest(array $spills, int $from, int $until): array
    {
        $counts = [];
        $seconds = [];
        $reached = [];

        foreach ($spills['starts'] as $index => $start) {
            if ($start >= $until) {
                break;
            }

            $end = min($spills['ends'][$index], $until);

            if ($end <= $from) {
                continue;
            }

            $monitorId = $spills['monitors'][$index];
            // Spills come in start order, so each overflow's run of spilling only grows forwards.
            $start = max($start, $from, $reached[$monitorId] ?? $from);
            $counts[$monitorId] = ($counts[$monitorId] ?? 0) + 1;
            $seconds[$monitorId] = ($seconds[$monitorId] ?? 0) + max(0, $end - $start);
            $reached[$monitorId] = max($reached[$monitorId] ?? $from, $end);
        }

        $rows = [];

        foreach ($counts as $monitorId => $count) {
            $rows[] = [$monitorId, $count, round($seconds[$monitorId] / 3600, 1)];
        }

        usort($rows, fn (array $a, array $b) => [$b[2], $b[1]] <=> [$a[2], $a[1]]);

        return array_slice($rows, 0, self::BUSIEST_SHOWN);
    }

    private function timezone(): string
    {
        return (string) config('app.display_timezone', 'Europe/London');
    }
}
