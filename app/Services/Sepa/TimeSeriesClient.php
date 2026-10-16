<?php

namespace App\Services\Sepa;

use App\Data\GaugeReadingData;
use App\Data\GaugeStationData;
use App\Data\RainfallStationData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads river level and rain gauges and their readings from SEPA's time
 * series API (KiWIS, open data under the OGL, no key required). Anonymous
 * use is capped at 5,000 credits a day: a list costs 1 credit and readings
 * 1 credit per 1,000 values, so readings are fetched for many gauges per
 * request and the national station lists are read once per run.
 */
class TimeSeriesClient
{
    /**
     * Gauges whose last stored reading is older than this are fetched on
     * their own, so one gauge that stops reporting does not make every
     * batched request reach back days.
     */
    private const BATCH_WITHIN_HOURS = 24;

    /** Keeps the request URL a sensible length. */
    private const SERIES_PER_REQUEST = 50;

    /** @var Collection<int, GaugeStationData>|null */
    private ?Collection $levelStations = null;

    /** @var Collection<int, RainfallStationData>|null */
    private ?Collection $rainfallStations = null;

    /**
     * Every SEPA gauge with a 15 minute stage series, with the river it is on.
     *
     * @return Collection<int, GaugeStationData>
     */
    public function levelStations(): Collection
    {
        if ($this->levelStations !== null) {
            return $this->levelStations;
        }

        $rivers = $this->table('getStationList', ['returnfields' => 'station_no,river_name'])->pluck('river_name', 'station_no');

        return $this->levelStations = $this->table('getTimeseriesList', [
            'parametertype_name' => 'S',
            'ts_name' => '15minute',
            'returnfields' => 'station_name,station_no,station_latitude,station_longitude,ts_id,ts_unitsymbol,coverage',
        ])
            ->filter(fn (array $row) => is_numeric($row['station_latitude'] ?? null) && is_numeric($row['station_longitude'] ?? null))
            ->map(fn (array $row) => GaugeStationData::fromSepa([...$row, 'river_name' => $rivers[$row['station_no']] ?? null]))
            ->values();
    }

    /**
     * SEPA's normal range for each gauge: the median annual minimum to the
     * median annual maximum of its stage, keyed by external id. Gauges
     * without enough history are left out.
     *
     * @param  Collection<int, GaugeStationData>  $stations
     * @return array<string, array{low: float, high: float}>
     */
    public function typicalRanges(Collection $stations): array
    {
        if ($stations->isEmpty()) {
            return [];
        }

        $series = $this->table('getTimeseriesList', [
            'parametertype_name' => 'S',
            'ts_name' => 'HydrologicalYear.M*',
            'station_no' => $stations->map(fn (GaugeStationData $station) => substr($station->externalId, 4))->implode(','),
            'returnfields' => 'station_no,ts_id,ts_name',
        ])->filter(fn (array $row) => in_array($row['ts_name'] ?? null, ['HydrologicalYear.Min', 'HydrologicalYear.Max'], true));

        $values = $this->values($series->pluck('ts_id')->all(), Carbon::create(1900));
        $medians = [];

        foreach ($series as $row) {
            $readings = collect($values[$row['ts_id']] ?? [])->map(fn (GaugeReadingData $reading) => $reading->value);

            if ($readings->isNotEmpty()) {
                $medians['SEPA'.$row['station_no']][$row['ts_name']] = (float) $readings->median();
            }
        }

        $ranges = [];

        foreach ($medians as $externalId => $median) {
            $low = $median['HydrologicalYear.Min'] ?? null;
            $high = $median['HydrologicalYear.Max'] ?? null;

            if ($low !== null && $high !== null && $high > $low) {
                $ranges[$externalId] = ['low' => round($low, 3), 'high' => round($high, 3)];
            }
        }

        return $ranges;
    }

    /**
     * Every SEPA rain gauge with a 15 minute total series.
     *
     * @return Collection<int, RainfallStationData>
     */
    public function rainfallStations(): Collection
    {
        return $this->rainfallStations ??= $this->table('getTimeseriesList', [
            'parametertype_name' => 'Precip',
            'ts_name' => '15minute.Total',
            'returnfields' => 'station_name,station_no,station_latitude,station_longitude,ts_id,coverage',
        ])
            ->filter(fn (array $row) => is_numeric($row['station_latitude'] ?? null) && is_numeric($row['station_longitude'] ?? null))
            ->map(fn (array $row) => RainfallStationData::fromSepa($row))
            ->values();
    }

    /**
     * New readings for each series after its own point in time, keyed by
     * series id. Series that are up to date share requests; the rest are
     * fetched one by one.
     *
     * @param  array<string, Carbon>  $sinceBySeries
     * @return array<string, Collection<int, GaugeReadingData>>
     */
    public function readingsSince(array $sinceBySeries): array
    {
        $recent = array_filter($sinceBySeries, fn (Carbon $since) => $since->isAfter(now()->subHours(self::BATCH_WITHIN_HOURS)));
        $values = $recent === [] ? [] : $this->values(array_keys($recent), min($recent));

        foreach (array_diff_key($sinceBySeries, $recent) as $seriesId => $since) {
            $values += $this->values([(string) $seriesId], $since);
        }

        $readings = [];

        foreach ($sinceBySeries as $seriesId => $since) {
            $readings[$seriesId] = collect($values[$seriesId] ?? [])
                ->filter(fn (GaugeReadingData $reading) => $reading->measuredAt->gte($since))
                ->values();
        }

        return $readings;
    }

    /**
     * Values for several series from a point in time, keyed by series id.
     *
     * @param  array<int, string>  $seriesIds
     * @return array<string, array<int, GaugeReadingData>>
     */
    private function values(array $seriesIds, Carbon $from): array
    {
        $values = [];

        foreach (array_chunk($seriesIds, self::SERIES_PER_REQUEST) as $chunk) {
            $response = $this->query('getTimeseriesValues', [
                'ts_id' => implode(',', $chunk),
                'from' => $from->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
                'returnfields' => 'Timestamp,Value',
            ]);

            foreach ($response as $series) {
                if (! is_array($series) || ! isset($series['ts_id']) || ! is_array($series['data'] ?? null)) {
                    continue;
                }

                $values[(string) $series['ts_id']] = collect($series['data'])
                    ->filter(fn (mixed $row) => is_array($row) && isset($row[0], $row[1]) && is_numeric($row[1]))
                    ->map(fn (array $row) => GaugeReadingData::fromSepa($row))
                    ->values()
                    ->all();
            }
        }

        return $values;
    }

    /**
     * A list request, whose first row names the columns, as one keyed array per row.
     *
     * @param  array<string, string>  $parameters
     * @return Collection<int, array<string, mixed>>
     */
    private function table(string $request, array $parameters): Collection
    {
        $rows = $this->query($request, $parameters);
        $columns = array_shift($rows);

        if (! is_array($columns)) {
            return collect();
        }

        $columns = array_map(fn (mixed $column) => is_scalar($column) ? (string) $column : '', $columns);

        return collect($rows)
            ->filter(fn (mixed $row) => is_array($row) && count($row) === count($columns))
            ->map(fn (array $row) => array_combine($columns, $row))
            ->values();
    }

    /**
     * @param  array<string, string>  $parameters
     * @return array<int, mixed>
     */
    private function query(string $request, array $parameters): array
    {
        $body = $this->request()
            ->get(config('services.sepa.timeseries_url'), [
                'service' => 'kisters',
                'type' => 'queryServices',
                'datasource' => 0,
                'format' => 'json',
                'request' => $request,
                ...$parameters,
            ])
            ->throw()
            ->json();

        // KiWIS reports bad requests and exhausted credits as a JSON error object with a 200 status.
        if (is_array($body) && ($body['type'] ?? null) === 'error') {
            throw new RuntimeException('SEPA time series API: '.($body['message'] ?? 'unknown error'));
        }

        return is_array($body) && array_is_list($body) ? $body : [];
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(2, 2000)
            ->withUserAgent(config('services.user_agent'));
    }
}
