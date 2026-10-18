<?php

namespace App\Actions\Ingest;

use App\Data\SpillLogRowData;
use App\Enums\SpillLogSource;
use App\Models\OverflowMonitor;
use App\Models\OverflowSpillLog;
use App\Models\SpillEvent;
use App\Services\EnvironmentAgency\SpillLogFilesClient;
use App\Services\Stream\SpillLogClient;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Fills the time before we started reading each overflow's live feed with the spills its company published
 * afterwards (services.overflow_spill_logs): the Environment Agency's start/stop files first, then Stream's logs for
 * the years the Environment Agency has not published, since its files are what the companies submit and what the
 * annual returns are worked out from. A spill is only taken for an overflow we hold, starting on or after the
 * configured date and before the overflow's record start (its created_at), so nothing the live feed recorded comes in
 * twice. A source is read again only when it changes. Reading one replaces its spills, clears the spills held from the
 * feed at first sight within the time it covers (the same spills, as the feed showed them), and an Environment Agency
 * file replaces Stream's spills for its company's year. A source's covered time is only recorded once its spills are
 * all in, so a run that dies part way leaves those months showing as no data until the next run reads it again.
 * Spills are deleted in chunks, as the live ingest writes to the same table every 15 minutes.
 */
class ImportOverflowSpillLogs
{
    private const CHUNK = 1000;

    private const DELETE_CHUNK = 5000;

    /** A spill longer than this is taken as a typo in the log rather than a spill (the longest seen is about 40 days). */
    private const MAX_SPILL_DAYS = 92;

    /** Where both sources hold a company's year and their hours differ by more than this share, it is logged. */
    private const HOURS_TOLERANCE = 0.05;

    /** @var array<string, array{id: int, company: string, recordStart: int}> */
    private array $monitors = [];

    /** The earliest spill start taken, as a timestamp (services.overflow_spill_logs.from). */
    private int $from = 0;

    /** When this run started, as a timestamp. */
    private int $now = 0;

    /** @var array<string, int> The newest record start among each company's overflows, to tell when a log has new ones to fill */
    private array $newestMonitor = [];

    public function __construct(private SpillLogFilesClient $files, private SpillLogClient $stream)
    {
    }

    /**
     * @return array{read: int, unchanged: int, spills: int, unmatched: int, rejected: int, failed: list<string>}
     */
    public function handle(bool $force = false): array
    {
        $totals = ['read' => 0, 'unchanged' => 0, 'spills' => 0, 'unmatched' => 0, 'rejected' => 0, 'failed' => []];
        $this->now = now()->getTimestamp();
        $this->from = Carbon::parse((string) config('services.overflow_spill_logs.from'), 'UTC')->getTimestamp();
        $this->monitors = OverflowMonitor::query()
            ->toBase()
            ->get(['id', 'external_id', 'company', 'created_at'])
            ->mapWithKeys(fn (object $monitor) => [strtoupper($monitor->external_id) => [
                'id' => (int) $monitor->id,
                'company' => (string) $monitor->company,
                'recordStart' => $monitor->created_at === null ? $this->now : Carbon::parse($monitor->created_at, 'UTC')->getTimestamp(),
            ]])
            ->all();
        $this->newestMonitor = collect($this->monitors)->groupBy('company')->map(fn ($monitors) => (int) $monitors->max('recordStart'))->all();

        foreach ([$this->importEnvironmentAgency(...), $this->importStream(...)] as $import) {
            $result = $import($force);
            $totals['read'] += $result['read'];
            $totals['unchanged'] += $result['unchanged'];
            $totals['spills'] += $result['spills'];
            $totals['unmatched'] += $result['unmatched'];
            $totals['rejected'] += $result['rejected'];
            $totals['failed'] = [...$totals['failed'], ...$result['failed']];
        }

        return $totals;
    }

    /**
     * @return array{read: int, unchanged: int, spills: int, unmatched: int, rejected: int, failed: list<string>}
     */
    private function importEnvironmentAgency(bool $force): array
    {
        $totals = ['read' => 0, 'unchanged' => 0, 'spills' => 0, 'unmatched' => 0, 'rejected' => 0, 'failed' => []];

        try {
            $files = $this->files->files();
        } catch (Throwable $exception) {
            Log::error("Environment Agency spill log list failed: {$exception->getMessage()}");

            return [...$totals, 'failed' => ['Environment Agency file list']];
        }

        $this->pruneRemovedFiles($files->pluck('name')->all());

        foreach ($files as $file) {
            if (preg_match('/\.(csv|xlsx)$/i', $file['name']) !== 1) {
                continue;
            }

            // Names use spaces or underscores ("Anglian 2024 Detailed EDM Data.xlsx").
            $year = preg_match('/(?<!\d)(20\d{2})(?!\d)/', $file['name'], $match) === 1 ? (int) $match[1] : null;

            if ($year === null) {
                Log::warning("Environment Agency spill log {$file['name']} names no year, so it was not read.");

                continue;
            }

            if ($year < $this->fromYear()) {
                continue;
            }

            $key = SpillLogSource::EnvironmentAgency->value.':'.$file['name'];
            $fingerprint = "{$file['modified']}|{$file['size']}";

            if (! $force && $this->isUnchanged($key, $fingerprint)) {
                $totals['unchanged']++;

                continue;
            }

            try {
                $result = $this->importFile($file['name'], $key, $year, $fingerprint);
            } catch (Throwable $exception) {
                Log::error("Environment Agency spill log {$file['name']} failed: {$exception->getMessage()}");
                $totals['failed'][] = $file['name'];

                continue;
            }

            $totals['read']++;
            $totals['spills'] += $result['spills'];
            $totals['unmatched'] += $result['unmatched'];
            $totals['rejected'] += $result['rejected'];
        }

        return $totals;
    }

    /**
     * One company's year (or the months of this year so far). A first pass finds whose log it is, how far it runs and
     * the hours it holds, so what it replaces (Stream's copy of the year included) is cleared before its spills go
     * in.
     *
     * @return array{spills: int, unmatched: int, rejected: int}
     */
    private function importFile(string $name, string $key, int $year, string $fingerprint): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'spill-log-');

        try {
            $this->files->download($name, $path);

            // Covered time runs between UK months, as the pages count them. Rows dated outside the file's year are left
            // to the file for that year, so no spill belongs to two files.
            $from = Carbon::create($year, 1, 1, 0, 0, 0, $this->timezone())->utc();
            $yearEnd = $from->copy()->addYear();
            $inYear = fn (SpillLogRowData $row) => $row->startedAt >= $from->getTimestamp() && $row->startedAt < $yearEnd->getTimestamp();
            $companies = [];
            $lastStart = null;
            $seconds = 0;
            $rows = 0;

            foreach ($this->files->rows($path) as $row) {
                $monitor = $this->monitors[$row->externalId] ?? null;

                $rows++;

                if (! $inYear($row) || $row->startedAt > $this->now) {
                    continue;
                }

                if ($monitor !== null) {
                    $companies[$monitor['company']] = ($companies[$monitor['company']] ?? 0) + 1;
                    $seconds += $this->isImported($row, $monitor) && $this->isPlausible($row) ? $row->endedAt - $row->startedAt : 0;
                }

                $lastStart = max($lastStart ?? $row->startedAt, $row->startedAt);
            }

            // A file we can read nothing from has changed shape; it is tried again each run until the reader is fixed.
            if ($rows === 0) {
                throw new RuntimeException('no readable rows (id, site name, start, stop)');
            }

            arsort($companies);
            $company = array_key_first($companies);
            $until = match (true) {
                $lastStart === null => $from->copy(),
                $year < now()->year => $yearEnd->copy(),
                default => $this->monthAfter($lastStart)->min($yearEnd),
            };

            $log = $this->startReading($key, SpillLogSource::EnvironmentAgency, $name, $company, $year);

            if ($company !== null) {
                $this->replaceStreamYear($company, $year, $seconds / 3600);
            }

            return $this->replace($log, $from, $until, $fingerprint, $this->files->rows($path), $inYear);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Stream's logs, for the years of each company the Environment Agency has not published.
     *
     * @return array{read: int, unchanged: int, spills: int, unmatched: int, rejected: int, failed: list<string>}
     */
    private function importStream(bool $force): array
    {
        $totals = ['read' => 0, 'unchanged' => 0, 'spills' => 0, 'unmatched' => 0, 'rejected' => 0, 'failed' => []];

        try {
            $services = $this->stream->services();
        } catch (Throwable $exception) {
            Log::error("Stream spill log list failed: {$exception->getMessage()}");

            return [...$totals, 'failed' => ['Stream catalogue']];
        }

        $environmentAgencyYears = OverflowSpillLog::query()
            ->where('source', SpillLogSource::EnvironmentAgency)
            ->whereNotNull('company')
            ->get(['company', 'year'])
            ->groupBy('company')
            ->map(fn ($logs) => $logs->pluck('year')->map(fn ($year) => (int) $year)->all());

        foreach ($services as $service) {
            try {
                $layer = $this->stream->layer($service['url']);

                if ($layer === null) {
                    continue;
                }

                $key = SpillLogSource::Stream->value.':'.$layer['url'];
                $range = $this->stream->range($layer);
                // A layer that records no edit date is known by its count and first and last spill instead.
                $fingerprint = $layer['fingerprint'] !== '' ? $layer['fingerprint'] : ($range === null ? 'empty' : "{$range['count']}|{$range['from']}|{$range['until']}");

                if (! $force && $this->isUnchanged($key, $fingerprint)) {
                    $totals['unchanged']++;

                    continue;
                }

                $named = preg_match('/\bmonthly\b/i', $service['title']) === 1 ? $this->namedMonths($service['title']) : null;

                if ($range !== null && $named !== null) {
                    $range = [...$range, 'from' => $named[0], 'until' => $named[1] - 1];
                }

                $company = $range === null ? null : $this->companyOf($range['siteIds']);
                $firstYear = $range === null ? 0 : max($this->fromYear(), $this->yearOf($range['from']));
                $lastYear = $range === null ? 0 : $this->yearOf($range['until']);
                $years = $company === null || $lastYear < $firstYear ? [] : array_values(array_diff(range($firstYear, $lastYear), $environmentAgencyYears->get($company, [])));
                $since = $range === null || $years === [] ? null : Carbon::create(min($years), 1, 1, 0, 0, 0, $this->timezone())->utc();

                $log = $this->startReading($key, SpillLogSource::Stream, $service['title'], $company, null);

                // Nothing to take: its years are the Environment Agency's, before the import window, or no one's we hold.
                $result = $since === null
                    ? $this->replace($log, null, null, $fingerprint, [], fn () => false)
                    : $this->replace(
                        $log,
                        $since->max(Carbon::createFromTimestamp($range['from'], $this->timezone())->startOfMonth()->utc()),
                        $this->monthAfter($range['until']),
                        $fingerprint,
                        $this->stream->rows($layer, $named === null ? $since->getTimestamp() : 0)
                            ->map(fn (SpillLogRowData $row) => $named === null ? $row : $this->withinMonths($row, $named))
                            ->filter(),
                        fn (SpillLogRowData $row) => in_array($this->yearOf($row->startedAt), $years, true),
                    );
            } catch (Throwable $exception) {
                Log::error("Stream spill log {$service['title']} failed: {$exception->getMessage()}");
                $totals['failed'][] = $service['title'];

                continue;
            }

            $totals['read']++;
            $totals['spills'] += $result['spills'];
            $totals['unmatched'] += $result['unmatched'];
            $totals['rejected'] += $result['rejected'];
        }

        return $totals;
    }

    /**
     * The log's row, with its covered time cleared until its spills are all in again, so pages never count months as
     * published while they are being replaced.
     */
    private function startReading(string $key, SpillLogSource $source, string $name, ?string $company, ?int $year): OverflowSpillLog
    {
        return OverflowSpillLog::query()->updateOrCreate(['key' => $key], [
            'source' => $source,
            'name' => $name,
            'company' => $company,
            'year' => $year,
            'covers_from' => null,
            'covers_until' => null,
        ]);
    }

    /**
     * Replaces a log's spills with those of $rows that start in the import window, before their overflow's record
     * start and pass $keep, after clearing the spills held from the feed at first sight in the time the log covers,
     * then records the time it covers and its fingerprint.
     *
     * @param  iterable<int, SpillLogRowData>  $rows
     * @param  Closure(SpillLogRowData): bool  $keep
     * @return array{spills: int, unmatched: int, rejected: int}
     */
    private function replace(OverflowSpillLog $log, ?Carbon $coversFrom, ?Carbon $coversUntil, string $fingerprint, iterable $rows, Closure $keep): array
    {
        $this->deleteInChunks(SpillEvent::query()->where('overflow_spill_log_id', $log->id));

        if ($log->company !== null && $coversFrom !== null && $coversUntil !== null && $coversUntil->greaterThan($coversFrom)) {
            $this->clearFirstSightSpills($log->company, $coversFrom, $coversUntil);
        }

        $now = now()->format('Y-m-d H:i:s');
        $spills = 0;
        $unmatched = 0;
        $rejected = 0;
        $batch = [];

        foreach ($rows as $row) {
            $monitor = $this->monitors[$row->externalId] ?? null;

            if ($monitor === null) {
                $unmatched++;

                continue;
            }

            if (! $this->isImported($row, $monitor) || ! $keep($row)) {
                continue;
            }

            if (! $this->isPlausible($row)) {
                $rejected++;

                continue;
            }

            $batch[] = [
                'overflow_monitor_id' => $monitor['id'],
                'overflow_spill_log_id' => $log->id,
                'started_at' => gmdate('Y-m-d H:i:s', $row->startedAt),
                'ended_at' => gmdate('Y-m-d H:i:s', $row->endedAt),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) === self::CHUNK) {
                $spills += SpillEvent::query()->insertOrIgnore($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $spills += SpillEvent::query()->insertOrIgnore($batch);
        }

        $log->update([
            'covers_from' => $coversFrom,
            'covers_until' => $coversUntil,
            'fingerprint' => $fingerprint,
            'spills' => $spills,
            'unmatched' => $unmatched,
            'read_at' => now(),
        ]);

        return ['spills' => $spills, 'unmatched' => $unmatched, 'rejected' => $rejected];
    }

    /**
     * Whether a row could be a real spill: stopped by now, and not months long. A typo in a log's year would
     * otherwise wreck an overflow's hours and its longest spill.
     */
    private function isPlausible(SpillLogRowData $row): bool
    {
        return $row->endedAt <= $this->now && $row->endedAt - $row->startedAt <= self::MAX_SPILL_DAYS * 86400;
    }

    /**
     * Whether a source was read at this fingerprint and nothing new needs it: a log with rows for overflows we did not
     * hold is read again once its company (or, for a log matching no company, any) has an overflow first seen since.
     */
    private function isUnchanged(string $key, string $fingerprint): bool
    {
        $log = OverflowSpillLog::query()->where('key', $key)->first(['fingerprint', 'company', 'unmatched', 'read_at']);

        if ($log === null || $log->fingerprint !== $fingerprint) {
            return false;
        }

        $newest = $log->company === null ? max([0, ...array_values($this->newestMonitor)]) : ($this->newestMonitor[$log->company] ?? 0);

        return $log->unmatched === 0 || $log->read_at === null || $newest <= $log->read_at->getTimestamp();
    }

    /**
     * Removes the logs, and their spills, of files the Environment Agency no longer lists (a file reissued under
     * another name would otherwise be held twice). It runs before any file is read, so a renamed file's spills are
     * not skipped as duplicates of the old file's.
     *
     * @param  list<string>  $names
     */
    private function pruneRemovedFiles(array $names): void
    {
        $keys = array_map(fn (string $name) => SpillLogSource::EnvironmentAgency->value.':'.$name, $names);

        OverflowSpillLog::query()
            ->where('source', SpillLogSource::EnvironmentAgency)
            ->whereNotIn('key', $keys)
            ->get()
            ->each(function (OverflowSpillLog $log) {
                $this->deleteInChunks(SpillEvent::query()->where('overflow_spill_log_id', $log->id));
                $log->delete();
            });
    }

    /**
     * Deletes what a query matches a chunk at a time, so no one statement holds locks the live ingest waits on.
     *
     * @param  Builder<SpillEvent>  $query
     */
    private function deleteInChunks(Builder $query): void
    {
        do {
            $ids = (clone $query)->limit(self::DELETE_CHUNK)->pluck('id');

            if ($ids->isNotEmpty()) {
                SpillEvent::query()->whereKey($ids->all())->delete();
            }
        } while ($ids->count() === self::DELETE_CHUNK);
    }

    /**
     * Whether a log's spill fills our record: from the configured start, and before its overflow's record start.
     *
     * @param  array{id: int, company: string, recordStart: int}  $monitor
     */
    private function isImported(SpillLogRowData $row, array $monitor): bool
    {
        return $row->startedAt >= $this->from && $row->startedAt < $monitor['recordStart'];
    }

    /**
     * Deletes the spills the feed showed for a company's overflows when we first saw them (each its latest then, from
     * before its record start) that a log now covers, so the log's own copy is the only one.
     */
    private function clearFirstSightSpills(string $company, Carbon $from, Carbon $until): void
    {
        $this->deleteInChunks(SpillEvent::query()
            ->whereIn('overflow_monitor_id', collect($this->monitors)->where('company', $company)->pluck('id')->all())
            ->whereNull('overflow_spill_log_id')
            ->whereNotNull('ended_at')
            ->where('started_at', '>=', $from)
            ->where('started_at', '<', $until)
            ->whereExists(fn ($query) => $query->selectRaw('1')
                ->from('overflow_monitors')
                ->whereColumn('overflow_monitors.id', 'spill_events.overflow_monitor_id')
                ->whereColumn('overflow_monitors.created_at', '>', 'spill_events.started_at')));
    }

    /**
     * An Environment Agency file for a company's year replaces Stream's spills for it. Their hours are compared first:
     * the two copies have matched exactly where both were read, so a gap is worth a look.
     */
    private function replaceStreamYear(string $company, int $year, float $environmentAgencyHours): void
    {
        $query = SpillEvent::query()
            ->whereIn('overflow_spill_log_id', OverflowSpillLog::query()->where('source', SpillLogSource::Stream)->where('company', $company)->select('id'))
            ->where('started_at', '>=', Carbon::create($year, 1, 1, 0, 0, 0, 'UTC'))
            ->where('started_at', '<', Carbon::create($year + 1, 1, 1, 0, 0, 0, 'UTC'));

        $seconds = 0;
        $count = 0;

        foreach ((clone $query)->select(['id', 'started_at', 'ended_at'])->toBase()->lazyById(self::DELETE_CHUNK) as $row) {
            $seconds += (int) strtotime($row->ended_at.' UTC') - (int) strtotime($row->started_at.' UTC');
            $count++;
        }

        if ($count === 0) {
            return;
        }

        $streamHours = $seconds / 3600;

        if (abs($streamHours - $environmentAgencyHours) > self::HOURS_TOLERANCE * max($streamHours, $environmentAgencyHours)) {
            Log::warning(sprintf('%s %d: the Environment Agency file has %.0f hours of spills and Stream had %.0f; the Environment Agency file replaces Stream\'s.', $company, $year, $environmentAgencyHours, $streamHours));
        }

        $this->deleteInChunks($query);
    }

    /**
     * The months a log's title names, as [from, until) timestamps: Anglian Water publishes one service a month or two
     * ("Monthly March 2026", "Monthly May and June 2026", "Monthly April 2025 to October 2025"). Null when it names
     * none.
     *
     * @return array{0: int, 1: int}|null
     */
    private function namedMonths(string $title): ?array
    {
        if (preg_match_all('/\b(January|February|March|April|May|June|July|August|September|October|November|December)\b(?:\s+(\d{4}))?/i', $title, $matches, PREG_SET_ORDER) === 0) {
            return null;
        }

        // A month without a year takes the next one given ("May and June 2026").
        $months = [];
        $year = null;

        foreach (array_reverse($matches) as $match) {
            $year = ($match[2] ?? '') !== '' ? (int) $match[2] : $year;

            if ($year === null) {
                return null;
            }

            $months[] = Carbon::parse("1 {$match[1]} {$year}", $this->timezone());
        }

        $first = collect($months)->min();
        $last = collect($months)->max();

        return [$first->getTimestamp(), $last->copy()->addMonthNoOverflow()->getTimestamp()];
    }

    /**
     * A row from a log covering the named months, or null if it falls outside them. Some of these logs swap the day
     * and month of the first 12 days (Anglian Water's March 2026 has 10 March as 3 October), so a row outside the
     * months is taken with its day and month swapped back where that puts it inside them, and a stop weeks after its
     * start is swapped back too. In a log of two months, a swapped day that lands inside them (5 June as 6 May) cannot
     * be told apart and stays as given.
     *
     * @param  array{0: int, 1: int}  $months
     */
    private function withinMonths(SpillLogRowData $row, array $months): ?SpillLogRowData
    {
        $inside = fn (int $timestamp) => $timestamp >= $months[0] && $timestamp < $months[1];
        $swap = function (int $timestamp): ?int {
            $time = Carbon::createFromTimestamp($timestamp, 'UTC');

            return $time->day > 12 ? null : $time->copy()->setDate($time->year, $time->day, $time->month)->getTimestamp();
        };

        if ($inside($row->startedAt)) {
            // Only the stop may be swapped, which shows as a spill of weeks.
            $end = $row->endedAt - $row->startedAt >= 31 * 86400 ? $swap($row->endedAt) : $row->endedAt;

            return $end === null || $end < $row->startedAt ? $row : new SpillLogRowData($row->externalId, $row->startedAt, $end);
        }

        $start = $swap($row->startedAt);

        if ($start === null || ! $inside($start)) {
            return null;
        }

        // The stop was swapped too where it now sits before the start or more than a month after it.
        $end = $row->endedAt >= $start && $row->endedAt - $start < 31 * 86400 ? $row->endedAt : $swap($row->endedAt);

        return $end === null || $end < $start ? null : new SpillLogRowData($row->externalId, $start, $end);
    }

    /**
     * The company most of a sample of a log's overflow ids belong to; some logs carry ids no feed uses as well
     * (Wessex Water's "17385C").
     *
     * @param  list<string>  $externalIds
     */
    private function companyOf(array $externalIds): ?string
    {
        $companies = array_count_values(array_filter(array_map(fn (string $id) => $this->monitors[$id]['company'] ?? null, $externalIds)));
        arsort($companies);

        return array_key_first($companies);
    }

    /**
     * The start of the UK month after the one $timestamp falls in.
     */
    private function monthAfter(int $timestamp): Carbon
    {
        return Carbon::createFromTimestamp($timestamp, $this->timezone())->startOfMonth()->addMonthNoOverflow()->utc();
    }

    private function timezone(): string
    {
        return (string) config('app.display_timezone', 'Europe/London');
    }

    private function fromYear(): int
    {
        return $this->yearOf($this->from);
    }

    private function yearOf(int $timestamp): int
    {
        return (int) gmdate('Y', $timestamp);
    }
}
