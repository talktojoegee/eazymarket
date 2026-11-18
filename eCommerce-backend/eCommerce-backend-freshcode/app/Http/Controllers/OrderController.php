<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\DateRangeFilterEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Order\OrderCreatedByEnum;
use App\Enums\Order\FulfillmentStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\RefundStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Http\Requests\Admin\Order\AdminAddOrderNoteRequest;
use App\Http\Requests\Order\CancelSellerOrderItemRequest;
use App\Models\OrderItemReturn;
use App\Services\MarketService;
use App\Services\SellerStoreService;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Models\OrderAuditLog;
use App\Models\OrderItem;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\Shipment;
use App\Services\CurrencyService;
use App\Http\Controllers\Admin\InvoiceBuilderController;
use App\Services\OrderEarningsCalculator;
use App\Services\OrderService;
use App\Services\ShipmentService;
use App\Services\SpatieMediaService;
use App\Support\PricePresenter;
use App\Traits\ChecksPermissions;
use App\Traits\AppliesDatatableDateRange;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use PanelAware, AuthorizesRequests, ChecksPermissions, AppliesDatatableDateRange;

    public bool $editPermission = false;
    public bool $viewPermission = false;
    public bool $refundPermission = false;
    public bool $returnViewPermission = false;
    public bool $paymentAcceptPermission = false;
    protected OrderService $orderService;
    protected CurrencyService $currencyService;

    public function __construct(OrderService $orderService, CurrencyService $currencyService)
    {
        $this->orderService = $orderService;
        $this->currencyService = $currencyService;
        $user = auth()->user();
        if ($user) {
            if ($this->getPanel() === 'admin') {
                $this->viewPermission = $this->hasPermission(AdminPermissionEnum::ORDER_VIEW());
                $this->editPermission = $this->hasPermission(AdminPermissionEnum::ORDER_EDIT());
                $this->refundPermission = $this->hasPermission(AdminPermissionEnum::ORDER_REFUND());
                $this->paymentAcceptPermission = $this->hasPermission(AdminPermissionEnum::ORDER_PAYMENT_ACCEPT());
            } else {
                $this->editPermission = $this->hasPermission(SellerPermissionEnum::ORDER_EDIT()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
                $this->viewPermission = $this->hasPermission(SellerPermissionEnum::ORDER_VIEW()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
                $this->returnViewPermission = $this->hasPermission(SellerPermissionEnum::RETURN_VIEW()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            }
        }
    }

    /**
     * Display a listing of the seller's orders.
     *
     * @return View
     * @throws AuthorizationException
     */
    public function index(): View
    {
        $this->authorize('viewAny', SellerOrder::class);

        $orderColumns = [
            ['data' => 'expand', 'name' => 'expand', 'title' => '', 'orderable' => false, 'searchable' => false],
            ['data' => 'order_id', 'name' => 'order_id', 'title' => __('labels.order_id'), 'orderable' => false],
            ['data' => 'order_date', 'name' => 'created_at', 'title' => __('labels.order_date')],
            ['data' => 'buyer', 'name' => 'buyer', 'title' => __('labels.customer'), 'orderable' => false],
            ['data' => 'payment', 'name' => 'payment', 'title' => __('labels.menu_payment'), 'orderable' => false, 'searchable' => false],
            ['data' => 'total', 'name' => 'total_price', 'title' => __('labels.total'), 'orderable' => false],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'orderable' => false, 'searchable' => false],
            ['data' => 'actions', 'name' => 'actions', 'title' => __('labels.actions'), 'orderable' => false, 'searchable' => false],
        ];

        $methodTabs = $this->fulfillmentMethodTabs();

        return view($this->panelView('orders.index'), compact('orderColumns', 'methodTabs'));
    }

    public function getPopupOrderStatuses(Request $request): JsonResponse
    {
        $isSeller = $this->getPanel() === 'seller';
        $this->authorize('viewAny', $isSeller ? SellerOrder::class : Order::class);

        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'max:100'],
            'order_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ]);

        if ($isSeller) {
            $sellerId = (int) $request->user()->seller()?->id;
            $sellerOrders = SellerOrder::query()
                ->whereIn('order_id', $validated['order_ids'])
                ->where('seller_id', $sellerId)
                ->with(['order.user', 'order.market', 'items.product'])
                ->get();
            $resolvedIds = $sellerOrders->filter(fn (SellerOrder $sellerOrder) =>
                (string) $sellerOrder->status === (string) OrderStatusEnum::CANCELLED()
                || (string) $sellerOrder->fulfillment_status === (string) FulfillmentStatusEnum::FULFILLED()
                || (string) $sellerOrder->order?->status === (string) OrderStatusEnum::CANCELLED()
                || (string) $sellerOrder->order?->fulfillment_status === (string) FulfillmentStatusEnum::FULFILLED()
            )->pluck('order_id');
            $orders = $sellerOrders->reject(fn (SellerOrder $sellerOrder) => $resolvedIds->contains($sellerOrder->order_id))->map(function (SellerOrder $sellerOrder) {
                $order = $sellerOrder->order;

                return [
                    'id' => (int) $order->id,
                    'label' => '#'.($order->order_number ?? $order->id),
                    'customer' => $order->walkin_customer_name ?: $order->shipping_name ?: $order->billing_name ?: $order->user?->name ?: __('labels.customer'),
                    'location' => collect([$order->shipping_city, $order->shipping_zip])->filter()->implode(', '),
                    'item_count' => (int) $sellerOrder->items->sum('quantity'),
                    'items' => $sellerOrder->items->take(3)->map(fn ($item) => [
                        'image' => $item->product?->main_image,
                        'name' => $item->product?->name ?? $item->product?->title ?? __('labels.product'),
                    ])->values(),
                    'total' => $order->market
                        ? PricePresenter::format((float) $sellerOrder->total_price, $order->market, PricePresenter::symbolFor((string) $order->currency_code))
                        : $order->currency_code.' '.number_format((float) $sellerOrder->total_price, 2),
                    'placed_at' => $order->created_at?->diffForHumans() ?? __('labels.just_now'),
                    'url' => route('seller.orders.show', $sellerOrder->id),
                ];
            })->values();
        } else {
            $allOrders = Order::query()
                ->whereIn('id', $validated['order_ids'])
                ->with(['user', 'market', 'items.product'])
                ->get();
            $resolvedIds = $allOrders->filter(fn (Order $order) =>
                (string) $order->status === (string) OrderStatusEnum::CANCELLED()
                || (string) $order->fulfillment_status === (string) FulfillmentStatusEnum::FULFILLED()
            )->pluck('id');
            $orders = $allOrders->reject(fn (Order $order) => $resolvedIds->contains($order->id))->map(function (Order $order) {
                return [
                    'id' => (int) $order->id,
                    'label' => '#'.($order->order_number ?? $order->id),
                    'customer' => $order->walkin_customer_name ?: $order->shipping_name ?: $order->billing_name ?: $order->user?->name ?: __('labels.customer'),
                    'location' => collect([$order->shipping_city, $order->shipping_zip])->filter()->implode(', '),
                    'item_count' => (int) $order->items->sum('quantity'),
                    'items' => $order->items->take(3)->map(fn ($item) => [
                        'image' => $item->product?->main_image,
                        'name' => $item->product?->name ?? $item->product?->title ?? __('labels.product'),
                    ])->values(),
                    'total' => $order->market
                        ? PricePresenter::format((float) ($order->final_total ?? $order->total_payable), $order->market, PricePresenter::symbolFor((string) $order->currency_code))
                        : $order->currency_code.' '.number_format((float) ($order->final_total ?? $order->total_payable), 2),
                    'placed_at' => $order->created_at?->diffForHumans() ?? __('labels.just_now'),
                    'url' => route('admin.orders.show', $order->id),
                ];
            })->values();
        }

        return response()->json([
            'resolved_order_ids' => $resolvedIds->map(fn ($id) => (int) $id)->values(),
            'orders' => $orders,
        ]);
    }

    /**
     * One listing tab per fulfillment method: manual always, plus each
     * third-party provider that is enabled or already has orders. Labels come
     * from the shipping settings display titles.
     */
    private function fulfillmentMethodTabs(): array
    {
        $config = app(\App\Services\SettingService::class)->shiprocketConfig();

        $tabs = [[
            'key' => (string)FulfillmentMethodEnum::MANUAL(),
            'label' => trim((string)($config['manualTitle'] ?? '')) ?: 'Standard Shipping',
            'icon' => 'ti-truck'
        ]];

        $shiprocket = (string)FulfillmentMethodEnum::SHIPROCKET();
        $hasShiprocketOrders = SellerOrder::query()
            ->where('fulfillment_method', $shiprocket)
            ->when($this->getPanel() === 'seller',
                fn($q) => $q->where('seller_id', (int)(auth()->user()?->seller()?->id ?? 0)))
            ->exists();

        if (!empty($config['shiprocketEnabled']) || $hasShiprocketOrders) {
            $tabs[] = [
                'key' => $shiprocket,
                'label' => trim((string)($config['shiprocketTitle'] ?? '')) ?: 'Shiprocket',
                'icon' => 'ti-truck-delivery'
            ];
        }

        return $tabs;
    }

    /**
     * DataTable feed for the order-grouped listing tab.
     * One row per order (admin: per Order, seller: per SellerOrder).
     */
    public function getOrdersGrouped(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission, 403);

        $draw = (int)$request->input('draw');
        $start = (int)$request->input('start', 0);
        $length = (int)$request->input('length', 10);

        $searchValue = $request->input('search.value', '');
        $status = $request->input('status');
        $paymentMethod = $request->input('payment_method', $request->input('payment_type'));
        $paymentStatus = $request->input('payment_status');
        $lifecycle = $request->input('lifecycle');
        $dateRange = $request->input('range');
        $orderType = $request->input('order_type');
        $marketId = $this->getPanel() === 'admin'
            ? app(MarketService::class)->adminSelectedId()
            : $request->input('market_id');
        $storeId = $this->getPanel() === 'seller'
            ? app(SellerStoreService::class)->selectedId()
            : $request->input('store_id');

        $isSeller = $this->getPanel() === 'seller';

        if ($isSeller) {
            $seller = auth()->user()?->seller();

            if (!$seller) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.seller_not_found')
                );
            }

            $query = SellerOrder::query()
                ->with(['order', 'order.market.currency', 'items.product.media', 'items.variant.media'])
                ->withCount('items')
                ->where('seller_id', $seller->id);

            $this->hideOnlinePendingForSeller($query);
        } else {
            $query = Order::query()->with(['market.currency', 'items.product.media', 'items.variant.media', 'sellerOrders'])->withCount('items');
        }

        $fulfillmentMethod = $request->input('fulfillment_method');

        $scopedQuery = clone $query;
        $this->applyCommonFilters($scopedQuery, null, null, null, null, null, $marketId, $storeId, $isSeller, $fulfillmentMethod);
        $totalRecords = $scopedQuery->count();

        $this->applyCommonFilters($query, $status, $paymentMethod, $paymentStatus, $lifecycle, $orderType, $marketId, $storeId, $isSeller, $fulfillmentMethod);

        if ($dateRange) {
            $fromDate = $this->getDateRange($dateRange);
            if ($fromDate) {
                $query->where('created_at', '>=', $fromDate);
            }
        }

        $this->applyDatatableDateRange($query, $request, 'created_at', $isSeller ? 'order' : null);

        if (!empty($searchValue)) {
            if ($isSeller) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('id', 'like', "%$searchValue%")
                        ->orWhere('total_price', 'like', "%$searchValue%")
                        ->orWhereHas('order', function ($oq) use ($searchValue) {
                            $oq->where('id', 'like', "%$searchValue%")
                                ->orWhere('order_number', 'like', "%$searchValue%")
                                ->orWhere('uuid', 'like', "%$searchValue%")
                                ->orWhere('shipping_name', 'like', "%$searchValue%");
                        });
                });
            } else {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('id', 'like', "%$searchValue%")
                        ->orWhere('order_number', 'like', "%$searchValue%")
                        ->orWhere('uuid', 'like', "%$searchValue%")
                        ->orWhere('shipping_name', 'like', "%$searchValue%")
                        ->orWhere('final_total', 'like', "%$searchValue%");
                });
            }
        }

        $hasActiveFilters = collect([
            $status,
            $paymentMethod,
            $paymentStatus,
            $lifecycle,
            $dateRange,
            $request->input('date_from'),
            $request->input('date_to'),
            $orderType,
            $searchValue,
        ])->contains(fn ($value): bool => $value !== null && $value !== '');

        $filteredRecords = $hasActiveFilters ? $query->count() : $totalRecords;

        $columnMap = [
            2 => 'created_at',
        ];
        $orderColumnIndex = (int)($request->get('order')[0]['column'] ?? 2);
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';
        $orderColumn = $columnMap[$orderColumnIndex] ?? 'created_at';

        $rows = $query->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $rows->map(fn($row) => $this->formatOrderGroupedRow($row, $isSeller));

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Apply common filters for both seller and admin panels
     */
    private function applyCommonFilters(
        $query,
        ?string $status,
        ?string $paymentMethod,
        ?string $paymentStatus,
        ?string $lifecycle,
        ?string $orderType,
        $marketId,
        $storeId,
        bool $isSeller,
        ?string $fulfillmentMethod = null
    ): void
    {
        if ($status) {
            $fulfillmentValues = array_map(fn($c) => (string)$c->value, FulfillmentStatusEnum::cases());
            if (in_array(strtolower($status), $fulfillmentValues, true)) {
                if ($status) {
                    if (is_array($status)) {
                        $query->where(function ($q) use ($status) {
                            $q->whereIn('fulfillment_status', $status)
                                ->orWhereIn('status', $status);
                        });
                    } else {
                        $query->where(function ($q) use ($status) {
                            $q->where('fulfillment_status', $status)
                                ->orWhere('status', $status);
                        });
                    }
                }
            } else {
                $query->where('status', $status);
            }
        }

        if ($fulfillmentMethod) {
            $query->when($isSeller,
                fn($q) => $q->where('fulfillment_method', $fulfillmentMethod),
                fn($q) => $q->whereHas('sellerOrders', fn($qq) => $qq->where('fulfillment_method', $fulfillmentMethod))
            );
        }

        if ($paymentMethod) {
            $query->when($isSeller,
                fn($q) => $q->whereHas('order', fn($qq) => $qq->where('payment_method', $paymentMethod)),
                fn($q) => $q->where('payment_method', $paymentMethod)
            );
        }

        if ($paymentStatus) {
            $query->when($isSeller,
                fn($q) => $q->whereHas('order', fn($qq) => $qq->where('payment_status', $paymentStatus)),
                fn($q) => $q->where('payment_status', $paymentStatus)
            );
        }

        if ($lifecycle) {
            $query->when($isSeller,
                fn($q) => $q->whereHas('order', fn($qq) => $qq->where('status', $lifecycle)),
                fn($q) => $q->where('status', $lifecycle)
            );
        }

        if ($orderType) {
            if ($orderType === 'pos') {
                $query->when($isSeller,
                    fn($q) => $q->whereHas('order', fn($qq) => $qq->where('created_by', OrderCreatedByEnum::SELLER())),
                    fn($q) => $q->where('created_by', OrderCreatedByEnum::SELLER())
                );
            } elseif ($orderType === 'regular') {
                $query->when($isSeller,
                    fn($q) => $q->whereHas('order', fn($qq) => $qq->whereNull('created_by')->orWhere('created_by', OrderCreatedByEnum::CUSTOMER())
                    ),
                    fn($q) => $q->whereNull('created_by')->orWhere('created_by', OrderCreatedByEnum::CUSTOMER())
                );
            }
        }

        if ($marketId) {
            $query->when($isSeller,
                fn($q) => $q->whereHas('order', fn($qq) => $qq->where('market_id', (int)$marketId)),
                fn($q) => $q->where('market_id', (int)$marketId)
            );
        }

        if ($storeId && $isSeller) {
            $query->whereHas('items.orderItem', fn($q) => $q->where('store_id', (int)$storeId));
        }
    }

    private function formatOrderGroupedRow($row, bool $isSeller): array
    {
        $expand = '<button type="button" class="btn btn-icon btn-ghost-secondary expand-order-items" data-order-id="' . (int)$row->id . '" aria-label="' . e(__('labels.order_items')) . '"><i class="ti ti-chevron-right"></i></button>';

        if ($isSeller) {
            $order = $row->order;
            $publicId = $order?->order_number ?? $order?->id ?? '—';
            $buyer = $order?->shipping_name ?? '—';
            $paymentMethod = $order?->payment_method ?? '—';
            $paymentStatus = $order?->payment_status;
            $statusValue = $order?->status;
            $total = $row->total_price ?? 0;
            $market = $order?->market;
            $detailRoute = route('seller.orders.show', $row->id);
        } else {
            $publicId = $row->order_number ?? $row->id;
            $buyer = $row->shipping_name ?? '—';
            $paymentMethod = $row->payment_method ?? '—';
            $paymentStatus = $row->payment_status;
            $statusValue = $row->status;
            $total = $row->final_total ?? 0;
            $market = $row->market;
            $detailRoute = route('admin.orders.show', $row->id);
        }

        $paymentStatusVal = $paymentStatus->value ?? $paymentStatus ?? null;

        $totalFormatted = formatOrderMoney($total, PricePresenter::payload($market));

        $itemsCount = (int)($row->items_count ?? 0);
        $itemsBadge = '<span class="badge bg-secondary-lt"><i class="ti ti-package me-1"></i>'
            . $itemsCount . ' ' . ($itemsCount === 1 ? e(__('labels.item')) : e(__('labels.items'))) . '</span>';

        $itemImages = ($row->relationLoaded('items') ? $row->items : collect())
            ->map(fn($it) => ($it->variant?->image ?: ($it->product?->main_image ?? null)))
            ->filter()
            ->values();
        $thumbsHtml = '';
        if ($itemImages->isNotEmpty()) {
            $thumbsHtml = '<div class="d-flex align-items-center gap-1 mb-1">';
            foreach ($itemImages->take(3) as $img) {
                $thumbsHtml .= '<img src="' . e($img) . '" alt="" class="rounded border" style="width:50px;height:50px;object-fit:cover;">';
            }
            $extra = $itemImages->count() - 3;
            if ($extra > 0) {
                $thumbsHtml .= '<span class="badge bg-secondary-lt text-secondary" style="width:50px;height:50px;">+' . $extra . '</span>';
            }
            $thumbsHtml .= '</div>';
        }
        $itemsBadge = $thumbsHtml . $itemsBadge;

        $lifecycle = $statusValue->value ?? $statusValue ?? null;

        $fulfillment = ($row->fulfillment_status ?? null);
        $fulfillment = $fulfillment->value ?? $fulfillment ?? 'unfulfilled';

        $paymentCell = '<div class="d-flex flex-column gap-1">'
            . '<span class="badge bg-blue-lt text-uppercase">' . e($paymentMethod) . '</span>'
            . ($paymentStatusVal
                ? '<span class="badge ' . paymentStatusBadgeClass($paymentStatusVal) . ' text-uppercase">'
                . e(formatOrderStatusLabel($paymentStatusVal)) . '</span>'
                : '')
            . '</div>';

        return [
            '_export_id' => $row->id,
            'expand' => $expand,
            'order_id' => '<span class="fw-medium">#' . e($publicId) . '</span>' . $itemsBadge,
            'order_date' => $row->created_at?->format('Y-m-d H:i') ?? '—',
            'buyer' => '<span class="text-capitalize">' . e($buyer) . '</span>',
            'payment' => $paymentCell,
            'total' => '<span class="fw-semibold">' . e($totalFormatted) . '</span>',
            'status' => view('partials.order-status-axes', [
                    'fulfillment' => $fulfillment,
                    'lifecycle' => $lifecycle,
                ])->render() . $this->manualShipByCell($row, $isSeller, $lifecycle),
            'actions' => '<a href="' . $detailRoute . '" class="btn btn-icon btn-outline-primary" title="' . e(__('labels.view')) . '"><i class="ti ti-eye"></i></a>',
        ];
    }

    /**
     * "Ship by <date>" caption (red when overdue) appended to the status cell
     * of the grouped order list — only for open orders with a manual seller
     * order that still has unfulfilled items and a dispatch SLA configured.
     */
    private function manualShipByCell($row, bool $isSeller, ?string $lifecycle): string
    {
        if ($lifecycle !== (string)OrderStatusEnum::OPEN()) {
            return '';
        }

        $sellerOrders = $isSeller
            ? collect([$row])
            : ($row->relationLoaded('sellerOrders') ? $row->sellerOrders : collect());

        $shipmentService = app(ShipmentService::class);
        $fulfilled = (string)FulfillmentStatusEnum::FULFILLED();

        $shipBy = $sellerOrders
            ->filter(fn($so) => (string)($so->fulfillment_status ?? 'unfulfilled') !== $fulfilled)
            ->map(fn($so) => $shipmentService->manualShipByDate($so))
            ->filter()
            ->sort()
            ->first();

        if (!$shipBy) {
            return '';
        }

        if (now()->greaterThan($shipBy)) {
            return '<div class="mt-1"><span class="badge bg-red-lt text-red"><i class="ti ti-alert-triangle me-1"></i>'
                . e(__('labels.ship_by_overdue')) . ' · ' . e($shipBy->format('d M')) . '</span></div>';
        }

        return '<div class="text-secondary small mt-1"><i class="ti ti-clock me-1"></i>'
            . e(__('labels.ship_by')) . ' ' . e($shipBy->format('d M')) . '</div>';
    }

    /**
     * Returns the items belonging to an order, scoped per-panel, for the
     * inline expand row in the orders-grouped listing tab.
     */
    public function getOrderItemsForExpand(int $id): JsonResponse
    {
        abort_unless($this->viewPermission, 403);

        try {
            $isSeller = $this->getPanel() === 'seller';

            if ($isSeller) {
                $seller = auth()->user()?->seller();
                if (!$seller) {
                    return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), []);
                }
                $sellerOrder = SellerOrder::with([
                    'order',
                    'order.market.currency',
                    'items.orderItem.store',
                    'items.orderItem.addons.addonGroup',
                    'items.orderItem.addons.addonItem',
                    'items.orderItem.media',
                    'items.product.media',
                    'items.variant.media',
                ])
                    ->where('id', $id)
                    ->where('seller_id', $seller->id)
                    ->firstOrFail();

                if ($this->isOnlinePaymentPending($sellerOrder->order)) {
                    return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
                }

                $this->authorize('viewAny', $sellerOrder);

                $items = $sellerOrder->items->map(fn($item) => $this->mapExpandItem($item->orderItem, $item->product, $item->variant));
                $detailRoute = route('seller.orders.show', $sellerOrder->id);
                $orderUuid = $sellerOrder->order?->uuid;
                $orderCurrency = PricePresenter::payload($sellerOrder->order?->market);
            } else {
                $order = Order::with([
                    'market.currency',
                    'items.product.media',
                    'items.variant.media',
                    'items.store',
                    'items.addons.addonGroup',
                    'items.addons.addonItem',
                    'items.media',
                ])->findOrFail($id);

                $this->authorize('viewAny', $order);

                $items = $order->items->map(fn($item) => $this->mapExpandItem($item, $item->product, $item->variant));
                $detailRoute = route('admin.orders.show', $order->id);
                $orderUuid = $order->uuid;
                $orderCurrency = PricePresenter::payload($order->market);
            }

            $html = view('partials.order-items-expand', [
                'items' => $items,
                'panel' => $this->getPanel(),
                'editPermission' => $isSeller && $this->editPermission,
                'detailRoute' => $detailRoute,
                'orderUuid' => $orderUuid,
                'currencyService' => $this->currencyService,
                'orderCurrency' => $orderCurrency ?? null,
            ])->render();

            return ApiResponseType::sendJsonResponse(true, 'labels.success', ['html' => $html]);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    private function mapExpandItem($orderItem, $product, $variant): array
    {
        $image = !empty($variant?->image) ? $variant->image : ($product?->main_image ?? null);
        $addons = $orderItem?->addons ?? collect();

        $attachments = [];
        if ($orderItem) {
            foreach ($orderItem->getMedia(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS()) as $media) {
                $attachments[] = $media->getUrl();
            }
        }

        return [
            'id' => $orderItem?->id,
            'image' => $image,
            'product_title' => $orderItem?->title ?? $product?->title ?? '—',
            'variant_title' => $orderItem?->variant_title ?? $variant?->title,
            'sku' => $orderItem?->sku,
            'quantity' => $orderItem?->quantity ?? 0,
            'subtotal' => $orderItem?->subtotal ?? 0,
            'status' => $orderItem?->status,
            'store' => $orderItem?->store?->name,
            'addons' => $addons,
            'attachments' => $attachments,
        ];
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
     * Display the specified order.
     *
     * @param int $id
     * @return View
     */
    public function show($id): View
    {
        abort_unless($this->viewPermission, 403);

        $panel = $this->getPanel();
        $isSeller = $panel === 'seller';

        if ($isSeller) {
            $seller = auth()->user()?->seller();
            if (!$seller) {
                abort(404, __('labels.seller_not_found'));
            }
        }

        $orderData = $isSeller
            ? $this->loadSellerOrder($id, $seller)
            : $this->loadAdminOrder($id);

        if ($isSeller && $this->isOnlinePaymentPending($orderData->order)) {
            abort(404, __('labels.order_not_found'));
        }

        $this->authorize('viewAny', SellerOrder::class);


        if ($isSeller) {
            $sellerOrder = $orderData;
            $shipments = $sellerOrder->shipments;
            $auditLogs = $this->getSellerOrderAuditLogs($sellerOrder);
            $cancelledItemHistory = $this->buildCancelledItemHistory(
                $auditLogs,
                $sellerOrder->items->pluck('orderItem')->filter()
            );
            $fulfillment = $this->buildSellerFulfillment($sellerOrder);
            $fulfillments = [$fulfillment];
            $itemTransitions = $this->buildSellerItemTransitions($sellerOrder);
            $viewOrder = $sellerOrder;

            $returnsPayload = $this->buildSellerReturns($sellerOrder);
            $returns = $returnsPayload['returns'];
            $returnableItems = [];
            $createReturnUrl = null;

            $restockStatuses = [
                (string)OrderItemStatusEnum::CANCELLED(),
                (string)OrderItemStatusEnum::RETURNED(),
                (string)OrderItemStatusEnum::REFUNDED(),
            ];
            $restockableItems = $sellerOrder->items
                ->map(fn($soi) => $soi->orderItem)
                ->filter(fn($it) => $it
                    && !$it->is_restocked
                    && (int)$it->quantity > 0
                    && in_array((string)$it->status, $restockStatuses, true))
                ->map(fn($it) => [
                    'id' => (int)$it->id,
                    'product_id' => (int)($it->product_id ?? 0),
                    'title' => $it->title ?? '—',
                    'variant' => $it->variant_title ?? '-',
                    'image' => $it->variant?->image ?: $it->product?->main_image,
                    'quantity' => (int)$it->quantity,
                    'status' => (string)$it->status,
                ])->values()->all();
            $restockUrl = route('seller.orders.restock', ['id' => $sellerOrder->id]);

            $earnings = new OrderEarningsCalculator()->forSellerOrder($sellerOrder);
        } else {
            $order = $orderData['order'];
            $shipments = $orderData['shipment'];
            $auditLogs = $this->getOrderAuditLogs($order);
            $cancelledItemHistory = $this->buildCancelledItemHistory($auditLogs, $order->items);
            $fulfillment = null;
            $fulfillments = $this->buildAdminFulfillments($order);
            $itemTransitions = $this->buildAdminItemTransitions($order);
            $viewOrder = $order;

            $returnsPayload = $this->buildAdminReturns($order);
            $returns = $returnsPayload['returns'];
            $returnableItems = $returnsPayload['eligible_items'];
            $createReturnUrl = route('admin.orders.returns.create', ['id' => $order->id]);

            $restockStatuses = [
                (string)OrderItemStatusEnum::CANCELLED(),
                (string)OrderItemStatusEnum::RETURNED(),
                (string)OrderItemStatusEnum::REFUNDED(),
            ];
            $restockableItems = $order->items
                ->filter(fn($it) => !$it->is_restocked
                    && (int)$it->quantity > 0
                    && in_array((string)$it->status, $restockStatuses, true))
                ->map(fn($it) => [
                    'id' => (int)$it->id,
                    'product_id' => (int)($it->product_id ?? 0),
                    'title' => $it->title ?? '—',
                    'variant' => $it->variant_title ?? "—",
                    'image' => $it->variant?->image ?: $it->product?->main_image,
                    'quantity' => (int)$it->quantity,
                    'status' => (string)$it->status,
                ])->values()->all();
            $restockUrl = route('admin.orders.restock', ['id' => $order->id]);

            $earnings = new OrderEarningsCalculator()->forOrder($order);
        }

        return view($this->panelView('orders.show'), [
            'order' => $viewOrder,
            'shipments' => $shipments->toArray(),
            'panel' => $panel,
            'isSeller' => $isSeller,
            'editPermission' => $this->editPermission,
            'refundPermission' => $isSeller ? false : $this->refundPermission,
            'paymentAcceptPermission' => $isSeller ? false : $this->paymentAcceptPermission,
            'auditLogs' => $auditLogs,
            'cancelledItemHistory' => $cancelledItemHistory,
            'fulfillment' => $fulfillment,
            'fulfillments' => $fulfillments,
            'itemTransitions' => $itemTransitions,
            'returns' => $returns,
            'returnableItems' => $returnableItems,
            'createReturnUrl' => $createReturnUrl,
            'restockableItems' => $restockableItems,
            'restockUrl' => $restockUrl,
            'earnings' => $earnings,
        ]);
    }

    private function loadSellerOrder(int $id, $seller): SellerOrder
    {
        return SellerOrder::where('id', $id)
            ->with([
                'order',
                'order.market.currency',
                'items.product',
                'items.variant',
                'items.orderItem',
                'items.orderItem.sellerOrderItem',
                'items.orderItem.addons.addonGroup',
                'items.orderItem.addons.addonItem',
                'items.orderItem.comboComponents',
                'order.items.store',
                'shipments.items.orderItem.product',
                'shipments.items.orderItem.variant',
                'shipments.items.orderItem.comboComponents',
                'shippingZone',
                'storeLocation',
            ])
            ->where('seller_id', $seller->id)
            ->firstOrFail();
    }

    private function loadAdminOrder(int $id): array
    {

        return [
            'order' => Order::with([
                'market.currency',
                'items',
                'items.product',
                'items.variant',
                'items.comboComponents',
                'refunds.issuer',
                'items.sellerOrderItem',
                'refunds.items',
                'refunds.settles',
                'paymentTransactions',
            ])->findOrFail($id),
            'shipment' => SellerOrder::with(['shippingZone', 'shippingZone.profile', 'storeLocation', 'shipments', 'items.orderItem', 'items.orderItem.comboComponents', 'items.product', 'items.variant'])->where('order_id', $id)->get(),
        ];
    }

    /**
     * Build per-order-item transitions for the admin bulk-update UI.
     */
    private function buildAdminItemTransitions(Order $order): array
    {
        $order->loadMissing(['items']);

        $transitions = [];

        foreach ($order->items as $item) {
            $transitions[(int)$item->id] = $this->mapItemTransitions((string)($item->status ?? ''));
        }

        return $transitions;
    }

    /**
     * Build per-order-item transitions for the seller bulk-update UI.
     *
     * The seller panel only exposes the same transitions the web endpoint
     * accepts: accept/reject for actionable lines, plus confirm_return for
     * items that are already returning to the store.
     */
    private function buildSellerItemTransitions(SellerOrder $sellerOrder): array
    {
        $sellerOrder->loadMissing(['items.orderItem']);

        $transitions = [];

        foreach ($sellerOrder->items as $item) {
            $orderItem = $item->orderItem;
            if (!$orderItem) {
                continue;
            }

            $transitions[(int)$orderItem->id] = $this->mapItemTransitions((string)($orderItem->status ?? ''));
        }

        return $transitions;
    }

    /**
     * Shared transition map for admin and seller panels.
     */
    private function mapItemTransitions(string $status): array
    {
        return [];
    }


    private function getOrderAuditLogs($order): \Illuminate\Support\Collection
    {
        $orderId = $order instanceof Order ? $order->id : ($order->order_id ?? null);

        if (!$orderId) {
            return collect();
        }

        return OrderAuditLog::with('admin')
            ->where('order_id', $orderId)
            ->orderByDesc('created_at')
            ->get();
    }

    private function buildCancelledItemHistory($auditLogs, $items): array
    {
        $itemsById = collect($items)->filter()->keyBy(fn($item) => (int)$item->id);
        $cancelledByItem = [];

        foreach (collect($auditLogs)->where('action', 'admin_cancel_lines') as $log) {
            foreach ((array)data_get($log, 'new_value.lines', []) as $line) {
                $itemId = (int)($line['order_item_id'] ?? 0);
                $quantity = (int)($line['cancel_qty'] ?? 0);
                if ($itemId <= 0 || $quantity <= 0 || !$itemsById->has($itemId)) {
                    continue;
                }

                if (!isset($cancelledByItem[$itemId])) {
                    $cancelledByItem[$itemId] = [
                        'quantity' => 0,
                        'reason' => $log->reason,
                        'is_restocked' => true,
                    ];
                }

                $cancelledByItem[$itemId]['quantity'] += $quantity;
                $cancelledByItem[$itemId]['is_restocked'] = $cancelledByItem[$itemId]['is_restocked']
                    && (bool)($line['restock'] ?? false);
            }
        }

        $presenter = new \App\Services\OrderCustomerStatusPresenter();
        foreach ($itemsById as $itemId => $item) {
            $quantities = $presenter->quantitiesForItem($item);
            if ($quantities['cancelled'] !== null && $quantities['cancelled'] > 0) {
                $cancelledByItem[$itemId] = array_merge([
                    'reason' => $item->cancellation_reason,
                    'is_restocked' => (bool) $item->is_restocked,
                ], $cancelledByItem[$itemId] ?? [], ['quantity' => $quantities['cancelled']]);
            }
        }

        return collect($cancelledByItem)->map(function ($history, $itemId) use ($itemsById) {
            $item = $itemsById->get((int)$itemId);
            $currentQuantity = max(1, (int)$item->quantity);
            $unitSubtotal = (float)$item->subtotal / $currentQuantity;

            return [
                'id' => (int)$item->id,
                'source_item_id' => (int)$item->id,
                'status' => (string)OrderItemStatusEnum::CANCELLED(),
                'quantity' => (int)$history['quantity'],
                'title' => $item->title,
                'product_id' => (int)$item->product_id,
                'variant_title' => $item->variant_title,
                'price' => (float)$item->price,
                'subtotal' => round($unitSubtotal * (int)$history['quantity'], 2),
                'is_restocked' => (bool)$history['is_restocked'],
                'cancellation_reason' => $history['reason'],
                'variant' => ['image' => $item->variant?->image],
                'product' => ['main_image' => $item->product?->main_image],
                'attachments' => [],
                'addons' => [],
                'is_cancellation_history' => true,
            ];
        })->values()->all();
    }

    private function buildAdminFulfillments(Order $order): array
    {
        $order->loadMissing([
            'sellerOrders.shipments.items.orderItem.product',
            'sellerOrders.shipments.items.orderItem.variant',
            'sellerOrders.shippingZone',
            'sellerOrders.storeLocation',
            'sellerOrders.seller.user',
        ]);

        return $order->sellerOrders
            ->map(fn($sellerOrder) => $this->buildSellerFulfillment($sellerOrder))
            ->all();
    }

    /**
     * Build the returns payload for the admin order page: existing returns (each
     * with a link to the refund screen) plus the items still eligible for a
     * direct (admin-created) return.
     */
    private function buildAdminReturns(Order $order): array
    {
        $returnRows = OrderItemReturn::with(['orderItem.product', 'orderItem.variant', 'sourceShipment'])
            ->where('order_id', $order->id)
            ->orderByDesc('id')
            ->get();

        $finalStatuses = [
            (string)OrderItemReturnStatusEnum::REFUNDED(),
            (string)OrderItemReturnStatusEnum::DECLINED(),
            (string)OrderItemReturnStatusEnum::CANCELLED(),
            (string)OrderItemReturnStatusEnum::CLOSED(),
        ];

        $approvedStatus = (string)OrderItemReturnStatusEnum::APPROVED();
        $returns = $returnRows->map(function ($r) use ($finalStatuses, $approvedStatus) {
            $status = (string)$r->return_status;
            $item = $r->orderItem;
            return [
                'id' => (int)$r->id,
                'product_id' => (int)($item?->product_id ?? 0),
                'title' => $item?->title ?? '—',
                'variant' => $item?->variant_title ?? null,
                'image' => $item?->variant?->image ?: $item?->product?->main_image,
                'quantity' => (int)($r->quantity ?: 1),
                'source_shipment_id' => $r->source_shipment_id,
                'source_tracking' => $r->sourceShipment?->tracking_number ?: $r->sourceShipment?->awb_number,
                'reason' => $r->reason_code?->label() ?? ($r->reason ?: null),
                'fault' => $r->reason_type?->value,
                'status' => $status,
                'status_label' => ucwords(str_replace('_', ' ', $status)),
                'refund_amount' => (float)$r->refund_amount,
                'is_final' => in_array($status, $finalStatuses, true),
                'is_approved' => $status === $approvedStatus,
                'images' => collect($r->images ?? [])->values()->all(),
                'pickup_carrier' => $r->return_carrier,
                'pickup_tracking' => $r->return_tracking_number,
                'pickup_tracking_url' => $r->return_tracking_url,
                'review_url' => route('admin.orders.return_review', ['returnId' => $r->id]),
                'refund_url' => route('admin.orders.refund', ['id' => $r->order_id]),
            ];
        })->values()->all();

        $eligible = [];
        foreach ($order->items as $item) {
            foreach ($this->orderService->returnableShipments($item, false) as $shipment) {
                $eligible[] = [
                    'id' => (int)$item->id,
                    'shipment_id' => $shipment['id'],
                    'title' => $item->title ?? '—',
                    'variant' => $item->variant_title ?? "-",
                    'quantity' => $shipment['quantity'],
                    'price' => (float)$item->price,
                    'image' => $item->product?->main_image ?? null,
                    'store_name' => $item->store?->name,
                    'net' => round(((float)$item->subtotal - (float)$item->promo_discount) * $shipment['quantity'] / max(1, (int)$item->quantity), 2),
                ];
            }
        }

        return ['returns' => $returns, 'eligible_items' => $eligible];
    }

    /**
     * Seller-scoped returns payload for the seller order page. Same shape as
     * buildAdminReturns but limited to the items in this seller order, with
     * seller route names for review/create (no refund links — refunds are
     * admin-only).
     */
    private function buildSellerReturns(SellerOrder $sellerOrder): array
    {
        if (!$this->returnViewPermission) {
            return [];
        }

        $sellerOrder->loadMissing(['items.orderItem.product', 'items.orderItem.variant', 'items.orderItem.store']);

        $orderItemIds = $sellerOrder->items
            ->map(fn($soi) => (int)($soi->order_item_id ?? $soi->orderItem?->id))
            ->filter()->values()->all();

        $returnRows = OrderItemReturn::with(['orderItem.product', 'orderItem.variant', 'sourceShipment'])
            ->whereIn('order_item_id', $orderItemIds)
            ->orderByDesc('id')
            ->get();

        $finalStatuses = [
            (string)OrderItemReturnStatusEnum::REFUNDED(),
            (string)OrderItemReturnStatusEnum::DECLINED(),
            (string)OrderItemReturnStatusEnum::CANCELLED(),
            (string)OrderItemReturnStatusEnum::CLOSED(),
        ];
        $approvedStatus = (string)OrderItemReturnStatusEnum::APPROVED();

        $returns = $returnRows->map(function ($r) use ($finalStatuses, $approvedStatus) {
            $status = (string)$r->return_status;
            $item = $r->orderItem;
            return [
                'id' => (int)$r->id,
                'product_id' => (int)($item?->product_id ?? 0),
                'title' => $item?->title ?? '—',
                'variant' => $item?->variant_title ?? null,
                'image' => $item?->variant?->image ?: $item?->product?->main_image,
                'quantity' => (int)($r->quantity ?: 1),
                'source_shipment_id' => $r->source_shipment_id,
                'source_tracking' => $r->sourceShipment?->tracking_number ?: $r->sourceShipment?->awb_number,
                'reason' => $r->reason_code?->label() ?? ($r->reason ?: null),
                'fault' => $r->reason_type?->value,
                'status' => $status,
                'status_label' => ucwords(str_replace('_', ' ', $status)),
                'refund_amount' => (float)$r->refund_amount,
                'is_final' => in_array($status, $finalStatuses, true),
                'is_approved' => $status === $approvedStatus,
                'images' => collect($r->images ?? [])->values()->all(),
                'pickup_carrier' => $r->return_carrier,
                'pickup_tracking' => $r->return_tracking_number,
                'pickup_tracking_url' => $r->return_tracking_url,
                'review_url' => route('seller.orders.return_review', ['returnId' => $r->id]),
                'refund_url' => null,
            ];
        })->values()->all();

        $eligible = [];
        foreach ($sellerOrder->items as $soi) {
            $item = $soi->orderItem;
            if (!$item) {
                continue;
            }
            foreach ($this->orderService->returnableShipments($item, false) as $shipment) {
                $eligible[] = [
                    'id' => (int)$item->id,
                    'shipment_id' => $shipment['id'],
                    'title' => $item->title ?? '—',
                    'variant' => $item->variant_title ?? "-",
                    'quantity' => $shipment['quantity'],
                    'price' => (float)$item->price,
                    'image' => $item->product?->main_image ?? null,
                    'store_name' => $item->store?->name,
                    'net' => round(((float)$item->subtotal - (float)$item->promo_discount) * $shipment['quantity'] / max(1, (int)$item->quantity), 2),
                ];
            }
        }

        return ['returns' => $returns, 'eligible_items' => $eligible];
    }

    /**
     * Read-only seller-scoped activity feed. Surfaces the order's audit log
     * entries that reference one of this seller-order's items (plus order-level
     * notes), newest first. Entries touching other sellers' items are excluded.
     */
    private function getSellerOrderAuditLogs(SellerOrder $sellerOrder): \Illuminate\Support\Collection
    {
        $orderId = (int)($sellerOrder->order_id ?? 0);
        if (!$orderId) {
            return collect();
        }

        $sellerOrder->loadMissing('items');
        $itemIds = $sellerOrder->items
            ->map(fn($soi) => (int)($soi->order_item_id ?? 0))
            ->filter()->values()->all();

        return OrderAuditLog::with('admin')
            ->where('order_id', $orderId)
            ->where(function ($q) use ($itemIds) {
                $q->whereNull('order_item_id');
                if (!empty($itemIds)) {
                    $q->orWhereIn('order_item_id', $itemIds);
                }
            })
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Build the seller fulfillment-card payload for one seller order
     * (= one shipment group). Lists the shipment(s), the current status, and
     * the next actions the per-method state machine allows.
     */
    private function buildSellerFulfillment(SellerOrder $sellerOrder): array
    {
        $shipmentService = app(ShipmentService::class);

        $method = $sellerOrder->fulfillment_method instanceof \BackedEnum
            ? $sellerOrder->fulfillment_method->value
            : (string)$sellerOrder->fulfillment_method;

        $supported = app(\App\Services\Shipping\ShipmentProviderResolver::class)->isSupported($method);

        $humanize = fn(string $v) => ucwords(strtolower(str_replace('_', ' ', $v)));

        $shippingConfig = app(\App\Services\SettingService::class)->shiprocketConfig();
        $methodLabel = match ($method) {
            (string)FulfillmentMethodEnum::MANUAL() => $shippingConfig['manualTitle'] ?? $humanize($method),
            (string)FulfillmentMethodEnum::SHIPROCKET() => $shippingConfig['shiprocketTitle'] ?? $humanize($method),
            default => $humanize($method),
        };

        $isPickup = $method === (string)FulfillmentMethodEnum::LOCAL_PICKUP();
        $cancelled = (string)ShipmentStatusEnum::CANCELLED();
        $deliveredV = (string)ShipmentStatusEnum::DELIVERED();
        $terminal = array_map(fn($s) => (string)$s, ShipmentStatusEnum::terminal());

        $shipmentItemIds = $sellerOrder->shipments
            ->flatMap(fn($s) => $s->relationLoaded('items') ? $s->items->pluck('order_item_id') : collect())
            ->filter()->map(fn($i) => (int)$i)->unique()->all();
        $committedReturns = empty($shipmentItemIds) ? collect() : OrderItemReturn::query()
            ->whereIn('order_item_id', $shipmentItemIds)
            ->whereNotIn('return_status', [
                (string)OrderItemReturnStatusEnum::CANCELLED(),
                (string)OrderItemReturnStatusEnum::DECLINED(),
            ])
            ->get(['order_item_id', 'source_shipment_id']);

        $lines = $shipmentService->lineFulfillmentSummary($sellerOrder);
        $linesByItem = collect($lines)->keyBy('order_item_id');

        $shipments = $sellerOrder->shipments->reject(fn($s) => (bool)$s->is_return)->map(function ($s) use ($shipmentService, $method, $humanize, $terminal, $deliveredV, $cancelled, $isPickup, $committedReturns, $linesByItem) {
            $status = (string)$s->status;
            $allowed = $shipmentService->allowedTransitions($method)[$status] ?? [];
            $isTerminal = in_array($status, $terminal, true);
            $isPending = $status === (string)ShipmentStatusEnum::PENDING();
            $isSrShipment = $s->provider_code === (string)FulfillmentMethodEnum::SHIPROCKET();
            $meta = (array)($s->metadata ?? []);
            $hasReturn = $s->relationLoaded('items')
                && $committedReturns->contains(fn($return) => $s->items->contains('order_item_id', $return->order_item_id)
                    && ($return->source_shipment_id === null || (int)$return->source_shipment_id === (int)$s->id));
            $shipmentProducts = $s->relationLoaded('items')
                ? $s->items->map(fn($si) => [
                    'title' => $si->orderItem->title ?? '—',
                    'variant' => $si->orderItem->variant_title ?? null,
                    'quantity' => (int)$si->quantity,
                ])->values()->all()
                : [];

            $editLines = [];
            if ($isPending && $isSrShipment && $s->relationLoaded('items')) {
                $inShipment = [];
                foreach ($s->items as $si) {
                    $itemId = (int)$si->order_item_id;
                    $inShipment[$itemId] = true;
                    $ln = $linesByItem[$itemId] ?? null;
                    $editLines[] = [
                        'order_item_id' => $itemId,
                        'title' => $si->orderItem->title ?? '—',
                        'variant' => $si->orderItem->variant_title ?? null,
                        'ordered' => (int)($ln['ordered'] ?? $si->quantity),
                        'remaining' => (int)$si->quantity + (int)($ln['remaining'] ?? 0),
                        'preset' => (int)$si->quantity,
                        'weight_kg' => $ln['weight_kg'] ?? 0,
                    ];
                }
                foreach ($linesByItem as $itemId => $ln) {
                    if (!empty($inShipment[(int)$itemId]) || (int)($ln['remaining'] ?? 0) <= 0) {
                        continue;
                    }
                    $editLines[] = [
                        'order_item_id' => (int)$itemId,
                        'title' => $ln['title'],
                        'variant' => $ln['variant'],
                        'ordered' => (int)$ln['ordered'],
                        'remaining' => (int)$ln['remaining'],
                        'preset' => 0,
                        'weight_kg' => $ln['weight_kg'] ?? 0,
                    ];
                }
            }
            $reconcile = null;
            if (
                $s->provider_code === (string)\App\Enums\Shipping\FulfillmentMethodEnum::SHIPROCKET()
                && $s->provider_shipping_cost !== null
                && $this->getPanel() === 'admin'
            ) {
                $base = \App\Models\Currency::query()->where('is_base', true)->first();
                $baseCode = $base?->code ?? 'USD';
                $orderCurrency = $s->shipping_currency ?: $baseCode;
                $orderCurrencyRow = strtoupper($orderCurrency) === strtoupper($baseCode)
                    ? $base
                    : \App\Models\Currency::query()->where('code', $orderCurrency)->first();

                $paid = round((float)$s->shipping_cost, 2);
                $actual = app(\App\Services\Fx\FxRateService::class)
                    ->convertCurrency((float)$s->provider_shipping_cost, $baseCode, $orderCurrency);
                $actual = $actual !== null ? round($actual, 2) : (float)$s->provider_shipping_cost;

                $reconcile = [
                    'symbol' => $orderCurrencyRow?->symbol ?? ($orderCurrency . ' '),
                    'paid' => $paid,
                    'actual' => $actual,
                    'delta' => round($actual - $paid, 2),
                ];
            }

            return [
                'id' => (int)$s->id,
                'status' => $status,
                'status_label' => $humanize($status),
                'provider_code' => $s->provider_code,
                'carrier_name' => $s->manual_carrier_name ?: $s->courier_name,
                'tracking_number' => $s->tracking_number,
                'tracking_url' => $s->tracking_url,
                'awb_number' => $s->awb_number,
                'label_url' => $s->label_url,
                'invoice_url' => $s->invoice_url,
                'manifest_url' => $s->manifest_url,
                'reconcile' => $reconcile,
                'is_shiprocket' => $isSrShipment,
                'is_pending' => $isPending,
                'booking_error' => $meta['booking_error'] ?? null,
                'booking_warning' => $meta['booking_warning'] ?? null,
                'sr_details' => $isSrShipment ? array_filter([
                    'shiprocket_order_id' => $meta['shiprocket_order_id'] ?? null,
                    'pickup_scheduled' => $s->pickup_scheduled_at?->format('d M Y, H:i')
                        ?? ($meta['pickup_scheduled_date'] ?? null),
                    'pickup_token' => $meta['pickup_token_number'] ?? null,
                    'etd' => data_get($s->raw_response, 'awb.response.data.etd'),
                    'applied_weight' => data_get($s->raw_response, 'awb.response.data.applied_weight'),
                    'routing_code' => data_get($s->raw_response, 'awb.response.data.routing_code'),
                ]) : [],
                'edit_lines' => $editLines,
                'dims' => [
                    'length_cm' => (float)$s->length_cm,
                    'breadth_cm' => (float)$s->breadth_cm,
                    'height_cm' => (float)$s->height_cm,
                    'weight_kg' => (float)$s->weight_kg,
                ],
                'is_delivered' => $status === $deliveredV,
                'is_cancelled' => $status === $cancelled,
                'is_terminal' => $isTerminal,
                'can_deliver' => in_array($deliveredV, $allowed, true)
                    && (!$isSrShipment || $this->getPanel() === 'admin'),
                'can_cancel' => in_array($cancelled, $allowed, true) && !$hasReturn,
                'can_edit' => !$isTerminal && !$isPickup && !$isPending
                    && (!$isSrShipment || $this->getPanel() === 'admin'),
                'can_retry' => $isSrShipment && !$isTerminal && ($isPending || !empty($meta['booking_warning'])),
                'can_ndr' => $isSrShipment && !empty($s->awb_number)
                    && $status === (string)ShipmentStatusEnum::ATTEMPTED_DELIVERY(),
                'can_sync' => $isSrShipment && !$isTerminal && !empty($meta['shiprocket_order_id']),
                'has_return' => $hasReturn,
                'products' => $shipmentProducts,
            ];
        })->values()->all();

        $isAdmin = $this->getPanel() === 'admin';

        $hasLiveShipment = $sellerOrder->shipments
            ->contains(fn($s) => (string)$s->status !== $cancelled);
        $remainingTotal = collect($lines)->sum('remaining');

        $shipBy = $remainingTotal > 0 ? $shipmentService->manualShipByDate($sellerOrder) : null;

        $r = fn($adminName, $sellerName, $params) => $isAdmin
            ? route($adminName, $params)
            : route($sellerName, $params);

        $isShiprocket = $method === (string)FulfillmentMethodEnum::SHIPROCKET();
        $packages = optional($sellerOrder->storeLocation?->store)->packages
            ? $sellerOrder->storeLocation->store->packages->map(fn($p) => [
                'id' => (int)$p->id,
                'name' => $p->name,
                'length_cm' => (float)$p->length_cm,
                'breadth_cm' => (float)$p->breadth_cm,
                'height_cm' => (float)$p->height_cm,
                'weight_kg' => (float)$p->empty_weight_kg,
                'is_default' => (bool)$p->is_default,
            ])->values()->all()
            : [];

        return [
            'seller_order_id' => (int)$sellerOrder->id,
            'fulfillment_method' => $method,
            'method_label' => $methodLabel,
            'is_shiprocket' => $isShiprocket,
            'shiprocket_title' => $shippingConfig['shiprocketTitle'] ?? 'Shiprocket',
            'packages' => $packages,
            'shiprocket_defaults' => [
                'length_cm' => (float)($shippingConfig['shiprocketDefaultLength'] ?? 10),
                'breadth_cm' => (float)($shippingConfig['shiprocketDefaultBreadth'] ?? 10),
                'height_cm' => (float)($shippingConfig['shiprocketDefaultHeight'] ?? 10),
                'weight_kg' => (float)($shippingConfig['shiprocketDefaultWeight'] ?? 0.5),
            ],
            'fulfillment_status' => (string)($sellerOrder->fulfillment_status ?? 'unfulfilled'),
            'is_pickup' => $isPickup,
            'supported' => $supported,
            'can_fulfill' => $supported && $remainingTotal > 0,
            'can_set_status' => $supported && !$hasLiveShipment && !empty($lines),
            'ship_by' => $shipBy?->format('d M Y'),
            'ship_by_overdue' => $shipBy !== null && now()->greaterThan($shipBy),
            'location_name' => $sellerOrder->storeLocation->name ?? null,
            'zone_name' => $sellerOrder->shippingZone->name ?? null,
            'shipping_charge' => (float)$sellerOrder->shipping_charge_applied,
            'seller_name' => $isAdmin ? ($sellerOrder->seller?->user?->name ?? $sellerOrder->seller?->store_name ?? null) : null,
            'fulfill_url' => $r('admin.orders.seller_order_fulfill', 'seller.orders.fulfill', ['id' => $sellerOrder->id]),
            'set_status_url' => $r('admin.orders.set_fulfillment_status', 'seller.orders.set_fulfillment_status', ['id' => $sellerOrder->id]),
            'transition_url_template' => $r('admin.orders.shipment_transition', 'seller.orders.shipment_transition', ['shipmentId' => 'SHIPMENT_ID']),
            'retry_url_template' => $r('admin.orders.shipment_retry_booking', 'seller.orders.shipment_retry_booking', ['shipmentId' => 'SHIPMENT_ID']),
            'update_lines_url_template' => $r('admin.orders.shipment_update_lines', 'seller.orders.shipment_update_lines', ['shipmentId' => 'SHIPMENT_ID']),
            'ndr_url_template' => $r('admin.orders.shipment_ndr_action', 'seller.orders.shipment_ndr_action', ['shipmentId' => 'SHIPMENT_ID']),
            'sync_url_template' => $r('admin.orders.shipment_sync', 'seller.orders.shipment_sync', ['shipmentId' => 'SHIPMENT_ID']),
            'edit_url_template' => $r('admin.orders.shipment_edit', 'seller.orders.shipment_edit', ['shipmentId' => 'SHIPMENT_ID']),
            'packing_slip_url_template' => $r('admin.orders.packing_slip', 'seller.orders.packing_slip', ['shipmentId' => 'SHIPMENT_ID']),
            'lines' => $lines,
            'shipments' => $shipments,
        ];
    }

    /**
     * Fulfill a seller order — create its shipment (web/seller panel).
     */
    /**
     * The fulfilment card payload as JSON, so the seller app renders the same
     * lines, shipments and capability flags the panel does.
     */
    public function sellerFulfillment(int $id): JsonResponse
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.seller_not_found'),
            );
        }

        $sellerOrder = SellerOrder::where('id', $id)->where('seller_id', $seller->id)->first();
        if (!$sellerOrder) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.order_not_found'),
            );
        }

        $sellerOrder = $this->loadSellerOrder($id, $seller);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.order_fetched_successfully') ?? 'Fulfillment fetched successfully',
            data: $this->buildSellerFulfillment($sellerOrder),
        );
    }

    public function fulfill(Request $request, int $id): JsonResponse
    {
        try {
            $query = SellerOrder::where('id', $id);
            if (!$this->editPermission) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.unauthorized'),
                );
            }
            if ($this->getPanel() === 'seller') {
                $seller = auth()->user()?->seller();
                if (!$seller) {
                    return ApiResponseType::sendJsonResponse(
                        success: false,
                        message: __('labels.seller_not_found'),
                    );
                }
                $query->where('seller_id', $seller->id);
            }
            $sellerOrder = $query->first();
            if (!$sellerOrder) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.order_not_found'),
                );
            }

            if ($this->getPanel() === 'seller' && $this->isSellerOrderArchived($sellerOrder)) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.order_archived_reopen_first'),
                );
            }

            if ($this->getPanel() === 'seller' && $this->isOnlinePaymentPending($sellerOrder->order)) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.order_not_found'),
                );
            }

            $data = $request->validate([
                'carrier_name' => ['nullable', 'string', 'max:80'],
                'tracking_number' => ['nullable', 'string', 'max:80'],
                'tracking_url' => ['nullable', 'url', 'max:512', 'regex:/^https?:\/\//i'],
                'weight_kg' => ['nullable', 'numeric', 'min:0'],
                'length_cm' => ['nullable', 'numeric', 'min:0'],
                'breadth_cm' => ['nullable', 'numeric', 'min:0'],
                'height_cm' => ['nullable', 'numeric', 'min:0'],
                'items' => ['nullable', 'array'],
                'items.*.order_item_id' => ['required', 'integer'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
            ]);

            $shipment = app(ShipmentService::class)->createForSellerOrder($sellerOrder, $data);

            $meta = (array)($shipment->metadata ?? []);
            $isPending = (string)$shipment->status === (string)ShipmentStatusEnum::PENDING();
            $message = $isPending
                ? __('labels.shiprocket_booking_pending') . (!empty($meta['booking_error']) ? ' — ' . $meta['booking_error'] : '')
                : __('labels.shipment_created');

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: $message,
                data: ['shipment' => $shipment, 'warning' => $isPending],
            );
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong') . ": {$e->getMessage()}",
            );
        }
    }

    /**
     * Advance a shipment's status (web/seller panel).
     */
    public function transitionShipment(Request $request, int $shipmentId): JsonResponse
    {
        try {
            $shipmentQuery = Shipment::where('id', $shipmentId);

            if (!$this->editPermission) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.unauthorized_action'),
                );
            }
            if ($this->getPanel() === 'seller') {
                $seller = auth()->user()?->seller();
                if (!$seller) {
                    return ApiResponseType::sendJsonResponse(
                        success: false,
                        message: __('labels.seller_not_found'),
                    );
                }
                $shipmentQuery->whereHas('sellerOrder', fn($q) => $q->where('seller_id', $seller->id));
            }

            $shipment = $shipmentQuery->first();
            if (!$shipment) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.order_not_found') ?? 'Shipment not found',
                );
            }

            $data = $request->validate([
                'status' => ['required', Rule::in([
                    (string)ShipmentStatusEnum::DELIVERED(),
                    (string)ShipmentStatusEnum::CANCELLED(),
                ])],
                'carrier_name' => ['nullable', 'string', 'max:80'],
                'tracking_number' => ['nullable', 'string', 'max:80'],
            ]);

            if ($this->getPanel() === 'seller'
                && $shipment->provider_code === (string)FulfillmentMethodEnum::SHIPROCKET()
                && $data['status'] === (string)ShipmentStatusEnum::DELIVERED()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.shiprocket_seller_deliver_blocked'),
                );
            }

            app(ShipmentService::class)->transition($shipment, $data['status'], $data);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.success')
            );

        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong') . ": {$e->getMessage()}",
            );
        }
    }

    public function retryShipmentBooking(Request $request, int $shipmentId): JsonResponse
    {
        try {
            $shipment = $this->findPanelShipment($shipmentId);
            if (!$shipment instanceof Shipment) {
                return $shipment;
            }

            app(ShipmentService::class)->retryBooking($shipment);

            $meta = (array)($shipment->metadata ?? []);
            $isPending = (string)$shipment->status === (string)ShipmentStatusEnum::PENDING();
            $message = $isPending
                ? __('labels.shiprocket_booking_pending') . (!empty($meta['booking_error']) ? ' — ' . $meta['booking_error'] : '')
                : __('labels.shiprocket_booking_completed');

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: $message,
                data: ['shipment' => $shipment, 'warning' => $isPending || !empty($meta['booking_warning'])],
            );
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong') . ": {$e->getMessage()}",
            );
        }
    }

    public function updateShipmentLines(Request $request, int $shipmentId): JsonResponse
    {
        try {
            $shipment = $this->findPanelShipment($shipmentId);
            if (!$shipment instanceof Shipment) {
                return $shipment;
            }

            $data = $request->validate([
                'items' => ['required', 'array', 'min:1'],
                'items.*.order_item_id' => ['required', 'integer'],
                'items.*.quantity' => ['required', 'integer', 'min:1'],
                'weight_kg' => ['nullable', 'numeric', 'min:0'],
                'length_cm' => ['nullable', 'numeric', 'min:0'],
                'breadth_cm' => ['nullable', 'numeric', 'min:0'],
                'height_cm' => ['nullable', 'numeric', 'min:0'],
            ]);

            app(ShipmentService::class)->updatePendingShipmentLines($shipment, $data);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.shipment_updated'),
                data: $shipment,
            );
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong') . ": {$e->getMessage()}",
            );
        }
    }

    public function syncShipment(Request $request, int $shipmentId): JsonResponse
    {
        try {
            $shipment = $this->findPanelShipment($shipmentId);
            if (!$shipment instanceof Shipment) {
                return $shipment;
            }

            app(ShipmentService::class)->syncShipmentFromRemote($shipment);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.shipment_synced'),
            );
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong') . ": {$e->getMessage()}",
            );
        }
    }

    public function ndrShipmentAction(Request $request, int $shipmentId): JsonResponse
    {
        try {
            $shipment = $this->findPanelShipment($shipmentId);
            if (!$shipment instanceof Shipment) {
                return $shipment;
            }

            $data = $request->validate([
                'action' => ['required', Rule::in(['re-attempt', 'return'])],
                'comments' => ['required', 'string', 'max:500'],
            ]);

            app(ShipmentService::class)->ndrAction($shipment, $data['action'], $data['comments']);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.ndr_action_submitted'),
            );
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong') . ": {$e->getMessage()}",
            );
        }
    }

    private function findPanelShipment(int $shipmentId): Shipment|JsonResponse
    {
        $shipmentQuery = Shipment::where('id', $shipmentId);

        if (!$this->editPermission) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('messages.unauthorized_action'),
            );
        }
        if ($this->getPanel() === 'seller') {
            $seller = auth()->user()?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.seller_not_found'),
                );
            }
            $shipmentQuery->whereHas('sellerOrder', fn($q) => $q->where('seller_id', $seller->id));
        }

        $shipment = $shipmentQuery->first();
        if (!$shipment) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.order_not_found') ?? 'Shipment not found',
            );
        }

        $sellerOrder = $shipment->sellerOrder;
        if ($sellerOrder && $this->isSellerOrderArchived($sellerOrder)) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.order_archived_reopen_first'),
            );
        }

        if ($sellerOrder && $this->isOnlinePaymentPending($sellerOrder->order)) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.online_payment_pending'),
            );
        }

        return $shipment;
    }

    /**
     * Manually set a seller order's fulfillment status — Shopify's
     * "Mark as in progress / on hold / unfulfilled". Allowed only before any
     * shipment exists; the rollup owns the status once fulfillment has begun.
     */
    public function setFulfillmentStatus(Request $request, int $id): JsonResponse
    {
        try {
            $query = SellerOrder::where('id', $id);
            if (!$this->editPermission) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.unauthorized'),
                );
            }
            if ($this->getPanel() === 'seller') {
                $seller = auth()->user()?->seller();
                if (!$seller) {
                    return ApiResponseType::sendJsonResponse(
                        success: false,
                        message: __('labels.seller_not_found'),
                    );
                }
                $query->where('seller_id', $seller->id);
            }
            $sellerOrder = $query->first();
            if (!$sellerOrder) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.order_not_found'),
                );
            }

            if ($this->getPanel() === 'seller' && $this->isSellerOrderArchived($sellerOrder)) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.order_archived_reopen_first'),
                );
            }

            if ($this->getPanel() === 'seller' && $this->isOnlinePaymentPending($sellerOrder->order)) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.order_not_found'),
                );
            }

            $data = $request->validate([
                'status' => ['required', Rule::in(array_map(
                    fn($s) => (string)$s,
                    FulfillmentStatusEnum::manuallySettable()
                ))],
            ]);

            app(\App\Services\FulfillmentRollupService::class)
                ->setManualStatus($sellerOrder, $data['status']);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.success'),
            );
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong') . ": {$e->getMessage()}",
            );
        }
    }

    /**
     * Edit a created fulfillment's carrier / tracking details (no status change).
     */
    public function editShipment(Request $request, int $shipmentId): JsonResponse
    {
        try {
            $shipmentQuery = Shipment::where('id', $shipmentId);
            if (!$this->editPermission) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.unauthorized_action'),
                );
            }
            if ($this->getPanel() === 'seller') {
                $seller = auth()->user()?->seller();
                if (!$seller) {
                    return ApiResponseType::sendJsonResponse(
                        success: false,
                        message: __('labels.seller_not_found'),
                    );
                }
                $shipmentQuery->whereHas('sellerOrder', fn($q) => $q->where('seller_id', $seller->id));
            }
            $shipment = $shipmentQuery->first();
            if (!$shipment) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.order_not_found') ?? 'Shipment not found',
                );
            }

            if ($this->getPanel() === 'seller'
                && $shipment->provider_code === (string)FulfillmentMethodEnum::SHIPROCKET()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.shiprocket_seller_edit_blocked'),
                );
            }

            $data = $request->validate([
                'carrier_name' => ['nullable', 'string', 'max:80'],
                'tracking_number' => ['nullable', 'string', 'max:80'],
                'tracking_url' => ['nullable', 'url', 'max:512', 'regex:/^https?:\/\//i'],
            ]);

            app(ShipmentService::class)->editShipment($shipment, $data);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.shipment_updated') ?? __('labels.success'),
            );
        } catch (\RuntimeException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong') . ": {$e->getMessage()}",
            );
        }
    }

    /**
     * Printable packing slip for one fulfillment (shipment) — Shopify's
     * "Print packing slip". Returns a standalone, print-optimised HTML view.
     */
    public function packingSlip(int $shipmentId)
    {
        $shipmentQuery = Shipment::where('id', $shipmentId);
        if ($this->getPanel() === 'seller') {
            $seller = auth()->user()?->seller();
            if (!$seller) {
                abort(403);
            }
            abort_unless($this->viewPermission, 403);
            $shipmentQuery->whereHas('sellerOrder', fn($q) => $q->where('seller_id', $seller->id));
        } elseif (!$this->viewPermission) {
            abort(403);
        }

        $shipment = $shipmentQuery->with([
            'sellerOrder.order',
            'sellerOrder.storeLocation',
            'sellerOrder.seller.user',
            'items.orderItem.product',
            'items.orderItem.variant',
        ])->first();

        if (!$shipment) {
            abort(404);
        }

        return view('orders.packing-slip', ['shipment' => $shipment]);
    }

    /**
     * Cancel an item the seller had already accepted. Pre-collect only —
     * post-collect cancellations go through the rider/admin return-to-store
     * flow.
     *
     * @param int $id Order item id (matches OrderItem.id, not SellerOrderItem.id)
     */
    public function cancelItem(CancelSellerOrderItemRequest $request, int $id): JsonResponse
    {
        try {
            $seller = auth()->user()?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), []);
            }

            $orderItem = SellerOrderItem::where('order_item_id', $id)
                ->whereHas('sellerOrder', function ($q) use ($seller) {
                    $q->where('seller_id', $seller->id);
                })
                ->with('orderItem.order')
                ->first();
            if (!$orderItem) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_item_not_found'), []);
            }

            if ($this->isSellerItemOnlinePaymentPending($orderItem)) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }

            if ($this->isSellerItemOrderArchived($orderItem)) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_archived_reopen_first'), []);
            }

            $this->authorize('updateStatus', $orderItem);
            $result = $this->orderService->cancelOrderItemBySeller(
                orderItemId: $id,
                sellerId: $seller->id,
                reason: $request->validated('reason'),
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Seller cancel-item endpoint failed', [
                'order_item_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * True when the order behind a SellerOrderItem is ARCHIVED — seller
     * mutations are rejected on archived orders (admin must reopen first).
     */
    private function isSellerItemOrderArchived(SellerOrderItem $sellerOrderItem): bool
    {
        $order = $sellerOrderItem->orderItem?->order
            ?? $sellerOrderItem->sellerOrder?->order;
        return $order && (string)$order->status === (string)OrderStatusEnum::ARCHIVED();
    }

    /**
     * True when the order behind a SellerOrderItem is an online order still
     * awaiting payment — hidden from the seller (see isOnlinePaymentPending).
     */
    private function isSellerItemOnlinePaymentPending(SellerOrderItem $sellerOrderItem): bool
    {
        $order = $sellerOrderItem->orderItem?->order
            ?? $sellerOrderItem->sellerOrder?->order;
        return $this->isOnlinePaymentPending($order);
    }

    /**
     * True when a SellerOrder's parent order is ARCHIVED.
     */
    private function isSellerOrderArchived(SellerOrder $sellerOrder): bool
    {
        $order = $sellerOrder->relationLoaded('order') ? $sellerOrder->order : $sellerOrder->order()->first();
        return $order && (string)$order->status === (string)OrderStatusEnum::ARCHIVED();
    }

    /**
     * Online (prepaid) orders whose payment is still PENDING are hidden from the
     * seller entirely — the seller may only see/act on them once payment clears.
     * COD orders are unaffected (pending is normal until delivery/collection).
     */
    private function isOnlinePaymentPending(?Order $order): bool
    {
        if (!$order) {
            return false;
        }
        $isCod = (string)$order->payment_method === (string)\App\Enums\Payment\PaymentTypeEnum::COD();
        $isPending = (string)$order->payment_status === (string)PaymentStatusEnum::PENDING();
        return !$isCod && $isPending;
    }

    /**
     * Apply the "hide online-but-unpaid orders from the seller" rule to a
     * SellerOrder query (keeps COD orders and any order whose payment has cleared).
     */
    private function hideOnlinePendingForSeller($query): void
    {
        $query->whereHas('order', function ($q) {
            $q->where('payment_method', (string)\App\Enums\Payment\PaymentTypeEnum::COD())
                ->orWhere('payment_status', '!=', (string)PaymentStatusEnum::PENDING());
        });
    }

    /**
     * Resolve the authenticated seller, scoped to a SellerOrder it owns. Returns
     * [seller, sellerOrder] or null when not found / not owned.
     */
    private function resolveOwnedSellerOrder(int $sellerOrderId): ?array
    {
        $seller = auth()->user()?->seller();
        if (!$seller) {
            return null;
        }
        $sellerOrder = SellerOrder::where('id', $sellerOrderId)
            ->where('seller_id', $seller->id)
            ->with('order')
            ->first();
        if (!$sellerOrder) {
            return null;
        }
        if ($this->isOnlinePaymentPending($sellerOrder->order)) {
            return null;
        }
        return [$seller, $sellerOrder];
    }

    /**
     * Seller return review screen (approve / decline / edit return shipping).
     * Reuses the shared return-review view; scoped to a return on one of the
     * seller's own items.
     */
    public function sellerReturnReview(int $returnId)
    {
        if ($this->getPanel() !== 'seller') {
            abort(403);
        }
        abort_unless($this->returnViewPermission, 403);
        $seller = auth()->user()?->seller();
        if (!$seller) {
            abort(404, __('labels.seller_not_found'));
        }

        $return = OrderItemReturn::with([
            'orderItem.product',
            'orderItem.variant',
            'order.market',
        ])->where('id', $returnId)
            ->where('seller_id', $seller->id)
            ->first();
        if (!$return || !$return->orderItem) {
            abort(404);
        }

        $sellerOrder = SellerOrder::where('order_id', $return->order_id)
            ->where('seller_id', $seller->id)
            ->first();
        $backUrl = $sellerOrder
            ? route('seller.orders.show', ['id' => $sellerOrder->id])
            : route('seller.orders.index');

        return view('orders.return-review', [
            'panel' => 'seller',
            'createMode' => false,
            'return' => $return,
            'orderItem' => $return->orderItem,
            'order' => $return->order,
            'currency' => PricePresenter::payload($return->order?->market ?? null),
            'approveUrl' => route('seller.orders.return_approve', ['returnId' => $return->id]),
            'declineUrl' => route('seller.orders.return_decline', ['returnId' => $return->id]),
            'cancelUrl' => null,
            'refundUrl' => null,
            'backUrl' => $backUrl,
        ]);
    }

    /**
     * Seller approves / edits a return (records return shipping). Scoped to a
     * return on the seller's own items. Reuses approveReturnByAdmin.
     */
    public function sellerReturnApprove(Request $request, int $returnId): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'seller') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $seller = auth()->user()?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), []);
            }

            $return = OrderItemReturn::with('orderItem.order')
                ->where('id', $returnId)
                ->where('seller_id', $seller->id)
                ->first();
            if (!$return) {
                return ApiResponseType::sendJsonResponse(false, __('labels.return_request_not_found'), []);
            }
            if ($return->orderItem?->order
                && (string)$return->orderItem->order->status === (string)OrderStatusEnum::ARCHIVED()) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_archived_reopen_first'), []);
            }

            $this->authorize('decide', $return);

            $returnedQty = $this->orderService->maximumReturnQuantity($return);

            $validated = $request->validate([
                'quantity' => "nullable|integer|min:1|max:{$returnedQty}",
                'return_shipping_charge' => 'nullable|numeric|min:0',
                'reason' => 'nullable|string|max:1000',
                'return_carrier' => 'nullable|string|max:120',
                'return_tracking_number' => 'nullable|string|max:120',
                'return_tracking_url' => 'nullable|url|max:512|regex:/^https?:\/\//i',
                'return_label_url' => 'nullable|url|max:512|regex:/^https?:\/\//i',
                'no_shipping_required' => 'nullable|boolean',
                'return_label' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            ]);

            if ($request->hasFile('return_label')) {
                $previousLabels = $return->getMedia(SpatieMediaCollectionName::RETURN_LABEL());
                SpatieMediaService::uploadFromRequest(
                    $return,
                    $request->file('return_label'),
                    SpatieMediaCollectionName::RETURN_LABEL()
                );
                $previousLabels->each->delete();
                $return->refresh();
                $validated['return_label_url'] = $return->labelFileUrl() ?? ($validated['return_label_url'] ?? null);
            }
            unset($validated['return_label']);

            $result = $this->orderService->approveReturnByAdmin(
                returnId: $returnId,
                adminId: (int)auth()->id(),
                data: $validated,
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Seller return approve failed', ['return_id' => $returnId, 'error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Seller declines a return on its own item. Reuses declineReturnByAdmin.
     */
    public function sellerReturnDecline(Request $request, int $returnId): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'seller') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $seller = auth()->user()?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), []);
            }

            $return = OrderItemReturn::with('orderItem.order')
                ->where('id', $returnId)
                ->where('seller_id', $seller->id)
                ->first();
            if (!$return) {
                return ApiResponseType::sendJsonResponse(false, __('labels.return_request_not_found'), []);
            }
            if ($return->orderItem?->order
                && (string)$return->orderItem->order->status === (string)OrderStatusEnum::ARCHIVED()) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_archived_reopen_first'), []);
            }

            $this->authorize('decide', $return);

            $validated = $request->validate([
                'reason' => ['nullable', 'string', 'max:1000'],
            ]);

            $result = $this->orderService->declineReturnByAdmin(
                returnId: $returnId,
                adminId: (int)auth()->id(),
                reason: $validated['reason'] ?? null,
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Seller decline return failed', ['return_id' => $returnId, 'error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Restock the seller's own cancelled/returned/refunded items. Scopes the
     * submitted item ids to the seller order before delegating to the shared
     * restock service.
     */
    public function sellerRestockItems(Request $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'seller' || !$this->editPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $resolved = $this->resolveOwnedSellerOrder($id);
            if (!$resolved) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }
            [$seller, $sellerOrder] = $resolved;

            $validated = $request->validate([
                'item_ids' => ['required', 'array', 'min:1'],
                'item_ids.*' => ['integer'],
            ]);

            $ownItemIds = SellerOrderItem::where('seller_order_id', $sellerOrder->id)
                ->pluck('order_item_id')->map(fn($i) => (int)$i)->all();
            $itemIds = collect($validated['item_ids'])
                ->map(fn($i) => (int)$i)
                ->filter(fn($i) => in_array($i, $ownItemIds, true))
                ->unique()->values()->all();

            if (empty($itemIds)) {
                return ApiResponseType::sendJsonResponse(false, __('labels.restock_select_item') ?? 'Select at least one item to restock.', []);
            }

            $result = $this->orderService->restockOrderItemsByAdmin(
                orderId: (int)$sellerOrder->order_id,
                adminId: (int)auth()->id(),
                itemIds: $itemIds,
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Seller restock submit failed', ['seller_order_id' => $id, 'error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    public function adminAddNote(AdminAddOrderNoteRequest $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $order = Order::find($id);
            if (!$order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }
            $this->authorize('addNote', $order);

            $result = $this->orderService->addOrderNoteByAdmin(
                orderId: $id,
                adminId: (int)auth()->id(),
                note: $request->validated('note'),
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Admin add-note endpoint failed', ['order_id' => $id, 'error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Mark order payment as received.
     *
     * @param Request $request
     * @param int $id Order ID
     * @return JsonResponse
     */
    public function adminMarkPaymentReceived(Request $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $order = Order::find($id);
            if (!$order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }

            if (!$this->paymentAcceptPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $validated = $request->validate([
                'remark' => 'nullable|string|max:1000',
            ]);

            $result = $this->orderService->markPaymentReceivedByAdmin(
                orderId: $id,
                adminId: (int)auth()->id(),
                remark: $validated['remark'] ?? null,
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Admin mark-payment-received failed', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Admin "Cancel order" screen. Lists the order's lines with editable cancel
     * quantities + per-line restock. Fulfilled lines are shown read-only with a
     * "create a return" note. Refund is not issued here — it is shown as owed and
     * handled from the Refund flow.
     *
     * @param int $id Order id
     */
    public function adminCancelOrder(int $id): Factory|View
    {
        if ($this->getPanel() !== 'admin') {
            abort(403);
        }

        $order = Order::with(['items.product.media', 'items.variant.media', 'items.sellerOrderItem.sellerOrder', 'items.shipmentItems.shipment.statusEvents', 'market'])
            ->find($id);
        if (!$order) {
            abort(404);
        }

        $this->authorize('forceUpdateStatus', $order);

        $rows = $order->items->map(function ($item) {
            $cancellation = app(\App\Services\FulfillmentRollupService::class)->cancellationForItem($item);
            return [
                'id' => (int)$item->id,
                'title' => $item->title,
                'variant_title' => $item->variant_title,
                'image' => $item->variant?->image ?: $item->product?->main_image,
                'price' => (float)$item->price,
                'quantity' => (int)$item->quantity,
                'subtotal' => (float)$item->subtotal,
                'tax_amount' => (float)$item->tax_amount,
                'promo_discount' => (float)$item->promo_discount,
                'status' => (string)$item->status,
                'fulfillment' => (string)$item->fulfillment_status,
                'cancellable' => $cancellation['allowed'],
                'cancelable_quantity' => $cancellation['quantity'],
                'is_fulfilled' => in_array($cancellation['reason'], ['shipped', 'delivered'], true),
                'cancel_block_reason' => $cancellation['reason'],
                'is_cancelable' => (bool)$item->is_cancelable,
                'is_restocked' => (bool)($item->is_restocked ?? false),
            ];
        })->values();
        $summary = [
            'subtotal' => (float)$order->subtotal,
            'shipping_total' => (float)($order->shipping_total ?? 0),
            'delivery_charge' => (float)($order->delivery_charge ?? 0),
            'tax_total' => (float)($order->tax_total ?? 0),
            'promo_discount' => (float)($order->promo_discount ?? 0),
            'gift_card_discount' => (float)($order->gift_card_discount ?? 0),
            'wallet_balance' => (float)($order->wallet_balance ?? 0),
            'final_total' => (float)$order->final_total,
        ];

        $amountPaid = (float)$order->amount_paid;
        $amountRefunded = (float)$order->amount_refunded;
        $amountRefundOwed = (float)$order->amount_refund_owed;

        return view('admin.orders.cancel', [
            'order' => $order,
            'rows' => $rows,
            'summary' => $summary,
            'currency' => PricePresenter::payload($order->market ?? null),
            'paid' => $amountPaid,
            'alreadyRefunded' => $amountRefunded + $amountRefundOwed,
            'refundsIssued' => $amountRefunded,
            'refundsOwed' => $amountRefundOwed,
            'submitUrl' => route('admin.orders.cancel_submit', ['id' => $order->id]),
            'backUrl' => route('admin.orders.show', ['id' => $order->id]),
        ]);
    }

    /**
     * Handle the admin cancel-order submission (partial / whole lines).
     *
     * @param Request $request
     * @param int $id Order id
     */
    public function adminCancelOrderSubmit(Request $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $order = Order::find($id);
            if (!$order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }

            $this->authorize('forceUpdateStatus', $order);

            $validated = $request->validate([
                'reason' => 'nullable|string|max:1000',
                'lines' => 'required|array|min:1',
                'lines.*.order_item_id' => 'required|integer|distinct',
                'lines.*.cancel_qty' => 'required|integer|min:0',
                'lines.*.restock' => 'nullable|boolean',
            ]);

            $lines = collect($validated['lines'])
                ->map(fn($l) => [
                    'order_item_id' => (int)$l['order_item_id'],
                    'cancel_qty' => (int)$l['cancel_qty'],
                    'restock' => (bool)($l['restock'] ?? false),
                ])
                ->filter(fn($l) => $l['cancel_qty'] > 0)
                ->values()
                ->all();

            if (empty($lines)) {
                return ApiResponseType::sendJsonResponse(false, __('labels.nothing_to_cancel'), []);
            }

            $reason = trim((string)($validated['reason'] ?? ''));
            if ($reason === '') {
                $reason = __('labels.cancelled_by_admin');
            }

            $result = $this->orderService->cancelOrderLinesByAdmin(
                orderId: $id,
                adminId: (int)auth()->id(),
                lines: $lines,
                reason: $reason,
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong') . $e->getMessage(), []);
        }
    }

    /**
     * Admin Refund screen — Shopify-parity refund flow. Lets the admin issue a
     * partial or full refund against an order that has already been paid. Pre-
     * fulfilment lines can be refunded directly (with optional restock).
     * Fulfilled lines are shown but locked: they need to go through the return
     * flow first, then refund.
     *
     * @param int $id Order id
     */
    public function adminRefundOrder(int $id): Factory|View
    {
        if ($this->getPanel() !== 'admin') {
            abort(403);
        }

        abort_unless($this->refundPermission, 403);

        $order = Order::with([
            'items.product',
            'items.variant',
            'items.store',
            'items.sellerOrderItem.sellerOrder.storeLocation',
            'items.shipmentItems.shipment',
            'items.addons.addonGroup',
            'items.addons.addonItem',
            'market',
            'refunds',
        ])->findOrFail($id);

        $activeShipItemIds = $this->orderService->itemIdsInActiveShipment(
            $order->items->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        $approvedReturns = OrderItemReturn::where('order_id', $order->id)
            ->with('sourceShipment')
            ->where(
                'return_status',
                OrderItemReturnStatusEnum::APPROVED()
            )
            ->get()
            ->groupBy('order_item_id');

        $rows = $this->buildRefundRows(
            $order->items,
            $activeShipItemIds,
            $approvedReturns
        );

        $refund = $this->calculateRefundAmounts($order);

        return view('admin.orders.refund', [
            'order' => $order,
            'rows' => $rows,

            'summary' => [
                'subtotal' => (float) $order->subtotal,
                'shipping_total' => (float) ($order->shipping_total ?? 0),
                'delivery_charge' => (float) ($order->delivery_charge ?? 0),
                'tax_total' => (float) ($order->tax_total ?? 0),
                'promo_discount' => (float) ($order->promo_discount ?? 0),
                'gift_card_discount' => (float) ($order->gift_card_discount ?? 0),
                'final_total' => (float) $order->final_total,
            ],

            'currency' => PricePresenter::payload($order->market ?? null),

            'amountPaid' => $refund['amountPaid'],
            'amountRefunded' => $refund['amountRefunded'],
            'amountRefundOwed' => $refund['amountRefundOwed'],
            'availableForRefund' => $refund['availableForRefund'],
            'maxRefundable' => $refund['maxRefundable'],
            'shippingRefunded' => $refund['shippingRefunded'],
            'shippingRemaining' => $refund['shippingRemaining'],

            'submitUrl' => route('admin.orders.refund_submit', [
                'id' => $order->id,
            ]),

            'backUrl' => route('admin.orders.show', [
                'id' => $order->id,
            ]),
        ]);
    }

    private function buildRefundRows($items, array $activeShipItemIds, $approvedReturns)
    {
        return $items->map(function ($item) use ($activeShipItemIds, $approvedReturns) {
            $isUnfulfilled = (string) $item->fulfillment_status ===
                (string) FulfillmentStatusEnum::UNFULFILLED();

            $isActive = (string) $item->status ===
                (string) OrderItemStatusEnum::ACTIVE();

            $isRefunded = (string) $item->status ===
                (string) OrderItemStatusEnum::REFUNDED();

            $inActiveShipment = in_array(
                (int) $item->id,
                $activeShipItemIds,
                true
            );

            $itemReturns = $approvedReturns->get($item->id, collect());
            $approvedReturn = $itemReturns->first();
            $hasApprovedReturn = (bool) $approvedReturn;
            $orderedQuantity = max(0, (int) $item->quantity);
            $outboundShipmentItems = $item->shipmentItems->filter(
                fn ($shipmentItem) => $shipmentItem->shipment
                    && ! $shipmentItem->shipment->is_return
                    && (string) $shipmentItem->shipment->status !== (string) ShipmentStatusEnum::CANCELLED()
            );
            $shippedQuantity = (int) $outboundShipmentItems->sum('quantity');
            $deliveredQuantity = (int) $outboundShipmentItems
                ->filter(
                    fn ($shipmentItem) => (string) $shipmentItem->shipment->status ===
                        (string) ShipmentStatusEnum::DELIVERED()
                )
                ->sum('quantity');
            $sellerOrder = $item->sellerOrderItem?->sellerOrder;

            return [
                'id' => (int) $item->id,
                'title' => $item->title,
                'variant_title' => $item->variant_title,
                'price' => (float) $item->price,
                'quantity' => (int) $item->quantity,
                'subtotal' => (float) $item->subtotal,
                'tax_amount' => (float) $item->tax_amount,
                'promo_discount' => (float) $item->promo_discount,
                'status' => (string) $item->status,
                'fulfillment' => (string) $item->fulfillment_status,
                'store_id' => (int) ($item->store_id ?? 0),
                'store_name' => $item->store?->name,
                'store_location_id' => (int) ($sellerOrder?->store_location_id ?? 0),
                'store_location_name' => $sellerOrder?->storeLocation?->name,
                'image' => $item->product?->main_image ?? null,
                'addons' => $item->addons,
                'unit_subtotal' => $orderedQuantity > 0
                    ? (float) $item->subtotal / $orderedQuantity
                    : 0.0,
                'unit_promo_discount' => $orderedQuantity > 0
                    ? (float) $item->promo_discount / $orderedQuantity
                    : 0.0,
                'unit_tax_amount' => $orderedQuantity > 0
                    ? (float) $item->tax_amount / $orderedQuantity
                    : 0.0,
                'shipped_quantity' => min($orderedQuantity, $shippedQuantity),
                'delivered_quantity' => min($orderedQuantity, $deliveredQuantity),
                'remaining_quantity' => max(0, $orderedQuantity - $shippedQuantity),

                'refundable' => $isActive && (
                        $hasApprovedReturn ||
                        ($isUnfulfilled && !$inActiveShipment)
                    ),

                'is_fulfilled' => (string) $item->fulfillment_status ===
                    (string) FulfillmentStatusEnum::FULFILLED(),
                'requires_return' => !$isUnfulfilled || $inActiveShipment,
                'is_refunded' => $isRefunded,
                'has_approved_return' => $hasApprovedReturn,
                'approved_returns' => $itemReturns->map(fn ($return) => [
                    'id' => (int) $return->id,
                    'quantity' => (int) $return->quantity,
                    'shipment_id' => $return->source_shipment_id,
                    'tracking_number' => $return->sourceShipment?->tracking_number ?: $return->sourceShipment?->awb_number,
                ])->values()->all(),

                'refund_max_qty' => $hasApprovedReturn
                    ? max(
                        1,
                        (int) ($approvedReturn->quantity ?: $item->quantity)
                    )
                    : (int) $item->quantity,

                'return_shipping_charge' => $hasApprovedReturn
                    ? (float) $itemReturns->sum('return_shipping_charge')
                    : 0.0,

                'return_qty' => $hasApprovedReturn
                    ? (int) $itemReturns->sum('quantity')
                    : 0,
            ];
        })->values();
    }

    private function calculateRefundAmounts(Order $order): array
    {
        $amountPaid = (float) $order->amount_paid;
        $amountRefunded = (float) $order->amount_refunded;
        $amountRefundOwed = (float) $order->amount_refund_owed;

        $availableForRefund = max(
            0,
            $amountPaid - $amountRefunded - $amountRefundOwed
        );

        $shippingTotalCharged = (float) ($order->shipping_total ?? 0);

        $shippingRefunded = (float) $order->refunds()
            ->where(
                'status',
                (string) RefundStatusEnum::ISSUED()
            )
            ->sum('shipping_refund_amount');

        return [
            'amountPaid' => $amountPaid,
            'amountRefunded' => $amountRefunded,
            'amountRefundOwed' => $amountRefundOwed,
            'availableForRefund' => $availableForRefund,
            'maxRefundable' => $availableForRefund + $amountRefundOwed,
            'shippingRefunded' => $shippingRefunded,
            'shippingRemaining' => max(
                0,
                $shippingTotalCharged - $shippingRefunded
            ),
        ];
    }

    /**
     * Handle the admin refund submission. Validates the per-line quantities
     * and the custom refund amount, then delegates to the service. Mirrors
     * adminCancelOrderSubmit shape.
     */
    public function adminRefundOrderSubmit(Request $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $order = Order::find($id);
            if (!$order) {
                return ApiResponseType::sendJsonResponse(false, __('labels.order_not_found'), []);
            }

            if (!$this->refundPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $maxRefundable = max(0, (float)$order->amount_paid - (float)$order->amount_refunded) + 0.01;
            $shippingRefundedSoFar = (float)$order->refunds()
                ->where('status', (string)RefundStatusEnum::ISSUED())
                ->sum('shipping_refund_amount');
            $maxShipping = max(0, (float)($order->shipping_total ?? 0) - $shippingRefundedSoFar) + 0.01;

            $validated = $request->validate([
                'reason' => 'required|string|min:3|max:1000',
                'lines' => 'nullable|array',
                'lines.*.order_item_id' => 'required|integer',
                'lines.*.return_id' => 'nullable|integer',
                'lines.*.refund_qty' => 'required|integer|min:0',
                'lines.*.restock' => 'nullable|boolean',
                'shipping_refund' => "nullable|numeric|min:0|max:{$maxShipping}",
                'refund_amount' => "required|numeric|min:0.01|max:{$maxRefundable}",
                'notify_customer' => 'nullable|boolean',
            ]);

            $lines = collect($validated['lines'] ?? [])
                ->map(fn($l) => [
                    'order_item_id' => (int)$l['order_item_id'],
                    'return_id' => isset($l['return_id']) ? (int)$l['return_id'] : null,
                    'refund_qty' => (int)$l['refund_qty'],
                    'restock' => (bool)($l['restock'] ?? false),
                ])
                ->filter(fn($l) => $l['refund_qty'] > 0)
                ->values()
                ->all();

            $result = $this->orderService->refundOrderLinesByAdmin(
                orderId: $id,
                adminId: (int)auth()->id(),
                lines: $lines,
                shippingRefund: (float)($validated['shipping_refund'] ?? 0),
                refundAmount: (float)$validated['refund_amount'],
                reason: $validated['reason'] ?? '',
                notifyCustomer: (bool)($validated['notify_customer'] ?? true),
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data'] ?? [],
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        } catch (\Exception $e) {
            Log::error('Admin refund-order submit failed', [
                'order_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Admin return review screen — review/approve/decline/edit a return; once
     * approved, link out to the order refund page. No money moves here.
     */
    public function adminReturnReview(int $returnId)
    {
        if ($this->getPanel() !== 'admin') {
            abort(403);
        }
        abort_unless($this->refundPermission, 403);

        $return = OrderItemReturn::with([
            'orderItem.product',
            'orderItem.variant',
            'order.market',
        ])->find($returnId);
        if (!$return || !$return->orderItem) {
            abort(404);
        }

        return view('orders.return-review', [
            'panel' => 'admin',
            'createMode' => false,
            'return' => $return,
            'orderItem' => $return->orderItem,
            'order' => $return->order,
            'currency' => PricePresenter::payload($return->order?->market ?? null),
            'approveUrl' => route('admin.orders.return_approve', ['returnId' => $return->id]),
            'declineUrl' => route('admin.orders.return_decline', ['returnId' => $return->id]),
            'cancelUrl' => route('admin.orders.return_cancel', ['returnId' => $return->id]),
            'refundUrl' => $return->order_id ? route('admin.orders.refund', ['id' => $return->order_id]) : null,
            'backUrl' => $return->order_id
                ? route('admin.orders.show', ['id' => $return->order_id])
                : route('admin.orders.index'),
        ]);
    }

    /**
     * Admin "create return" page — select the item + reason + note inline (no
     * modal), set the return shipping options, and submit to create the return.
     */
    public function adminReturnCreatePage(int $orderId)
    {
        if ($this->getPanel() !== 'admin') {
            abort(403);
        }
        abort_unless($this->refundPermission, 403);

        $order = $this->loadAdminOrder($orderId)['order'] ?? null;
        if (!$order) {
            abort(404);
        }
        $eligible = collect($this->buildAdminReturns($order)['eligible_items'])
            ->keyBy(fn($item) => $item['id'].':'.($item['shipment_id'] ?? 'legacy'));

        $cancelledShip = (string)ShipmentStatusEnum::CANCELLED();
        $order->loadMissing(['sellerOrders.shipments.items', 'sellerOrders.shipments.storeLocation', 'sellerOrders.seller']);
        $groups = [];
        $seen = [];
        $seq = 0;
        foreach ($order->sellerOrders as $so) {
            foreach ($so->shipments as $sh) {
                if ((string)$sh->status === $cancelledShip) {
                    continue;
                }
                $seq++;
                $items = [];
                foreach ($sh->items as $si) {
                    $oiId = (int)$si->order_item_id;
                    $key = $oiId.':'.$sh->id;
                    if ($eligible->has($key)) {
                        $items[] = $eligible->get($key);
                        $seen[$key] = true;
                    }
                }
                if (!empty($items)) {
                    $groups[] = [
                        'ref' => __('labels.shipment') . ' #' . $sh->id,
                        'location' => $sh->storeLocation->name ?? $so->seller?->store_name ?? null,
                        'items' => $items,
                    ];
                }
            }
        }
        $ungrouped = $eligible->reject(fn($it) => !empty($seen[$it['id'].':'.($it['shipment_id'] ?? 'legacy')]))->values()->all();
        if (!empty($ungrouped)) {
            $groups[] = ['ref' => null, 'location' => null, 'items' => $ungrouped];
        }

        return view('orders.return-review', [
            'panel' => 'admin',
            'createMode' => true,
            'return' => null,
            'orderItem' => null,
            'order' => $order,
            'currency' => PricePresenter::payload($order->market ?? null),
            'returnGroups' => $groups,
            'reasonOptions' => \App\Enums\Order\ReturnReasonCodeEnum::options(),
            'createUrl' => route('admin.orders.returns.create', ['id' => $order->id]),
            'backUrl' => route('admin.orders.show', ['id' => $order->id]),
        ]);
    }

    /**
     * Approve (or edit) a return — records return shipping + carrier/tracking/
     * label and an optional updated qty. No refund (that's the order refund page).
     */
    public function adminReturnApprove(Request $request, int $returnId): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin' || !$this->refundPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $return = OrderItemReturn::with('orderItem')->find($returnId);
            if (!$return) {
                return ApiResponseType::sendJsonResponse(false, __('labels.return_request_not_found'), []);
            }
            $returnedQty = $this->orderService->maximumReturnQuantity($return);

            $validated = $request->validate([
                'quantity' => "nullable|integer|min:1|max:{$returnedQty}",
                'return_shipping_charge' => 'nullable|numeric|min:0',
                'reason' => 'nullable|string|max:1000',
                'return_carrier' => 'nullable|string|max:120',
                'return_tracking_number' => 'nullable|string|max:120',
                'return_tracking_url' => 'nullable|url|max:512|regex:/^https?:\/\//i',
                'return_label_url' => 'nullable|url|max:512|regex:/^https?:\/\//i',
                'no_shipping_required' => 'nullable|boolean',
                'return_label' => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:5120',
            ]);

            if ($request->hasFile('return_label')) {
                $previousLabels = $return->getMedia(SpatieMediaCollectionName::RETURN_LABEL());
                SpatieMediaService::uploadFromRequest(
                    $return,
                    $request->file('return_label'),
                    SpatieMediaCollectionName::RETURN_LABEL()
                );
                $previousLabels->each->delete();
                $return->refresh();
                $validated['return_label_url'] = $return->labelFileUrl() ?? ($validated['return_label_url'] ?? null);
            }
            unset($validated['return_label']);

            $result = $this->orderService->approveReturnByAdmin(
                returnId: $returnId,
                adminId: (int)auth()->id(),
                data: $validated,
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Admin return approve failed', ['return_id' => $returnId, 'error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Cancel a return (admin).
     */
    public function adminReturnCancel(Request $request, int $returnId): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin' || !$this->refundPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
            $result = $this->orderService->cancelReturnByAdmin(
                returnId: $returnId,
                adminId: (int)auth()->id(),
                reason: $validated['reason'] ?? null,
            );
            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Admin return cancel failed', ['return_id' => $returnId, 'error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Decline a return (admin) — no refund issued.
     */
    public function adminDeclineReturn(Request $request, int $returnId): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            if (!$this->refundPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $validated = $request->validate([
                'reason' => ['nullable', 'string', 'max:1000'],
            ]);

            $result = $this->orderService->declineReturnByAdmin(
                returnId: $returnId,
                adminId: (int)auth()->id(),
                reason: $validated['reason'] ?? null,
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Admin decline return submit failed', [
                'return_id' => $returnId,
                'error' => $e->getMessage(),
            ]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Admin returns list page (all sellers).
     */
    public function adminReturnsIndex()
    {
        if ($this->getPanel() !== 'admin') {
            abort(403);
        }
        abort_unless($this->viewPermission, 403);

        $columns = [
            ['title' => __('labels.id'), 'data' => 'id'],
            ['title' => __('labels.order_date'), 'data' => 'order_date'],
            ['title' => __('labels.order_details'), 'data' => 'order_details'],
            ['title' => __('labels.return_reason') ?? 'Reason', 'data' => 'reason'],
            ['title' => __('labels.refund_amount'), 'data' => 'refund_amount'],
            ['title' => __('labels.return_status'), 'data' => 'return_status'],
            ['title' => __('labels.actions'), 'data' => 'actions', 'orderable' => false, 'searchable' => false],
        ];

        return view('admin.orders.returns.index', compact('columns'));
    }

    /**
     * Datatable endpoint for the admin returns list.
     */
    public function adminReturnsDatatable(Request $request): JsonResponse
    {
        if ($this->getPanel() !== 'admin' || !$this->viewPermission) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
        }

        $draw = (int)$request->get('draw');
        $start = (int)$request->get('start', 0);
        $length = (int)$request->get('length', 10);
        $search = $request->get('search')['value'] ?? '';

        $query = OrderItemReturn::with([
            'order.market', 'orderItem.product', 'orderItem.variant', 'sourceShipment', 'store', 'user',
        ])->where('return_status', '!=', OrderItemReturnStatusEnum::CANCELLED());

        $marketId = app(MarketService::class)->adminSelectedId();
        $query->whereHas('order', fn($q) => $q->where('market_id', $marketId));

        $totalRecords = (clone $query)->count();

        $this->applyDatatableDateRange($query, $request, 'order_item_returns.created_at');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                    ->orWhere('order_id', 'like', "%{$search}%")
                    ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $hasFilters = !empty($search) || $request->filled('date_from') || $request->filled('date_to');
        $filteredRecords = $hasFilters ? (clone $query)->count() : $totalRecords;

        $results = $query->orderByDesc('id')->skip($start)->take($length)->get();

        $canRefund = $this->refundPermission;
        $data = $results->map(fn($r) => $this->formatAdminReturnRow($r, $canRefund))->toArray();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Format one return row for the admin datatable.
     */
    private function formatAdminReturnRow(OrderItemReturn $r, bool $canRefund): array
    {
        $created = $r->created_at;
        $orderDate = $created
            ? "<div><div class='fw-medium'>{$created->format('M j, Y')}</div><small class='text-muted'>{$created->format('H:i')}</small></div>"
            : '—';

        $title = e($r->orderItem->title ?? '—');
        $variant = e($r->orderItem->variant_title ?? '');
        $qty = (int)($r->quantity ?: 1);
        $orderDetails = "<div><div class='fw-medium'>{$title} " . ($variant ? "<small class='text-muted'>{$variant}</small>" : '') . "</div>"
            . "<small class='text-muted'>" . __('labels.order') . " #{$r->order_id} · × {$qty}</small>"
            . ($r->source_shipment_id
                ? "<div class='small text-muted'>" . __('labels.shipment') . " #{$r->source_shipment_id}"
                    . ($r->sourceShipment?->tracking_number ? ' · ' . e($r->sourceShipment->tracking_number) : '') . '</div>'
                : '') . '</div>';

        $reasonLabel = $r->reason_code?->label() ?? ($r->reason ?: '—');
        $reason = '<div>' . e($reasonLabel) . '</div>';
        $refund = '<div class="fw-medium">' . formatOrderMoney($r->refund_amount, $r->order?->currency) . '</div>';

        $statusVal = (string)$r->return_status;
        $statusClass = match ($statusVal) {
            (string)OrderItemReturnStatusEnum::REFUNDED() => 'bg-green-lt text-green',
            (string)OrderItemReturnStatusEnum::DECLINED() => 'bg-red-lt text-red',
            (string)OrderItemReturnStatusEnum::APPROVED() => 'bg-blue-lt text-blue',
            (string)OrderItemReturnStatusEnum::REQUESTED() => 'bg-yellow-lt text-yellow',
            default => 'bg-secondary-lt',
        };
        $status = "<span class='badge {$statusClass} text-uppercase'>" . ucwords(str_replace('_', ' ', $statusVal)) . "</span>";

        $finals = [
            (string)OrderItemReturnStatusEnum::REFUNDED(),
            (string)OrderItemReturnStatusEnum::DECLINED(),
            (string)OrderItemReturnStatusEnum::CANCELLED(),
            (string)OrderItemReturnStatusEnum::CLOSED(),
        ];
        $actions = '<a href="' . route('admin.orders.show', ['id' => $r->order_id]) . '" class="btn btn-sm me-1">' . __('labels.view') . '</a>';
        if ($canRefund && !in_array($statusVal, $finals, true)) {
            if ($statusVal === (string)OrderItemReturnStatusEnum::APPROVED()) {
                $actions .= '<a href="' . route('admin.orders.refund', ['id' => $r->order_id]) . '" class="btn btn-sm btn-primary">' . (__('labels.process_return_refund') ?? 'Process refund') . '</a>';
            } else {
                $actions .= '<a href="' . route('admin.orders.return_review', ['returnId' => $r->id]) . '" class="btn btn-sm btn-primary">' . (__('labels.review') ?? 'Review') . '</a>';
            }
        }

        return [
            'id' => $r->id,
            'order_date' => $orderDate,
            'order_details' => $orderDetails,
            'reason' => $reason,
            'refund_amount' => $refund,
            'return_status' => $status,
            'actions' => $actions,
        ];
    }

    /**
     * Admin-initiated (direct) return — one or more items selected on the create
     * page. A separate return is created (and approved) per item, with shared
     * return shipping options; the return shipping charge is attached to the
     * first one only (it's a single return shipment). Redirects to the order.
     */
    public function adminCreateReturn(Request $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin') {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }
            if (!$this->refundPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $validated = $request->validate([
                'items' => ['required', 'array', 'min:1'],
                'items.*.order_item_id' => ['required', 'integer'],
                'items.*.shipment_id' => ['nullable', 'integer', 'min:1'],
                'items.*.quantity' => ['nullable', 'integer', 'min:1'],
                'items.*.reason_code' => ['nullable', Rule::in(\App\Enums\Order\ReturnReasonCodeEnum::values())],
                'return_shipping_charge' => ['nullable', 'numeric', 'min:0'],
                'return_carrier' => ['nullable', 'string', 'max:120'],
                'return_tracking_number' => ['nullable', 'string', 'max:120'],
                'return_tracking_url' => ['nullable', 'url', 'max:512', 'regex:/^https?:\/\//i'],
                'return_label_url' => ['nullable', 'url', 'max:512', 'regex:/^https?:\/\//i'],
                'no_shipping_required' => ['nullable', 'boolean'],
                'return_label' => ['nullable', 'file', 'mimes:pdf,png,jpg,jpeg', 'max:5120'],
            ]);

            $sharedShipping = [
                'return_carrier' => $validated['return_carrier'] ?? null,
                'return_tracking_number' => $validated['return_tracking_number'] ?? null,
                'return_tracking_url' => $validated['return_tracking_url'] ?? null,
                'return_label_url' => $validated['return_label_url'] ?? null,
                'no_shipping_required' => (bool)($validated['no_shipping_required'] ?? false),
            ];

            $createdIds = [];
            $first = true;
            foreach ($validated['items'] as $line) {
                $orderItemId = (int)($line['order_item_id'] ?? 0);
                if (!OrderItem::where('id', $orderItemId)->where('order_id', $id)->exists()) {
                    continue;
                }

                $created = $this->orderService->createDirectReturnByAdmin(
                    orderItemId: $orderItemId,
                    adminId: (int)auth()->id(),
                    reasonCode: $line['reason_code'] ?? null,
                    note: null,
                    quantity: isset($line['quantity']) ? (int)$line['quantity'] : null,
                    sourceShipmentId: isset($line['shipment_id']) ? (int)$line['shipment_id'] : null,
                );
                if (!($created['success'] ?? false) || empty($created['data']['return_id'])) {
                    continue;
                }
                $returnId = (int)$created['data']['return_id'];
                $return = OrderItemReturn::find($returnId);
                if (!$return) {
                    continue;
                }

                $approveData = $sharedShipping;
                if ($first) {
                    $approveData['return_shipping_charge'] = (float)($validated['return_shipping_charge'] ?? 0);
                    if ($request->hasFile('return_label')) {
                        SpatieMediaService::uploadFromRequest(
                            $return,
                            $request->file('return_label'),
                            SpatieMediaCollectionName::RETURN_LABEL()
                        );
                        $approveData['return_label_url'] = $return->labelFileUrl() ?? $approveData['return_label_url'];
                    }
                } else {
                    $approveData['return_shipping_charge'] = 0;
                }

                $this->orderService->approveReturnByAdmin($returnId, (int)auth()->id(), $approveData);
                $createdIds[] = $returnId;
                $first = false;
            }

            if (empty($createdIds)) {
                return ApiResponseType::sendJsonResponse(false, __('labels.select_at_least_one_item') ?? 'Select at least one item.', []);
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.return_created'),
                data: [
                    'return_ids' => $createdIds,
                    'redirect' => count($createdIds) === 1
                        ? route('admin.orders.return_review', ['returnId' => $createdIds[0]])
                        : route('admin.orders.show', ['id' => $id]),
                ],
            );
        } catch (\Exception $e) {
            Log::error('Admin create return failed', ['order_id' => $id, 'error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Restock selected order items (admin) — put their units back into inventory.
     */
    public function adminRestockItems(Request $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin' || !$this->editPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $validated = $request->validate([
                'item_ids' => ['required', 'array', 'min:1'],
                'item_ids.*' => ['integer'],
            ]);

            $result = $this->orderService->restockOrderItemsByAdmin(
                orderId: $id,
                adminId: (int)auth()->id(),
                itemIds: $validated['item_ids'],
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Admin restock submit failed', ['order_id' => $id, 'error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Reopen an archived order (admin) — reverses the pending settlement and flips
     * the order back to OPEN so returns/refunds can be made.
     */
    public function adminReopenOrder(Request $request, int $id): JsonResponse
    {
        try {
            if ($this->getPanel() !== 'admin' || !$this->editPermission) {
                return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), []);
            }

            $result = $this->orderService->reopenArchivedOrder(
                orderId: $id,
                adminId: (int)auth()->id(),
            );

            return ApiResponseType::sendJsonResponse($result['success'], $result['message'], $result['data'] ?? []);
        } catch (\Exception $e) {
            Log::error('Admin reopen order failed', ['order_id' => $id, 'error' => $e->getMessage()]);
            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Build the small addons list that hangs under the product details cell
     * in the orders datatable. Returns an empty string when the order item
     * has no addons — keeps the DOM clean for non-addon orders.
     *
     * Output shape (HTML, escaped):
     *   <div class="mt-1">
     *     <span>Add-ons:</span>
     *     <ul>
     *       <li>Group — Item · $1.50</li>
     *       ...
     *     </ul>
     *   </div>
     */
    private function renderAddonsSnippet(?OrderItem $orderItem): string
    {
        if (!$orderItem) {
            return '';
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->with(['addonGroup', 'addonItem'])->get();

        if (!$addons || $addons->isEmpty()) {
            return '';
        }

        $orderCurrency = PricePresenter::payload($orderItem->order->market ?? null);

        $rows = $addons->map(function ($addon) use ($orderCurrency) {
            $groupTitle = $addon->addonGroup?->title;
            $itemTitle = $addon->addonItem?->title ?? '—';
            $price = formatOrderMoney((float)$addon->price, $orderCurrency);
            $label = $groupTitle ? e($groupTitle) . ' — ' . e($itemTitle) : e($itemTitle);

            return "<li>{$label} <span class='text-muted'>· {$price}</span></li>";
        })->implode('');

        return "<div class='mt-1'>
                    <span class='fw-medium'>" . __('labels.addons') . ":</span>
                    <ul class='list-unstyled mb-0 ps-3 small'>{$rows}</ul>
                </div>";
    }

    /**
     * Compact attachment badge for the orders datatable. Renders one visible
     * "Prescription (N)" chip plus N-1 hidden anchors so all attachments form
     * a single fslightbox gallery scoped to the order item, not the whole page.
     * Returns '' when the item has no attachments.
     */
    private function renderAttachmentsBadge(?OrderItem $orderItem): string
    {
        if (!$orderItem) {
            return '';
        }

        $media = $orderItem->getMedia(SpatieMediaCollectionName::ORDER_ITEM_ATTACHMENTS());
        if ($media->isEmpty()) {
            return '';
        }

        $count = $media->count();
        $gallery = 'order-item-attachments-' . (int)$orderItem->id;
        $label = __('labels.prescription') . ' (' . $count . ')';

        $html = '';
        foreach ($media as $index => $m) {
            $isImage = str_starts_with(strtolower((string) $m->mime_type), 'image/');
            $extension = strtoupper((string) pathinfo($m->file_name, PATHINFO_EXTENSION));
            $itemLabel = $index === 0 ? $label : ($extension ?: __('labels.attachment'));
            $lightbox = $isImage ? ' data-fslightbox="' . e($gallery) . '"' : '';
            $icon = $isImage ? 'ti-photo' : ($extension === 'PDF' ? 'ti-file-type-pdf' : 'ti-file-description');
            $html .= '<a href="' . e($m->getUrl()) . '"' . $lightbox . ' class="badge bg-orange-lt mt-2 me-1" target="_blank" rel="noopener">'
                . '<i class="ti ' . $icon . ' me-1"></i>' . e($itemLabel)
                . '</a>';
        }
        return $html;
    }

    public function orderInvoice(Request $request): View
    {
        try {
            $orderId = $request->input('id');
            $sellerOrder = sellerOrder::with(
                'order',
                'order.market',
                'seller',
                'order.promoLine',
                'items.product',
                'items.orderItem.store',
                'items.variant',
                'items.orderItem',
                'items.orderItem.addons.addonGroup',
                'items.orderItem.addons.addonItem',
            )
                ->whereHas('order', function ($q) use ($orderId) {
                    $q->where('uuid', $orderId);
                })
                ->get();
            if (count($sellerOrder) === 0) {
                abort(404, __('labels.order_not_found'));
            }
            foreach ($sellerOrder as $so) {
                if ($so->seller) {
                    $so->seller->authorized_signature = $so->seller->getFirstMediaUrl(SpatieMediaCollectionName::AUTHORIZED_SIGNATURE()) ?? null;
                }
            }
            $orderData = $sellerOrder[0]['order'];
            $currency = PricePresenter::payload($orderData->market ?? null);
            return view('layouts.custom-order-invoice', [
                'order' => $orderData,
                'sellerOrder' => $sellerOrder,
                'currency' => $currency,
                'invoiceConfig' => InvoiceBuilderController::publishedConfig(),
            ]);
        } catch (AuthorizationException) {
            abort(403, __('messages.unauthorized_action'));
        }
    }

}
