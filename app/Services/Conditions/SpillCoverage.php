<?php

namespace App\Services\Conditions;

use App\Enums\SpillLogSource;
use App\Models\OverflowMonitor;
use App\Models\OverflowSpillLog;
use Illuminate\Support\Carbon;

/**
 * The time we hold an overflow's spills for: the months its company has published in logs (overflow_spill_logs, from
 * ImportOverflowSpillLogs), then everything from when we started reading its live feed (its created_at). Pages count
 * a spill only where it starts inside that time, mark months outside it as having no data, and say where each part
 * comes from (notes()).
 */
class SpillCoverage
{
    /** @var array<string, array{intervals: list<array{0: int, 1: int}>, sources: list<SpillLogSource>}>|null */
    private ?array $logs = null;

    /**
     * Each company's published time as merged [from, until) stretches, oldest first, by the company as the feeds name
     * it. A source is only counted once its spills are all in.
     *
     * @return array<string, array{intervals: list<array{0: int, 1: int}>, sources: list<SpillLogSource>}>
     */
    public function logs(): array
    {
        return $this->logs ??= OverflowSpillLog::query()
            ->whereNotNull('company')
            ->whereNotNull('covers_from')
            ->whereNotNull('covers_until')
            ->get(['company', 'source', 'covers_from', 'covers_until'])
            ->groupBy('company')
            ->map(fn ($logs) => [
                'intervals' => $this->merge($logs->map(fn (OverflowSpillLog $log) => [(int) $log->covers_from?->getTimestamp(), (int) $log->covers_until?->getTimestamp()])->all()),
                'sources' => $logs->pluck('source')->unique()->sortBy(fn (SpillLogSource $source) => $source->value)->values()->all(),
            ])
            ->all();
    }

    /**
     * The stretches we hold an overflow's spills for, as [from, until) timestamps, oldest first; the last never ends.
     *
     * @return list<array{0: int, 1: int}>
     */
    public function intervals(string $company, int $recordStart): array
    {
        $published = array_values(array_filter(
            array_map(fn (array $interval) => [$interval[0], min($interval[1], $recordStart)], $this->logs()[$company]['intervals'] ?? []),
            fn (array $interval) => $interval[1] > $interval[0],
        ));

        return $this->merge([...$published, [$recordStart, PHP_INT_MAX]]);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $intervals
     */
    public static function covers(array $intervals, int $timestamp): bool
    {
        foreach ($intervals as [$from, $until]) {
            if ($timestamp >= $from && $timestamp < $until) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a spill counts in the figures: it starts inside the time held, or it runs into the last stretch (one
     * already running when we first saw the overflow is part of what the live feed showed).
     *
     * @param  list<array{0: int, 1: int}>  $intervals
     */
    public static function counts(array $intervals, int $start, int $end): bool
    {
        return self::covers($intervals, $start) || ($intervals !== [] && $end > $intervals[count($intervals) - 1][0]);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $intervals
     */
    public static function overlaps(array $intervals, int $from, int $until): bool
    {
        foreach ($intervals as [$start, $end]) {
            if ($start < $until && $end > $from) {
                return true;
            }
        }

        return false;
    }

    /**
     * What to say beside figures covering [from, until), per company: where its spills before the record start come
     * from and which months are missing; or, with no log, when its spills here start, saying it publishes none only
     * for a company known not to (services.overflow_spill_logs.unpublished). Each only while it applies to the time shown, so they drop off as the
     * record grows.
     *
     * @param  array<string, int>  $recordStarts  The earliest record start of the company's overflows shown, by company
     * @return list<string>
     */
    public function notes(array $recordStarts, int $from, int $until): array
    {
        $notes = [];
        ksort($recordStarts);

        foreach ($recordStarts as $company => $recordStart) {
            if ($recordStart <= $from) {
                continue;
            }

            $name = OverflowMonitor::shortCompanyName($company);
            $started = $this->date($recordStart);
            $intervals = $this->intervals($company, $recordStart);

            if ($intervals[0][0] >= $recordStart) {
                $notes[] = in_array($company, config()->array('services.overflow_spill_logs.unpublished'), true)
                    ? "{$name} does not publish past spills, so its spills here start when we started reading its live feed on {$started->format('j F Y')}."
                    : "Spills from {$name} here start when we started reading its live feed on {$started->format('j F Y')}.";

                continue;
            }

            $via = collect($this->logs()[$company]['sources'] ?? [])->map(fn (SpillLogSource $source) => $source->viaLabel())->join(' and ');
            $notes[] = "Spills before {$started->format('j F Y')} come from {$name}'s own published records, via {$via}. From then on they come from its live feed, which we read every 15 minutes. The two are recorded differently, so they may not match exactly.";

            // The holes between the stretches held; the last runs up to the record start, which the company has not
            // published yet.
            for ($index = 1; $index < count($intervals); $index++) {
                $holeFrom = $intervals[$index - 1][1];
                $holeUntil = $intervals[$index][0];

                if ($holeFrom >= $until || $holeUntil <= $from) {
                    continue;
                }

                $range = $this->range($holeFrom, $holeUntil);
                $notes[] = $holeUntil === $recordStart ? "{$name} has not published {$range} yet." : "We hold no spills from {$name} for {$range}.";
            }
        }

        return $notes;
    }

    /**
     * Overlapping or touching stretches joined, oldest first.
     *
     * @param  list<array{0: int, 1: int}>  $intervals
     * @return list<array{0: int, 1: int}>
     */
    private function merge(array $intervals): array
    {
        usort($intervals, fn (array $a, array $b) => $a[0] <=> $b[0]);
        $merged = [];

        foreach ($intervals as $interval) {
            $last = count($merged) - 1;

            if ($last >= 0 && $interval[0] <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $interval[1]);
            } else {
                $merged[] = $interval;
            }
        }

        return $merged;
    }

    /**
     * "1 August to 13 October 2026": from the day $from falls on to the day before $until.
     */
    private function range(int $from, int $until): string
    {
        $first = $this->date($from);
        $last = $this->date($until)->subDay();

        return $first->isSameYear($last) ? "{$first->format('j F')} to {$last->format('j F Y')}" : "{$first->format('j F Y')} to {$last->format('j F Y')}";
    }

    private function date(int $timestamp): Carbon
    {
        return Carbon::createFromTimestamp($timestamp, (string) config('app.display_timezone', 'Europe/London'))->startOfDay();
    }
}
