<?php

namespace App\Services\RiversTrust;

use App\Data\OverflowAnnualReturnData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads one year of the storm overflow event duration monitoring annual returns from the Rivers Trust's ArcGIS
 * layers, which republish the Environment Agency's and Welsh companies' returns for England and Wales (OGL).
 */
class AnnualReturnsClient
{
    private const PAGE_SIZE = 2000;

    /**
     * @param  string  $layerUrl  The feature layer URL, ending in the layer number
     * @return Collection<int, OverflowAnnualReturnData>
     */
    public function rows(string $layerUrl): Collection
    {
        $rows = collect();
        $offset = 0;

        do {
            $response = $this->request()->get(rtrim($layerUrl, '/').'/query', [
                'where' => '1=1',
                'outFields' => '*',
                'returnGeometry' => 'false',
                'resultOffset' => $offset,
                'resultRecordCount' => self::PAGE_SIZE,
                'f' => 'json',
            ])->throw();

            if (is_array($response->json('error'))) {
                throw new RuntimeException('Annual returns layer error: '.json_encode($response->json('error')));
            }

            $features = $response->json('features', []);

            foreach ($features as $feature) {
                if (is_array($feature['attributes'] ?? null)) {
                    $rows->push(OverflowAnnualReturnData::fromArcGis($feature['attributes']));
                }
            }

            // Some layers return fewer than asked for per page (2023 stops at 1,000).
            $offset += count($features);
            $hasMore = (bool) $response->json('exceededTransferLimit', false);
        } while ($hasMore && count($features) > 0);

        return $rows;
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(2, 2000)
            ->withUserAgent(config('services.user_agent'));
    }
}
