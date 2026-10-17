<?php

namespace App\Services\Stream;

use App\Data\OverflowMonitorData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Reads near real-time storm overflow activity from the Stream water data
 * ArcGIS feature services published by each water company.
 */
class StormOverflowClient implements OverflowFeedReader
{
    private const PAGE_SIZE = 2000;

    /**
     * Every monitor in one company's feed.
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
                'outFields' => 'Id,Company,Status,StatusStart,LatestEventStart,LatestEventEnd,Latitude,Longitude,ReceivingWaterCourse,LastUpdated',
                'returnGeometry' => 'false',
                'resultOffset' => $offset,
                'resultRecordCount' => self::PAGE_SIZE,
                'f' => 'json',
            ])->throw();

            $features = $response->json('features', []);

            foreach ($features as $feature) {
                $monitors->push(OverflowMonitorData::fromArcGis($feature['attributes']));
            }

            // A service can return fewer than asked for per page (Anglian and Southern Water stop at 1,000), so the next
            // page starts after what actually came back.
            $offset += count($features);
            $hasMore = (bool) $response->json('exceededTransferLimit', false) || count($features) === self::PAGE_SIZE;
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
