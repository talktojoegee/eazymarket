<?php

namespace App\Http\Middleware;

use App\Services\MarketService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request's market once per request and binds it on:
 *   - the Request as `$request->attributes->set('market', $market)`
 *   - the container as a singleton-style binding via MarketService
 *
 * Controllers, services, and Blade views read it via:
 *   - $request->attributes->get('market')
 *   - app(MarketService::class)->current()
 *
 * Mount on the API + customer web stack. Admin/seller panels don't need it —
 * they operate platform-wide. The route definitions add this middleware
 * explicitly so we don't pay the lookup cost on routes that don't care.
 */
class DetectMarket
{
    public function __construct(protected MarketService $markets)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->markets->forget();

        $market = $this->markets->resolveForRequest($request);

        $request->attributes->set('market', $market);

        view()->share('currentMarket', $market);

        return $next($request);
    }
}
