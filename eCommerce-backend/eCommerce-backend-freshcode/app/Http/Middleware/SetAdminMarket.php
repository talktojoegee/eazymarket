<?php

namespace App\Http\Middleware;

use App\Services\MarketService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global market scope for the ADMIN panel.
 *
 * The admin panel is always scoped to exactly one market (chosen from the
 * header selector, persisted in the session — there is no "all markets"
 * view). This middleware resolves the selected market once per request and
 * shares it — plus the list of switchable markets — with every admin view so
 * the header selector can render. Controllers read the same selection via
 * `app(MarketService::class)->adminSelectedId()`.
 *
 * Mounted on the admin auth group only. The seller panel is not market-scoped.
 */
class SetAdminMarket
{
    public function __construct(protected MarketService $markets)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $current = $this->markets->adminSelectedMarket();
            $all = $this->markets->active();
        } catch (\Throwable $e) {
            $current = null;
            $all = collect();
        }
        view()->share('adminCurrentMarket', $current);
        view()->share('adminMarkets', $all);

        return $next($request);
    }
}
