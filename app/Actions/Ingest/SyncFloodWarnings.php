<?php

namespace App\Actions\Ingest;

use App\Data\FloodWarningData;
use App\Enums\Agency;
use App\Models\FloodArea;
use App\Models\FloodWarning;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use Illuminate\Support\Collection;

/**
 * Replaces the stored warnings with those in force now for the flood areas
 * our spots use, one agency's feed at a time. Each feed lists every warning
 * in England (or Wales, or Scotland) in one small response, so each run reads
 * it whole; a warning missing from its agency's feed, or marked no longer in
 * force, has been lifted and is removed. A warning is confirmed as of the
 * poll, or of the feed's own publication time where it carries one (SEPA's
 * layers), so a feed that stops refreshing goes stale on the pages.
 */
class SyncFloodWarnings
{
    public function __construct(private FloodMonitoringClient $client)
    {
    }

    /**
     * @param  Collection<int, FloodWarningData>|null  $feed  The agency's national feed, if already fetched (the EA's is fetched otherwise)
     * @return array{warnings: int}
     */
    public function handle(?Collection $feed = null, Agency $agency = Agency::EnvironmentAgency): array
    {
        $inForce = ($feed ?? $this->client->floodWarnings())
            ->filter(fn (FloodWarningData $warning) => $warning->isInForce())
            ->keyBy('floodAreaCode');

        $areas = FloodArea::query()->where('agency', $agency)->whereHas('spots')->whereIn('code', $inForce->keys())->get();

        foreach ($areas as $area) {
            /** @var FloodWarningData $data */
            $data = $inForce[$area->code];

            FloodWarning::query()->updateOrCreate(
                ['flood_area_id' => $area->id],
                [
                    'severity_level' => $data->severity,
                    'message' => $data->message,
                    'raised_at' => $data->raisedAt,
                    'message_changed_at' => $data->messageChangedAt,
                    'checked_at' => $data->publishedAt?->min(now()) ?? now(),
                ],
            );
        }

        FloodWarning::query()
            ->whereHas('floodArea', fn ($query) => $query->where('agency', $agency))
            ->whereNotIn('flood_area_id', $areas->pluck('id'))
            ->delete();

        return ['warnings' => $areas->count()];
    }
}
