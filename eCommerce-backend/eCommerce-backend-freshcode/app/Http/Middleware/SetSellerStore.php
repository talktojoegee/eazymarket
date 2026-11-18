<?php

namespace App\Http\Middleware;

use App\Services\SellerStoreService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global store scope for the SELLER panel (analog of SetAdminMarket).
 *
 * Resolves the seller's currently-selected store once per request and shares
 * it — plus the list of the seller's own switchable stores — with every
 * seller view so the header selector can render. Controllers read the same
 * selection via `app(SellerStoreService::class)->selectedId()`.
 *
 * Mounted on the seller auth group only.
 */
class SetSellerStore
{
    public function __construct(protected SellerStoreService $stores)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $current = $this->stores->selectedStore();
            $all = $this->stores->storesForCurrentSeller();
        } catch (\Throwable $e) {
            $current = null;
            $all = collect();
        }

        view()->share('sellerCurrentStore', $current);
        view()->share('sellerStores', $all);

        return $next($request);
    }
}
