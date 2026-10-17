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
 * regions:refresh), since we started recording them. Hours count time when at least one overflow was spilling
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

    public function __construct(private DailyRainfall $rainfall)
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
            "place-spills:{$place->getMorphClass()}:{$place->getKey()}",
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
            recordingSince: $at($figures['recordingSince']),
            dataUpdatedAt: $at($figures['dataUpdatedAt']),
            record: $figures['record'],
            yearFrom: $at($figures['yearFrom']),
            daysWithSpillLast30: count(array_filter($figures['daily'], fn (array $day) => $day[1] > 0)),
            dailyHistory: collect($figures['daily'])->map(fn (array $day) => new SpillHistoryDay(Carbon::createFromTimestamp($day[0], $timezone), $day[1], $day[2])),
            rainfallStation: $figures['rainfallStationId'] === null ? null : RainfallStation::query()->find($figures['rainfallStationId']),
            dailyRainfall: $figures['rainfall'],
            months: array_map(fn (array $month) => ['month' => Carbon::createFromTimestamp($month[0], $timezone), 'spills' => $month[1], 'hours' => $month[2]], $figures['months']),
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
     * @return array{distances: array<int, int>, recordingSince: int|null, dataUpdatedAt: int|null, record: array{7: array{spills: int, hours: float}, 30: array{spills: int, hours: float}, year: array{spills: int, hours: float}}, yearFrom: int, daily: list<array{0: int, 1: int, 2: int}>, months: list<array{0: int, 1: int, 2: float}>, busiest: list<array{0: int, 1: int, 2: float}>, rainfallStationId: int|null, rainfall: array<string, float|null>|null}
     */
    private function figures(Spot|BathingWater|Lake $place): array
    {
        $now = now();
        $timezone = $this->timezone();
        $distances = array_map('intval', $this->links($place)->pluck('distance_metres', 'overflow_monitor_id')->all());
        $monitors = OverflowMonitor::query()->whereKey(array_keys($distances))->get(['id', 'created_at', 'source_updated_at']);
        $recordingSince = $monitors->min('created_at');
        $nowTs = $now->getTimestamp();
        $days = SpillIntervals::dayBoundaries($now, SpillHistoryService::HISTORY_DAYS);
        $yearFrom = $now->copy()->timezone($timezone)->startOfYear()->utc()->max($recordingSince ?? $now);
        $monthsFrom = $now->copy()->timezone($timezone)->startOfMonth()->subMonths(self::MONTHS_SHOWN - 1)
            ->max(($recordingSince ?? $now)->copy()->timezone($timezone)->startOfMonth());
        $spans = $this->spans($place, $monitors, $now, min($days[0], $yearFrom->getTimestamp(), $monthsFrom->getTimestamp()));
        $station = $place->rainfall_station_id === null ? null : RainfallStation::query()->find($place->rainfall_station_id);

        $daily = [];

        for ($day = 0; $day < SpillHistoryService::HISTORY_DAYS; $day++) {
            [$count, $seconds] = $this->overlap($spans, $days[$day], $days[$day + 1]);
            $daily[] = [$days[$day], $count, (int) round($seconds / 60)];
        }

        $months = [];

        for ($month = $monthsFrom->copy(); $month->getTimestamp() <= $nowTs; $month = $month->copy()->addMonth()) {
            $window = $this->window($spans, $month->getTimestamp(), min($month->copy()->addMonth()->getTimestamp(), $nowTs));
            $months[] = [$month->getTimestamp(), $window['spills'], $window['hours']];
        }

        return [
            'distances' => $distances,
            'recordingSince' => $recordingSince?->getTimestamp(),
            'dataUpdatedAt' => $monitors->max('source_updated_at')?->getTimestamp(),
            'record' => [
                7 => $this->window($spans, $days[SpillHistoryService::HISTORY_DAYS - 7], $nowTs),
                30 => $this->window($spans, $days[0], $nowTs),
                'year' => $this->window($spans, $yearFrom->getTimestamp(), $nowTs),
            ],
            'yearFrom' => $yearFrom->getTimestamp(),
            'daily' => $daily,
            'months' => $months,
            'busiest' => $this->busiest($spans, $yearFrom->getTimestamp(), $nowTs),
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
     * Spills that reach into [from, now] as [monitor id, start, end] timestamps; an open one ends now, or at its
     * monitor's last report if that is old.
     *
     * @param  EloquentCollection<int, OverflowMonitor>  $monitors
     * @return list<array{0: int, 1: int, 2: int}>
     */
    private function spans(Spot|BathingWater|Lake $place, EloquentCollection $monitors, Carbon $now, int $from): array
    {
        $openUntil = $monitors->mapWithKeys(fn (OverflowMonitor $monitor) => [$monitor->id => SpillIntervals::openUntil($monitor, $now)->getTimestamp()]);

        return SpillEvent::query()
            ->whereIn('overflow_monitor_id', $this->links($place)->select('overflow_monitor_id'))
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', Carbon::createFromTimestamp($from, 'UTC')))
            ->toBase()
            ->get(['overflow_monitor_id', 'started_at', 'ended_at'])
            ->map(function (object $row) use ($openUntil, $now) {
                $start = Carbon::parse($row->started_at, 'UTC')->getTimestamp();
                $end = $row->ended_at === null ? max($start, $openUntil->get($row->overflow_monitor_id) ?? $now->getTimestamp()) : Carbon::parse($row->ended_at, 'UTC')->getTimestamp();

                return [(int) $row->overflow_monitor_id, $start, $end];
            })
            ->all();
    }

    /**
     * Spills overlapping [from, until) and the hours when at least one was spilling.
     *
     * @param  list<array{0: int, 1: int, 2: int}>  $spans
     * @return array{spills: int, hours: float}
     */
    private function window(array $spans, int $from, int $until): array
    {
        [$count, $seconds] = $this->overlap($spans, $from, $until);

        return ['spills' => $count, 'hours' => round($seconds / 3600, 1)];
    }

    /**
     * How many spills overlap [from, until) and the seconds when at least one was spilling.
     *
     * @param  list<array{0: int, 1: int, 2: int}>  $spans
     * @return array{0: int, 1: int}
     */
    private function overlap(array $spans, int $from, int $until): array
    {
        $intervals = [];

        foreach ($spans as [, $start, $end]) {
            if ($start < $until && $end > $from) {
                $intervals[] = [$start, $end];
            }
        }

        return [count($intervals), SpillIntervals::unionSeconds($intervals, $from, $until)];
    }

    /**
     * The overflows with the most hours spilling in the window, each with its own spills and hours.
     *
     * @param  list<array{0: int, 1: int, 2: int}>  $spans
     * @return list<array{0: int, 1: int, 2: float}>
     */
    private function busiest(array $spans, int $from, int $until): array
    {
        $byMonitor = [];

        foreach ($spans as [$monitorId, $start, $end]) {
            if ($start < $until && $end > $from) {
                $byMonitor[$monitorId][] = [$start, $end];
            }
        }

        $rows = [];

        foreach ($byMonitor as $monitorId => $intervals) {
            $rows[] = [$monitorId, count($intervals), round(SpillIntervals::unionSeconds($intervals, $from, $until) / 3600, 1)];
        }

        usort($rows, fn (array $a, array $b) => [$b[2], $b[1]] <=> [$a[2], $a[1]]);

        return array_slice($rows, 0, self::BUSIEST_SHOWN);
    }

    private function timezone(): string
    {
        return (string) config('app.display_timezone', 'Europe/London');
    }
}
