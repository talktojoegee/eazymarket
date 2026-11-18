<?php

namespace App\Http\Controllers\Api\Seller;

use App\Enums\DateRangeFilterEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Product\ProductTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Http\Resources\Seller\OrderItemAddonResource;
use App\Http\Resources\Seller\SellerReturnResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Services\CurrencyService;
use App\Services\OrderEarningsCalculator;
use App\Services\OrderService;
use App\Support\PricePresenter;
use App\Traits\FormatsOrderMoney;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Store;

#[Group('Seller Orders')]
class SellerOrderApiController extends Controller
{
    use AuthorizesRequests;
    use FormatsOrderMoney;

    public function __construct(
        protected OrderService    $orderService,
        protected CurrencyService $currencyService,
    )
    {
    }

    /**
     * List seller order items with filters and pagination
     */
    #[QueryParameter('page', description: 'Page number for pagination', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Items per page', type: 'int', default: 15, example: 15)]
    #[QueryParameter('status', description: 'Filter by order item status', type: 'string', example: 'processing')]
    #[QueryParameter('payment_type', description: 'Filter by payment method', type: 'string', example: 'cod')]
    #[QueryParameter('range', description: 'Date range filter from DateRangeFilterEnum', type: 'string', example: 'LAST_7_DAYS')]
    #[QueryParameter('search', description: 'Search by order id, buyer name, product/variant title', type: 'string', example: 'John')]
    #[QueryParameter('sort_by', description: 'Sort column: id|order_id|price|status|created_at', type: 'string', default: 'id')]
    #[QueryParameter('sort_dir', description: 'Sort direction asc|desc', type: 'string', default: 'desc')]
    #[QueryParameter('store_id', description: 'Filter items by a specific store owned by the seller', type: 'int', example: 123)]
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $seller = $user?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $perPage = (int)$request->integer('per_page', 15);
        $searchValue = trim((string)$request->input('search', ''));
        $status = $request->input('status');
        $paymentType = $request->input('payment_type');
        $fulfillmentMethod = $request->input('fulfillment_method');
        $dateRange = $request->input('range');
        $sortBy = in_array($request->input('sort_by'), ['id', 'order_id', 'price', 'status', 'created_at']) ? $request->input('sort_by') : 'id';
        $sortDir = strtolower($request->input('sort_dir')) === 'asc' ? 'asc' : 'desc';

        $storeId = $request->integer('store_id');
        if ($storeId) {
            $storeValid = Store::query()->where('id', $storeId)->where('seller_id', $seller->id)->exists();
            if (!$storeValid) {
                return ApiResponseType::sendJsonResponse(false, 'You are not authorized to access this store.', null, 403);
            }
        } else {
            $storeId = null;
        }

        $query = SellerOrderItem::with(['sellerOrder', 'orderItem', 'orderItem.store', 'orderItem.addons.addonGroup', 'orderItem.addons.addonItem', 'variant', 'product', 'sellerOrder.order', 'sellerOrder.order.market'])
            ->whereHas('sellerOrder', fn($q) => $q->where('seller_id', $seller->id))
            ->when($storeId, function ($q) use ($storeId) {
                $q->whereHas('orderItem', function ($qq) use ($storeId) {
                    $qq->where('store_id', $storeId);
                });
            });

        if (!empty($status)) {
            $query->whereHas('orderItem', fn($q) => $q->where('status', $status));
        }

        if (!empty($paymentType)) {
            $query->whereHas('sellerOrder', function ($q) use ($paymentType) {
                $q->whereHas('order', fn($qo) => $qo->where('payment_method', $paymentType));
            });
        }

        if (!empty($fulfillmentMethod)) {
            $query->whereHas('sellerOrder', fn($q) => $q->where('fulfillment_method', $fulfillmentMethod));
        }

        if (!empty($dateRange)) {
            $fromDate = $this->getDateRange($dateRange);
            if ($fromDate) {
                $query->where('created_at', '>=', $fromDate);
            }
        }

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('seller_order_id', 'like', "%$searchValue%")
                    ->orWhereHas('sellerOrder', function ($orderQuery) use ($searchValue) {
                        $orderQuery->where('total_price', 'like', "%$searchValue%")
                            ->orWhereHas('order', function ($oq) use ($searchValue) {
                                $oq->where('shipping_name', 'like', "%$searchValue%")
                                    ->orWhere('order_id', 'like', "%$searchValue%");
                            });
                    })
                    ->orWhereHas('orderItem', function ($orderItemQuery) use ($searchValue) {
                        $orderItemQuery->where('status', 'like', "%$searchValue%")
                            ->orWhere('sku', 'like', "%$searchValue%");
                    })
                    ->orWhereHas('product', function ($productQuery) use ($searchValue) {
                        $productQuery->where('title', 'like', "%$searchValue%");
                    })
                    ->orWhereHas('variant', function ($variantQuery) use ($searchValue) {
                        $variantQuery->where('title', 'like', "%$searchValue%");
                    });
            });
        }

        $paginator = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        $paginator->getCollection()->transform(function ($item) {
            return $this->transformOrderItem($item);
        });

        $response = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'data' => $paginator->items(),
        ];

        return ApiResponseType::sendJsonResponse(true, __('labels.orders_fetched_successfully') ?? 'Orders fetched successfully', $response);
    }

    /**
     * Show a seller order (not a global Order). ID is seller_orders.id
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $seller = $user?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $sellerOrder = SellerOrder::where('id', $id)
            ->with(['order', 'items.product', 'items.variant', 'items.orderItem', 'items.orderItem.addons.addonGroup', 'items.orderItem.addons.addonItem', 'order.items.store', 'shipments'])
            ->where('seller_id', $seller->id)
            ->first();

        if (!$sellerOrder) {
            return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', null, 404);
        }

        try {
            $this->authorize('view', $sellerOrder);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?? __('messages.unauthorized_action'), [], 403);
        }

        $resource = new OrderResource($sellerOrder);
        return ApiResponseType::sendJsonResponse(true, __('labels.order_fetched_successfully') ?? 'Order fetched successfully', $resource);
    }

    /**
     * Earnings and settlement breakdown for one seller order. Mirrors the
     * "Earnings & settlement" tab of the seller panel order page.
     */
    public function earnings(int $id): JsonResponse
    {
        $sellerOrder = $this->findSellerOrder($id, ['order.market.currency', 'items.orderItem', 'items.product', 'items.variant']);

        if ($sellerOrder instanceof JsonResponse) {
            return $sellerOrder;
        }

        $earnings = (new OrderEarningsCalculator())->forSellerOrder($sellerOrder);

        return ApiResponseType::sendJsonResponse(true, __('labels.earnings_fetched_successfully') ?? 'Earnings fetched successfully', [
            'earnings' => $earnings,
            'currency' => PricePresenter::payload($sellerOrder->order?->market ?? null),
        ]);
    }

    /**
     * Returns raised against this seller order only — the per-order slice of
     * the "Returns & refunds" tab.
     */
    public function orderReturns(int $id): JsonResponse
    {
        $sellerOrder = $this->findSellerOrder($id, ['items']);

        if ($sellerOrder instanceof JsonResponse) {
            return $sellerOrder;
        }

        $orderItemIds = $sellerOrder->items->pluck('order_item_id')->filter()->all();

        $currency = PricePresenter::payload($sellerOrder->order?->market ?? null);

        $returns = OrderItemReturn::with(['orderItem.product', 'orderItem.variant', 'orderItem.addons.addonGroup', 'orderItem.addons.addonItem', 'media', 'store', 'user'])
            ->where('seller_id', $sellerOrder->seller_id)
            ->whereIn('order_item_id', $orderItemIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($return) => (new SellerReturnResource($return, $currency, $sellerOrder->id))->toArray(request()));

        return ApiResponseType::sendJsonResponse(true, __('labels.returns_fetched_successfully'), [
            'data' => $returns,
            'currency' => $currency,
        ]);
    }

    /**
     * Load a seller order owned by the caller, or the matching error response.
     */
    private function findSellerOrder(int $id, array $with = []): SellerOrder|JsonResponse
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $sellerOrder = SellerOrder::where('id', $id)
            ->where('seller_id', $seller->id)
            ->with(array_merge(['order'], $with))
            ->first();

        if (!$sellerOrder) {
            return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found') ?? 'Order not found', null, 404);
        }

        try {
            $this->authorize('view', $sellerOrder);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?? __('messages.unauthorized_action'), [], 403);
        }

        return $sellerOrder;
    }

    private function transformOrderItem(SellerOrderItem $sellerOrderItem): array
    {
        $variantTitle = in_array($sellerOrderItem->product->type ?? null, [ProductTypeEnum::SIMPLE(), ProductTypeEnum::KIT()], true)
            ? ''
            : ($sellerOrderItem->variant->title ?? '');
        $storeName = $sellerOrderItem->orderItem->store ? $sellerOrderItem->orderItem->store->name : 'N/A';

        $fulfillmentMethod = $sellerOrderItem->sellerOrder->fulfillment_method ?? null;
        $fulfillmentStatus = $sellerOrderItem->sellerOrder->fulfillment_status ?? 'unfulfilled';

        $currency = PricePresenter::payload($sellerOrderItem->sellerOrder->order?->market ?? null);

        return [
            'order_item_id' => $sellerOrderItem->order_item_id,
            'seller_order_id' => $sellerOrderItem->seller_order_id,
            'fulfillment_method' => $fulfillmentMethod,
            'fulfillment_status' => $fulfillmentStatus,
            'fulfillment_status_label' => formatOrderStatusLabel($fulfillmentStatus),
            'created_at' => $sellerOrderItem->created_at,
            'order' => [
                'id' => $sellerOrderItem->sellerOrder->order_id,
                'image' => !empty($sellerOrderItem->variant->image) ? $sellerOrderItem->variant->image : $sellerOrderItem->product?->main_image,
                'uuid' => $sellerOrderItem->sellerOrder->order->uuid ?? null,
                'buyer_name' => $sellerOrderItem->sellerOrder->order->shipping_name ?? null,
                'payment_method' => $sellerOrderItem->sellerOrder->order->payment_method ?? null,
                'is_rush_order' => (bool)($sellerOrderItem->sellerOrder->order->is_rush_order ?? false),
                'status' => $sellerOrderItem->sellerOrder->order->status ?? null,
            ],
            'product' => [
                'id' => $sellerOrderItem->product?->id,
                'title' => $sellerOrderItem->product?->title,
                'variant' => $variantTitle,
            ],
            'store' => [
                'name' => $storeName,
            ],
            'sku' => $sellerOrderItem->orderItem->sku,
            'quantity' => (int)$sellerOrderItem->orderItem->quantity,
            'currency' => $currency,
            'subtotal' => [
                'raw' => (float)$sellerOrderItem->orderItem->subtotal,
                'formatted' => $this->formatOrderMoney((float)$sellerOrderItem->orderItem->subtotal, $currency),
            ],
            'status' => $sellerOrderItem->orderItem->status,
            'status_label' => formatOrderStatusLabel($sellerOrderItem->orderItem->status),
            'cancellation_reason' => $sellerOrderItem->orderItem->cancellation_reason,
            'delivery_fail_reason' => $sellerOrderItem->orderItem->delivery_fail_reason,
            'addons' => $this->formatOrderItemAddons($sellerOrderItem->orderItem, $currency),
            'addons_total' => $this->sumOrderItemAddonsLineTotal($sellerOrderItem->orderItem),
        ];
    }

    /**
     * Flatten an OrderItem's addons into a scalar-friendly list, shaped by
     * `OrderItemAddonResource` so the order and returns screens read the same
     * addon payload.
     *
     * @param array{code:string,symbol:string,format:array<string,mixed>}|null $currency
     * @return array<int, array<string, mixed>>
     */
    private function formatOrderItemAddons($orderItem, ?array $currency = null): array
    {
        if (!$orderItem) {
            return [];
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->with(['addonGroup', 'addonItem'])->get();

        if (!$addons || $addons->isEmpty()) {
            return [];
        }

        return OrderItemAddonResource::listFor($addons, $currency, request());
    }

    /**
     * Line-total addon contribution: `quantity × sum(addon.price)`.
     */
    private function sumOrderItemAddonsLineTotal($orderItem): float
    {
        if (!$orderItem) {
            return 0.0;
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->get();

        if (!$addons || $addons->isEmpty()) {
            return 0.0;
        }

        return round(((int)$orderItem->quantity) * (float)$addons->sum(fn($a) => (float)$a->price), 2);
    }

    private function getDateRange($dateRange): ?Carbon
    {
        $fromDate = null;
        $now = Carbon::now();
        switch ($dateRange) {
            case DateRangeFilterEnum::LAST_30_MINUTES():
                $fromDate = $now->copy()->subMinutes(30);
                break;
            case DateRangeFilterEnum::LAST_1_HOUR():
                $fromDate = $now->copy()->subHour();
                break;
            case DateRangeFilterEnum::LAST_5_HOURS():
                $fromDate = $now->copy()->subHours(5);
                break;
            case DateRangeFilterEnum::LAST_1_DAY():
                $fromDate = $now->copy()->subDay();
                break;
            case DateRangeFilterEnum::LAST_7_DAYS():
                $fromDate = $now->copy()->subDays(7);
                break;
            case DateRangeFilterEnum::LAST_30_DAYS():
                $fromDate = $now->copy()->subDays(30);
                break;
            case DateRangeFilterEnum::LAST_365_DAYS():
                $fromDate = $now->copy()->subDays(365);
                break;
        }
        return $fromDate;
    }

    /**
     * Get all enums for filtering orders.
     */
    public function enums(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.order_enums_retrieved_successfully',
            data: [
                'status' => OrderItemStatusEnum::values(),
                'range' => DateRangeFilterEnum::values(),
                'payment_type' => PaymentTypeEnum::values()
            ]
        );
    }

    /**
     * Returns raised against the seller's items, newest first. Scoped to the
     * store the request is scoped to via `X-Store-Id` unless `all=1`.
     */
    #[QueryParameter('return_status', description: 'Filter by return status', type: 'string', example: 'requested')]
    #[QueryParameter('search', description: 'Search return id, reason, seller comment or order number', type: 'string')]
    #[QueryParameter('per_page', description: 'Items per page', type: 'int', default: 15)]
    #[QueryParameter('all', description: 'Ignore the store scope and return every store', type: 'bool', default: false)]
    public function returns(Request $request): JsonResponse
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $storeId = $request->boolean('all') ? null : $request->attributes->get('seller_store')?->id;
        $search = trim((string) $request->input('search', ''));

        $paginator = OrderItemReturn::with([
                'order.market',
                'media',
                'orderItem.product',
                'orderItem.variant',
                'orderItem.addons.addonGroup',
                'orderItem.addons.addonItem',
                'store',
                'user',
            ])
            ->where('seller_id', $seller->id)
            ->where('return_status', '!=', OrderItemReturnStatusEnum::CANCELLED())
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($request->filled('return_status'), fn ($q) => $q->where('return_status', $request->input('return_status')))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('id', 'like', "%{$search}%")
                        ->orWhere('reason', 'like', "%{$search}%")
                        ->orWhere('seller_comment', 'like', "%{$search}%")
                        ->orWhereHas('order', fn ($qo) => $qo->where('order_id', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        $sellerOrderIds = SellerOrder::where('seller_id', $seller->id)
            ->whereIn('order_id', $paginator->getCollection()->pluck('order_id')->unique()->all())
            ->pluck('id', 'order_id');

        $paginator->getCollection()->transform(fn ($return) => (new SellerReturnResource(
            $return,
            PricePresenter::payload($return->order?->market ?? null),
            $sellerOrderIds[$return->order_id] ?? null,
        ))->toArray($request));

        return ApiResponseType::sendJsonResponse(true, __('labels.returns_fetched_successfully'), [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }

    /**
     * One return with everything the review screen needs. The seller panel
     * renders this as a Blade page; the API returns the same data as JSON.
     */
    public function returnDetail(int $returnId): JsonResponse
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
        }

        $return = OrderItemReturn::with([
                'orderItem.product',
                'orderItem.variant',
                'orderItem.addons.addonGroup',
                'orderItem.addons.addonItem',
                'order.market',
                'media',
                'store',
                'user',
            ])
            ->where('id', $returnId)
            ->where('seller_id', $seller->id)
            ->first();

        if (!$return || !$return->orderItem) {
            return ApiResponseType::sendJsonResponse(false, __('labels.return_not_found'), [], 404);
        }

        $sellerOrder = SellerOrder::where('order_id', $return->order_id)
            ->where('seller_id', $seller->id)
            ->first();

        $currency = PricePresenter::payload($return->order?->market ?? null);
        $resource = (new SellerReturnResource($return, $currency, $sellerOrder?->id))->toArray(request());

        return ApiResponseType::sendJsonResponse(true, __('labels.returns_fetched_successfully'), [
            'return' => $resource,
            'order_item' => $resource['order_item'] ?? null,
            'order' => $return->order,
            'seller_order_id' => $sellerOrder?->id,
            'currency' => PricePresenter::payload($return->order?->market ?? null),
        ]);
    }
}
