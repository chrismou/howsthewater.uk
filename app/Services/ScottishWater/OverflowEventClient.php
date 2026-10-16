<?php

namespace App\Services\ScottishWater;

use App\Data\OverflowMonitorData;
use App\Services\Stream\OverflowFeedReader;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Reads Scottish Water's near real-time overflow event monitoring API,
 * which returns every monitor in one response and refreshes hourly.
 */
class OverflowEventClient implements OverflowFeedReader
{
    /**
     * @return Collection<int, OverflowMonitorData>
     */
    public function all(string $feedUrl): Collection
    {
        $response = $this->request()->get($feedUrl)->throw();

        $lastUpdatedMillis = $response->json('last_updated');
        $feedUpdatedAt = is_numeric($lastUpdatedMillis) ? Carbon::createFromTimestampMs((int) $lastUpdatedMillis, 'UTC') : null;

        $results = $response->json('results');

        if (! is_array($results)) {
            return collect();
        }

        return collect(array_values(array_filter($results, is_array(...))))->map(fn (array $record) => OverflowMonitorData::fromScottishWater($record, $feedUpdatedAt));
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(2, 2000)
            ->withUserAgent(config('services.user_agent'));
    }
}
