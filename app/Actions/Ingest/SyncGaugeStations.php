<?php

namespace App\Actions\Ingest;

use App\Data\GaugeStationData;
use App\Enums\Agency;
use App\Models\GaugeStation;
use App\Models\River;
use App\Services\EnvironmentAgency\FloodMonitoringClient;
use App\Services\Geo\Coordinates;
use App\Services\Geo\PolylineProjector;
use App\Services\NaturalResourcesWales\RiversAndSeasClient;
use App\Services\Sepa\TimeSeriesClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Discovers level gauges on each river we cover and keeps their metadata
 * current: Environment Agency gauges in England, SEPA gauges in Scotland
 * and Natural Resources Wales gauges in Wales.
 */
class SyncGaugeStations
{
    /**
     * The Environment Agency names rivers nationally, so a search for
     * "River Calder" also returns Lancashire and Cumbria. Stations further
     * than this from our centreline belong to one of those.
     */
    private const MAX_OFFSET_METRES = 2000;

    /**
     * Border gauges such as Norham on the Tweed are published by two
     * agencies; the Environment Agency copy is kept and SEPA's or NRW's skipped.
     */
    private const SHARED_GAUGE_METRES = 200;

    /** SEPA is down for the whole run once its station list fails, so the failure is logged once. */
    private bool $sepaFailed = false;

    /** Likewise for NRW's station list. */
    private bool $nrwFailed = false;

    public function __construct(private FloodMonitoringClient $client, private TimeSeriesClient $sepa, private RiversAndSeasClient $nrw)
    {
    }

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

                $this->store($river, $data);
                $count++;
            }
        }

        $environmentAgencyGauges = GaugeStation::query()->where('river_id', $river->id)->where('network', Agency::EnvironmentAgency)->get();

        $sepaStations = $this->onCentrelineAndNotShared($this->sepaStationsOn($river), $projector, $environmentAgencyGauges);
        $ranges = $this->sepaTypicalRanges($sepaStations);

        foreach ($sepaStations as $data) {
            $this->store($river, $data, $ranges[$data->externalId] ?? null);
            $count++;
        }

        // NRW's station list has no typical range; SyncGaugeReadings works one out from each gauge's history.
        foreach ($this->onCentrelineAndNotShared($this->nrwStationsOn($river), $projector, $environmentAgencyGauges) as $data) {
            $this->store($river, $data);
            $count++;
        }

        return ['stations' => $count];
    }

    /**
     * @param  Collection<int, GaugeStationData>  $stations
     * @param  EloquentCollection<int, GaugeStation>  $environmentAgencyGauges
     * @return Collection<int, GaugeStationData>
     */
    private function onCentrelineAndNotShared(Collection $stations, ?PolylineProjector $projector, EloquentCollection $environmentAgencyGauges): Collection
    {
        return $stations
            ->filter(fn (GaugeStationData $data) => $projector === null || $projector->project($data->latitude, $data->longitude)->offsetMetres <= self::MAX_OFFSET_METRES)
            ->reject(fn (GaugeStationData $data) => $environmentAgencyGauges->contains(
                fn (GaugeStation $gauge) => Coordinates::distanceMetres($gauge->latitude, $gauge->longitude, $data->latitude, $data->longitude) <= self::SHARED_GAUGE_METRES,
            ))
            ->values();
    }

    /**
     * SEPA gauges whose river name matches this river. SEPA names rivers
     * without the "River" prefix ("Tweed", "Teviot") but keeps "Water".
     *
     * @return Collection<int, GaugeStationData>
     */
    private function sepaStationsOn(River $river): Collection
    {
        if ($this->sepaFailed) {
            return collect();
        }

        try {
            return $this->sepa->levelStations()
                ->filter(fn (GaugeStationData $data) => $river->matchesBareName($data->riverName));
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->sepaFailed = true;
            Log::error("SEPA gauge discovery failed: {$exception->getMessage()}");

            return collect();
        }
    }

    /**
     * NRW gauges whose title names this river ("Wye at Glasbury"), skipped
     * while NRW's key is not set.
     *
     * @return Collection<int, GaugeStationData>
     */
    private function nrwStationsOn(River $river): Collection
    {
        if ($this->nrwFailed || ! $this->nrw->isEnabled()) {
            return collect();
        }

        try {
            return $this->nrw->levelStations()
                ->filter(fn (GaugeStationData $data) => $river->matchesBareName($data->riverName));
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            $this->nrwFailed = true;
            Log::error("Natural Resources Wales gauge discovery failed: {$exception->getMessage()}");

            return collect();
        }
    }

    /**
     * A failure here only costs the typical range, so the gauges are still stored.
     *
     * @param  Collection<int, GaugeStationData>  $stations
     * @return array<string, array{low: float, high: float}>
     */
    private function sepaTypicalRanges(Collection $stations): array
    {
        if ($stations->isEmpty()) {
            return [];
        }

        try {
            return $this->sepa->typicalRanges($stations);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::error("SEPA typical ranges failed: {$exception->getMessage()}");

            return [];
        }
    }

    /**
     * @param  array{low: float, high: float}|null  $sepaRange  SEPA's normal range, which its station list does not carry
     */
    private function store(River $river, GaugeStationData $data, ?array $sepaRange = null): void
    {
        $attributes = [
            'network' => $data->network,
            'rloi_id' => $data->rloiId,
            'river_id' => $river->id,
            'label' => $data->label,
            'river_name' => $data->riverName,
            'town' => $data->town,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'measure_id' => $data->measureId,
            'unit' => $data->unit,
            'is_active' => $data->isActive,
        ];

        // A SEPA gauge whose range lookup failed keeps the range it already has.
        if ($data->network === Agency::EnvironmentAgency || $sepaRange !== null) {
            $attributes['typical_range_low'] = $sepaRange['low'] ?? $data->typicalRangeLow;
            $attributes['typical_range_high'] = $sepaRange['high'] ?? $data->typicalRangeHigh;
        }

        GaugeStation::query()->updateOrCreate(['external_id' => $data->externalId], $attributes);
    }
}
