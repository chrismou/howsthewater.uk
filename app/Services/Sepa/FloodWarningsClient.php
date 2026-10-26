<?php

namespace App\Services\Sepa;

use App\Data\FloodAreaData;
use App\Data\FloodWarningData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Reads SEPA's live flood alerts and warnings from the two public ArcGIS Online layers
 * behind its Floodline map, which carry each area's current status (severity, message
 * and times) joined onto the area outlines. SEPA publishes no documented feed without a
 * key, so these layers are read as the map reads them, and every response is checked
 * against the fields and values relied on: a changed layer throws rather than quietly
 * reading nulls, and the ingest logs that at error level.
 */
class FloodWarningsClient
{
    /** SEPA's regional flood alert areas (Tayside, Fife...), with the live alert joined on. */
    public const ALERT_FIELDS = ['name', 'id', 'severity', 'message', 'startTime', 'endTime', 'updatedTime'];

    /** SEPA's flood warning areas (a town or a stretch of river), with the live warning joined on. */
    public const WARNING_FIELDS = ['fwa_name', 'map_id', 'scheme', 'source_of_flood', 'local_authority', 'severity', 'message', 'startTime', 'endTime', 'updatedTime'];

    /**
     * How long the layers can go without SEPA's refresh rewriting them before their
     * warnings count as out of date, the same as the pages' own threshold. It rewrites
     * them every few minutes while anything is in force.
     */
    public const REFRESH_STALE_AFTER_HOURS = 6;

    /**
     * How far ahead of our clock a layer's times may run before they cannot be trusted
     * (a clock or time zone slip on either side).
     */
    public const REFRESH_FUTURE_MINUTES = 15;

    /**
     * Scotland, padded slightly; places outside it are not searched. South of the Solway
     * the border runs north-east from Gretna to Berwick, so east of Gretna the southern
     * edge is that line (BORDER_SOUTH plus BORDER_SLOPE per degree east of BORDER_WEST),
     * which keeps the Tyne, Eden and the Till out and the Tweed in.
     *
     * @var array{south: float, west: float, north: float, east: float}
     */
    private const AREA_EXTENT = ['south' => 54.75, 'west' => -8.80, 'north' => 61.00, 'east' => -0.50];

    private const BORDER_WEST = -3.05;

    private const BORDER_SOUTH = 54.99;

    private const BORDER_SLOPE = 0.804;

    public function isEnabled(): bool
    {
        return (bool) config('services.sepa.flood_warnings.enabled');
    }

    /**
     * Whether a place could sit in or beside one of SEPA's areas.
     */
    public function covers(float $latitude, float $longitude): bool
    {
        $south = $longitude <= self::BORDER_WEST
            ? self::AREA_EXTENT['south']
            : self::BORDER_SOUTH + ($longitude - self::BORDER_WEST) * self::BORDER_SLOPE;

        return $latitude >= $south && $latitude <= self::AREA_EXTENT['north']
            && $longitude >= self::AREA_EXTENT['west'] && $longitude <= self::AREA_EXTENT['east'];
    }

    /**
     * Every area with a current status: the alerts and warnings in force, and those SEPA
     * marks no longer in force for the 12 hours after lifting them.
     *
     * @return Collection<int, FloodWarningData>
     */
    public function floodWarnings(): Collection
    {
        $current = ['where' => 'severity IS NOT NULL'];

        return $this->alertAreas($current)
            ->map(fn (array $attributes) => FloodWarningData::fromSepa($attributes, isAlertArea: true))
            ->concat($this->warningAreas($current)->map(fn (array $attributes) => FloodWarningData::fromSepa($attributes, isAlertArea: false)))
            ->values();
    }

    /**
     * When SEPA's service last wrote to the layers (the later of the two), from each
     * layer's own editing record: a UTC epoch in milliseconds, so no time zone is read
     * into it. It ticks every few minutes whether or not any status changed, so it only
     * shows the service is alive; the rows' own updatedTime says how fresh the statuses
     * are, and this stands in for it when no area has a status at all.
     */
    public function lastRefreshedAt(): Carbon
    {
        $times = collect([config('services.sepa.flood_warnings.alert_areas_url'), config('services.sepa.flood_warnings.warning_areas_url')])
            ->map(fn (mixed $url) => $this->lastEditDate((string) $url));

        return $times->first()->max($times->last());
    }

    /**
     * Alert and warning areas whose outline comes within a radius of a point.
     *
     * @return Collection<int, FloodAreaData>
     */
    public function floodAreasNear(float $latitude, float $longitude, float $radiusKm): Collection
    {
        $near = [
            'geometry' => sprintf('%F,%F', $longitude, $latitude),
            'geometryType' => 'esriGeometryPoint',
            'inSR' => 4326,
            'spatialRel' => 'esriSpatialRelIntersects',
            'distance' => (int) round($radiusKm * 1000),
            'units' => 'esriSRUnit_Meter',
        ];

        return $this->alertAreas($near)
            ->map(fn (array $attributes) => FloodAreaData::fromSepa($attributes, isAlertArea: true))
            ->concat($this->warningAreas($near)->map(fn (array $attributes) => FloodAreaData::fromSepa($attributes, isAlertArea: false)))
            ->values();
    }

    /**
     * @param  array<string, string|int>  $query
     * @return Collection<int, array<string, mixed>>
     */
    private function alertAreas(array $query): Collection
    {
        return $this->query((string) config('services.sepa.flood_warnings.alert_areas_url'), self::ALERT_FIELDS, $query);
    }

    /**
     * @param  array<string, string|int>  $query
     * @return Collection<int, array<string, mixed>>
     */
    private function warningAreas(array $query): Collection
    {
        return $this->query((string) config('services.sepa.flood_warnings.warning_areas_url'), self::WARNING_FIELDS, $query);
    }

    private function lastEditDate(string $layerUrl): Carbon
    {
        $body = $this->layerBody($layerUrl, rtrim($layerUrl, '/'), ['f' => 'json']);
        $milliseconds = is_array($body['editingInfo'] ?? null) ? ($body['editingInfo']['lastEditDate'] ?? null) : null;

        if (! is_int($milliseconds) && ! is_float($milliseconds)) {
            throw new RuntimeException("SEPA flood layer {$layerUrl} gives no last edit time (editingInfo.lastEditDate): its description has changed");
        }

        return Carbon::createFromTimestampMs((int) $milliseconds, 'UTC');
    }

    /**
     * The matching rows of a layer as attribute arrays. ArcGIS answers a bad request (an
     * unknown field, say) with a 200 and an error body, so the body is checked; the
     * fields it lists (left out when no row matches) and each row's own keys are compared
     * with those expected, so a renamed field is noticed rather than read as null.
     *
     * @param  array<int, string>  $fields
     * @param  array<string, string|int>  $query
     * @return Collection<int, array<string, mixed>>
     */
    private function query(string $layerUrl, array $fields, array $query): Collection
    {
        $body = $this->layerBody($layerUrl, rtrim($layerUrl, '/').'/query', [
            'where' => '1=1',
            ...$query,
            'outFields' => implode(',', $fields),
            'returnGeometry' => 'false',
            'f' => 'json',
        ]);

        if (is_array($body['fields'] ?? null)) {
            $missing = array_diff($fields, collect($body['fields'])->pluck('name')->all());

            if ($missing !== []) {
                throw new RuntimeException(sprintf('SEPA flood layer %s no longer has the fields %s: its schema has changed', $layerUrl, implode(', ', $missing)));
            }
        }

        if (! empty($body['exceededTransferLimit'])) {
            throw new RuntimeException("SEPA flood layer {$layerUrl} returned more rows than one page holds");
        }

        if (! is_array($body['features'] ?? null)) {
            throw new RuntimeException("SEPA flood layer {$layerUrl} returned no features list");
        }

        $rows = [];

        foreach ($body['features'] as $feature) {
            if (! is_array($feature) || ! is_array($feature['attributes'] ?? null)) {
                throw new RuntimeException("SEPA flood layer {$layerUrl} returned a feature without attributes");
            }

            // Attribute names are the field names, so only string keys are kept.
            $attributes = array_filter($feature['attributes'], 'is_string', ARRAY_FILTER_USE_KEY);
            $missing = array_diff($fields, array_keys($attributes));

            if ($missing !== []) {
                throw new RuntimeException(sprintf('SEPA flood layer %s returned a row without the fields %s: its schema has changed', $layerUrl, implode(', ', $missing)));
            }

            $rows[] = $attributes;
        }

        return collect($rows);
    }

    /**
     * A layer's JSON answer, checked for ArcGIS's 200-with-error bodies.
     *
     * @param  array<string, string|int>  $query
     * @return array<string, mixed>
     */
    private function layerBody(string $layerUrl, string $url, array $query): array
    {
        if (! str_starts_with($layerUrl, 'https://')) {
            throw new RuntimeException("SEPA flood layer {$layerUrl} is not an https URL");
        }

        $body = $this->request()->get($url, $query)->throw()->json();

        if (! is_array($body)) {
            throw new RuntimeException("SEPA flood layer {$layerUrl} did not return JSON");
        }

        if (isset($body['error'])) {
            $error = is_array($body['error']) ? $body['error'] : ['message' => $body['error']];

            throw new RuntimeException(sprintf(
                'SEPA flood layer %s returned error %s: %s',
                $layerUrl,
                is_scalar($error['code'] ?? null) ? $error['code'] : 'unknown',
                trim((is_scalar($error['message'] ?? null) ? $error['message'] : '').' '.implode(' ', array_filter((array) ($error['details'] ?? []), 'is_scalar'))),
            ));
        }

        return array_filter($body, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /**
     * Retried only on connection failures and server errors: a 4xx is a bad request
     * (a changed layer, say), which a retry would not mend and should be reported at
     * once, and a redirect (passed in as null, since it is no error) is left to fail as
     * a body that is not JSON.
     */
    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(30)
            ->withoutRedirecting()
            ->retry(2, 2000, fn (?Throwable $exception) => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && $exception->response->serverError()))
            ->withUserAgent(config('services.user_agent'));
    }
}
