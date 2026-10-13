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
class StormOverflowClient
{
    private const PAGE_SIZE = 2000;

    /**
     * @return Collection<int, OverflowMonitorData>
     */
    public function all(): Collection
    {
        $monitors = collect();
        $offset = 0;

        do {
            $response = $this->request()->get($this->queryUrl(), [
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

            $offset += self::PAGE_SIZE;
            $hasMore = (bool) $response->json('exceededTransferLimit', false) || count($features) === self::PAGE_SIZE;
        } while ($hasMore && count($features) > 0);

        return $monitors;
    }

    private function queryUrl(): string
    {
        return rtrim(config('services.stream.yorkshire_water_url'), '/').'/query';
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(2, 2000)
            ->withUserAgent(config('services.user_agent'));
    }
}
