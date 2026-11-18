<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\Seller\SellerSettlementStatusEnum;
use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\SellerStatement;
use App\Services\CurrencyService;
use App\Support\SellerStatementFigures;
use App\Support\SettlementSettings;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Seller Earnings')]
class SellerEarningApiController extends Controller
{
    use AuthorizesRequests, SellerStatementFigures;

    public function __construct(protected CurrencyService $currencyService)
    {
    }

    /**
     * List unsettled seller earnings (credit statements) for the authenticated seller.
     */
    #[QueryParameter('page', description: 'Page number', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Items per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('search', description: 'Search in reference, description, product/store name', type: 'string')]
    #[QueryParameter('store_id', description: 'Filter by store id', type: 'int')]
    #[QueryParameter('sort_by', description: 'Sort column: id|amount|posted_at', type: 'string', default: 'id')]
    #[QueryParameter('sort_dir', description: 'Sort direction: asc|desc', type: 'string', default: 'desc')]
    public function unsettled(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', SellerStatement::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?? __('messages.unauthorized_action'), [], 403);
        }

        $user = auth()->user();
        $seller = $user?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found') ?? 'Seller not found', null, 404);
        }

        $perPage = (int)$request->integer('per_page', 15);
        $searchValue = trim((string)$request->input('search', ''));
        $storeId = $request->input('store_id');
        $sortBy = in_array($request->input('sort_by'), ['id', 'amount', 'posted_at']) ? $request->input('sort_by') : 'id';
        $sortDir = strtolower($request->input('sort_dir')) === 'asc' ? 'asc' : 'desc';

        $query = SellerStatement::with(['orderItem.store', 'orderItem.sellerOrderItem'])
            ->where('seller_id', $seller->id)
            ->where('entry_type', SellerSettlementTypeEnum::CREDIT())
            ->where('settlement_status', SellerSettlementStatusEnum::PENDING());

        if (!empty($storeId)) {
            $query->whereHas('orderItem', function ($qi) use ($storeId) {
                $qi->where('store_id', $storeId);
            });
        }

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('reference_id', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('orderItem', function ($qi) use ($searchValue) {
                        $qi->where('title', 'like', "%{$searchValue}%")
                            ->orWhere('variant_title', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('orderItem.store', function ($qs) use ($searchValue) {
                        $qs->where('name', 'like', "%{$searchValue}%");
                    });
            });
        }

        $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        $paginator->getCollection()->transform(function ($st) {
            $orderItem = $st->orderItem;
            return [
                'id' => $st->id,
                'entry_type' => $st->entry_type,
                'reference_type' => $st->reference_type,
                'reference_id' => $st->reference_id,
                'description' => $st->description,
                'order' => [
                    'order_id' => $st->order_id,
                    'order_item_id' => $st->order_item_id,
                    'seller_order_id' => $orderItem?->sellerOrderItem?->seller_order_id,
                    'product_title' => $orderItem?->title,
                    'variant_title' => $orderItem?->variant_title,
                    'store_name' => $orderItem?->store?->name,
                ],
                'amount' => [
                    'raw' => (float)$st->amount,
                    'formatted' => $this->currencyService->format($st->amount),
                ],
                'posted_at' => $st->posted_at,
                'settlement_status' => $st->settlement_status,
            ];
        });

        return ApiResponseType::sendJsonResponse(true, __('labels.unsettled_commissions_retrieved_successfully') ?? 'Unsettled commissions retrieved successfully', [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }

    /**
     * List unsettled debit statements for the authenticated seller (e.g., returns to be debited).
     */
    #[QueryParameter('page', description: 'Page number', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Items per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('search', description: 'Search query', type: 'string')]
    #[QueryParameter('store_id', description: 'Filter by store id', type: 'int')]
    #[QueryParameter('sort_by', description: 'Sort column: id|amount|posted_at', type: 'string', default: 'id')]
    #[QueryParameter('sort_dir', description: 'Sort direction: asc|desc', type: 'string', default: 'desc')]
    public function unsettledDebits(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', SellerStatement::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?? __('messages.unauthorized_action'), [], 403);
        }

        $user = auth()->user();
        $seller = $user?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found') ?? 'Seller not found', null, 404);
        }

        $perPage = (int)$request->integer('per_page', 15);
        $searchValue = trim((string)$request->input('search', ''));
        $storeId = $request->input('store_id');
        $sortBy = in_array($request->input('sort_by'), ['id', 'amount', 'posted_at']) ? $request->input('sort_by') : 'id';
        $sortDir = strtolower($request->input('sort_dir')) === 'asc' ? 'asc' : 'desc';

        $query = SellerStatement::with(['orderItem.store', 'orderItem.sellerOrderItem'])
            ->where('seller_id', $seller->id)
            ->where('entry_type', SellerSettlementTypeEnum::DEBIT())
            ->where('settlement_status', SellerSettlementStatusEnum::PENDING());

        if (!empty($storeId)) {
            $query->whereHas('orderItem', function ($qi) use ($storeId) {
                $qi->where('store_id', $storeId);
            });
        }

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('reference_id', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('orderItem', function ($qi) use ($searchValue) {
                        $qi->where('title', 'like', "%{$searchValue}%")
                            ->orWhere('variant_title', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('orderItem.store', function ($qs) use ($searchValue) {
                        $qs->where('name', 'like', "%{$searchValue}%");
                    });
            });
        }

        $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        $paginator->getCollection()->transform(function ($st) {
            $orderItem = $st->orderItem;
            return [
                'id' => $st->id,
                'entry_type' => $st->entry_type,
                'reference_type' => $st->reference_type,
                'reference_id' => $st->reference_id,
                'description' => $st->description,
                'order' => [
                    'order_id' => $st->order_id,
                    'order_item_id' => $st->order_item_id,
                    'seller_order_id' => $orderItem?->sellerOrderItem?->seller_order_id,
                    'product_title' => $orderItem?->title,
                    'variant_title' => $orderItem?->variant_title,
                    'store_name' => $orderItem?->store?->name,
                ],
                'amount' => [
                    'raw' => (float)$st->amount,
                    'formatted' => $this->currencyService->format($st->amount),
                ],
                'posted_at' => $st->posted_at,
                'settlement_status' => $st->settlement_status,
            ];
        });

        return ApiResponseType::sendJsonResponse(true, __('labels.unsettled_debits_retrieved_successfully') ?? 'Unsettled debits retrieved successfully', [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }

    /**
     * List settled statements (credits and debits) for the authenticated seller.
     */
    #[QueryParameter('page', description: 'Page number', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Items per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('search', description: 'Search query', type: 'string')]
    #[QueryParameter('store_id', description: 'Filter by store id', type: 'int')]
    #[QueryParameter('sort_by', description: 'Sort column: id|amount|settled_at', type: 'string', default: 'id')]
    #[QueryParameter('sort_dir', description: 'Sort direction: asc|desc', type: 'string', default: 'desc')]
    public function history(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', SellerStatement::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?? __('messages.unauthorized_action'), [], 403);
        }

        $user = auth()->user();
        $seller = $user?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found') ?? 'Seller not found', null, 404);
        }

        $perPage = (int)$request->integer('per_page', 15);
        $searchValue = trim((string)$request->input('search', ''));
        $storeId = $request->input('store_id');
        $sortBy = in_array($request->input('sort_by'), ['id', 'amount', 'settled_at']) ? $request->input('sort_by') : 'id';
        $sortDir = strtolower($request->input('sort_dir')) === 'asc' ? 'asc' : 'desc';

        $query = SellerStatement::with(['orderItem.store', 'orderItem.sellerOrderItem'])
            ->where('seller_id', $seller->id)
            ->where('settlement_status', SellerSettlementStatusEnum::SETTLED());

        if (!empty($storeId)) {
            $query->whereHas('orderItem', function ($qi) use ($storeId) {
                $qi->where('store_id', $storeId);
            });
        }

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('reference_id', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('orderItem', function ($qi) use ($searchValue) {
                        $qi->where('title', 'like', "%{$searchValue}%")
                            ->orWhere('variant_title', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('orderItem.store', function ($qs) use ($searchValue) {
                        $qs->where('name', 'like', "%{$searchValue}%");
                    });
            });
        }

        $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        $paginator->getCollection()->transform(function ($st) {
            $orderItem = $st->orderItem;
            return [
                'id' => $st->id,
                'entry_type' => $st->entry_type,
                'reference_type' => $st->reference_type,
                'reference_id' => $st->reference_id,
                'description' => $st->description,
                'order' => [
                    'order_id' => $st->order_id,
                    'order_item_id' => $st->order_item_id,
                    'seller_order_id' => $orderItem?->sellerOrderItem?->seller_order_id,
                    'product_title' => $orderItem?->title,
                    'variant_title' => $orderItem?->variant_title,
                    'store_name' => $orderItem?->store?->name,
                ],
                'amount' => [
                    'raw' => (float)$st->amount,
                    'formatted' => $this->currencyService->format($st->amount),
                ],
                'posted_at' => $st->posted_at,
                'settled_at' => $st->settled_at,
                'settlement_status' => $st->settlement_status,
            ];
        });

        return ApiResponseType::sendJsonResponse(true, __('labels.settled_commissions_retrieved_successfully') ?? 'Settled statements retrieved successfully', [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }

    /**
     * Pending settlement summary cards: credits, debits, net settleable, matured
     * balance and aging buckets. Scoped to the seller and to the store the
     * request is scoped to via `X-Store-Id`.
     */
    #[QueryParameter('date_range', description: 'Only count entries posted in the last N days', type: 'int')]
    public function summary(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', SellerStatement::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
        }

        $seller = auth()->user()?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $storeId = $request->attributes->get('seller_store')?->id;
        $days = (int) $request->input('date_range', 0);

        $base = function () use ($seller, $storeId, $days) {
            $q = SellerStatement::query()
                ->where('seller_id', $seller->id)
                ->where('settlement_status', SellerSettlementStatusEnum::PENDING())
                ->where('on_hold', false);

            if ($storeId) {
                $q->whereHas('orderItem', fn ($qi) => $qi->where('store_id', $storeId));
            }
            if ($days > 0) {
                $q->where('posted_at', '>=', now()->subDays($days)->startOfDay());
            }

            return $q;
        };

        $creditsTotal = (float) $base()->where('entry_type', SellerSettlementTypeEnum::CREDIT())->sum('amount');
        $creditsCount = (int) $base()->where('entry_type', SellerSettlementTypeEnum::CREDIT())->count();
        $debitsTotal = (float) $base()->where('entry_type', SellerSettlementTypeEnum::DEBIT())->sum('amount');
        $debitsCount = (int) $base()->where('entry_type', SellerSettlementTypeEnum::DEBIT())->count();
        $net = round($creditsTotal - $debitsTotal, 2);

        $maturedBase = fn () => $base()
            ->where('entry_type', SellerSettlementTypeEnum::CREDIT())
            ->where(function ($q) {
                $q->whereNull('available_at')->orWhere('available_at', '<=', now());
            });
        $maturedTotal = (float) $maturedBase()->sum('amount');
        $maturedCount = (int) $maturedBase()->count();

        $aging = [];
        foreach (['0_7' => [0, 7], '8_15' => [8, 15], '16_30' => [16, 30], '31_plus' => [31, null]] as $key => [$min, $max]) {
            $q = $base()->where('entry_type', SellerSettlementTypeEnum::CREDIT())
                ->where('posted_at', '<=', now()->subDays($min)->endOfDay());
            if ($max !== null) {
                $q->where('posted_at', '>=', now()->subDays($max)->startOfDay());
            }
            $amount = (float) (clone $q)->sum('amount');
            $aging[$key] = [
                'count' => (int) $q->count(),
                'amount' => $amount,
                'amount_formatted' => $this->currencyService->format($amount),
            ];
        }

        return ApiResponseType::sendJsonResponse(true, __('labels.commissions_retrieved_successfully'), [
            'credits_total' => $creditsTotal,
            'credits_total_formatted' => $this->currencyService->format($creditsTotal),
            'credits_count' => $creditsCount,
            'debits_total' => $debitsTotal,
            'debits_total_formatted' => $this->currencyService->format($debitsTotal),
            'debits_count' => $debitsCount,
            'net_total' => $net,
            'net_total_formatted' => $this->currencyService->format($net),
            'pending_count' => $creditsCount + $debitsCount,
            'matured_total' => $maturedTotal,
            'matured_total_formatted' => $this->currencyService->format($maturedTotal),
            'matured_count' => $maturedCount,
            'auto_settle_enabled' => SettlementSettings::autoSettleEnabled(),
            'clearing_days' => SettlementSettings::clearingDays(),
            'aging' => $aging,
        ]);
    }

    /**
     * Breakdown of one settlement entry. The seller panel renders this as HTML;
     * the API returns the same figures as structured fields instead.
     */
    public function detail(int $id): JsonResponse
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $st = SellerStatement::with(['orderItem.store', 'order', 'orderReturn', 'settledBy'])
            ->where('seller_id', $seller->id)
            ->find($id);

        if (!$st) {
            return ApiResponseType::sendJsonResponse(false, __('labels.no_detail_available'), [], 404);
        }

        $f = $this->statementFigures($st);
        $gross = (float) $f['gross'];
        $commission = (float) $f['commission'];
        $tax = (float) ($f['tax'] ?? 0.0);
        $preTax = $f['has_item'] ? round($gross - $tax, 2) : 0.0;

        return ApiResponseType::sendJsonResponse(true, __('labels.commissions_retrieved_successfully'), [
            'id' => $st->id,
            'reference' => $st->reference,
            'description' => $st->description,
            'entry_type' => $st->entry_type,
            'settlement_status' => $st->settlement_status,
            'on_hold' => (bool) $st->on_hold,
            'posted_at' => $st->posted_at,
            'available_at' => $st->available_at,
            'settled_at' => $st->settled_at,
            'settled_by' => $st->settledBy?->name,
            'currency_code' => $f['currency'],
            'figures' => [
                'gross' => $gross,
                'gross_formatted' => $this->currencyService->format($gross),
                'tax' => $f['has_item'] ? $tax : null,
                'pre_tax' => $f['has_item'] ? $preTax : null,
                'commission' => $commission,
                'commission_formatted' => $this->currencyService->format($commission),
                'commission_rate_percent' => $preTax > 0 ? round($commission / $preTax * 100, 2) : null,
                'net' => (float) $f['net'],
                'net_formatted' => $this->currencyService->format((float) $f['net']),
                'market_amount' => $f['market_amount'],
                'market_code' => $f['market_code'],
                'market_rate' => $f['rate'],
            ],
            'order' => $st->order ? [
                'id' => $st->order->id,
                'order_number' => $st->order->order_number ?? null,
            ] : null,
            'order_item' => $st->orderItem ? [
                'id' => $st->orderItem->id,
                'title' => $st->orderItem->title,
                'quantity' => $st->orderItem->quantity,
                'store' => $st->orderItem->store?->only(['id', 'name']),
            ] : null,
            'return_id' => $st->orderReturn?->id,
        ]);
    }
}
