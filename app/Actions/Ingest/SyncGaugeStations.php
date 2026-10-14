<?php

namespace App\Actions\Ingest;

use App\Models\GaugeStation;
use App\Models\River;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use App\Services\Geo\PolylineProjector;

/**
 * Discovers Environment Agency level gauges on each river we cover and
 * keeps their metadata current.
 */
class SyncGaugeStations
{
    /**
     * The Environment Agency names rivers nationally, so a search for
     * "River Calder" also returns Lancashire and Cumbria. Stations further
     * than this from our centreline belong to one of those.
     */
    private const MAX_OFFSET_METRES = 2000;

    public function __construct(private FloodMonitoringClient $client) {}

    /**
     * @return array{stations: int}
     */
    public function handle(River $river): array
    {
        $count = 0;
        $projector = $river->hasCentreline() ? new PolylineProjector($river->centreline) : null;

        foreach ($river->aliases ?? [] as $alias) {
            foreach ($this->client->stationsOnRiver($alias) as $data) {
                if ($projector !== null && $projector->project($data->latitude, $data->longitude)->offsetMetres > self::MAX_OFFSET_METRES) {
                    continue;
                }

                GaugeStation::query()->updateOrCreate(
                    ['external_id' => $data->externalId],
                    [
                        'river_id' => $river->id,
                        'label' => $data->label,
                        'river_name' => $data->riverName,
                        'town' => $data->town,
                        'latitude' => $data->latitude,
                        'longitude' => $data->longitude,
                        'measure_id' => $data->measureId,
                        'unit' => $data->unit,
                        'typical_range_low' => $data->typicalRangeLow,
                        'typical_range_high' => $data->typicalRangeHigh,
                        'is_active' => $data->isActive,
                    ],
                );

                $count++;
            }
        }

        return ['stations' => $count];
    }
}
