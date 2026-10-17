<?php

namespace App\Services\NaturalResourcesWales;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Requests to Natural Resources Wales's API, where each product has its own
 * subscription key and allowance: 10 calls a minute and 2,880 a day, after
 * which access is denied. Calls are counted in the cache, so every command
 * and process shares one budget per product and stays a little under both.
 */
class Api
{
    public const LEVELS = 'levels';

    public const FLOOD_WARNINGS = 'flood_warnings';

    private const PER_MINUTE = 9;

    private const PER_DAY = 2700;

    /**
     * Whether the product's key is set; NRW data is skipped without one.
     */
    public function isEnabled(string $product): bool
    {
        return filled(config("services.nrw.{$product}_key"));
    }

    /**
     * Whether a call can be made now without going over the product's allowance.
     */
    public function hasBudget(string $product): bool
    {
        return ! RateLimiter::tooManyAttempts($this->limiterKey($product, 'minute'), self::PER_MINUTE)
            && ! RateLimiter::tooManyAttempts($this->limiterKey($product, 'day'), self::PER_DAY);
    }

    /**
     * @param  array<string, string|int>  $query
     */
    public function get(string $product, string $path, array $query = []): mixed
    {
        if (! $this->isEnabled($product)) {
            throw new RuntimeException("Natural Resources Wales API: no key for {$product}");
        }

        if (! $this->hasBudget($product)) {
            throw new RuntimeException("Natural Resources Wales API: {$product} call allowance used up");
        }

        RateLimiter::hit($this->limiterKey($product, 'minute'), 60);
        RateLimiter::hit($this->limiterKey($product, 'day'), 86400);

        return $this->request($product)
            ->get(rtrim(config('services.nrw.api_url'), '/').'/'.ltrim($path, '/'), $query)
            ->throw()
            ->json();
    }

    private function request(string $product): PendingRequest
    {
        // No retries: each attempt counts against the allowance, and the next scheduled run tries again.
        return Http::acceptJson()
            ->timeout(60)
            ->withHeader('Ocp-Apim-Subscription-Key', (string) config("services.nrw.{$product}_key"))
            ->withUserAgent(config('services.user_agent'));
    }

    private function limiterKey(string $product, string $window): string
    {
        return "nrw-api:{$product}:{$window}";
    }
}
