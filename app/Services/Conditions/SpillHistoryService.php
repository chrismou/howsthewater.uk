<?php

namespace App\Services\Conditions;

use App\Data\SpillHistoryDay;
use App\Data\UpstreamMonitorSummary;
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

    /**
     * Spill events from the given monitors that overlap the history window,
     * most recent first, with the monitor and its river loaded.
     *
     * @param  Collection<int, UpstreamMonitorSummary>  $monitors
     * @return Collection<int, SpillEvent>
     */
    public function recentEvents(Collection $monitors, ?Carbon $since = null): Collection
    {
        $monitorIds = $monitors->map(fn (UpstreamMonitorSummary $summary) => $summary->monitor->id);

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
     * One entry per day for the history window, oldest first. Minutes are
     * split across the days an event overlaps; the count goes to each day
     * the event was active.
     *
     * @param  Collection<int, SpillEvent>  $events
     * @return Collection<int, SpillHistoryDay>
     */
    public function dailyBreakdown(Collection $events, ?Carbon $until = null): Collection
    {
        $until ??= now();
        $firstDay = $until->copy()->startOfDay()->subDays(self::HISTORY_DAYS - 1);

        $days = collect(range(0, self::HISTORY_DAYS - 1))
            ->mapWithKeys(function (int $offset) use ($firstDay) {
                $date = $firstDay->copy()->addDays($offset);

                return [$date->toDateString() => ['date' => $date, 'count' => 0, 'minutes' => 0]];
            })
            ->all();

        foreach ($events as $event) {
            $start = $event->started_at->copy();
            $end = ($event->ended_at ?? $until)->copy()->min($until);

            for ($day = $start->copy()->startOfDay(); $day->lessThanOrEqualTo($end); $day->addDay()) {
                $key = $day->toDateString();

                if (! isset($days[$key])) {
                    continue;
                }

                $overlapStart = $start->max($day);
                $overlapEnd = $end->min($day->copy()->endOfDay());

                $days[$key]['count']++;
                $days[$key]['minutes'] += max(0, (int) $overlapStart->diffInMinutes($overlapEnd));
            }
        }

        return collect($days)
            ->map(fn (array $day) => new SpillHistoryDay($day['date'], $day['count'], $day['minutes']))
            ->values();
    }
}
