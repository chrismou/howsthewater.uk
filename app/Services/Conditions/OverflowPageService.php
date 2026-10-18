<?php

namespace App\Services\Conditions;

use App\Data\OverflowPage;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\OverflowMonitorPlace;
use App\Models\OverflowOfflinePeriod;
use App\Models\RainfallStation;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Builds an overflow's page from its spills, its annual returns and the rain gauge of the nearest place linked to it.
 * Spills come from the live feed since we started reading it and, before that, from the company's published log
 * (SpillCoverage); figures count only spills that start inside that time. Totals come from each spill's start and end
 * alone, so a monitor with thousands of spills stays cheap. Hours are each spill's time within the window; a spill
 * still open counts up to now, or only up to the monitor's last report if it has stopped reporting.
 */
class OverflowPageService
{
    /** Spills per page of the log. */
    public const SPILLS_PER_PAGE = 50;

    /** Months in the year chart. */
    private const MONTHS_SHOWN = 12;

    /**
     * Offline periods were first recorded by the first poll after this (the deploy that added them), so uptime is
     * worked out from then, or from when the monitor was first seen if later.
     */
    public const UPTIME_RECORDED_FROM = '2026-10-17 22:00:00';

    /** Uptime over less than this reads as more certain than it is, so it is not shown. */
    private const UPTIME_MINIMUM_DAYS = 7;

    public function __construct(private RegionOverflows $overflows, private SpillHistoryService $history, private DailyRainfall $rainfall, private SpillCoverage $coverage)
    {
    }

    public function for(OverflowMonitor $monitor, Region $region): OverflowPage
    {
        $now = now();
        $allSpans = $this->spans($monitor, SpillIntervals::openUntil($monitor, $now));
        $places = $this->overflows->placesFor($monitor);
        $rainfallStation = $this->rainfallStationFor($places);
        $uptime = $this->uptime($monitor, $now);
        $recordingSince = ($monitor->created_at ?? $now)->copy();
        // A spill the feed showed when we first saw the overflow, from before any published log, stays in the spill
        // log but not in the figures.
        $coverage = $this->coverage->intervals($monitor->company, $recordingSince->getTimestamp());
        $spans = array_values(array_filter($allSpans, fn (array $span) => SpillCoverage::counts($coverage, $span[0], $span[1])));
        $coveredFrom = Carbon::createFromTimestamp($coverage[0][0], 'UTC');
        $yearFrom = $now->copy()->timezone($this->timezone())->startOfYear()->utc()->max($coveredFrom);
        $days = SpillIntervals::dayBoundaries($now, SpillHistoryService::HISTORY_DAYS);
        $monthsFrom = $now->copy()->timezone($this->timezone())->startOfMonth()->subMonths(self::MONTHS_SHOWN - 1);

        return new OverflowPage(
            monitor: $monitor,
            region: $region,
            places: $places,
            recordingSince: $recordingSince,
            record: [
                7 => $this->window($spans, $days[SpillHistoryService::HISTORY_DAYS - 7], $now->getTimestamp()),
                30 => $this->window($spans, $days[0], $now->getTimestamp()),
                'year' => $this->window($spans, $yearFrom->getTimestamp(), $now->getTimestamp()),
            ],
            yearFrom: $yearFrom,
            totalSpills: count($allSpans),
            longestSpillMinutes: $spans === [] ? null : (int) round(max(array_map(fn (array $span) => $span[1] - $span[0], $spans)) / 60),
            dailyHistory: $this->history->dailyBreakdown($this->recentEvents($monitor, $now)->filter(fn (SpillEvent $event) => SpillCoverage::counts($coverage, $event->started_at->getTimestamp(), SpillIntervals::endOf($event, $now)->getTimestamp()))->values(), $now),
            rainfallStation: $rainfallStation,
            dailyRainfall: $rainfallStation === null ? null : $this->rainfall->forStation($rainfallStation, $now),
            annualReturns: $monitor->annualReturns()->get(),
            uptimePercent: $uptime['uptimePercent'],
            uptimeSince: $uptime['uptimeSince'],
            centreline: $monitor->river_id === null ? null : River::query()->whereKey($monitor->river_id)->first(['id', 'centreline'])?->centreline,
            months: $this->months($spans, $coverage, $rainfallStation, $now),
            coveredFrom: $coveredFrom,
            notes: $this->coverage->notes([$monitor->company => $recordingSince->getTimestamp()], $monthsFrom->getTimestamp(), $now->getTimestamp()),
        );
    }

    /**
     * The last MONTHS_SHOWN UK months, oldest first: spills and hours in each, whether we hold any of its time (the
     * company's published log or the live feed; a month in neither has no data rather than no spills), and the rain
     * gauge's total. Past months' rain is cached for an hour, as it no longer changes; the current month's is read
     * each time.
     *
     * @param  list<array{0: int, 1: int}>  $spans  Spills inside $coverage
     * @param  list<array{0: int, 1: int}>  $coverage
     * @return list<array{month: Carbon, spills: int, hours: float, recorded: bool, rain: float|null}>
     */
    private function months(array $spans, array $coverage, ?RainfallStation $station, Carbon $now): array
    {
        $first = $now->copy()->timezone($this->timezone())->startOfMonth()->subMonths(self::MONTHS_SHOWN - 1);
        $starts = array_map(fn (int $offset) => $first->copy()->addMonths($offset), range(0, self::MONTHS_SHOWN - 1));
        $boundaries = [...array_map(fn (Carbon $start) => $start->getTimestamp(), $starts), $now->getTimestamp()];
        $rain = $station === null ? array_fill(0, self::MONTHS_SHOWN, null) : [
            ...Cache::remember(
                "overflow-page:monthly-rain:{$station->id}:{$boundaries[0]}",
                now()->addHour(),
                fn () => $this->rainfall->forPeriods($station, array_slice($boundaries, 0, self::MONTHS_SHOWN)),
            ),
            ...$this->rainfall->forPeriods($station, array_slice($boundaries, self::MONTHS_SHOWN - 1)),
        ];

        return array_map(function (int $index) use ($starts, $boundaries, $spans, $coverage, $rain) {
            $recorded = SpillCoverage::overlaps($coverage, $boundaries[$index], $boundaries[$index + 1]);
            $window = $recorded ? $this->window($spans, $boundaries[$index], $boundaries[$index + 1]) : ['spills' => 0, 'hours' => 0.0];

            return [
                'month' => $starts[$index],
                'spills' => $window['spills'],
                'hours' => $window['hours'],
                'recorded' => $recorded,
                'rain' => $rain[$index],
            ];
        }, range(0, self::MONTHS_SHOWN - 1));
    }

    /**
     * The overflow's spill log, newest first, a page at a time. Null when the page asked for is past the last.
     *
     * @return LengthAwarePaginator<int, SpillEvent>|null
     */
    public function spillLog(OverflowMonitor $monitor): ?LengthAwarePaginator
    {
        $spills = SpillEvent::query()
            ->where('overflow_monitor_id', $monitor->id)
            ->orderByDesc('started_at')
            ->paginate(self::SPILLS_PER_PAGE)
            ->withPath(route('overflows.show', $monitor))
            ->fragment('spills');

        return $spills->currentPage() > max(1, $spills->lastPage()) ? null : $spills;
    }

    /**
     * Each spill's start and end as timestamps; an open one ends at $openUntil.
     *
     * @return list<array{0: int, 1: int}>
     */
    private function spans(OverflowMonitor $monitor, Carbon $openUntil): array
    {
        return SpillEvent::query()
            ->where('overflow_monitor_id', $monitor->id)
            ->toBase()
            ->get(['started_at', 'ended_at'])
            ->map(function (object $row) use ($openUntil) {
                $start = Carbon::parse($row->started_at, 'UTC')->getTimestamp();

                return [$start, $row->ended_at === null ? max($start, $openUntil->getTimestamp()) : Carbon::parse($row->ended_at, 'UTC')->getTimestamp()];
            })
            ->all();
    }

    /**
     * Spills overlapping the history window, each with its monitor, so one still open on a monitor that has stopped
     * reporting is counted only to its last report.
     *
     * @return Collection<int, SpillEvent>
     */
    private function recentEvents(OverflowMonitor $monitor, Carbon $now): Collection
    {
        return SpillEvent::query()
            ->where('overflow_monitor_id', $monitor->id)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $now->copy()->subDays(SpillHistoryService::HISTORY_DAYS + 1)))
            ->get()
            ->each(fn (SpillEvent $event) => $event->setRelation('overflowMonitor', $monitor));
    }

    /**
     * Spills overlapping the window and the hours spent spilling in it.
     *
     * @param  list<array{0: int, 1: int}>  $spans
     * @return array{spills: int, hours: float}
     */
    private function window(array $spans, int $from, int $until): array
    {
        $inWindow = array_filter($spans, fn (array $span) => $span[0] < $until && $span[1] > $from);

        return ['spills' => count($inWindow), 'hours' => round(SpillIntervals::unionSeconds($inWindow, $from, $until) / 3600, 1)];
    }

    /**
     * The rain gauge of the nearest linked place that has one.
     *
     * @param  Collection<int, OverflowMonitorPlace>  $places
     */
    private function rainfallStationFor(Collection $places): ?RainfallStation
    {
        $stationId = $places
            ->map(fn (OverflowMonitorPlace $link) => $link->place)
            ->first(fn (Spot|BathingWater|Lake|null $place) => $place?->rainfall_station_id !== null)
            ?->rainfall_station_id;

        return $stationId === null ? null : RainfallStation::query()->find($stationId);
    }

    /**
     * The share of time since we started recording offline periods that the monitor was reporting.
     *
     * @return array{uptimePercent: float|null, uptimeSince: Carbon|null}
     */
    private function uptime(OverflowMonitor $monitor, Carbon $now): array
    {
        $since = Carbon::parse(self::UPTIME_RECORDED_FROM, 'UTC')->max($monitor->created_at ?? $now);

        if ($since->diffInDays($now) < self::UPTIME_MINIMUM_DAYS) {
            return ['uptimePercent' => null, 'uptimeSince' => null];
        }

        $offlineMinutes = $monitor->offlinePeriods()->get()->sum(fn (OverflowOfflinePeriod $period) => max(0, (int) $period->started_at->copy()->max($since)->diffInMinutes(($period->ended_at ?? $now)->copy()->min($now))));
        $totalMinutes = max(1, (int) $since->diffInMinutes($now));

        return ['uptimePercent' => round(100 * (1 - min($offlineMinutes, $totalMinutes) / $totalMinutes), 1), 'uptimeSince' => $since];
    }

    private function timezone(): string
    {
        return (string) config('app.display_timezone', 'Europe/London');
    }
}
