<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides the API (404) until it is released, as `API_ENABLED` turns it on.
 */
class EnsureApiIsEnabled
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('app.api_enabled'), 404);

        return $next($request);
    }
}
