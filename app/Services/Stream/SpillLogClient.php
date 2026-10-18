<?php

namespace App\Services\Stream;

use App\Data\SpillLogRowData;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use RuntimeException;

/**
 * Reads the spill logs some companies publish on Stream (CC BY 4.0) as ArcGIS feature services, one row per spill:
 * Yorkshire Water's 2026 history, Wessex Water's yearly and monthly logs, Anglian Water's monthly ones and others.
 * They are found through Stream's catalogue, as companies add a service a month or a year.
 */
class SpillLogClient
{
    private const PAGE_SIZE = 2000;

    private const DATE_TYPES = ['esriFieldTypeDate', 'esriFieldTypeTimestampOffset'];

    /** The catalogue search returns at most this many, so more matches than this would be missed. */
    private const CATALOGUE_LIMIT = 100;

    /**
     * The catalogue's event duration monitoring services, leaving out the live activity feeds. Only services on the
     * allowed ArcGIS hosts are read, over HTTPS, since the catalogue's entries decide which URLs the server fetches.
     *
     * @return Collection<int, array{url: string, title: string}>
     */
    public function services(): Collection
    {
        $response = $this->request()->get((string) config('services.overflow_spill_logs.stream_catalogue_url'), [
            'q' => '"event duration monitoring"',
            'limit' => self::CATALOGUE_LIMIT,
        ])->throw();
        $items = $this->items($response->json('features'));

        if ((int) $response->json('numberMatched', 0) > count($items)) {
            Log::error(sprintf('Stream catalogue matched %d spill logs but returned %d; the rest were not read.', (int) $response->json('numberMatched'), count($items)));
        }

        return collect($items)
            ->map(fn (array $feature) => is_array($feature['properties'] ?? null) ? $feature['properties'] : [])
            ->filter(fn (array $item) => ($item['type'] ?? null) === 'Feature Service'
                && is_string($item['url'] ?? null)
                && $this->isAllowed($item['url'])
                && preg_match('/event duration monitoring|\bEDM\b/i', (string) ($item['title'] ?? '')) === 1
                && preg_match('/overflow activity/i', (string) ($item['title'] ?? '')) !== 1)
            ->map(fn (array $item) => ['url' => rtrim($item['url'], '/'), 'title' => (string) $item['title']])
            ->unique('url')
            ->values();
    }

    /**
     * An HTTPS URL on one of services.overflow_spill_logs.stream_hosts (or a subdomain), with no credentials or port.
     */
    private function isAllowed(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? null) !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || $host === '') {
            return false;
        }

        return collect(config()->array('services.overflow_spill_logs.stream_hosts'))
            ->contains(fn (mixed $allowed) => is_string($allowed) && ($host === $allowed || str_ends_with($host, ".{$allowed}")));
    }

    /**
     * A service's first layer, if it is a spill log we can read: an id field and start and stop fields held as
     * dates (SiteId, EventStart and EventEnd on most; SITE_CODE, START_DATE_TIME and END_DATE_TIME on Anglian Water's
     * older months). Logs that keep their dates as text (Northumbrian Water's 2020-2024, Severn Trent's 2025) are left
     * out; the Environment Agency's files cover those years.
     *
     * @return array{url: string, name: string, fingerprint: string, idField: string, siteField: string, startField: string, endField: string}|null
     */
    public function layer(string $serviceUrl): ?array
    {
        $service = $this->json($this->request()->get($serviceUrl, ['f' => 'json']));
        $layerId = data_get($service, 'layers.0.id', data_get($service, 'tables.0.id'));

        if ($layerId === null) {
            return null;
        }

        $url = "{$serviceUrl}/{$layerId}";
        $layer = $this->json($this->request()->get($url, ['f' => 'json']));
        $fields = collect($this->items($layer['fields'] ?? null));
        $field = fn (string $pattern, bool $date) => $fields->first(fn (array $field) => preg_match($pattern, (string) ($field['name'] ?? '')) === 1
            && (! $date || in_array($field['type'] ?? null, self::DATE_TYPES, true)))['name'] ?? null;

        $idField = $layer['objectIdField'] ?? $fields->first(fn (array $field) => ($field['type'] ?? null) === 'esriFieldTypeOID')['name'] ?? null;
        $siteField = $field('/^site_?(id|code)$/i', false);
        $startField = $field('/^(event_?start|start_?date_?time)$/i', true);
        $endField = $field('/^(event_?(end|stop)|end_?date_?time)$/i', true);

        if (! is_string($idField) || preg_match('/^\w+$/', $idField) !== 1 || $siteField === null || $startField === null || $endField === null) {
            return null;
        }

        return [
            'url' => $url,
            'name' => (string) ($layer['name'] ?? $url),
            'fingerprint' => (string) data_get($layer, 'editingInfo.dataLastEditDate', data_get($layer, 'editingInfo.lastEditDate', '')),
            'idField' => $idField,
            'siteField' => $siteField,
            'startField' => $startField,
            'endField' => $endField,
        ];
    }

    /**
     * The earliest and latest spill start in a log, leaving out any dated in the future (Anglian Water's March 2026
     * has one in December), how many spills it holds, and a sample of its overflow ids, to tell whose log it is. Null
     * for an empty log.
     *
     * @param  array{url: string, siteField: string, startField: string}  $layer
     * @return array{from: int, until: int, count: int, siteIds: list<string>}|null
     */
    public function range(array $layer): ?array
    {
        $stats = $this->json($this->request()->get("{$layer['url']}/query", [
            'where' => "{$layer['startField']} <= TIMESTAMP '".now()->utc()->format('Y-m-d H:i:s')."'",
            'outStatistics' => json_encode([
                ['statisticType' => 'min', 'onStatisticField' => $layer['startField'], 'outStatisticFieldName' => 'first_start'],
                ['statisticType' => 'max', 'onStatisticField' => $layer['startField'], 'outStatisticFieldName' => 'last_start'],
                ['statisticType' => 'count', 'onStatisticField' => $layer['startField'], 'outStatisticFieldName' => 'spills'],
            ]),
            'f' => 'json',
        ]));
        $sample = $this->json($this->request()->get("{$layer['url']}/query", [
            'where' => '1=1',
            'outFields' => $layer['siteField'],
            'returnGeometry' => 'false',
            'resultRecordCount' => 50,
            'f' => 'json',
        ]));

        $from = $this->timestamp(data_get($stats, 'features.0.attributes.first_start'));
        $until = $this->timestamp(data_get($stats, 'features.0.attributes.last_start'));
        $siteIds = collect($this->items($sample['features'] ?? null))
            ->map(fn (array $feature) => $feature['attributes'][$layer['siteField']] ?? null)
            ->filter(fn (mixed $id) => is_string($id) && trim($id) !== '')
            ->map(fn (string $id) => strtoupper(trim($id)))
            ->values()
            ->all();

        return $from === null || $until === null || $siteIds === [] ? null : [
            'from' => $from,
            'until' => $until,
            'count' => (int) data_get($stats, 'features.0.attributes.spills', 0),
            'siteIds' => $siteIds,
        ];
    }

    /**
     * Every spill in a log starting at or after $since, a page at a time, in object id order so no page skips or
     * repeats a row.
     *
     * @param  array{url: string, idField: string, siteField: string, startField: string, endField: string}  $layer
     * @return LazyCollection<int, SpillLogRowData>
     */
    public function rows(array $layer, int $since): LazyCollection
    {
        return LazyCollection::make(function () use ($layer, $since) {
            $offset = 0;

            do {
                $page = $this->json($this->request()->get("{$layer['url']}/query", [
                    'where' => "{$layer['startField']} >= TIMESTAMP '".Carbon::createFromTimestamp($since, 'UTC')->format('Y-m-d H:i:s')."'",
                    'outFields' => "{$layer['siteField']},{$layer['startField']},{$layer['endField']}",
                    'orderByFields' => $layer['idField'],
                    'returnGeometry' => 'false',
                    'resultOffset' => $offset,
                    'resultRecordCount' => self::PAGE_SIZE,
                    'f' => 'json',
                ]));
                $features = $page['features'] ?? [];

                foreach ($features as $feature) {
                    $attributes = $feature['attributes'] ?? [];
                    $start = $this->timestamp($attributes[$layer['startField']] ?? null);
                    $end = $this->timestamp($attributes[$layer['endField']] ?? null);
                    $id = $attributes[$layer['siteField']] ?? null;

                    if (is_string($id) && $start !== null && $end !== null && $end >= $start) {
                        yield new SpillLogRowData(strtoupper(trim($id)), $start, $end);
                    }
                }

                // Services return fewer than asked for per page when their own limit is lower (1,000 for some).
                $offset += count($features);
            } while (count($features) > 0 && (bool) ($page['exceededTransferLimit'] ?? false));
        });
    }

    /**
     * Date fields come back as milliseconds, timestamp-with-offset fields as ISO strings.
     */
    private function timestamp(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            return (int) floor($value / 1000);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp();
        } catch (Exception) {
            return null;
        }
    }

    /**
     * The arrays in a list from a response, leaving out anything else.
     *
     * @return list<array<string, mixed>>
     */
    private function items(mixed $list): array
    {
        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    /**
     * ArcGIS reports errors with a 200 and an error body.
     *
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        $body = $response->throw()->json();

        if (! is_array($body) || is_array($body['error'] ?? null)) {
            throw new RuntimeException('Stream spill log error: '.json_encode(is_array($body) ? $body['error'] : $body));
        }

        return $body;
    }

    /**
     * Redirects are not followed, so a service cannot send the server somewhere the host check never saw.
     */
    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(2, 2000)
            ->withOptions(['allow_redirects' => false])
            ->withUserAgent(config('services.user_agent'));
    }
}
