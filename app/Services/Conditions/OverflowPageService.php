<?php

namespace App\Services\Conditions;

use App\Data\OverflowPage;
use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\OverflowMonitorPlace;
use App\Models\OverflowOfflinePeriod;
use App\Models\RainfallReading;
use App\Models\RainfallStation;
use App\Models\Region;
use App\Models\River;
use App\Models\SpillEvent;
use App\Models\Spot;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds an overflow's page from our own spill records, its annual returns and the rain gauge of the nearest place
 * linked to it. Totals come from each spill's start and end alone, so a monitor with thousands of spills stays cheap.
 * Hours are each spill's time within the window; a spill still open counts up to now, or only up to the monitor's last
 * report if it has stopped reporting.
 */
class OverflowPageService
{
    /** Spills per page of the log. */
    public const SPILLS_PER_PAGE = 50;

    /**
     * Offline periods were first recorded by the first poll after this (the deploy that added them), so uptime is
     * worked out from then, or from when the monitor was first seen if later.
     */
    public const UPTIME_RECORDED_FROM = '2026-10-17 22:00:00';

    /** Uptime over less than this reads as more certain than it is, so it is not shown. */
    private const UPTIME_MINIMUM_DAYS = 7;

    public function __construct(private RegionOverflows $overflows, private SpillHistoryService $history)
    {
    }

    public function for(OverflowMonitor $monitor, Region $region): OverflowPage
    {
        $now = now();
        $openUntil = $monitor->isStale() ? ($monitor->source_updated_at ?? $now)->copy()->min($now) : $now;
        $spans = $this->spans($monitor, $openUntil);
        $places = $this->overflows->placesFor($monitor);
        $rainfallStation = $this->rainfallStationFor($places);
        $startOfYear = $now->copy()->timezone($this->timezone())->startOfYear()->utc();
        $uptime = $this->uptime($monitor, $now);
        $firstSpill = $spans->min('start');

        return new OverflowPage(
            monitor: $monitor,
            region: $region,
            places: $places,
            // The first spill recorded is the feed's latest when we first saw the overflow, often from before then.
            recordingSince: collect([$monitor->created_at ?? $now, $firstSpill])->filter()->min()->copy(),
            record: [
                7 => $this->window($spans, $now->copy()->subDays(7), $now),
                30 => $this->window($spans, $now->copy()->subDays(30), $now),
                'year' => $this->window($spans, $startOfYear, $now),
            ],
            totalSpills: $spans->count(),
            longestSpillMinutes: $spans->isEmpty() ? null : (int) $spans->max(fn (array $span) => $span['start']->diffInMinutes($span['end'])),
            dailyHistory: $this->history->dailyBreakdown($this->recentEvents($monitor, $now, $openUntil), $now),
            rainfallStation: $rainfallStation,
            dailyRainfall: $rainfallStation === null ? null : $this->dailyRainfall($rainfallStation, $now),
            annualReturns: $monitor->annualReturns()->get(),
            uptimePercent: $uptime['uptimePercent'],
            uptimeSince: $uptime['uptimeSince'],
            centreline: $monitor->river_id === null ? null : River::query()->whereKey($monitor->river_id)->first(['id', 'centreline'])?->centreline,
        );
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
     * Each spill's start and end, read as plain values; an open one ends at $openUntil.
     *
     * @return Collection<int, array{start: Carbon, end: Carbon}>
     */
    private function spans(OverflowMonitor $monitor, Carbon $openUntil): Collection
    {
        return SpillEvent::query()
            ->where('overflow_monitor_id', $monitor->id)
            ->toBase()
            ->get(['started_at', 'ended_at'])
            ->map(function (object $row) use ($openUntil) {
                $start = Carbon::parse($row->started_at, 'UTC');

                return ['start' => $start, 'end' => $row->ended_at === null ? $openUntil->copy()->max($start) : Carbon::parse($row->ended_at, 'UTC')];
            });
    }

    /**
     * Spills overlapping the history window, those still open on a monitor that has stopped reporting ended at its
     * last report.
     *
     * @return Collection<int, SpillEvent>
     */
    private function recentEvents(OverflowMonitor $monitor, Carbon $now, Carbon $openUntil): Collection
    {
        return SpillEvent::query()
            ->where('overflow_monitor_id', $monitor->id)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $now->copy()->subDays(SpillHistoryService::HISTORY_DAYS + 1)))
            ->get()
            ->map(function (SpillEvent $event) use ($monitor, $openUntil) {
                if ($event->ended_at === null && $monitor->isStale()) {
                    $event = clone $event;
                    $event->ended_at = $openUntil->copy()->max($event->started_at);
                }

                return $event;
            });
    }

    /**
     * @param  Collection<int, array{start: Carbon, end: Carbon}>  $spans
     * @return array{spills: int, hours: float}
     */
    private function window(Collection $spans, Carbon $from, Carbon $until): array
    {
        $inWindow = $spans->filter(fn (array $span) => $span['start']->lessThanOrEqualTo($until) && $span['end']->greaterThanOrEqualTo($from));

        $minutes = $inWindow->sum(fn (array $span) => max(0, (int) $span['start']->copy()->max($from)->diffInMinutes($span['end']->copy()->min($until))));

        return ['spills' => $inWindow->count(), 'hours' => round($minutes / 60, 1)];
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
     * Rain per UK day over the history window, oldest first, with every day present: null for a day the gauge sent
     * no readings, so a silent gauge never reads as a dry day.
     *
     * @return array<string, float|null>
     */
    private function dailyRainfall(RainfallStation $station, Carbon $now): array
    {
        $timezone = $this->timezone();
        $firstDay = $now->copy()->timezone($timezone)->startOfDay()->subDays(SpillHistoryService::HISTORY_DAYS - 1);
        $totals = [];

        foreach (range(0, SpillHistoryService::HISTORY_DAYS - 1) as $offset) {
            $totals[$firstDay->copy()->addDays($offset)->toDateString()] = null;
        }

        $readings = RainfallReading::query()
            ->where('rainfall_station_id', $station->id)
            ->where('measured_at', '>', $firstDay->copy()->utc())
            ->toBase()
            ->get(['measured_at', 'millimetres']);

        foreach ($readings as $reading) {
            // A reading closes its 15 minutes, so one at midnight belongs to the day before.
            $day = Carbon::parse($reading->measured_at, 'UTC')->subSecond()->timezone($timezone)->toDateString();

            if (array_key_exists($day, $totals)) {
                $totals[$day] = ($totals[$day] ?? 0.0) + (float) $reading->millimetres;
            }
        }

        return array_map(fn (?float $millimetres) => $millimetres === null ? null : round($millimetres, 1), $totals);
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
