<?php

namespace App\Actions\Ingest;

use App\Data\OverflowMonitorData;
use App\Enums\OverflowStatus;
use App\Models\OverflowMonitor;
use App\Models\OverflowOfflinePeriod;
use App\Models\Region;
use App\Models\SpillEvent;
use App\Services\ScottishWater\OverflowEventClient;
use App\Services\Stream\OverflowFeedReader;
use App\Services\Stream\StormOverflowClient;
use App\Services\WelshWater\StormOverflowMapClient;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pulls the current state of every storm overflow monitor and records any
 * new or changed spill events, so a history builds up over time.
 */
class SyncOverflowMonitors
{
    /**
     * How long a feed that only edits monitors when they change can go without any edit before its monitors are
     * treated as out of date. A dry spell has left Northumbrian Water's 1,500 or so monitors unedited for a day and a
     * half.
     */
    public const QUIET_FEED_HOURS = 48;

    public function __construct(private StormOverflowClient $stream, private OverflowEventClient $scottishWater, private StormOverflowMapClient $welshWater)
    {
    }

    /**
     * Syncs every configured water company feed. A feed that fails is
     * logged and skipped, so one company's outage never stops the others;
     * its monitors simply go stale and the pages say so.
     *
     * @return array{monitors: int, monitors_added: int, events_opened: int, events_closed: int, feeds: int, failed: array<int, string>}
     */
    public function handle(): array
    {
        $totals = ['monitors' => 0, 'monitors_added' => 0, 'events_opened' => 0, 'events_closed' => 0, 'feeds' => 0, 'failed' => []];

        foreach (Region::overflowFeeds() as $key => $feed) {
            try {
                $monitors = $this->readerFor($feed['driver'])->all($feed['url']);
                $result = $this->sync($feed['updated_on_change'] ? $this->confirmedAsRead($monitors) : $monitors);
            } catch (Throwable $exception) {
                Log::error("Storm overflow feed {$key} failed: {$exception->getMessage()}");
                $totals['failed'][] = $feed['name'];

                continue;
            }

            $totals['monitors'] += $result['monitors'];
            $totals['monitors_added'] += $result['monitors_added'];
            $totals['events_opened'] += $result['events_opened'];
            $totals['events_closed'] += $result['events_closed'];
            $totals['feeds']++;
        }

        return $totals;
    }

    /**
     * Northumbrian Water only moves a monitor's LastUpdated when its status changes, so a quiet monitor's time says
     * when it last changed, not when the feed last confirmed it. While anything in the feed has changed within
     * QUIET_FEED_HOURS, every monitor it returns is current as of this read; past that, the monitors keep their own
     * times and pages show the data as out of date.
     *
     * @param  Collection<int, OverflowMonitorData>  $monitors
     * @return Collection<int, OverflowMonitorData>
     */
    private function confirmedAsRead(Collection $monitors): Collection
    {
        $lastEdited = $monitors->max(fn (OverflowMonitorData $data) => $data->sourceUpdatedAt);

        if ($lastEdited === null || $lastEdited->lessThan(now()->subHours(self::QUIET_FEED_HOURS))) {
            return $monitors;
        }

        return $monitors->map(fn (OverflowMonitorData $data) => $data->withSourceUpdatedAt(now()));
    }

    /**
     * Stream feeds share one set of fields; Scottish Water publishes its own API and Welsh Water its own map.
     */
    private function readerFor(string $driver): OverflowFeedReader
    {
        return match ($driver) {
            'scottish_water' => $this->scottishWater,
            'welsh_water' => $this->welshWater,
            default => $this->stream,
        };
    }

    /**
     * A feed can list one monitor twice (Anglian Water has AWS00528 twice, word for word), which would otherwise be
     * inserted twice and fail the whole feed on the unique id, so each id is synced once, from its most recently
     * updated record.
     *
     * @param  Collection<int, OverflowMonitorData>  $monitors
     * @return array{monitors: int, monitors_added: int, events_opened: int, events_closed: int}
     */
    public function sync(Collection $monitors): array
    {
        $monitors = $monitors
            ->sortBy(fn (OverflowMonitorData $data) => $data->sourceUpdatedAt?->getTimestampMs() ?? 0)
            ->keyBy('externalId')
            ->values();

        $existing = OverflowMonitor::query()
            ->with('openSpillEvents')
            ->get()
            ->keyBy('external_id');

        $opened = 0;
        $closed = 0;
        $added = 0;
        $openOfflinePeriods = OverflowOfflinePeriod::query()->whereNull('ended_at')->get()->keyBy('overflow_monitor_id');

        foreach ($monitors as $data) {
            /** @var OverflowMonitor|null $monitor */
            $monitor = $existing->get($data->externalId);

            if ($monitor === null) {
                $monitor = new OverflowMonitor(['external_id' => $data->externalId]);
                $added++;
                $monitor->setRelation('openSpillEvents', new EloquentCollection());
            }

            $monitor->fill([
                'company' => $data->company,
                'receiving_watercourse' => $data->receivingWatercourse,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'status' => $data->status,
                'status_started_at' => $data->statusStartedAt,
                'latest_event_started_at' => $data->latestEventStartedAt,
                'latest_event_ended_at' => $data->latestEventEndedAt,
                'source_updated_at' => $data->sourceUpdatedAt,
                // Stream feeds give no name, so only a feed that does replaces it.
                ...($data->name !== null ? ['name' => $data->name] : []),
            ])->save();

            $this->recordOfflinePeriod($monitor, $openOfflinePeriods->get($monitor->id));
            [$openedHere, $closedHere] = $this->reconcileSpillEvents($monitor, $data);

            $opened += $openedHere;
            $closed += $closedHere;
        }

        return ['monitors' => $monitors->count(), 'monitors_added' => $added, 'events_opened' => $opened, 'events_closed' => $closed];
    }

    /**
     * Feeds keep no status history, so each poll that finds a monitor offline opens a period (from now, when we first
     * saw it) and the first that finds it back closes it. Those periods give the monitor's uptime.
     */
    private function recordOfflinePeriod(OverflowMonitor $monitor, ?OverflowOfflinePeriod $open): void
    {
        if ($monitor->status === OverflowStatus::Offline && $open === null) {
            $monitor->offlinePeriods()->create(['started_at' => now()]);
        } elseif ($monitor->status !== OverflowStatus::Offline && $open !== null) {
            $open->update(['ended_at' => now()]);
        }
    }

    /**
     * The feed only exposes the latest event per monitor, so each sync
     * records that event if unseen, closes it once the monitor stops
     * discharging, and closes any older event still open. An event is never
     * left open on a monitor that is not discharging, even when the feed
     * gives no end time, or it would count as an ongoing spill forever.
     *
     * @return array{0: int, 1: int}
     */
    private function reconcileSpillEvents(OverflowMonitor $monitor, OverflowMonitorData $data): array
    {
        $opened = 0;
        $closed = 0;
        $latest = null;
        $previous = null;

        // Scottish Water also gives the event before the latest, so one that started and ended between polls is kept.
        if ($data->previousEventStartedAt !== null && $data->previousEventEndedAt !== null
            && $data->previousEventEndedAt->greaterThanOrEqualTo($data->previousEventStartedAt)
            && ($data->latestEventStartedAt === null || $data->previousEventStartedAt->lessThan($data->latestEventStartedAt))) {
            $previous = SpillEvent::query()->firstOrNew([
                'overflow_monitor_id' => $monitor->id,
                'started_at' => $data->previousEventStartedAt,
            ]);

            if (! $previous->exists) {
                $opened++;
            } elseif ($previous->ended_at === null) {
                $closed++;
            }

            if (! $previous->exists || $previous->ended_at === null) {
                $previous->ended_at = $data->previousEventEndedAt;
                $previous->save();
            }
        }

        if ($data->latestEventStartedAt !== null) {
            $latest = SpillEvent::query()->firstOrNew([
                'overflow_monitor_id' => $monitor->id,
                'started_at' => $data->latestEventStartedAt,
            ]);

            $wasOpen = ! $latest->exists || $latest->ended_at === null;

            if (! $latest->exists) {
                $opened++;
            }

            $latest->ended_at = $this->resolveEndedAt($data);

            if ($latest->exists && $wasOpen && $latest->ended_at !== null) {
                $closed++;
            }

            $latest->save();
        }

        foreach ($monitor->openSpillEvents as $open) {
            if (($latest !== null && $open->is($latest)) || ($previous !== null && $open->is($previous))) {
                continue;
            }

            $open->update(['ended_at' => $this->fallbackEndedAt($data)->max($open->started_at)]);
            $closed++;
        }

        return [$opened, $closed];
    }

    private function resolveEndedAt(OverflowMonitorData $data): ?Carbon
    {
        if ($data->status === OverflowStatus::Discharging) {
            return null;
        }

        if ($data->latestEventEndedAt !== null && $data->latestEventStartedAt !== null
            && $data->latestEventEndedAt->greaterThanOrEqualTo($data->latestEventStartedAt)) {
            return $data->latestEventEndedAt;
        }

        return $this->fallbackEndedAt($data)->max($data->latestEventStartedAt);
    }

    /**
     * The best estimate of when an event ended when the feed does not say:
     * the start of the next event while discharging, otherwise when the
     * monitor's status last changed.
     */
    private function fallbackEndedAt(OverflowMonitorData $data): Carbon
    {
        if ($data->status === OverflowStatus::Discharging) {
            return $data->latestEventStartedAt ?? now();
        }

        return $data->statusStartedAt ?? $data->sourceUpdatedAt ?? now();
    }
}
