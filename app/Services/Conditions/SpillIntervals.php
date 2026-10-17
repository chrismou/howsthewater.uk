<?php

namespace App\Services\Conditions;

use App\Models\OverflowMonitor;
use App\Models\SpillEvent;
use Illuminate\Support\Carbon;

/**
 * Time spent spilling, worked out on plain Unix timestamps. Spills that overlap count once, so hours over several
 * overflows never exceed the time that passed, and a spill still open counts up to now, or only up to its monitor's
 * last report if the monitor has stopped reporting, so stale data never reads as spilling now.
 */
final class SpillIntervals
{
    /**
     * When a spill ended, or when it should be counted to if it is still open.
     */
    public static function endOf(SpillEvent $event, Carbon $now): Carbon
    {
        if ($event->ended_at !== null) {
            return $event->ended_at;
        }

        $monitor = $event->relationLoaded('overflowMonitor') ? $event->overflowMonitor : null;

        return self::openUntil($monitor, $now)->max($event->started_at);
    }

    /**
     * Where a monitor's open spill stops counting: now, or its last report if that is older than the stale limit.
     */
    public static function openUntil(?OverflowMonitor $monitor, Carbon $now): Carbon
    {
        return $monitor !== null && $monitor->isStale() ? ($monitor->source_updated_at ?? $now)->copy()->min($now) : $now->copy();
    }

    /**
     * Seconds within [from, until) covered by at least one interval.
     *
     * @param  iterable<array{0: int, 1: int}>  $intervals  Start and end timestamps, in any order
     */
    public static function unionSeconds(iterable $intervals, int $from, int $until): int
    {
        $clipped = [];

        foreach ($intervals as [$start, $end]) {
            $start = max($start, $from);
            $end = min($end, $until);

            if ($end > $start) {
                $clipped[] = [$start, $end];
            }
        }

        usort($clipped, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $total = 0;
        $run = null;

        foreach ($clipped as [$start, $end]) {
            if ($run !== null && $start <= $run[1]) {
                $run[1] = max($run[1], $end);

                continue;
            }

            if ($run !== null) {
                $total += $run[1] - $run[0];
            }

            $run = [$start, $end];
        }

        return $run === null ? $total : $total + $run[1] - $run[0];
    }

    /**
     * The start of each UK day of the history window ending today, as timestamps, with the end of the window last:
     * days run from one entry to the next, so the clock changes give 23 or 25 hour days.
     *
     * @return list<int>
     */
    public static function dayBoundaries(Carbon $now, int $days): array
    {
        $timezone = (string) config('app.display_timezone', 'Europe/London');
        $firstDay = $now->copy()->timezone($timezone)->startOfDay()->subDays($days - 1);
        $boundaries = [];

        for ($offset = 0; $offset < $days; $offset++) {
            $boundaries[] = $firstDay->copy()->addDays($offset)->getTimestamp();
        }

        $boundaries[] = $now->getTimestamp();

        return $boundaries;
    }
}
