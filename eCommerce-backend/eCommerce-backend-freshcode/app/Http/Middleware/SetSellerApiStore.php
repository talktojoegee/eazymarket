<?php

namespace App\Http\Middleware;

use App\Services\SellerStoreService;
use App\Types\Api\ApiResponseType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stateless store scope for the SELLER API (analog of SetSellerStore, which is
 * session-backed and panel-only).
 *
 * The client sends `X-Store-Id` (or a `store_id` request field) to scope the
 * call to one of the seller's own stores; with neither, the seller's first
 * store is used. The resolved store is pinned on SellerStoreService for the
 * request and bound on the request as `seller_store`, so controllers shared
 * with the panel keep working through `selectedId()`.
 *
 * A store id the seller doesn't own is rejected with 403 rather than silently
 * falling back, so a client bug can never write into someone else's store.
 */
class SetSellerApiStore
{
    public function __construct(protected SellerStoreService $stores)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('store_id')
            ?? $request->header(SellerStoreService::HEADER_KEY)
            ?? $request->input('store_id');

        if ($requested !== null && $requested !== '') {
            $store = $this->stores->useStore((int) $requested);

            if ($store === null) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'labels.store_not_found',
                    null,
                    403
                );
            }
        } else {
            $store = $this->stores->useDefaultStore();
        }

        $request->attributes->set('seller_store', $store);

        return $next($request);
    }
}
