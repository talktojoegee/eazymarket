<?php

namespace App\Services;

use App\Enums\AdminPermissionEnum;
use App\Enums\DateRangeFilterEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use App\Enums\Order\FulfillmentStatusEnum;
use App\Enums\Order\OrderCreatedByEnum;
use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\SellerPermissionEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerOrderItem;
use App\Models\SellerStatement;
use App\Models\SellerWithdrawalRequest;
use App\Models\User;
use App\Support\DemoMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DatatableExportRegistry
{
    public function definition(string $module, string $panel): array
    {
        $definitions = [
            'orders' => [
                'panels' => ['admin', 'seller'],
                'permissions' => ['admin' => AdminPermissionEnum::ORDER_EXPORT(), 'seller' => SellerPermissionEnum::ORDER_EXPORT()],
                'date_label' => __('labels.order_placed_date'),
                'current_page_expands' => true,
                'visible_columns' => ['order_id', 'created_at', 'buyer', 'item_name', 'variant_name', 'sku', 'quantity', 'item_subtotal', 'payment_method', 'payment_status', 'fulfillment_status', 'status'],
                'columns' => [
                    'id' => __('labels.order_item_id'), 'order_id' => __('labels.order_id'), 'seller_order_id' => __('labels.seller_order_id'),
                    'uuid' => __('labels.order_uuid'), 'product_id' => __('labels.product_id'),
                    'product_variant_id' => __('labels.product_variant_id'), 'item_name' => __('labels.item_name'),
                    'variant_name' => __('labels.variant'), 'sku' => __('labels.sku'), 'quantity' => __('labels.quantity'),
                    'unit_price' => __('labels.unit_price'), 'item_discount' => __('labels.item_discount'),
                    'item_promo_discount' => __('labels.item_promo_discount'), 'item_tax_percent' => __('labels.item_tax_percent'),
                    'item_tax_amount' => __('labels.item_tax_amount'), 'item_subtotal' => __('labels.item_subtotal'),
                    'item_status' => __('labels.item_status'), 'item_fulfillment_status' => __('labels.item_fulfillment_status'),
                    'customer_id' => __('labels.customer_id'), 'buyer' => __('labels.customer'), 'email' => __('labels.email'),
                    'phone' => __('labels.customer_phone'), 'seller' => __('labels.seller'), 'store' => __('labels.store'),
                    'market' => __('labels.market'), 'items_count' => __('labels.items_count'), 'created_by' => __('labels.created_by'),
                    'payment_method' => __('labels.payment_method'), 'payment_status' => __('labels.payment_status'),
                    'fulfillment_method' => __('labels.fulfillment_method'), 'fulfillment_status' => __('labels.fulfillment_status'),
                    'status' => __('labels.order_status'), 'currency' => __('labels.currency'), 'currency_rate' => __('labels.currency_rate'),
                    'subtotal' => __('labels.subtotal'), 'promo_code' => __('labels.promo_code'),
                    'promo_discount' => __('labels.promo_discount'), 'gift_card' => __('labels.gift_card'),
                    'gift_card_discount' => __('labels.gift_card_discount'), 'wallet_amount' => __('labels.wallet_amount'),
                    'shipping_charge' => __('labels.shipping_charge'), 'tax_total' => __('labels.tax_total'),
                    'platform_fee' => __('labels.platform_fee'), 'cod_fee' => __('labels.cod_fee'),
                    'total_payable' => __('labels.total_payable'), 'total' => __('labels.total'),
                    'shipping_address' => __('labels.shipping_address'), 'billing_address' => __('labels.billing_address'),
                    'order_note' => __('labels.order_note'), 'cancellation_state' => __('labels.cancellation_state'),
                    'flagged' => __('labels.flagged'),
                    'created_at' => __('labels.order_placed_date'),
                ],
            ],
            'products' => [
                'panels' => ['admin', 'seller'],
                'permissions' => ['admin' => AdminPermissionEnum::PRODUCT_EXPORT(), 'seller' => SellerPermissionEnum::PRODUCT_EXPORT()],
                'date_label' => __('labels.product_created_date'),
                'columns' => [
                    'id' => __('labels.id'), 'title' => __('labels.title'), 'category' => __('labels.category'),
                    'brand' => __('labels.brand'), 'seller' => __('labels.seller'), 'type' => __('labels.type'),
                    'status' => __('labels.status'), 'verification_status' => __('labels.verification_status'),
                    'featured' => __('labels.featured'), 'created_at' => __('labels.product_created_date'),
                ],
            ],
            'brands' => [
                'panels' => ['admin', 'seller'],
                'permissions' => ['admin' => AdminPermissionEnum::BRAND_EXPORT(), 'seller' => SellerPermissionEnum::BRAND_EXPORT()],
                'date_label' => __('labels.created_at'),
                'columns' => [
                    'id' => __('labels.id'), 'title' => __('labels.title'), 'description' => __('labels.description'),
                    'scope' => __('labels.scope_type'), 'category' => __('labels.category'),
                    'status' => __('labels.status'), 'products_count' => __('labels.products'),
                    'created_at' => __('labels.created_at'),
                ],
            ],
            'categories' => [
                'panels' => ['admin', 'seller'],
                'permissions' => ['admin' => AdminPermissionEnum::CATEGORY_EXPORT(), 'seller' => SellerPermissionEnum::CATEGORY_EXPORT()],
                'date_label' => __('labels.created_at'),
                'visible_columns' => ['id', 'title', 'parent', 'commission', 'status', 'requires_approval', 'products_count', 'created_at'],
                'columns' => [
                    'id' => __('labels.id'), 'uuid' => __('labels.uuid'), 'title' => __('labels.title'),
                    'slug' => __('labels.slug'), 'description' => __('labels.description'), 'parent_id' => __('labels.parent_id'),
                    'parent' => __('labels.parent'), 'commission' => __('labels.commission'), 'status' => __('labels.status'),
                    'requires_approval' => __('labels.requires_approval'), 'sort_order' => __('labels.sort_order'),
                    'products_count' => __('labels.products'), 'created_at' => __('labels.created_at'),
                ],
            ],
            'customers' => [
                'panels' => ['admin'],
                'permissions' => ['admin' => AdminPermissionEnum::CUSTOMER_EXPORT()],
                'date_label' => __('labels.customer_joined_date'),
                'columns' => [
                    'id' => __('labels.id'), 'name' => __('labels.name'), 'email' => __('labels.email'),
                    'mobile' => __('labels.mobile'), 'wallet_balance' => __('labels.wallet_balance'),
                    'created_at' => __('labels.customer_joined_date'),
                ],
            ],
            'sellers' => [
                'panels' => ['admin'],
                'permissions' => ['admin' => AdminPermissionEnum::SELLER_EXPORT()],
                'date_label' => __('labels.seller_joined_date'),
                'columns' => [
                    'id' => __('labels.id'), 'name' => __('labels.name'), 'email' => __('labels.email'),
                    'mobile' => __('labels.mobile'), 'verification_status' => __('labels.verification_status'),
                    'visibility_status' => __('labels.visibility'), 'stores' => __('labels.stores'),
                    'plan' => __('labels.subscription_plan'), 'created_at' => __('labels.seller_joined_date'),
                ],
            ],
            'earnings' => [
                'panels' => ['admin', 'seller'],
                'permissions' => ['admin' => AdminPermissionEnum::COMMISSION_EXPORT(), 'seller' => SellerPermissionEnum::EARNING_EXPORT()],
                'date_label' => __('labels.posted_at'),
                'columns' => [
                    'id' => __('labels.id'), 'entry_type' => __('labels.entry_type'), 'order_id' => __('labels.order_id'),
                    'order_item_id' => __('labels.order_item_id'), 'title' => __('labels.title'), 'store' => __('labels.store'),
                    'seller' => __('labels.seller'), 'currency' => __('labels.currency'), 'amount' => __('labels.amount'),
                    'settlement_status' => __('labels.settlement_status'), 'posted_at' => __('labels.posted_at'),
                    'settled_at' => __('labels.settled_on'),
                ],
            ],
            'withdrawals' => [
                'panels' => ['admin', 'seller'],
                'permissions' => ['admin' => AdminPermissionEnum::SELLER_WITHDRAWAL_EXPORT(), 'seller' => SellerPermissionEnum::WITHDRAWAL_EXPORT()],
                'date_label' => __('labels.withdrawal_request_date'),
                'columns' => [
                    'id' => __('labels.id'), 'seller' => __('labels.seller'), 'amount' => __('labels.amount'),
                    'status' => __('labels.status'), 'request_note' => __('labels.request_note'),
                    'admin_remark' => __('labels.admin_remark'), 'processed_at' => __('labels.processed_at'),
                    'created_at' => __('labels.withdrawal_request_date'),
                ],
            ],
            'returns' => [
                'panels' => ['admin', 'seller'],
                'permissions' => ['admin' => AdminPermissionEnum::RETURN_EXPORT(), 'seller' => SellerPermissionEnum::RETURN_EXPORT()],
                'date_label' => __('labels.return_requested_date'),
                'columns' => [
                    'id' => __('labels.id'), 'order_id' => __('labels.order_id'), 'customer' => __('labels.customer'),
                    'seller' => __('labels.seller'), 'store' => __('labels.store'), 'reason' => __('labels.return_reason'),
                    'refund_amount' => __('labels.refund_amount'), 'status' => __('labels.return_status'),
                    'created_at' => __('labels.return_requested_date'),
                ],
            ],
        ];

        $definition = $definitions[$module] ?? null;
        if (! $definition || ! in_array($panel, $definition['panels'], true)) {
            throw ValidationException::withMessages(['module' => __('labels.invalid_export_module')]);
        }

        return $definition;
    }

    public function authorize(User $user, string $module, string $panel): void
    {
        abort_unless($this->can($user, $module, $panel), 403, __('labels.permission_denied'));
    }

    public function frontendOptions(User $user, string $module, string $panel, array $options = []): array
    {
        $definition = $this->definition($module, $panel);

        return array_merge($options, [
            'module' => $module,
            'columns' => $definition['columns'],
            'dateLabel' => $definition['date_label'],
            'visibleColumns' => $options['visibleColumns'] ?? ($definition['visible_columns'] ?? null),
            'canExport' => $this->can($user, $module, $panel),
            'estimateUrl' => route($panel.'.datatable-exports.estimate'),
            'createUrl' => route($panel.'.datatable-exports.store'),
            'labels' => [
                'selectRows' => __('labels.select_rows_to_export'),
                'selectColumns' => __('labels.select_columns_to_export'),
                'calculating' => __('labels.calculating_export'),
                'immediateShort' => __('labels.export_immediate_short'),
                'queuedShort' => __('labels.export_queued_short'),
                'rows' => __('labels.export_estimate_rows'),
                'itemsMatch' => __('labels.export_items_match'),
                'error' => __('labels.unable_to_prepare_export'),
            ],
        ]);
    }

    public function can(User $user, string $module, string $panel): bool
    {
        $definition = $this->definition($module, $panel);
        $allowed = $panel === 'admin'
            ? $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN(), GuardNameEnum::ADMIN->value)
            : (int) ($user->seller()?->user_id ?? 0) === (int) $user->id;

        if (! $allowed) {
            try {
                $allowed = $user->hasPermissionTo($definition['permissions'][$panel], $panel);
            } catch (\Throwable) {
                $allowed = false;
            }
        }

        return $allowed;
    }

    public function query(string $module, string $panel, User $user, array $parameters, string $scope): Builder
    {
        $filtered = $scope !== 'all_records';
        $query = match ($module) {
            'orders' => $this->ordersQuery($panel, $parameters, $filtered, $scope),
            'products' => $this->productsQuery($panel, $parameters, $filtered),
            'brands' => $this->brandsQuery($parameters, $filtered),
            'categories' => $this->categoriesQuery($parameters, $filtered),
            'customers' => $this->customersQuery($parameters, $filtered),
            'sellers' => $this->sellersQuery($parameters, $filtered),
            'earnings' => $this->earningsQuery($panel, $parameters, $filtered),
            'withdrawals' => $this->withdrawalsQuery($panel, $parameters, $filtered),
            'returns' => $this->returnsQuery($panel, $parameters, $filtered),
            default => throw ValidationException::withMessages(['module' => __('labels.invalid_export_module')]),
        };

        if ($scope === 'selected' && $module !== 'orders') {
            $query->whereKey(array_values(array_unique(array_map('intval', $parameters['selected_ids'] ?? []))));
        }

        return $query;
    }

    public function row(string $module, string $panel, object $record, User $user): array
    {
        return match ($module) {
            'orders' => $this->orderRow($panel, $record, $user),
            'products' => [
                'id' => $record->id,
                'title' => $record->title,
                'category' => $record->category?->title,
                'brand' => $record->brand?->title,
                'seller' => $record->seller?->user?->name,
                'type' => $this->labelValue($record->type),
                'status' => $this->labelValue($record->status),
                'verification_status' => $this->labelValue($record->verification_status),
                'featured' => $record->featured ? __('labels.yes') : __('labels.no'),
                'created_at' => $record->created_at?->format('Y-m-d H:i:s'),
            ],
            'brands' => [
                'id' => $record->id,
                'title' => $record->title,
                'description' => $record->description,
                'scope' => $this->labelValue($record->scope_type),
                'category' => $record->scopeCategory?->title,
                'status' => $this->labelValue($record->status),
                'products_count' => $record->products_count,
                'created_at' => $record->created_at?->format('Y-m-d H:i:s'),
            ],
            'categories' => [
                'id' => $record->id,
                'uuid' => $record->uuid,
                'title' => $record->title,
                'slug' => $record->slug,
                'description' => $record->description,
                'parent_id' => $record->parent_id,
                'parent' => $record->parent?->title,
                'commission' => $this->decimal($record->commission),
                'status' => $this->labelValue($record->status),
                'requires_approval' => $record->requires_approval ? __('labels.yes') : __('labels.no'),
                'sort_order' => $record->sort_order,
                'products_count' => $record->products_count,
                'created_at' => $record->created_at?->format('Y-m-d H:i:s'),
            ],
            'customers' => $this->customerRow($record, $user),
            'sellers' => $this->sellerRow($record, $user),
            'earnings' => [
                'id' => $record->id,
                'entry_type' => $this->labelValue($record->entry_type),
                'order_id' => $record->order_id,
                'order_item_id' => $record->order_item_id,
                'title' => $record->orderItem?->title,
                'store' => $record->orderItem?->store?->name,
                'seller' => $record->seller?->user?->name,
                'currency' => $record->currency_code,
                'amount' => number_format((float) $record->amount, 2, '.', ''),
                'settlement_status' => $this->labelValue($record->settlement_status),
                'posted_at' => $record->posted_at?->format('Y-m-d H:i:s'),
                'settled_at' => $record->settled_at?->format('Y-m-d H:i:s'),
            ],
            'withdrawals' => [
                'id' => $record->id,
                'seller' => $record->seller?->user?->name,
                'amount' => number_format((float) $record->amount, 2, '.', ''),
                'status' => $this->labelValue($record->status),
                'request_note' => $record->request_note,
                'admin_remark' => $record->admin_remark,
                'processed_at' => $record->processed_at?->format('Y-m-d H:i:s'),
                'created_at' => $record->created_at?->format('Y-m-d H:i:s'),
            ],
            'returns' => [
                'id' => $record->id,
                'order_id' => $record->order_id,
                'customer' => $record->user?->name,
                'seller' => $record->seller?->user?->name,
                'store' => $record->store?->name,
                'reason' => $record->reason,
                'refund_amount' => number_format((float) $record->refund_amount, 2, '.', ''),
                'status' => $this->labelValue($record->return_status),
                'created_at' => $record->created_at?->format('Y-m-d H:i:s'),
            ],
        };
    }

    private function ordersQuery(string $panel, array $parameters, bool $filtered, string $scope): Builder
    {
        $parents = $this->orderParentsQuery($panel, $parameters, $filtered);
        $parentIds = null;
        if ($scope === 'selected') {
            $parentIds = array_values(array_unique(array_map('intval', $parameters['selected_ids'] ?? [])));
        } elseif ($scope === 'current_page') {
            $parentIds = (clone $parents)
                ->offset(max(0, (int) ($parameters['start'] ?? 0)))
                ->limit(max(1, min(1000, (int) ($parameters['length'] ?? 10))))
                ->pluck($panel === 'seller' ? 'seller_orders.id' : 'orders.id')
                ->all();
        }

        $direction = ($parameters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        if ($panel === 'seller') {
            $query = SellerOrderItem::with([
                'orderItem.order' => fn ($order) => $order->with('market')->withCount('items'),
                'orderItem.store',
                'sellerOrder.seller.user',
            ]);
            if (! empty($parameters['context']['store_id'])) {
                $query->whereHas('orderItem', fn (Builder $item) => $item->where('store_id', (int) $parameters['context']['store_id']));
            }
            $parentColumn = 'seller_order_id';
            $dateOrder = Order::query()
                ->select('orders.created_at')
                ->join('seller_orders', 'seller_orders.order_id', '=', 'orders.id')
                ->whereColumn('seller_orders.id', 'seller_order_items.seller_order_id')
                ->limit(1);
        } else {
            $query = OrderItem::with([
                'order' => fn ($order) => $order->with('market')->withCount('items'),
                'store',
                'sellerOrderItem.sellerOrder.seller.user',
            ]);
            $parentColumn = 'order_id';
            $dateOrder = Order::query()
                ->select('orders.created_at')
                ->whereColumn('orders.id', 'order_items.order_id')
                ->limit(1);
        }

        if ($parentIds !== null) {
            $query->whereIn($parentColumn, $parentIds);
        } else {
            $query->whereIn($parentColumn, (clone $parents)->reorder()->select($panel === 'seller' ? 'seller_orders.id' : 'orders.id'));
        }

        return $query->orderBy($dateOrder, $direction)->orderBy('id', $direction);
    }

    private function orderParentsQuery(string $panel, array $parameters, bool $filtered): Builder
    {
        $context = $parameters['context'] ?? [];
        if ($panel === 'seller') {
            $query = SellerOrder::query()
                ->where('seller_id', (int) ($context['seller_id'] ?? 0))
                ->whereHas('order', fn (Builder $order) => $order
                    ->where('payment_method', PaymentTypeEnum::COD())
                    ->orWhere('payment_status', '!=', PaymentStatusEnum::PENDING()));
            if (! empty($context['store_id'])) {
                $query->whereHas('items.orderItem', fn (Builder $builder) => $builder->where('store_id', (int) $context['store_id']));
            }
        } else {
            $query = Order::query();
            if (! empty($context['market_id'])) {
                $query->where('market_id', (int) $context['market_id']);
            }
        }

        if (! $filtered) {
            return $query->orderByDesc('id');
        }

        $filters = $parameters['filters'] ?? [];
        if (! empty($filters['range'])) {
            $from = match ($filters['range']) {
                DateRangeFilterEnum::LAST_30_MINUTES() => now()->subMinutes(30),
                DateRangeFilterEnum::LAST_1_HOUR() => now()->subHour(),
                DateRangeFilterEnum::LAST_5_HOURS() => now()->subHours(5),
                DateRangeFilterEnum::LAST_1_DAY() => now()->subDay(),
                DateRangeFilterEnum::LAST_7_DAYS() => now()->subDays(7),
                DateRangeFilterEnum::LAST_30_DAYS() => now()->subDays(30),
                DateRangeFilterEnum::LAST_365_DAYS() => now()->subDays(365),
                default => null,
            };
            if ($from) {
                $query->where('created_at', '>=', $from);
            }
        }
        if ($panel === 'seller') {
            $this->applyRelationDate($query, $parameters, 'order', 'orders.created_at');
        } else {
            $this->applyDate($query, $parameters, 'orders.created_at');
        }
        $search = $parameters['search'] ?? '';
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search, $panel) {
                $builder->where('id', 'like', "%{$search}%");
                if ($panel === 'seller') {
                    $builder->orWhere('total_price', 'like', "%{$search}%")
                        ->orWhereHas('order', fn (Builder $order) => $order->where('id', 'like', "%{$search}%")->orWhere('uuid', 'like', "%{$search}%")->orWhere('shipping_name', 'like', "%{$search}%"));
                } else {
                    $builder->orWhere('uuid', 'like', "%{$search}%")->orWhere('shipping_name', 'like', "%{$search}%")->orWhere('final_total', 'like', "%{$search}%");
                }
            });
        }
        foreach (['payment_method', 'payment_status', 'lifecycle'] as $filter) {
            if (empty($filters[$filter])) {
                continue;
            }
            $field = $filter === 'lifecycle' ? 'status' : $filter;
            $panel === 'seller'
                ? $query->whereHas('order', fn (Builder $order) => $order->where($field, $filters[$filter]))
                : $query->where($field, $filters[$filter]);
        }
        if (! empty($filters['status'])) {
            $fulfillmentValues = array_map(fn (FulfillmentStatusEnum $case) => $case->value, FulfillmentStatusEnum::cases());
            $field = in_array(strtolower((string) $filters['status']), $fulfillmentValues, true) ? 'fulfillment_status' : 'status';
            $query->where(fn (Builder $builder) => $builder->where($field, $filters['status'])->when($field === 'fulfillment_status', fn (Builder $nested) => $nested->orWhere('status', $filters['status'])));
        }
        if (! empty($filters['order_type'])) {
            $createdBy = $filters['order_type'] === 'pos' ? OrderCreatedByEnum::SELLER() : OrderCreatedByEnum::CUSTOMER();
            if ($filters['order_type'] === 'regular') {
                $panel === 'seller'
                    ? $query->whereHas('order', fn (Builder $order) => $order->whereNull('created_by')->orWhere('created_by', $createdBy))
                    : $query->where(fn (Builder $order) => $order->whereNull('created_by')->orWhere('created_by', $createdBy));
            } elseif ($filters['order_type'] === 'pos') {
                $panel === 'seller'
                    ? $query->whereHas('order', fn (Builder $order) => $order->where('created_by', $createdBy))
                    : $query->where('created_by', $createdBy);
            }
        }
        if (! empty($filters['fulfillment_method'])) {
            $panel === 'seller'
                ? $query->where('fulfillment_method', $filters['fulfillment_method'])
                : $query->whereHas('sellerOrders', fn (Builder $order) => $order->where('fulfillment_method', $filters['fulfillment_method']));
        }

        return $this->applySort($query, $parameters, ['id', 'created_at'], 'created_at');
    }

    private function productsQuery(string $panel, array $parameters, bool $filtered): Builder
    {
        $context = $parameters['context'] ?? [];
        $query = Product::with(['category', 'brand', 'seller.user']);
        if ($panel === 'seller') {
            $query->where('seller_id', (int) ($context['seller_id'] ?? 0));
        }
        if (! $filtered) {
            return $query->orderByDesc('id');
        }
        $this->applyDate($query, $parameters, 'products.created_at');
        $filters = $parameters['filters'] ?? [];
        $search = $parameters['search'] ?? '';
        if ($search !== '') {
            $query->where(fn (Builder $builder) => $builder
                ->where('title', 'like', "%{$search}%")
                ->orWhere('id', 'like', "%{$search}%")
                ->orWhereHas('category', fn (Builder $category) => $category->where('title', 'like', "%{$search}%"))
                ->orWhereHas('brand', fn (Builder $brand) => $brand->where('title', 'like', "%{$search}%"))
                ->orWhereHas('seller.user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))
                ->orWhereHas('variants', fn (Builder $variant) => $variant->where('sku', 'like', "%{$search}%")->orWhere('barcode', 'like', "%{$search}%")));
        }
        foreach (['product_type' => 'type', 'product_status' => 'status', 'verification_status' => 'verification_status', 'category_id' => 'category_id', 'brand_id' => 'brand_id', 'badge_id' => 'badge_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter] === 'pending' ? 'pending_verification' : $filters[$filter]);
            }
        }
        if (! empty($filters['seller_id']) && $panel === 'admin') {
            $query->where('seller_id', (int) $filters['seller_id']);
        }
        if (! empty($filters['store_id'])) {
            $query->whereHas('variants.storeProductVariants', fn (Builder $builder) => $builder->where('store_id', (int) $filters['store_id']));
        }
        if (! empty($filters['product_filter'])) {
            $query->applyProductFilter($filters['product_filter']);
        }

        return $this->applySort($query, $parameters, ['id', 'title', 'verification_status', 'created_at'], 'id');
    }

    private function brandsQuery(array $parameters, bool $filtered): Builder
    {
        $query = Brand::with('scopeCategory')->withCount('products');
        if (! $filtered) {
            return $query->orderByDesc('id');
        }

        $query->applyListingFilters($parameters);

        return $this->applySort($query, $parameters, ['id', 'title', 'status', 'products_count', 'created_at'], 'title');
    }

    private function categoriesQuery(array $parameters, bool $filtered): Builder
    {
        $query = Category::with('parent')->withCount('products');
        if (! $filtered) {
            return $query->orderByDesc('id');
        }

        $this->applyDate($query, $parameters, 'categories.created_at');
        $search = $parameters['search'] ?? '';
        if ($search !== '') {
            $query->where(fn (Builder $builder) => $builder
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('parent', fn (Builder $parent) => $parent->where('title', 'like', "%{$search}%")));
        }

        return $this->applySort($query, $parameters, ['id', 'title', 'commission', 'status', 'sort_order', 'products_count', 'created_at'], 'title');
    }

    private function customersQuery(array $parameters, bool $filtered): Builder
    {
        $query = User::with('wallet')->where(fn (Builder $builder) => $builder->whereNull('access_panel')->orWhere('access_panel', 'web'));
        if (! $filtered) {
            return $query->orderByDesc('id');
        }
        $this->applyDate($query, $parameters, 'users.created_at');
        $search = $parameters['search'] ?? '';
        if ($search !== '') {
            $query->where(fn (Builder $builder) => $builder->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%"));
        }

        return $this->applySort($query, $parameters, ['id', 'name', 'created_at'], 'id');
    }

    private function sellersQuery(array $parameters, bool $filtered): Builder
    {
        $query = Seller::with(['user', 'activeSubscription.plan'])->withCount('stores');
        if (! $filtered) {
            return $query->orderByDesc('id');
        }
        $this->applyDate($query, $parameters, 'sellers.created_at');
        $search = $parameters['search'] ?? '';
        if ($search !== '') {
            $query->where(fn (Builder $builder) => $builder->whereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%"))->orWhere('verification_status', 'like', "%{$search}%"));
        }

        return $this->applySort($query, $parameters, ['id', 'verification_status', 'visibility_status', 'created_at'], 'id');
    }

    private function earningsQuery(string $panel, array $parameters, bool $filtered): Builder
    {
        $context = $parameters['context'] ?? [];
        $query = SellerStatement::with(['orderItem.store', 'seller.user']);
        if ($panel === 'seller') {
            $query->where('seller_id', (int) ($context['seller_id'] ?? 0));
        } elseif (! empty($context['market_id'])) {
            $query->where(fn (Builder $builder) => $builder
                ->whereHas('order', fn (Builder $order) => $order->where('market_id', (int) $context['market_id']))
                ->orWhereDoesntHave('order'));
        }
        $variant = $context['variant'] ?? 'credits';
        if ($variant === 'history') {
            $query->where('settlement_status', 'settled');
        } else {
            $query->where('settlement_status', 'pending')->where('entry_type', $variant === 'debits' ? 'debit' : 'credit');
        }
        if (! empty($context['store_id'])) {
            $query->whereHas('orderItem', fn (Builder $item) => $item->where('store_id', (int) $context['store_id']));
        }
        $query->whereHas('seller.user', fn (Builder $user) => $user->where('status', true));
        if (! $filtered) {
            return $query->orderByDesc('id');
        }
        $filters = $parameters['filters'] ?? [];
        if ((int) ($filters['date_range'] ?? 0) > 0) {
            $query->where('posted_at', '>=', now()->subDays((int) $filters['date_range'])->startOfDay());
        }
        $this->applyDate($query, $parameters, 'posted_at');
        $search = $parameters['search'] ?? '';
        if ($search !== '') {
            $query->where(fn (Builder $builder) => $builder->where('id', 'like', "%{$search}%")
                ->orWhere('order_id', 'like', "%{$search}%")
                ->orWhere('reference_id', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('orderItem', fn (Builder $item) => $item->where('title', 'like', "%{$search}%")->orWhere('variant_title', 'like', "%{$search}%"))
                ->orWhereHas('orderItem.store', fn (Builder $store) => $store->where('name', 'like', "%{$search}%")));
        }

        return $this->applySort($query, $parameters, ['id', 'amount', 'posted_at', 'settled_at'], 'id');
    }

    private function withdrawalsQuery(string $panel, array $parameters, bool $filtered): Builder
    {
        $context = $parameters['context'] ?? [];
        $query = SellerWithdrawalRequest::with(['seller.user']);
        if ($panel === 'seller') {
            $query->where('seller_id', (int) ($context['seller_id'] ?? 0));
        }
        $variant = $context['variant'] ?? 'pending';
        $variant === 'history' ? $query->whereIn('status', ['approved', 'rejected']) : $query->where('status', 'pending');
        if (! $filtered) {
            return $query->orderByDesc('id');
        }
        $this->applyDate($query, $parameters, 'seller_withdrawal_requests.created_at');
        $filters = $parameters['filters'] ?? [];
        if ($variant === 'history' && ! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if ($variant === 'history' && ! empty($filters['seller_ids'])) {
            $ids = array_filter(array_map('intval', explode(',', (string) $filters['seller_ids'])));
            if ($ids !== []) {
                $query->whereIn('seller_id', $ids);
            }
        }
        $search = $parameters['search'] ?? '';
        if ($search !== '') {
            $query->where(fn (Builder $builder) => $builder->where('id', 'like', "%{$search}%")->orWhere('amount', 'like', "%{$search}%")->orWhere('request_note', 'like', "%{$search}%")->orWhereHas('seller.user', fn (Builder $seller) => $seller->where('name', 'like', "%{$search}%")));
        }

        return $this->applySort($query, $parameters, ['id', 'amount', 'status', 'created_at', 'processed_at'], 'id');
    }

    private function returnsQuery(string $panel, array $parameters, bool $filtered): Builder
    {
        $context = $parameters['context'] ?? [];
        $query = OrderItemReturn::with(['order', 'user', 'seller.user', 'store'])
            ->where('return_status', '!=', 'cancelled');
        if ($panel === 'seller') {
            $query->where('seller_id', (int) ($context['seller_id'] ?? 0));
            if (! empty($context['store_id'])) {
                $query->where('store_id', (int) $context['store_id']);
            }
        } elseif (! empty($context['market_id'])) {
            $query->whereHas('order', fn (Builder $order) => $order->where('market_id', (int) $context['market_id']));
        }
        if (! $filtered) {
            return $query->orderByDesc('id');
        }
        $this->applyDate($query, $parameters, 'order_item_returns.created_at');
        $filters = $parameters['filters'] ?? [];
        if (! empty($filters['return_status'])) {
            $query->where('return_status', $filters['return_status']);
        }
        $search = $parameters['search'] ?? '';
        if ($search !== '') {
            $query->where(fn (Builder $builder) => $builder->where('id', 'like', "%{$search}%")->orWhere('order_id', 'like', "%{$search}%")->orWhere('reason', 'like', "%{$search}%"));
        }

        return $this->applySort($query, $parameters, ['id', 'created_at', 'refund_amount', 'return_status'], 'id');
    }

    private function applyDate(Builder $query, array $parameters, string $column): void
    {
        if (! empty($parameters['date_from'])) {
            $query->where($column, '>=', Carbon::parse($parameters['date_from'], config('app.timezone'))->startOfDay());
        }
        if (! empty($parameters['date_to'])) {
            $query->where($column, '<=', Carbon::parse($parameters['date_to'], config('app.timezone'))->endOfDay());
        }
    }

    private function applyRelationDate(Builder $query, array $parameters, string $relation, string $column): void
    {
        if (empty($parameters['date_from']) && empty($parameters['date_to'])) {
            return;
        }

        $query->whereHas($relation, function (Builder $related) use ($parameters, $column): void {
            $this->applyDate($related, $parameters, $column);
        });
    }

    private function applySort(Builder $query, array $parameters, array $allowed, string $default): Builder
    {
        $column = in_array($parameters['sort'] ?? '', $allowed, true) ? $parameters['sort'] : $default;
        $direction = ($parameters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($column, $direction)->orderBy('id', $direction);
    }

    private function orderRow(string $panel, object $record, User $actor): array
    {
        $item = $panel === 'seller' ? $record->orderItem : $record;
        $sellerOrder = $panel === 'seller' ? $record->sellerOrder : $item?->sellerOrderItem?->sellerOrder;
        $order = $item?->order;
        $demo = DemoMode::isDemoUser($actor);
        $email = (string) ($order?->email ?? '');
        $phone = (string) ($order?->shipping_phone ?? '');

        return [
            'id' => $item?->id,
            'order_id' => $order?->id,
            'seller_order_id' => $sellerOrder?->id,
            'uuid' => $order?->uuid,
            'product_id' => $item?->product_id,
            'product_variant_id' => $item?->product_variant_id,
            'item_name' => $item?->title,
            'variant_name' => $item?->variant_title,
            'sku' => $item?->sku,
            'quantity' => $item?->quantity,
            'unit_price' => $this->decimal($item?->price),
            'item_discount' => $this->decimal($item?->discount),
            'item_promo_discount' => $this->decimal($item?->promo_discount),
            'item_tax_percent' => $this->decimal($item?->tax_percent),
            'item_tax_amount' => $this->decimal($item?->tax_amount),
            'item_subtotal' => $this->decimal($item?->subtotal),
            'item_status' => $this->labelValue($item?->status),
            'item_fulfillment_status' => $this->labelValue($item?->fulfillment_status),
            'customer_id' => $order?->user_id,
            'buyer' => $order?->shipping_name,
            'email' => $demo ? Str::mask($email, '****', 3, 4) : $email,
            'phone' => $demo ? Str::mask($phone, '****', 3, 4) : $phone,
            'seller' => $sellerOrder?->seller?->user?->name,
            'store' => $item?->store?->name,
            'market' => $order?->market?->name,
            'items_count' => $order?->items_count,
            'created_by' => $this->labelValue($order?->created_by),
            'payment_method' => $this->labelValue($order?->payment_method),
            'payment_status' => $this->labelValue($order?->payment_status),
            'fulfillment_method' => $this->labelValue($sellerOrder?->fulfillment_method),
            'fulfillment_status' => $this->labelValue($sellerOrder?->fulfillment_status ?? $order?->fulfillment_status),
            'status' => $this->labelValue($order?->status),
            'currency' => $order?->currency_code,
            'currency_rate' => $order?->currency_rate,
            'subtotal' => $this->decimal($panel === 'seller' ? $sellerOrder?->gross_subtotal : $order?->subtotal),
            'promo_code' => $order?->promo_code,
            'promo_discount' => $this->decimal($panel === 'seller' ? $sellerOrder?->promo_share : $order?->promo_discount),
            'gift_card' => $order?->gift_card,
            'gift_card_discount' => $this->decimal($order?->gift_card_discount),
            'wallet_amount' => $this->decimal($order?->wallet_balance),
            'shipping_charge' => $this->decimal($panel === 'seller' ? $sellerOrder?->shipping_charge_applied : $order?->shipping_total),
            'tax_total' => $this->decimal($panel === 'seller' ? $sellerOrder?->tax_amount : $order?->tax_total),
            'platform_fee' => $panel === 'admin' ? $this->decimal($order?->platform_fee) : null,
            'cod_fee' => $panel === 'admin' ? $this->decimal($order?->cod_fee) : null,
            'total_payable' => $this->decimal($panel === 'seller' ? $sellerOrder?->total_price : $order?->total_payable),
            'total' => $this->decimal($panel === 'seller' ? $sellerOrder?->total_price : $order?->final_total),
            'shipping_address' => $this->address($order, 'shipping'),
            'billing_address' => $this->address($order, 'billing'),
            'order_note' => $order?->order_note,
            'cancellation_state' => $this->labelValue($order?->cancellation_state),
            'flagged' => $order?->is_flagged ? __('labels.yes') : __('labels.no'),
            'created_at' => $order?->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function decimal(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    private function address(?Order $order, string $prefix): ?string
    {
        if (! $order) {
            return null;
        }

        return collect([
            $order->{$prefix.'_name'},
            $order->{$prefix.'_address_1'},
            $order->{$prefix.'_address_2'},
            $order->{$prefix.'_city'},
            $order->{$prefix.'_state'},
            $order->{$prefix.'_zip'},
            $order->{$prefix.'_country'},
        ])->filter()->implode(', ');
    }

    private function customerRow(User $record, User $actor): array
    {
        $demo = DemoMode::isDemoUser($actor);

        return [
            'id' => $record->id,
            'name' => $record->name,
            'email' => $demo ? Str::mask((string) $record->email, '****', 3, 4) : $record->email,
            'mobile' => $demo ? Str::mask((string) $record->mobile, '****', 3, 4) : $record->mobile,
            'wallet_balance' => number_format((float) ($record->wallet?->balance ?? 0), 2, '.', ''),
            'created_at' => $record->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function sellerRow(Seller $record, User $actor): array
    {
        $demo = DemoMode::isDemoUser($actor);
        $email = (string) ($record->user?->email ?? '');
        $mobile = (string) ($record->user?->mobile ?? '');

        return [
            'id' => $record->id,
            'name' => $record->user?->name,
            'email' => $demo ? Str::mask($email, '****', 3, 4) : $email,
            'mobile' => $demo ? Str::mask($mobile, '****', 3, 4) : $mobile,
            'verification_status' => $this->labelValue($record->verification_status),
            'visibility_status' => $this->labelValue($record->visibility_status),
            'stores' => $record->stores_count,
            'plan' => $record->activeSubscription?->plan?->name,
            'created_at' => $record->created_at?->format('Y-m-d H:i:s'),
        ];
    }

    private function labelValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return Lang::has('labels.'.$value) ? __('labels.'.$value) : Str::headline($value);
    }
}
