<?php

namespace App\Services\EnvironmentAgency;

use App\Data\FloodAreaData;
use App\Data\FloodWarningData;
use App\Data\GaugeReadingData;
use App\Data\GaugeStationData;
use App\Data\RainfallStationData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads river level and rain gauges, their readings, and flood alerts and
 * warnings from the Environment Agency's real-time flood monitoring API
 * (open data, no key required). Readings follow the API's own advice: one
 * call for every measure's latest reading each run, and a per-measure
 * "since" query only to fill a gap, since some gauges report in batches.
 */
class FloodMonitoringClient
{
    /** Long enough for the level and rainfall ingests in one scheduler pass to share one read. */
    private const LATEST_CACHE_SECONDS = 300;

    /**
     * @return Collection<int, GaugeStationData>
     */
    public function stationsOnRiver(string $riverName): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url('id/stations'), [
                'riverName' => $riverName,
                'parameter' => 'level',
                '_view' => 'full',
                '_limit' => 500,
            ])
            ->throw()
            ->json('items', []);

        return collect($items)
            ->map(fn (array $item) => GaugeStationData::fromEnvironmentAgency($item))
            ->filter(fn (GaugeStationData $station) => $station->measureId !== null)
            ->values();
    }

    /**
     * One station by its reference, or null when the EA no longer lists it.
     */
    public function station(string $reference): ?GaugeStationData
    {
        $response = $this->request()->get($this->url('id/stations/'.rawurlencode($reference)));

        if ($response->notFound()) {
            return null;
        }

        /** @var array<string, mixed> $item */
        $item = $response->throw()->json('items', []);

        return isset($item['lat'], $item['long']) ? GaugeStationData::fromEnvironmentAgency($item) : null;
    }

    /**
     * Rain gauges within a radius of a point.
     *
     * @return Collection<int, RainfallStationData>
     */
    public function rainfallStationsNear(float $latitude, float $longitude, int $radiusKm): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url('id/stations'), [
                'parameter' => 'rainfall',
                'lat' => $latitude,
                'long' => $longitude,
                'dist' => $radiusKm,
                '_view' => 'full',
                '_limit' => 1000,
            ])
            ->throw()
            ->json('items', []);

        return collect($items)
            ->map(fn (array $item) => RainfallStationData::fromEnvironmentAgency($item))
            ->values();
    }

    /**
     * New readings for each measure after its own point in time, keyed by
     * measure id. Every measure gets its latest reading from the one bulk
     * call; a measure whose latest reading leaves a gap of more than
     * $gapMinutes after its point in time (or has none stored) is filled
     * from its own readings, longest gap first, up to $maxCatchUps. The rest
     * wait for a later run, and a failing catch-up stops the others.
     *
     * @param  array<string, Carbon>  $sinceByMeasure
     * @return array<string, Collection<int, GaugeReadingData>>
     */
    public function readingsSinceInBulk(array $sinceByMeasure, int $gapMinutes, int $maxCatchUps): array
    {
        $latest = $this->latestReadings();
        $readings = [];
        $gaps = [];

        foreach ($sinceByMeasure as $measureId => $since) {
            $reading = $latest[(string) $measureId] ?? null;
            $readings[(string) $measureId] = $reading !== null && $reading->measuredAt->gt($since) ? collect([$reading]) : collect();

            if ($reading !== null && $reading->measuredAt->gt($since->copy()->addMinutes($gapMinutes))) {
                $gaps[(string) $measureId] = $since->getTimestamp();
            }
        }

        asort($gaps);

        foreach (array_slice(array_keys($gaps), 0, $maxCatchUps) as $measureId) {
            try {
                $readings[(string) $measureId] = $this->readingsSince((string) $measureId, $sinceByMeasure[$measureId]);
            } catch (ConnectionException|RequestException $exception) {
                Log::error("Environment Agency readings catch-up failed: {$exception->getMessage()}");

                break;
            }
        }

        return $readings;
    }

    /**
     * The latest reading of every measure in England, keyed by measure id,
     * from one call (about 5,000 readings), read at most once per scheduler pass.
     *
     * @return array<string, GaugeReadingData>
     */
    public function latestReadings(): array
    {
        // Cached as plain values, since the cache only unserialises its allowed classes.
        $rows = Cache::remember('ea:latest-readings', self::LATEST_CACHE_SECONDS, function () {
            /** @var array<int, array<string, mixed>> $items */
            $items = $this->request()
                ->get($this->url('data/readings'), ['latest' => ''])
                ->throw()
                ->json('items', []);

            return collect($items)
                ->filter(fn (array $item) => is_string($item['measure'] ?? null) && isset($item['dateTime'], $item['value']))
                ->mapWithKeys(fn (array $item) => [basename($item['measure']) => GaugeReadingData::fromEnvironmentAgency($item)])
                ->map(fn (GaugeReadingData $reading) => [$reading->measuredAt->toIso8601String(), $reading->value])
                ->all();
        });

        return collect($rows)
            ->map(fn (array $row) => new GaugeReadingData(Carbon::parse($row[0])->utc(), (float) $row[1]))
            ->all();
    }

    /**
     * @return Collection<int, GaugeReadingData>
     */
    public function readingsSince(string $measureId, Carbon $since, int $limit = 2000): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url("id/measures/{$measureId}/readings"), [
                'since' => $since->utc()->format('Y-m-d\TH:i:s\Z'),
                '_sorted' => '',
                '_limit' => $limit,
            ])
            ->throw()
            ->json('items', []);

        return collect($items)
            ->filter(fn (array $item) => isset($item['dateTime'], $item['value']))
            ->map(fn (array $item) => GaugeReadingData::fromEnvironmentAgency($item))
            ->values();
    }

    /**
     * Flood alert and warning areas whose outline comes within a radius of a point.
     *
     * @return Collection<int, FloodAreaData>
     */
    public function floodAreasNear(float $latitude, float $longitude, float $radiusKm): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url('id/floodAreas'), [
                'lat' => $latitude,
                'long' => $longitude,
                'dist' => $radiusKm,
                '_limit' => 500,
            ])
            ->throw()
            ->json('items', []);

        return collect($items)
            ->map(fn (array $item) => FloodAreaData::fromEnvironmentAgency($item))
            ->values();
    }

    /**
     * Every flood alert and warning in England issued in the last 24 hours,
     * including those since lifted. Usually a few dozen items at most.
     *
     * @return Collection<int, FloodWarningData>
     */
    public function floodWarnings(): Collection
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->request()
            ->get($this->url('id/floods'))
            ->throw()
            ->json('items', []);

        return collect($items)
            ->map(fn (array $item) => FloodWarningData::fromEnvironmentAgency($item))
            ->values();
    }

    private function url(string $path): string
    {
        return rtrim(config('services.environment_agency.flood_monitoring_url'), '/').'/'.ltrim($path, '/');
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(2, 2000)
            ->withUserAgent(config('services.user_agent'));
    }
}
