<?php

namespace App\Actions\Ingest;

use App\Data\FloodWarningData;
use App\Models\FloodArea;
use App\Models\FloodWarningEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the flood warning history from each poll of the national feed,
 * for every area in England rather than only those our spots use, so a
 * region added later already has its history. An area's event stays open
 * while it is in force at the same severity; a change of severity closes
 * it and opens another, and the EA marking it "no longer in force" (or it
 * leaving the feed) closes it.
 */
class RecordFloodWarningHistory
{
    /**
     * @param  Collection<int, FloodWarningData>  $feed
     * @return array{opened: int, closed: int}
     */
    public function handle(Collection $feed): array
    {
        $now = now();
        $feed = $feed->keyBy('floodAreaCode');
        $areas = $this->areasFor($feed);

        // A plain collection, since an Eloquent collection's except() filters by model id rather than by our keys.
        $open = FloodWarningEvent::query()->whereNull('ended_at')->with('floodArea:id,code')->get()->toBase()
            ->keyBy(fn (FloodWarningEvent $event) => $event->floodArea->code);
        $counts = ['opened' => 0, 'closed' => 0];

        foreach ($feed as $code => $warning) {
            $event = $open->get($code);

            if ($event !== null && (! $warning->isInForce() || $warning->severity !== $event->severity_level)) {
                $this->close($event, $warning->severityChangedAt ?? $now);
                $counts['closed']++;
                $event = null;
            }

            if (! $warning->isInForce()) {
                continue;
            }

            if ($event === null) {
                $event = new FloodWarningEvent([
                    'flood_area_id' => $areas[$code]->id,
                    'severity_level' => $warning->severity,
                    'started_at' => $warning->severityChangedAt ?? $warning->raisedAt ?? $now,
                ]);
                $counts['opened']++;
            }

            $event->fill([
                'last_seen_at' => $now,
                'message' => $warning->message,
                'message_changed_at' => $warning->messageChangedAt,
            ])->save();
        }

        // A warning that drops out of the feed without a "no longer in force" entry was lifted since the last poll.
        $open->except($feed->keys()->all())->each(function (FloodWarningEvent $event) use ($now, &$counts) {
            $this->close($event, $now);
            $counts['closed']++;
        });

        return $counts;
    }

    /**
     * The flood area for every warning in force, creating any we have not
     * seen; areas found by the spot search keep their own label.
     *
     * @param  Collection<string, FloodWarningData>  $feed
     * @return Collection<string, FloodArea>
     */
    private function areasFor(Collection $feed): Collection
    {
        $inForce = $feed->filter(fn (FloodWarningData $warning) => $warning->isInForce());
        $areas = FloodArea::query()->whereIn('code', $inForce->keys())->get()->toBase()->keyBy('code');

        foreach ($inForce->except($areas->keys()->all()) as $code => $warning) {
            $areas[$code] = FloodArea::query()->create([
                'code' => $code,
                'label' => $warning->areaLabel ?? $code,
                'river_or_sea' => $warning->riverOrSea,
                'county' => $warning->county,
            ]);
        }

        return $areas;
    }

    private function close(FloodWarningEvent $event, Carbon $at): void
    {
        $event->update(['ended_at' => $at->max($event->started_at)]);
    }
}
