<?php

namespace App\Services\Conditions;

use App\Data\SpillHistoryDay;
use App\Data\UpstreamMonitorSummary;
use App\Models\OverflowMonitor;
use App\Models\SpillEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the recent spill history for a set of upstream monitors: the
 * individual events and a per-day breakdown for charting.
 */
class SpillHistoryService
{
    public const HISTORY_DAYS = 30;

    public function __construct(private SpillCoverage $coverage)
    {
    }

    /**
     * The events that count in figures: those inside the time we hold their
     * overflow's spills for (its company's published log, then its live
     * feed, SpillCoverage::counts()). The feed's latest spill when we first
     * saw an overflow, from before that, stays in lists but not in counts.
     *
     * @param  Collection<int, SpillEvent>  $events  With their overflowMonitor loaded
     * @return Collection<int, SpillEvent>
     */
    public function counted(Collection $events, ?Carbon $now = null): Collection
    {
        $now ??= now();

        return $events->filter(fn (SpillEvent $event) => SpillCoverage::counts(
            $this->coverage->intervals($event->overflowMonitor->company, ($event->overflowMonitor->created_at ?? $now)->getTimestamp()),
            $event->started_at->getTimestamp(),
            SpillIntervals::endOf($event, $now)->getTimestamp(),
        ))->values();
    }

    /**
     * When the earliest of these overflows' records starts, or null for none.
     *
     * @param  Collection<int, OverflowMonitor>  $monitors
     */
    public function recordedFrom(Collection $monitors): ?int
    {
        $starts = $monitors->map(fn (OverflowMonitor $monitor) => $this->coverage->intervals($monitor->company, ($monitor->created_at ?? now())->getTimestamp())[0][0]);

        return $starts->isEmpty() ? null : (int) $starts->min();
    }

    /**
     * Spill events from the given monitors that overlap the history window,
     * most recent first, with the monitor and its river loaded.
     *
     * @param  Collection<int, UpstreamMonitorSummary>  $monitors
     * @return Collection<int, SpillEvent>
     */
    public function recentEvents(Collection $monitors, ?Carbon $since = null): Collection
    {
        return $this->recentEventsForMonitors($monitors->map(fn (UpstreamMonitorSummary $summary) => $summary->monitor->id), $since);
    }

    /**
     * As recentEvents(), for any set of monitor ids.
     *
     * @param  Collection<int, int>  $monitorIds
     * @return Collection<int, SpillEvent>
     */
    public function recentEventsForMonitors(Collection $monitorIds, ?Carbon $since = null): Collection
    {
        if ($monitorIds->isEmpty()) {
            return collect();
        }

        $since ??= now()->subDays(self::HISTORY_DAYS);

        return SpillEvent::query()
            ->whereIn('overflow_monitor_id', $monitorIds)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $since))
            ->with(['overflowMonitor.river' => fn ($query) => $query->withoutCentreline()])
            ->orderByDesc('started_at')
            ->get();
    }

    /**
     * One entry per UK day for the history window, oldest first. Minutes are
     * those when at least one spill was running, so overlapping spills count
     * once; the count goes to each day the event was active.
     *
     * @param  Collection<int, SpillEvent>  $events
     * @return Collection<int, SpillHistoryDay>
     * @param  int|null  $recordedFrom  When the record starts, so days before it are marked as no data
     */
    public function dailyBreakdown(Collection $events, ?Carbon $until = null, ?int $recordedFrom = null): Collection
    {
        // Days are UK days, as the pages print them and as rainfall is totalled, so a spill just after midnight in
        // summer lands on the day it happened.
        $timezone = (string) config('app.display_timezone', 'Europe/London');
        $until = ($until ?? now())->copy();
        $boundaries = SpillIntervals::dayBoundaries($until, self::HISTORY_DAYS);
        $counts = array_fill(0, self::HISTORY_DAYS, 0);
        $intervals = array_fill(0, self::HISTORY_DAYS, []);

        foreach ($events as $event) {
            $start = $event->started_at->getTimestamp();
            $end = min(SpillIntervals::endOf($event, $until)->getTimestamp(), $boundaries[self::HISTORY_DAYS]);

            for ($day = 0; $day < self::HISTORY_DAYS; $day++) {
                if ($start < $boundaries[$day + 1] && $end > $boundaries[$day]) {
                    $counts[$day]++;
                    $intervals[$day][] = [$start, $end];
                }
            }
        }

        return collect(range(0, self::HISTORY_DAYS - 1))->map(fn (int $day) => new SpillHistoryDay(
            Carbon::createFromTimestamp($boundaries[$day], $timezone),
            $counts[$day],
            (int) round(SpillIntervals::unionSeconds($intervals[$day], $boundaries[$day], $boundaries[$day + 1]) / 60),
            $recordedFrom === null || $boundaries[$day + 1] > $recordedFrom,
        ));
    }
}
