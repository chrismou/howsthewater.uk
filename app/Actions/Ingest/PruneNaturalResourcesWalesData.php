<?php

namespace App\Actions\Ingest;

use App\Enums\Agency;
use App\Models\FloodWarningEvent;
use App\Models\GaugeReading;
use App\Models\RainfallReading;

/**
 * Natural Resources Wales asks that data from its API is not kept for more
 * than a year, so its level and rainfall readings, and the Welsh flood
 * warning history built from its feed, are deleted once they pass that.
 * Typical ranges worked out from its history are summaries, not its data,
 * so they stay.
 */
class PruneNaturalResourcesWalesData
{
    public const KEEP_DAYS = 365;

    /**
     * @return array{levels: int, rainfall: int, flood_warnings: int}
     */
    public function handle(): array
    {
        $cutoff = now()->subDays(self::KEEP_DAYS);

        return [
            'levels' => GaugeReading::query()
                ->whereHas('gaugeStation', fn ($query) => $query->where('network', Agency::NaturalResourcesWales))
                ->where('measured_at', '<', $cutoff)
                ->delete(),
            'rainfall' => RainfallReading::query()
                ->whereHas('rainfallStation', fn ($query) => $query->where('network', Agency::NaturalResourcesWales))
                ->where('measured_at', '<', $cutoff)
                ->delete(),
            'flood_warnings' => FloodWarningEvent::query()
                ->whereHas('floodArea', fn ($query) => $query->where('agency', Agency::NaturalResourcesWales))
                ->whereNotNull('ended_at')
                ->where('ended_at', '<', $cutoff)
                ->delete(),
        ];
    }
}
