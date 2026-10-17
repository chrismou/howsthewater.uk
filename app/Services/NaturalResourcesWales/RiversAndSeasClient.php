<?php

namespace App\Services\NaturalResourcesWales;

use App\Data\GaugeReadingData;
use App\Data\GaugeStationData;
use App\Data\RainfallStationData;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Reads Welsh river level and rain gauges from Natural Resources Wales's
 * River level, Rainfall and Sea data API. One call lists every station with
 * its latest 15 minute reading, so each run reads that once (shared by the
 * level and rainfall ingests) and keeps the history call, which returns a
 * whole year for one gauge, for filling gaps and new gauges a few at a time.
 */
class RiversAndSeasClient
{
    private const STATION_DATA_PATH = 'rivers-and-seas/v1/api/StationData';

    /** Long enough for the level and rainfall ingests in one scheduler pass to share one read. */
    private const STATION_DATA_CACHE_SECONDS = 300;

    /** A typical range needs most of a year of readings to cover the seasons. */
    private const TYPICAL_RANGE_MINIMUM_DAYS = 180;

    public function __construct(private Api $api)
    {
    }

    public function isEnabled(): bool
    {
        return $this->api->isEnabled(Api::LEVELS);
    }

    /**
     * Every NRW river level gauge, with the river named in its title.
     *
     * @return Collection<int, GaugeStationData>
     */
    public function levelStations(): Collection
    {
        return collect($this->parameters('River Level'))
            ->map(fn (array $pair) => GaugeStationData::fromNaturalResourcesWales($pair['station'], $pair['parameter']))
            ->values();
    }

    /**
     * @return Collection<int, RainfallStationData>
     */
    public function rainfallStations(): Collection
    {
        return collect($this->parameters('Rainfall'))
            ->map(fn (array $pair) => RainfallStationData::fromNaturalResourcesWales($pair['station'], $pair['parameter']))
            ->values();
    }

    /**
     * New readings for each series after its own point in time, keyed by
     * parameter id (our measure_id). Every series gets its latest reading
     * from the station list; a series whose latest reading leaves a gap of
     * more than $gapMinutes after its point in time (or has none stored) is
     * filled from its history, longest gap first, up to $maxHistoryCalls and
     * while the allowance lasts. The rest wait for a later run.
     *
     * @param  array<string, array{externalId: string, since: Carbon}>  $series
     * @param  (Closure(string, Collection<int, GaugeReadingData>): void)|null  $onHistory  Given each history read, e.g. for its typical range
     * @return array<string, Collection<int, GaugeReadingData>>
     */
    public function readingsSince(array $series, int $gapMinutes, int $maxHistoryCalls, ?Closure $onHistory = null): array
    {
        $latest = $this->latestReadings();
        $readings = [];
        $gaps = [];

        foreach ($series as $parameter => ['since' => $since]) {
            $reading = $latest[(string) $parameter] ?? null;
            $readings[(string) $parameter] = $reading !== null && $reading->measuredAt->gte($since) ? collect([$reading]) : collect();

            if ($reading !== null && $reading->measuredAt->gt($since->copy()->addMinutes($gapMinutes))) {
                $gaps[(string) $parameter] = $since->getTimestamp();
            }
        }

        asort($gaps);

        foreach (array_slice(array_keys($gaps), 0, $maxHistoryCalls) as $parameter) {
            if (! $this->api->hasBudget(Api::LEVELS)) {
                break;
            }

            try {
                $history = $this->history($series[$parameter]['externalId'], (string) $parameter);
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                Log::error("Natural Resources Wales history request failed: {$exception->getMessage()}");

                break;
            }

            if ($onHistory !== null) {
                $onHistory((string) $parameter, $history);
            }

            $since = $series[$parameter]['since'];
            $readings[(string) $parameter] = $history
                ->concat($readings[(string) $parameter])
                ->filter(fn (GaugeReadingData $reading) => $reading->measuredAt->gte($since))
                ->unique(fn (GaugeReadingData $reading) => $reading->measuredAt->getTimestamp())
                ->values();
        }

        return $readings;
    }

    /**
     * Every reading NRW holds for one gauge's parameter, about a year at 15
     * minute intervals. The readings follow the parameter alone, so it must
     * be the gauge's own.
     *
     * @return Collection<int, GaugeReadingData>
     */
    public function history(string $externalId, string $parameter): Collection
    {
        $body = $this->api->get(Api::LEVELS, self::STATION_DATA_PATH.'/historical', [
            'location' => substr($externalId, strlen(GaugeStationData::NRW_PREFIX)),
            'parameter' => $parameter,
        ]);

        return collect(is_array($body) && is_array($body['parameterReadings'] ?? null) ? $body['parameterReadings'] : [])
            ->filter(fn (mixed $row) => is_array($row) && is_string($row['time'] ?? null) && is_numeric($row['value'] ?? null))
            ->map(fn (array $row) => new GaugeReadingData(Carbon::parse($row['time'])->utc(), (float) $row['value']))
            ->values();
    }

    /**
     * The band a gauge sits in 90% of the time: its 5th to 95th percentile
     * level, from at least half a year of readings, the way the Environment
     * Agency describes its typical range. Null when there is too little.
     *
     * @param  Collection<int, GaugeReadingData>  $readings
     * @return array{low: float, high: float}|null
     */
    public static function typicalRange(Collection $readings): ?array
    {
        $times = $readings->map(fn (GaugeReadingData $reading) => $reading->measuredAt->getTimestamp());

        if ($readings->isEmpty() || $times->max() - $times->min() < self::TYPICAL_RANGE_MINIMUM_DAYS * 86400) {
            return null;
        }

        $values = $readings->map(fn (GaugeReadingData $reading) => $reading->value)->sort()->values();
        $low = (float) $values[(int) floor(($values->count() - 1) * 0.05)];
        $high = (float) $values[(int) floor(($values->count() - 1) * 0.95)];

        return $high > $low ? ['low' => round($low, 3), 'high' => round($high, 3)] : null;
    }

    /**
     * Each parameter's latest reading, keyed by parameter id.
     *
     * @return array<string, GaugeReadingData>
     */
    private function latestReadings(): array
    {
        $readings = [];

        foreach ($this->stationData() as $station) {
            foreach ($station['parameters'] ?? [] as $parameter) {
                if (is_array($parameter) && isset($parameter['parameter']) && is_string($parameter['latestTime'] ?? null) && is_numeric($parameter['latestValue'] ?? null)) {
                    $readings[(string) $parameter['parameter']] = new GaugeReadingData(Carbon::parse($parameter['latestTime'])->utc(), (float) $parameter['latestValue']);
                }
            }
        }

        return $readings;
    }

    /**
     * Each station's parameters of one kind ("River Level", "Rainfall"), with the station they belong to.
     *
     * @return list<array{station: array<string, mixed>, parameter: array<array-key, mixed>}>
     */
    private function parameters(string $name): array
    {
        $pairs = [];

        foreach ($this->stationData() as $station) {
            if (! is_numeric($station['latitude'] ?? null) || ! is_numeric($station['longitude'] ?? null) || ! isset($station['location'])) {
                continue;
            }

            foreach (is_array($station['parameters'] ?? null) ? $station['parameters'] : [] as $parameter) {
                if (is_array($parameter) && ($parameter['paramNameEN'] ?? null) === $name && isset($parameter['parameter'])) {
                    $pairs[] = ['station' => $station, 'parameter' => $parameter];
                }
            }
        }

        return $pairs;
    }

    /**
     * Every station with its parameters and their latest readings, read at most once per scheduler pass.
     *
     * @return array<int, array<string, mixed>>
     */
    private function stationData(): array
    {
        return Cache::remember('nrw:station-data', self::STATION_DATA_CACHE_SECONDS, function () {
            $body = $this->api->get(Api::LEVELS, self::STATION_DATA_PATH);

            if (! is_array($body) || ! array_is_list($body)) {
                throw new RuntimeException('Natural Resources Wales station data: unexpected response');
            }

            return array_values(array_filter($body, 'is_array'));
        });
    }
}
