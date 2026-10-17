<?php

namespace App\Services\WelshWater;

use App\Data\OverflowMonitorData;
use App\Services\Stream\OverflowFeedReader;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Reads Dŵr Cymru Welsh Water's storm overflow map, an ArcGIS feature service of its own (not on Stream) with other
 * fields: the status in words, local times and the position only in the geometry.
 */
class StormOverflowMapClient implements OverflowFeedReader
{
    private const PAGE_SIZE = 2000;

    /**
     * Every overflow on the map, skipping any without an id.
     *
     * @param  string  $feedUrl  The feature layer URL, ending in the layer number
     * @return Collection<int, OverflowMonitorData>
     */
    public function all(string $feedUrl): Collection
    {
        $monitors = collect();
        $offset = 0;

        do {
            $response = $this->request()->get(rtrim($feedUrl, '/').'/query', [
                'where' => '1=1',
                'outFields' => 'DCWW_ID,asset_name,status,start_date_time_discharge,stop_date_time_discharge,Receiving_Water,EditDate',
                'returnGeometry' => 'true',
                'outSR' => 4326,
                'orderByFields' => 'objectid',
                'resultOffset' => $offset,
                'resultRecordCount' => self::PAGE_SIZE,
                'f' => 'json',
            ])->throw();

            $features = $response->json('features', []);

            foreach ($features as $feature) {
                if (! is_string($feature['attributes']['DCWW_ID'] ?? null) || ! is_array($feature['geometry'] ?? null)) {
                    continue;
                }

                $monitors->push(OverflowMonitorData::fromWelshWater($feature['attributes'], $feature['geometry']));
            }

            $offset += count($features);
            $hasMore = (bool) $response->json('exceededTransferLimit', false);
        } while ($hasMore && count($features) > 0);

        return $monitors;
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(2, 2000)
            ->withUserAgent(config('services.user_agent'));
    }
}
