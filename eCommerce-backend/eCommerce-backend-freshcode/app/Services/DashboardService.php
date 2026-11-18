<?php

namespace App\Services;

use App\Enums\ActiveInactiveStatusEnum;
use App\Enums\Advertisement\AdCampaignStatusEnum;
use App\Enums\Market\MarketStatusEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\ReturnReasonTypeEnum;
use App\Enums\Order\FulfillmentStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductFilterEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\Seller\SellerSettlementStatusEnum;
use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use App\Enums\Seller\SellerWithdrawalStatusEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Enums\Store\StoreVerificationStatusEnum;
use App\Enums\Store\StoreVisibilityStatusEnum;
use App\Enums\Subscription\SellerSubscriptionStatusEnum;
use App\Models\AdCampaign;
use App\Models\AdCampaignStat;
use App\Models\Category;
use App\Models\Market;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerOrderItem;
use App\Models\SellerStatement;
use App\Models\SellerSubscription;
use App\Models\SellerWithdrawalRequest;
use App\Models\Store;
use App\Models\SellerFeedback;
use App\Models\StoreLocationInventory;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionTransaction;
use App\Models\User;
use App\Services\Fx\FxRateService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    protected CurrencyService $currencyService;

    public function __construct(CurrencyService $currencyService)
    {
        $this->currencyService = $currencyService;
    }

    /** Per-request cache of resolved Market models, keyed by id. */
    private array $marketCache = [];

    private function marketFor(?int $marketId): ?Market
    {
        if (!$marketId) {
            return null;
        }
        if (!array_key_exists($marketId, $this->marketCache)) {
            $this->marketCache[$marketId] = Market::find($marketId);
        }
        return $this->marketCache[$marketId];
    }

    /**
     * Format an amount that is ALREADY in the selected market's currency
     * (order-derived metrics are snapshotted in the order's market currency,
     * and the dashboard scopes those queries to one market). Formats with that
     * market's rules; falls back to the platform default when no market is set.
     */
    private function fmtMarket($amount, ?int $marketId): string
    {
        $market = $this->marketFor($marketId);
        return $market
            ? formatOrderMoney((float) $amount, \App\Support\PricePresenter::payload($market))
            : $this->currencyService->format($amount);
    }

    /**
     * Format an amount tracked in the BASE currency (wallet-backed figures:
     * settlements, withdrawals, ad spend, subscriptions) by FX-converting it
     * into the selected market's currency, then formatting with that market's
     * rules. No market selected → platform default, no conversion.
     */
    private function fmtBaseToMarket($amount, ?int $marketId): string
    {
        $market = $this->marketFor($marketId);
        if (!$market) {
            return $this->currencyService->format($amount);
        }
        $base = \App\Support\PricePresenter::baseCode();
        $converted = app(FxRateService::class)->convertCurrency((float) $amount, $base, $market->currency_code);
        return formatOrderMoney($converted ?? (float) $amount, \App\Support\PricePresenter::payload($market));
    }

    /** Currency symbol for the selected market (platform default when none). */
    private function fmtSymbol(?int $marketId): string
    {
        $market = $this->marketFor($marketId);
        return $market
            ? \App\Support\PricePresenter::symbolFor($market->currency_code)
            : ($this->currencyService->getSymbol() ?? '$');
    }

    /** Format an amount already in a specific market's currency (per-row use). */
    private function fmtForMarket($amount, Market $market): string
    {
        return formatOrderMoney((float) $amount, \App\Support\PricePresenter::payload($market));
    }

    /**
     * Convert an amount expressed in an order's MARKET currency into the platform
     * BASE currency using that order's snapshot FX rate. Mirrors the wallet logic
     * on the Order model (see [[currency-units-wallet-vs-order]]): base = market
     * amount / currency_rate. Legacy orders with a missing/zero rate are treated
     * as already being in base currency (rate = 1).
     *
     * Needed wherever a seller's money is summed across multiple markets — each
     * order's amount is stored in its own market currency, so they must be
     * normalised to base before they can be added together.
     */
    private function marketToBase(float $marketAmount, $currencyRate): float
    {
        $rate = (float) $currencyRate;
        return $rate > 0 ? $marketAmount / $rate : $marketAmount;
    }

    /**
     * SQL fragment that normalises a market-currency money expression to base
     * currency using the joined `orders.currency_rate` snapshot. Requires the
     * query to join the `orders` table. Legacy 0/NULL rates fall back to 1.
     */
    private function baseConversionSql(string $amountExpr): string
    {
        return "($amountExpr) / COALESCE(NULLIF(orders.currency_rate, 0), 1)";
    }

    /**
     * Get revenue data for the specified number of days.
     */
    public function getRevenueData(int $days, ?int $sellerId = null, ?int $marketId = null, ?int $storeId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        if ($sellerId) {
            $query = OrderItem::with(['order', 'store'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('fulfillment_status', FulfillmentStatusEnum::FULFILLED());

            $query->whereHas('store', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            });

            if ($storeId) {
                $query->where('order_items.store_id', $storeId);
            }

            if ($marketId) {
                $query->whereHas('order', fn($q) => $q->where('market_id', $marketId));
            }
        } else {
            $query = Order::whereBetween('created_at', [$startDate, $endDate])
                ->where('fulfillment_status', FulfillmentStatusEnum::FULFILLED());

            if ($marketId) {
                $query->where('market_id', $marketId);
            }
        }

        $revenueByDay = $query->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            })
            ->map(function ($items) use ($sellerId, $marketId) {
                $totalRevenue = $items->sum(function ($item) use ($sellerId, $marketId) {
                    if ($sellerId) {
                        $net = $item->subtotal - $item->admin_commission_amount;
                        return $marketId ? $net : $this->marketToBase($net, $item->order?->currency_rate);
                    }
                    return $item->total_payable;
                });

                return [
                    'date' => $items->first()->created_at->format('Y-m-d'),
                    'revenue' => $totalRevenue,
                    'formatted_revenue' => $this->fmtMarket($totalRevenue, $marketId)
                ];
            })
            ->values()
            ->toArray();

        $result = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = false;

            foreach ($revenueByDay as $day) {
                if ($day['date'] === $dateStr) {
                    $result[] = $day;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $result[] = [
                    'date' => $dateStr,
                    'revenue' => 0,
                    'formatted_revenue' => $this->fmtMarket(0, $marketId)
                ];
            }

            $currentDate->addDay();
        }

        $totalRevenue = array_sum(array_column($result, 'revenue'));

        return [
            'daily' => $result,
            'total' => $totalRevenue,
            'formatted_total' => $this->fmtMarket($totalRevenue, $marketId)
        ];
    }

    /**
     * Get Gross Merchandise Value (GMV) for the period.
     *
     * GMV is measured at ORDER PLACEMENT — every order placed in the window counts,
     * regardless of whether it was later fulfilled or cancelled. This is the gross
     * demand figure (distinct from getRevenueData, which is net delivered revenue).
     *
     * Platform GMV sums orders.total_payable; seller GMV sums the seller's
     * order_items.subtotal (gross item value, before commission). Scoped to one
     * market when $marketId is given; formatted in that market's currency.
     */
    public function getGmvData(int $days, ?int $sellerId = null, ?int $marketId = null, ?int $storeId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        if ($sellerId) {
            $query = OrderItem::query()
                ->whereBetween('order_items.created_at', [$startDate, $endDate])
                ->whereHas('store', fn($q) => $q->where('seller_id', $sellerId));

            if ($storeId) {
                $query->where('order_items.store_id', $storeId);
            }

            if ($marketId) {
                $query->whereHas('order', fn($q) => $q->where('market_id', $marketId));
                $gmv = (float) $query->sum('order_items.subtotal');
            } else {
                $query->join('orders', 'order_items.order_id', '=', 'orders.id');
                $gmv = (float) $query->sum(DB::raw($this->baseConversionSql('order_items.subtotal')));
            }
        } else {
            $query = Order::whereBetween('created_at', [$startDate, $endDate]);

            if ($marketId) {
                $query->where('market_id', $marketId);
            }

            $gmv = (float) $query->sum('total_payable');
        }


        return [
            'total' => $gmv,
            'formatted_total' => $this->fmtMarket($gmv, $marketId),
            'days' => $days,
        ];
    }

    /**
     * Get store-wise order totals for the seller.
     */
    public function getStoreOrderTotals(int $sellerId, ?int $storeId = null): array
    {
        $stores = Store::where('seller_id', $sellerId)
            ->when($storeId, fn($q) => $q->where('id', $storeId))
            ->get();

        $result = [];
        $totalOrders = 0;

        foreach ($stores as $store) {
            $orderCount = SellerOrderItem::whereHas('orderItem', function ($q) use ($store) {
                $q->where('store_id', $store->id);
            })->count();

            $result[] = [
                'id' => $store->id,
                'name' => $store->name,
                'order_count' => $orderCount
            ];

            $totalOrders += $orderCount;
        }

        if ($totalOrders > 0) {
            foreach ($result as &$store) {
                $store['percentage'] = round(($store['order_count'] / $totalOrders) * 100);
            }
        } else {
            foreach ($result as &$store) {
                $store['percentage'] = 0;
            }
        }

        return [
            'stores' => $result,
            'total' => $totalOrders
        ];
    }

    /**
     * Get store-wise revenue data with date filtering for the seller.
     */
    public function getStoreRevenueData(int $sellerId, int $days = 30, ?int $storeId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $stores = Store::where('seller_id', $sellerId)
            ->when($storeId, fn($q) => $q->where('id', $storeId))
            ->get();

        $result = [];
        $totalRevenue = 0;

        foreach ($stores as $store) {
            $revenue = OrderItem::query()
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('order_items.store_id', $store->id)
                ->whereBetween('order_items.created_at', [$startDate, $endDate])
                ->where('order_items.fulfillment_status', FulfillmentStatusEnum::FULFILLED())
                ->sum(DB::raw($this->baseConversionSql('order_items.subtotal - order_items.admin_commission_amount')));

            $result[] = [
                'id' => $store->id,
                'name' => $store->name,
                'revenue' => $revenue,
                'formatted_revenue' => $this->currencyService->format($revenue)
            ];

            $totalRevenue += $revenue;
        }

        usort($result, function ($a, $b) {
            return $b['revenue'] <=> $a['revenue'];
        });

        return [
            'stores' => $result,
            'total' => $totalRevenue,
            'formatted_total' => $this->currencyService->format($totalRevenue),
            'days' => $days
        ];
    }

    /**
     * Get today's earning with comparison to yesterday.
     */
    public function getTodaysEarning(?int $sellerId = null, ?int $marketId = null, ?int $storeId = null): array
    {
        $today = Carbon::now()->format('Y-m-d');
        $yesterday = Carbon::now()->subDay()->format('Y-m-d');

        if ($sellerId) {
            $earningCalculation = $marketId
                ? 'SUM(order_items.subtotal - order_items.admin_commission_amount)'
                : 'SUM(' . $this->baseConversionSql('order_items.subtotal - order_items.admin_commission_amount') . ')';
        } else {
            $earningCalculation = 'SUM(order_items.admin_commission_amount)';
        }

        $query = OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->selectRaw("
            DATE(order_items.created_at) as date,
            {$earningCalculation} as earning
        ")
            ->whereIn(DB::raw('DATE(order_items.created_at)'), [$today, $yesterday])
            ->where('order_items.fulfillment_status', FulfillmentStatusEnum::FULFILLED());

        if ($sellerId) {
            $query->whereHas('store', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            });

            if ($storeId) {
                $query->where('order_items.store_id', $storeId);
            }
        }

        if ($marketId) {
            $query->where('orders.market_id', $marketId);
        }

        $earnings = $query->groupByRaw('DATE(order_items.created_at)')
            ->get()
            ->keyBy('date');

        $todaysEarning = $earnings->get($today)?->earning ?? 0;
        $yesterdaysEarning = $earnings->get($yesterday)?->earning ?? 0;

        $percentageChange = 0;
        if ($yesterdaysEarning > 0) {
            $percentageChange = (($todaysEarning - $yesterdaysEarning) / $yesterdaysEarning) * 100;
        } elseif ($todaysEarning > 0) {
            $percentageChange = 100;
        }

        return [
            'today' => $todaysEarning,
            'yesterday' => $yesterdaysEarning,
            'formatted_today' => $this->fmtMarket($todaysEarning, $marketId),
            'formatted_yesterday' => $this->fmtMarket($yesterdaysEarning, $marketId),
            'percentage_change' => round($percentageChange, 2),
            'is_increase' => $percentageChange >= 0
        ];
    }

    /**
     * Get daily purchase history for the last month.
     */
    public function getDailyPurchaseHistory(int $days = 30, ?int $sellerId = null, ?int $marketId = null, ?int $storeId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = OrderItem::selectRaw('DATE(created_at) as date, COUNT(*) as order_count')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($sellerId) {
            $query->whereHas('store', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            });

            if ($storeId) {
                $query->where('order_items.store_id', $storeId);
            }
        }

        if ($marketId) {
            $query->whereHas('order', fn($q) => $q->where('market_id', $marketId));
        }

        $ordersByDay = $query->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        $result = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');

            if (isset($ordersByDay[$dateStr])) {
                $result[] = [
                    'date' => $dateStr,
                    'order_count' => (int)$ordersByDay[$dateStr]['order_count'],
                ];
            } else {
                $result[] = [
                    'date' => $dateStr,
                    'order_count' => 0,
                ];
            }

            $currentDate->addDay();
        }

        $totalOrders = array_sum(array_column($result, 'order_count'));

        return [
            'daily' => $result,
            'total' => $totalOrders,
            'days' => count($result)
        ];
    }

    /**
     * Get recent seller feedback.
     */
    public function getRecentSellerFeedback(int $sellerId): array
    {
        $feedback = SellerFeedback::where('seller_id', $sellerId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'user_name' => $item->user->name ?? 'Anonymous',
                    'rating' => $item->rating,
                    'title' => $item->title,
                    'description' => $item->description,
                    'date' => $item->created_at->format('d M Y')
                ];
            })
            ->toArray();

        $stats = SellerFeedback::getSellerFeedbackStatistics($sellerId);

        return [
            'items' => $feedback,
            'total_reviews' => $stats->total_reviews ?? 0,
            'average_rating' => round($stats->average_rating ?? 0, 1)
        ];
    }

    /**
     * Get total sales and unsettled payments for the seller.
     */
    public function getSalesData(int $sellerId, ?int $storeId = null): array
    {
        $salesQuery = OrderItem::whereHas('store', function ($query) use ($sellerId) {
            $query->where('seller_id', $sellerId);
        })
            ->when($storeId, function ($query) use ($storeId) {
                $query->where('store_id', $storeId);
            });

        $totalSales = (clone $salesQuery)
            ->where('fulfillment_status', FulfillmentStatusEnum::FULFILLED())
            ->count();

        $unsettledPayments = $this->sellerStatements($sellerId, $storeId)
            ->where('settlement_status', SellerSettlementStatusEnum::PENDING())
            ->count();

        return [
            'total_sales' => $totalSales,
            'unsettled_payments' => $unsettledPayments
        ];
    }

    /**
     * Get the seller action center: the things waiting on the seller right now,
     * ordered by urgency. Mirrors the admin alerts pattern (severity + count +
     * action_route) so the blade can render them the same way. Each item only
     * appears when its count > 0.
     */
    public function getSellerActionItems(int $sellerId, ?int $storeId = null): array
    {
        $items = [];

        $toFulfill = OrderItem::where('status', OrderItemStatusEnum::ACTIVE())
            ->where('fulfillment_status', FulfillmentStatusEnum::UNFULFILLED())
            ->whereHas('store', fn($q) => $q->where('seller_id', $sellerId))
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->whereHas('order', fn($q) => $q->where('status', OrderStatusEnum::OPEN()))
            ->distinct('order_id')
            ->count('order_id');
        if ($toFulfill > 0) {
            $items[] = [
                'severity' => 'critical',
                'message' => __('labels.action_orders_to_fulfill', ['count' => $toFulfill]),
                'count' => $toFulfill,
                'type' => 'orders_to_fulfill',
                'action_route' => 'seller.orders.index',
                'action_params' => ['status' => (string) FulfillmentStatusEnum::UNFULFILLED()],
            ];
        }

        $returnsToApprove = OrderItemReturn::where('seller_id', $sellerId)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->where('return_status', OrderItemReturnStatusEnum::REQUESTED())
            ->count();
        if ($returnsToApprove > 0) {
            $items[] = [
                'severity' => 'warning',
                'message' => __('labels.action_returns_to_approve', ['count' => $returnsToApprove]),
                'count' => $returnsToApprove,
                'type' => 'returns_to_approve',
                'action_route' => 'seller.returns.index',
                'action_params' => ['return_status' => (string) OrderItemReturnStatusEnum::REQUESTED()],
            ];
        }

        $lowStock = StoreLocationInventory::query()
            ->where('tracked', true)
            ->where('is_fulfillment_location', true)
            ->where('low_stock_threshold', '>', 0)
            ->where('stock', '>', 0)
            ->whereRaw('(stock - reserved) < low_stock_threshold')
            ->whereHas('storeLocation.store', fn($q) => $q->where('seller_id', $sellerId)
                ->when($storeId, fn($sq) => $sq->where('id', $storeId)))
            ->distinct('product_variant_id')
            ->count('product_variant_id');
        if ($lowStock > 0) {
            $items[] = [
                'severity' => 'warning',
                'message' => __('labels.action_low_stock', ['count' => $lowStock]),
                'count' => $lowStock,
                'type' => 'low_stock',
                'action_route' => 'seller.products.index',
                'action_params' => ['product_filter' => (string) ProductFilterEnum::LOW_STOCK()],
            ];
        }

        $rejectedProducts = Product::where('seller_id', $sellerId)
            ->where('verification_status', ProductVarificationStatusEnum::REJECTED())
            ->when($storeId, fn ($q) => $q->whereHas(
                'variants.storeProductVariants',
                fn ($sq) => $sq->where('store_id', $storeId)
            ))
            ->count();
        if ($rejectedProducts > 0) {
            $items[] = [
                'severity' => 'warning',
                'message' => __('labels.action_products_rejected', ['count' => $rejectedProducts]),
                'count' => $rejectedProducts,
                'type' => 'products_rejected',
                'action_route' => 'seller.products.index',
                'action_params' => ['verification_status' => (string) ProductVarificationStatusEnum::REJECTED()],
            ];
        }

        $pendingProducts = Product::where('seller_id', $sellerId)
            ->where('verification_status', ProductVarificationStatusEnum::PENDING())
            ->when($storeId, fn ($q) => $q->whereHas(
                'variants.storeProductVariants',
                fn ($sq) => $sq->where('store_id', $storeId)
            ))
            ->count();
        if ($pendingProducts > 0) {
            $items[] = [
                'severity' => 'info',
                'message' => __('labels.action_products_pending', ['count' => $pendingProducts]),
                'count' => $pendingProducts,
                'type' => 'products_pending',
                'action_route' => 'seller.products.index',
                'action_params' => ['verification_status' => (string) ProductVarificationStatusEnum::PENDING()],
            ];
        }

        $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($items, fn($a, $b) => $severityOrder[$a['severity']] <=> $severityOrder[$b['severity']]);

        return ['items' => $items, 'total' => count($items)];
    }

    /**
     * Get product statistics for the seller.
     */
    public function getProductStats(int $sellerId, ?int $storeId = null): array
    {
        $productQuery = Product::where('seller_id', $sellerId)
            ->when($storeId, function ($query) use ($storeId) {
                $query->whereHas('variants.storeProductVariants', function ($storeProductVariantQuery) use ($storeId) {
                    $storeProductVariantQuery->where('store_id', $storeId);
                });
            });

        $totalProducts = (clone $productQuery)->count();

        $recentProducts = (clone $productQuery)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        return [
            'total_products' => $totalProducts,
            'recent_products' => $recentProducts
        ];
    }

    /**
     * Get active customers data for the seller.
     */
    public function getActiveCustomersData(int $sellerId, ?int $storeId = null): array
    {
        $currentPeriodStart = Carbon::now()->subDays(7)->startOfDay();
        $currentPeriodEnd = Carbon::now()->endOfDay();

        $previousPeriodStart = Carbon::now()->subDays(14)->startOfDay();
        $previousPeriodEnd = Carbon::now()->subDays(7)->startOfDay()->subSecond();

        $currentPeriodCustomers = OrderItem::with(['order'])
            ->whereHas('store', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            })
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->whereBetween('created_at', [$currentPeriodStart, $currentPeriodEnd])
            ->get()
            ->pluck('order.user_id')
            ->unique()
            ->count();

        $previousPeriodCustomers = OrderItem::with(['order'])
            ->whereHas('store', function ($q) use ($sellerId) {
                $q->where('seller_id', $sellerId);
            })
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])
            ->get()
            ->pluck('order.user_id')
            ->unique()
            ->count();

        $percentageChange = 0;
        if ($previousPeriodCustomers > 0) {
            $percentageChange = (($currentPeriodCustomers - $previousPeriodCustomers) / $previousPeriodCustomers) * 100;
        } elseif ($currentPeriodCustomers > 0) {
            $percentageChange = 100;
        }

        return [
            'count' => $currentPeriodCustomers,
            'previous_count' => $previousPeriodCustomers,
            'percentage_change' => round($percentageChange, 2),
            'is_increase' => $percentageChange >= 0
        ];
    }

    /**
     * Get conversion rate data for the seller.
     * Conversion rate is the percentage of delivered orders out of total orders.
     */
    public function getConversionRateData(int $sellerId, int $days = 7, ?int $storeId = null): array
    {
        $scopeStore = fn($query) => $query->when(
            $storeId,
            fn($q) => $q->whereHas('orderItem', fn($oi) => $oi->where('store_id', $storeId))
        );

        $currentPeriodStart = Carbon::now()->subDays($days)->startOfDay();
        $currentPeriodEnd = Carbon::now()->endOfDay();

        $previousPeriodStart = Carbon::now()->subDays($days * 2)->startOfDay();
        $previousPeriodEnd = $currentPeriodStart->copy()->subSecond();

        $currentPeriodTotalOrders = $scopeStore(SellerOrderItem::whereHas('sellerOrder', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        }))
            ->whereBetween('created_at', [$currentPeriodStart, $currentPeriodEnd])
            ->count();

        $currentPeriodDeliveredOrders = $scopeStore(SellerOrderItem::whereHas('sellerOrder', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        }))
            ->whereHas('orderItem', function ($q) {
                $q->where('fulfillment_status', FulfillmentStatusEnum::FULFILLED());
            })
            ->whereBetween('created_at', [$currentPeriodStart, $currentPeriodEnd])
            ->count();

        $previousPeriodTotalOrders = $scopeStore(SellerOrderItem::whereHas('sellerOrder', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        }))
            ->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])
            ->count();

        $previousPeriodDeliveredOrders = $scopeStore(SellerOrderItem::whereHas('sellerOrder', function ($q) use ($sellerId) {
            $q->where('seller_id', $sellerId);
        }))
            ->whereHas('orderItem', function ($q) {
                $q->where('fulfillment_status', FulfillmentStatusEnum::FULFILLED());
            })
            ->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])
            ->count();

        $currentPeriodRate = $currentPeriodTotalOrders > 0
            ? round(($currentPeriodDeliveredOrders / $currentPeriodTotalOrders) * 100, 2)
            : 0;

        $previousPeriodRate = $previousPeriodTotalOrders > 0
            ? round(($previousPeriodDeliveredOrders / $previousPeriodTotalOrders) * 100, 2)
            : 0;

        $percentageChange = 0;
        if ($previousPeriodRate > 0) {
            $percentageChange = (($currentPeriodRate - $previousPeriodRate) / $previousPeriodRate) * 100;
        } elseif ($currentPeriodRate > 0) {
            $percentageChange = 100;
        }

        return [
            'rate' => $currentPeriodRate,
            'previous_rate' => $previousPeriodRate,
            'delivered_orders' => $currentPeriodDeliveredOrders,
            'total_orders' => $currentPeriodTotalOrders,
            'percentage_change' => round($percentageChange, 2),
            'is_increase' => $percentageChange >= 0
        ];
    }

    /**
     * Base query for a seller's DELIVERED order items in a window, store-scoped
     * via order_items.store_id and joined to orders (so amounts can be
     * normalised to base currency — a seller's stores can span markets).
     */
    private function sellerDeliveredItems(int $sellerId, ?int $storeId, $startDate, $endDate)
    {
        return OrderItem::query()
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereExists(fn($q) => $q->select(DB::raw(1))->from('stores')
                ->whereColumn('stores.id', 'order_items.store_id')
                ->where('stores.seller_id', $sellerId))
            ->when($storeId, fn($q) => $q->where('order_items.store_id', $storeId))
            ->where('order_items.fulfillment_status', FulfillmentStatusEnum::FULFILLED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate]);
    }

    /**
     * Seller sales summary (store-scoped): gross sales, units, AOV, order count
     * and a daily sales trend. Amounts normalised to base currency, before
     * commission — the net figure a seller is actually owed comes from the
     * settlement ledger (getSellerEarningsSummary), not from here.
     */
    public function getSellerSalesSummary(int $sellerId, int $days = 30, ?int $storeId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $revenue = (float) $this->sellerDeliveredItems($sellerId, $storeId, $startDate, $endDate)
            ->sum(DB::raw($this->baseConversionSql('order_items.subtotal')));
        $units = (int) $this->sellerDeliveredItems($sellerId, $storeId, $startDate, $endDate)
            ->sum('order_items.quantity');
        $orders = (int) $this->sellerDeliveredItems($sellerId, $storeId, $startDate, $endDate)
            ->distinct('order_items.order_id')->count('order_items.order_id');
        $aov = $orders > 0 ? $revenue / $orders : 0;

        $daily = $this->sellerDeliveredItems($sellerId, $storeId, $startDate, $endDate)
            ->selectRaw('DATE(order_items.created_at) as d, SUM(' . $this->baseConversionSql('order_items.subtotal') . ') as rev')
            ->groupBy('d')->orderBy('d')->get()
            ->map(fn($r) => ['date' => $r->d, 'revenue' => round((float) $r->rev, 2)])->toArray();

        return [
            'revenue' => $this->currencyService->format($revenue),
            'revenue_raw' => round($revenue, 2),
            'units' => $units,
            'orders' => $orders,
            'aov' => $this->currencyService->format($aov),
            'aov_raw' => round($aov, 2),
            'daily' => $daily,
            'days' => $days,
            'currency_symbol' => $this->currencyService->getSymbol(),
        ];
    }

    /**
     * Top-selling products for the seller (store-scoped), ranked by units sold.
     */
    public function getSellerTopProducts(int $sellerId, int $days = 30, int $limit = 5, ?int $storeId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $rows = $this->sellerDeliveredItems($sellerId, $storeId, $startDate, $endDate)
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->groupBy('order_items.product_id', 'products.title')
            ->orderByDesc('units')
            ->limit($limit)
            ->get([
                'order_items.product_id',
                'products.title as title',
                DB::raw('SUM(order_items.quantity) as units'),
                DB::raw('SUM(' . $this->baseConversionSql('order_items.subtotal') . ') as revenue'),
            ]);

        return [
            'products' => $rows->map(fn($r) => [
                'id' => $r->product_id,
                'title' => $r->title ?? ('#' . $r->product_id),
                'units' => (int) $r->units,
                'revenue' => $this->currencyService->format($r->revenue),
                'revenue_raw' => round((float) $r->revenue, 2),
            ])->toArray(),
        ];
    }

    /**
     * The seller's approved products that sold NOTHING in the window
     * (store-scoped sales check). Dead stock to discount or drop.
     */
    public function getSellerDeadProducts(int $sellerId, int $days = 30, int $limit = 5, ?int $storeId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $soldIds = $this->sellerDeliveredItems($sellerId, $storeId, $startDate, $endDate)
            ->distinct()->pluck('order_items.product_id')->filter()->all();

        $deadQuery = Product::where('seller_id', $sellerId)
            ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
            ->whereHas('variants.locationInventories.storeLocation.store', fn($q) => $q->where('seller_id', $sellerId)
                ->when($storeId, fn($sq) => $sq->where('id', $storeId)))
            ->when(!empty($soldIds), fn($q) => $q->whereNotIn('id', $soldIds));

        $count = (clone $deadQuery)->count();
        $products = (clone $deadQuery)->orderBy('created_at')->limit($limit)->get(['id', 'title']);

        return [
            'count' => $count,
            'products' => $products->map(fn($p) => ['id' => $p->id, 'title' => $p->title])->toArray(),
        ];
    }

    /**
     * Seller sales split by market (store-scoped). Each market has one currency,
     * so per-market revenue is formatted in that market's own currency.
     */
    public function getSellerSalesByMarket(int $sellerId, int $days = 30, ?int $storeId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $rows = $this->sellerDeliveredItems($sellerId, $storeId, $startDate, $endDate)
            ->groupBy('orders.market_id')
            ->orderByDesc('revenue')
            ->get([
                'orders.market_id',
                DB::raw('SUM(order_items.subtotal) as revenue'),
                DB::raw('COUNT(DISTINCT order_items.order_id) as orders'),
            ]);

        $markets = Market::whereIn('id', $rows->pluck('market_id'))->get()->keyBy('id');

        return [
            'markets' => $rows->map(function ($r) use ($markets) {
                $market = $markets->get($r->market_id);
                return [
                    'market_id' => $r->market_id,
                    'name' => $market?->name ?? ('#' . $r->market_id),
                    'orders' => (int) $r->orders,
                    'revenue' => $market ? $this->fmtForMarket($r->revenue, $market) : $this->currencyService->format($r->revenue),
                    'revenue_raw' => round((float) $r->revenue, 2),
                ];
            })->toArray(),
        ];
    }

    /**
     * Seller return analytics (store-scoped): return rate, seller vs customer
     * fault split, and the most-returned products.
     */
    public function getSellerReturnAnalytics(int $sellerId, int $days = 30, ?int $storeId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $base = fn() => OrderItemReturn::where('seller_id', $sellerId)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalReturns = $base()->count();
        $sellerFault = (clone $base())->where('reason_type', ReturnReasonTypeEnum::SELLER_FAULT())->count();
        $customerFault = (clone $base())->where('reason_type', ReturnReasonTypeEnum::CUSTOMER_FAULT())->count();
        $faultDenom = $sellerFault + $customerFault;

        $deliveredItems = $this->sellerDeliveredItems($sellerId, $storeId, $startDate, $endDate)->count();
        $returnRate = $deliveredItems > 0 ? round(($totalReturns / $deliveredItems) * 100, 1) : 0;

        $topReturned = OrderItemReturn::query()
            ->join('order_items', 'order_items.id', '=', 'order_item_returns.order_item_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('order_item_returns.seller_id', $sellerId)
            ->when($storeId, fn($q) => $q->where('order_item_returns.store_id', $storeId))
            ->whereBetween('order_item_returns.created_at', [$startDate, $endDate])
            ->groupBy('products.id', 'products.title')
            ->orderByDesc('cnt')
            ->limit(5)
            ->get(['products.title as title', DB::raw('COUNT(*) as cnt')]);

        return [
            'total_returns' => $totalReturns,
            'delivered_items' => $deliveredItems,
            'return_rate' => $returnRate,
            'seller_fault' => $sellerFault,
            'customer_fault' => $customerFault,
            'seller_fault_share' => $faultDenom > 0 ? round(($sellerFault / $faultDenom) * 100, 1) : 0,
            'customer_fault_share' => $faultDenom > 0 ? round(($customerFault / $faultDenom) * 100, 1) : 0,
            'top_returned' => $topReturned->map(fn($r) => [
                'title' => $r->title ?? '—',
                'count' => (int) $r->cnt,
            ])->toArray(),
        ];
    }

    /**
     * Seller customer insights (store-scoped): repeat-customer rate and the
     * new-vs-returning buyer split for this store's customers.
     */
    public function getSellerCustomerInsights(int $sellerId, int $days = 30, ?int $storeId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $counts = $this->sellerDeliveredItems($sellerId, $storeId, $startDate, $endDate)
            ->groupBy('orders.user_id')
            ->get(['orders.user_id', DB::raw('COUNT(DISTINCT order_items.order_id) as oc')]);

        $totalCustomers = $counts->count();
        $repeatCustomers = $counts->where('oc', '>', 1)->count();
        $repeatRate = $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0;

        $currentIds = $counts->pluck('user_id');
        $previousIds = $this->sellerDeliveredItems($sellerId, $storeId, Carbon::createFromTimestamp(0), $startDate->copy()->subSecond())
            ->distinct()->pluck('orders.user_id')->unique();

        $newCustomers = $currentIds->diff($previousIds)->count();
        $returningCustomers = $currentIds->intersect($previousIds)->count();

        return [
            'total_customers' => $totalCustomers,
            'repeat_customers' => $repeatCustomers,
            'repeat_rate' => $repeatRate,
            'new_customers' => $newCustomers,
            'returning_customers' => $returningCustomers,
        ];
    }

    /**
     * A seller's settlement ledger, store-scoped. Per-item entries carry
     * `order_item_id`; the per-seller-order entries written when an order is
     * archived carry none, so those are matched back to the store through
     * their seller order's items.
     */
    private function sellerStatements(int $sellerId, ?int $storeId = null)
    {
        return SellerStatement::where('seller_id', $sellerId)
            ->when($storeId, fn($q) => $q->where(function ($w) use ($storeId) {
                $w->whereIn('order_item_id', function ($sub) use ($storeId) {
                    $sub->select('id')->from('order_items')->where('store_id', $storeId);
                })->orWhere(function ($s) use ($storeId) {
                    $s->where('reference_type', OrderSettlementService::REFERENCE_TYPE)
                        ->whereIn('reference_id', function ($sub) use ($storeId) {
                            $sub->select('seller_order_items.seller_order_id')
                                ->from('seller_order_items')
                                ->join('order_items', 'order_items.id', '=', 'seller_order_items.order_item_id')
                                ->where('order_items.store_id', $storeId);
                        });
                });
            }));
    }

    private function statementNet($query): float
    {
        return (float) ($query->selectRaw("SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE -amount END) as net")->value('net') ?? 0);
    }

    /**
     * A seller's earnings for a date window, taken from the settlement ledger and
     * bucketed by `posted_at` — the moment the order was archived and the entry
     * written. Net of commission, base currency, pending and settled together.
     * This is the same money the panel's earnings card reports.
     */
    public function getSellerSettlementEarnings(int $sellerId, $start, $end, ?int $storeId = null): float
    {
        return round($this->statementNet(
            $this->sellerStatements($sellerId, $storeId)->whereBetween('posted_at', [$start, $end])
        ), 2);
    }

    /**
     * Seller earnings summary. Statement amounts are net (credit − debit),
     * store-scoped through the linked order item where possible. Base currency.
     */
    public function getSellerEarningsSummary(int $sellerId, ?int $storeId = null): array
    {
        $base = fn() => $this->sellerStatements($sellerId, $storeId);

        $net = fn($q) => $this->statementNet($q);

        $unsettled = $net((clone $base())->where('settlement_status', 'pending'));
        $settled = $net((clone $base())->where('settlement_status', 'settled'));

        return [
            'unsettled' => $this->currencyService->format($unsettled),
            'unsettled_raw' => round($unsettled, 2),
            'settled' => $this->currencyService->format($settled),
            'settled_raw' => round($settled, 2),
        ];
    }

    /**
     * Get admin commission charts data.
     */
    public function getAdminCommissionChartsData(int $days, ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $commissionByDay = OrderItem::whereBetween('created_at', [$startDate, $endDate])
            ->where('fulfillment_status', FulfillmentStatusEnum::FULFILLED())
            ->when($marketId, fn($q) => $q->whereHas('order', fn($oq) => $oq->where('market_id', $marketId)))
            ->get()
            ->groupBy(function ($item) {
                return $item->created_at->format('Y-m-d');
            })
            ->map(function ($items) use ($marketId) {
                $totalCommission = $items->sum(function ($item) {
                    return $item->admin_commission_amount;
                });

                return [
                    'date' => $items->first()->created_at->format('Y-m-d'),
                    'revenue' => $totalCommission,
                    'formatted_revenue' => $this->fmtMarket($totalCommission, $marketId)
                ];
            })
            ->values()
            ->toArray();

        $result = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = false;

            foreach ($commissionByDay as $day) {
                if ($day['date'] === $dateStr) {
                    $result[] = $day;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $result[] = [
                    'date' => $dateStr,
                    'revenue' => 0,
                    'formatted_revenue' => $this->fmtMarket(0, $marketId)
                ];
            }

            $currentDate->addDay();
        }

        $totalCommission = array_sum(array_column($result, 'revenue'));

        return [
            'daily' => $result,
            'total' => $totalCommission,
            'formatted_total' => $this->fmtMarket($totalCommission, $marketId)
        ];
    }

    public function getAdminInsightsData(?int $marketId = null): array
    {
        $today = Carbon::now()->format('Y-m-d');

        if ($marketId) {
            $storeIds = DB::table('store_market')->where('market_id', $marketId)->pluck('store_id');
            $sellerIds = Store::whereIn('id', $storeIds)
                ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
                ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
                ->pluck('seller_id')
                ->unique();

            $totalSellers = Seller::whereIn('id', $sellerIds)
                ->where('verification_status', SellerVerificationStatusEnum::Approved())
                ->where('visibility_status', SellerVisibilityStatusEnum::Visible())
                ->count();

            $totalStores = Store::whereIn('id', $storeIds)
                ->where('verification_status', StoreVerificationStatusEnum::APPROVED())
                ->where('visibility_status', StoreVisibilityStatusEnum::VISIBLE())
                ->count();

            $ordersData = Order::where('market_id', $marketId)
                ->selectRaw('
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN fulfillment_status = ? THEN 1 ELSE 0 END) as total_delivered_orders
                ', [FulfillmentStatusEnum::FULFILLED()])
                ->first();

            $orderItemsData = OrderItem::whereHas('order', fn($q) => $q->where('market_id', $marketId))
                ->selectRaw('
                    COUNT(CASE WHEN fulfillment_status = ? THEN 1 END) as total_product_sales,
                    SUM(CASE WHEN DATE(created_at) = ? AND fulfillment_status = ? THEN admin_commission_amount ELSE 0 END) as todays_commission
                ', [
                    FulfillmentStatusEnum::FULFILLED(),
                    $today,
                    FulfillmentStatusEnum::FULFILLED()
                ])->first();

            $totalUsers = DB::table('user_market')->where('market_id', $marketId)->distinct('user_id')->count('user_id');
            $totalProducts = Product::whereIn('seller_id', $sellerIds)->count();
        } else {
            $totalSellers = Seller::where('verification_status', SellerVerificationStatusEnum::Approved())
                ->where('visibility_status', SellerVisibilityStatusEnum::Visible())->count();

            $totalStores = Store::where([
                ['verification_status', StoreVerificationStatusEnum::APPROVED()],
                ['visibility_status', StoreVisibilityStatusEnum::VISIBLE()]
            ])->count();

            $ordersData = Order::selectRaw('
                COUNT(*) as total_orders,
                SUM(CASE WHEN fulfillment_status = ? THEN 1 ELSE 0 END) as total_delivered_orders
            ', [FulfillmentStatusEnum::FULFILLED()])
                ->first();

            $orderItemsData = OrderItem::selectRaw('
                COUNT(CASE WHEN fulfillment_status = ? THEN 1 END) as total_product_sales,
                SUM(CASE WHEN DATE(created_at) = ? AND fulfillment_status = ? THEN admin_commission_amount ELSE 0 END) as todays_commission
            ', [
                FulfillmentStatusEnum::FULFILLED(),
                $today,
                FulfillmentStatusEnum::FULFILLED()
            ])->first();

            $totalUsers = User::count();
            $totalProducts = Product::count();
        }

        return [
            'total_sellers' => $totalSellers,
            'total_stores' => $totalStores,
            'total_orders' => $ordersData->total_orders ?? 0,
            'total_delivered_orders' => $ordersData->total_delivered_orders ?? 0,
            'total_users' => $totalUsers,
            'total_products' => $totalProducts,
            'total_product_sales' => $orderItemsData->total_product_sales ?? 0,
            'todays_commission' => $orderItemsData->todays_commission ?? 0,
            'formatted_todays_commission' => $this->fmtMarket($orderItemsData->todays_commission ?? 0, $marketId),
        ];
    }

    public function getAdminConversionRateData($days = 7, ?int $marketId = null): array
    {
        $currentPeriodStart = Carbon::now()->subDays($days)->startOfDay();
        $currentPeriodEnd = Carbon::now()->endOfDay();

        $previousPeriodStart = Carbon::now()->subDays($days * 2)->startOfDay();
        $previousPeriodEnd = $currentPeriodStart->copy()->subSecond();

        $periodCounts = OrderItem::query()
            ->whereBetween('created_at', [$previousPeriodStart, $currentPeriodEnd])
            ->when($marketId, fn($q) => $q->whereHas('order', fn($oq) => $oq->where('market_id', $marketId)))
            ->selectRaw(
                'SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as current_total,
                SUM(CASE WHEN created_at BETWEEN ? AND ? AND fulfillment_status = ? THEN 1 ELSE 0 END) as current_delivered,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as previous_total,
                SUM(CASE WHEN created_at BETWEEN ? AND ? AND fulfillment_status = ? THEN 1 ELSE 0 END) as previous_delivered',
                [
                    $currentPeriodStart,
                    $currentPeriodEnd,
                    $currentPeriodStart,
                    $currentPeriodEnd,
                    FulfillmentStatusEnum::FULFILLED(),
                    $previousPeriodStart,
                    $previousPeriodEnd,
                    $previousPeriodStart,
                    $previousPeriodEnd,
                    FulfillmentStatusEnum::FULFILLED(),
                ],
            )
            ->first();

        $currentPeriodTotalOrders = (int) ($periodCounts->current_total ?? 0);
        $currentPeriodDeliveredOrders = (int) ($periodCounts->current_delivered ?? 0);
        $previousPeriodTotalOrders = (int) ($periodCounts->previous_total ?? 0);
        $previousPeriodDeliveredOrders = (int) ($periodCounts->previous_delivered ?? 0);

        $currentPeriodRate = $currentPeriodTotalOrders > 0
            ? round(($currentPeriodDeliveredOrders / $currentPeriodTotalOrders) * 100, 2)
            : 0;

        $previousPeriodRate = $previousPeriodTotalOrders > 0
            ? round(($previousPeriodDeliveredOrders / $previousPeriodTotalOrders) * 100, 2)
            : 0;

        $percentageChange = 0;
        if ($previousPeriodRate > 0) {
            $percentageChange = (($currentPeriodRate - $previousPeriodRate) / $previousPeriodRate) * 100;
        } elseif ($currentPeriodRate > 0) {
            $percentageChange = 100;
        }

        return [
            'rate' => $currentPeriodRate,
            'previous_rate' => $previousPeriodRate,
            'delivered_orders' => $currentPeriodDeliveredOrders,
            'total_orders' => $currentPeriodTotalOrders,
            'percentage_change' => round($percentageChange, 2),
            'is_increase' => $percentageChange >= 0
        ];
    }

    /**
     * Get category product weightage data for pie chart.
     * Only includes categories that have products.
     */
    public function getCategoryProductWeightage(): array
    {
        $categories = Category::withCount('products')
            ->having('products_count', '>', 0)
            ->get();

        $totalProducts = $categories->sum('products_count');

        $series = [];
        $labels = [];

        foreach ($categories as $category) {
            $series[] = $category->products_count;
            $labels[] = $category->title;
        }

        return [
            'series' => $series,
            'labels' => $labels,
            'total' => $totalProducts
        ];
    }

    /**
     * Get new user registrations data for the specified number of days.
     */
    public function getNewUserRegistrationsData(int $days = 7, ?int $marketId = null): array
    {
        $currentPeriodStart = Carbon::now()->subDays($days)->startOfDay();
        $currentPeriodEnd = Carbon::now()->endOfDay();

        $previousPeriodStart = Carbon::now()->subDays($days * 2)->startOfDay();
        $previousPeriodEnd = $currentPeriodStart->copy()->subSecond();

        $userQuery = $marketId
            ? User::whereHas('markets', fn($q) => $q->where('markets.id', $marketId))
            : new User;

        $currentPeriodRegistrations = (clone $userQuery)->whereBetween('created_at', [$currentPeriodStart, $currentPeriodEnd])
            ->count();

        $previousPeriodRegistrations = (clone $userQuery)->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])
            ->count();

        $percentageChange = 0;
        if ($previousPeriodRegistrations > 0) {
            $percentageChange = (($currentPeriodRegistrations - $previousPeriodRegistrations) / $previousPeriodRegistrations) * 100;
        } elseif ($currentPeriodRegistrations > 0) {
            $percentageChange = 100;
        }

        $registrationsByDay = (clone $userQuery)->whereBetween('created_at', [$currentPeriodStart, $currentPeriodEnd])
            ->get()
            ->groupBy(function ($user) {
                return $user->created_at->format('Y-m-d');
            })
            ->map(function ($users) {
                return [
                    'date' => $users->first()->created_at->format('Y-m-d'),
                    'count' => $users->count()
                ];
            })
            ->values()
            ->toArray();

        $result = [];
        $currentDate = clone $currentPeriodStart;

        while ($currentDate <= $currentPeriodEnd) {
            $dateStr = $currentDate->format('Y-m-d');
            $found = false;

            foreach ($registrationsByDay as $day) {
                if ($day['date'] === $dateStr) {
                    $result[] = $day;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $result[] = [
                    'date' => $dateStr,
                    'count' => 0
                ];
            }

            $currentDate->addDay();
        }

        return [
            'count' => $currentPeriodRegistrations,
            'previous_count' => $previousPeriodRegistrations,
            'percentage_change' => round($percentageChange, 2),
            'is_increase' => $percentageChange >= 0,
            'daily' => $result
        ];
    }

    /**
     * Get top sellers based on revenue for the specified number of days.
     */
    public function getTopSellers(int $days = 7, int $limit = 10, string $sortBy = 'revenue', ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $revenueExpr = $marketId
            ? 'SUM(seller_order_items.price)'
            : 'SUM(' . $this->baseConversionSql('seller_order_items.price') . ')';

        $query = Seller::select('sellers.*')->with('user')
            ->selectRaw($revenueExpr . ' as total_revenue')
            ->selectRaw('COUNT(seller_order_items.id) as total_orders')
            ->join('seller_orders', 'sellers.id', '=', 'seller_orders.seller_id')
            ->join('seller_order_items', 'seller_orders.id', '=', 'seller_order_items.seller_order_id')
            ->join('order_items', 'seller_order_items.order_item_id', '=', 'order_items.id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.fulfillment_status', FulfillmentStatusEnum::FULFILLED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->where('sellers.verification_status', SellerVerificationStatusEnum::Approved())
            ->where('sellers.visibility_status', SellerVisibilityStatusEnum::Visible());

        if ($marketId) {
            $query->where('orders.market_id', $marketId);
        }

        $orderColumn = match ($sortBy) {
            'orders' => 'total_orders',
            default => 'total_revenue',
        };

        $topSellers = $query->groupBy('sellers.id')
            ->orderBy($orderColumn, 'desc')
            ->limit($limit)
            ->get();

        return $topSellers->map(function ($seller) use ($marketId) {
            return [
                'id' => $seller->id,
                'name' => $seller->user->name ?? 'N/A',
                'email' => $seller->user->email ?? '',
                'total_revenue' => $marketId ? $this->fmtMarket($seller->total_revenue, $marketId) : $this->fmtBaseToMarket($seller->total_revenue, $marketId),
                'total_revenue_raw' => $seller->total_revenue,
                'total_orders' => $seller->total_orders,
                'avatar' => $seller->user?->getFirstMediaUrl(SpatieMediaCollectionName::PROFILE_IMAGE()) ?: null,
            ];
        })->toArray();
    }

    /**
     * Get top selling products for the specified number of days.
     */
    public function getTopSellingProducts(int $days = 7, int $limit = 10, string $sortBy = 'quantity', ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = Product::select('products.*')->with('category')
            ->selectRaw('SUM(order_items.quantity) as total_quantity')
            ->selectRaw('SUM(order_items.subtotal) as total_revenue')
            ->selectRaw('COUNT(order_items.id) as total_orders')
            ->join('order_items', 'products.id', '=', 'order_items.product_id')
            ->where('order_items.fulfillment_status', FulfillmentStatusEnum::FULFILLED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->where('products.status', ProductStatusEnum::ACTIVE());

        if ($marketId) {
            $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.market_id', $marketId);
        }

        $orderColumn = match ($sortBy) {
            'revenue' => 'total_revenue',
            'orders' => 'total_orders',
            default => 'total_quantity',
        };

        $topProducts = $query->groupBy('products.id')
            ->orderBy($orderColumn, 'desc')
            ->limit($limit)
            ->get();

        return $topProducts->map(function ($product) use ($marketId) {
            return [
                'id' => $product->id,
                'name' => $product->title,
                'category' => $product->category->title,
                'slug' => $product->slug,
                'sku' => $product->sku,
                'total_quantity' => $product->total_quantity,
                'total_revenue' => $this->fmtMarket($product->total_revenue, $marketId),
                'total_revenue_raw' => $product->total_revenue,
                'total_orders' => $product->total_orders,
                'image' => $product?->getFirstMediaUrl(SpatieMediaCollectionName::PRODUCT_MAIN_IMAGE()) ?: null,
            ];
        })->toArray();
    }

    /**
     * Get categories with filters and sorting options.
     */
    public function getCategoriesWithFilters(string $sortBy = 'name', string $filterBy = 'all'): array
    {
        $query = Category::withCount(['products' => function ($query) {
            $query->where('status', ProductStatusEnum::ACTIVE());
        }]);

        switch ($filterBy) {
            case 'top_selling':
                $query->whereHas('products.orderItems', function ($query) {
                    $query->where('fulfillment_status', FulfillmentStatusEnum::FULFILLED());
                })
                    ->withCount(['products as total_sold' => function ($query) {
                        $query->join('order_items', 'products.id', '=', 'order_items.product_id')
                            ->where('order_items.fulfillment_status', FulfillmentStatusEnum::FULFILLED())
                            ->selectRaw('SUM(order_items.quantity)');
                    }]);
                break;
            case 'no_products':
                $query->having('products_count', '=', 0);
                break;
            default:
                break;
        }

        switch ($sortBy) {
            case 'products_count':
                $query->orderBy('products_count', 'desc');
                break;
            case 'total_sold':
                if ($filterBy === 'top_selling') {
                    $query->orderBy('total_sold', 'desc');
                }
                break;
            case 'name':
            default:
                $query->orderBy('title', 'asc');
                break;
        }

        $categories = $query->limit(12)->get();

        return $categories->map(function ($category) use ($filterBy) {
            $data = [
                'id' => $category->id,
                'title' => $category->title,
                'products_count' => $category->products_count,
                'image' => $category?->getFirstMediaUrl('image') ?: null,
            ];

            if ($filterBy === 'top_selling') {
                $data['total_sold'] = $category->total_sold ?? 0;
            }

            return $data;
        })->toArray();
    }

    /**
     * Get enhanced commission data with currency and filters.
     */
    public function getEnhancedCommissionsData(int $days = 30, string $type = 'all', ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = SellerOrderItem::join('order_items', 'seller_order_items.order_item_id', '=', 'order_items.id')
            ->where('order_items.fulfillment_status', FulfillmentStatusEnum::FULFILLED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate]);

        if ($marketId) {
            $query->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.market_id', $marketId);
        }

        if ($type !== 'all') {
            if ($type === 'high_commission') {
                $query->where('order_items.admin_commission_amount', '>', 100);
            } elseif ($type === 'low_commission') {
                $query->where('order_items.admin_commission_amount', '<=', 100);
            }
        }

        $commissions = $query->select(
            DB::raw('SUM(order_items.admin_commission_amount) as total_commission'),
            DB::raw('COUNT(seller_order_items.id) as total_orders'),
            DB::raw('AVG(order_items.admin_commission_amount) as avg_commission')
        )->first();

        $dailyQuery = SellerOrderItem::join('order_items', 'seller_order_items.order_item_id', '=', 'order_items.id')
            ->where('order_items.fulfillment_status', FulfillmentStatusEnum::FULFILLED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate]);

        if ($marketId) {
            $dailyQuery->join('orders as orders_daily', 'order_items.order_id', '=', 'orders_daily.id')
                ->where('orders_daily.market_id', $marketId);
        }

        $dailyCommissionsRaw = $dailyQuery->select(
                DB::raw('DATE(order_items.created_at) as date'),
                DB::raw('SUM(order_items.admin_commission_amount) as daily_commission')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('daily_commission', 'date');

        $period = CarbonPeriod::create($startDate, $endDate);

        $dailyCommissions = collect($period)->map(function ($date) use ($dailyCommissionsRaw, $marketId) {
            $dateStr = $date->format('Y-m-d');
            $commission = $dailyCommissionsRaw[$dateStr] ?? 0;

            return [
                'date' => $dateStr,
                'commission' => $commission,
                'formatted_commission' => $this->fmtMarket($commission, $marketId),
            ];
        });

        return [
            'total_commission' => $this->fmtMarket($commissions->total_commission ?? 0, $marketId),
            'total_commission_raw' => $commissions->total_commission ?? 0,
            'total_orders' => $commissions->total_orders ?? 0,
            'avg_commission' => $this->fmtMarket($commissions->avg_commission ?? 0, $marketId),
            'avg_commission_raw' => $commissions->avg_commission ?? 0,
            'daily_data' => $dailyCommissions->toArray(),
            'period' => $days,
            'currency_symbol' => $this->fmtSymbol($marketId)
        ];
    }

    /**
     * Get combined revenue and order count data for dual-axis chart.
     */
    public function getRevenueVsOrdersData(int $days = 30, ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = Order::where('fulfillment_status', FulfillmentStatusEnum::FULFILLED())
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($marketId) {
            $query->where('market_id', $marketId);
        }

        $revenueSql = $marketId
            ? 'SUM(total_payable)'
            : 'SUM((total_payable) / COALESCE(NULLIF(currency_rate, 0), 1))';

        $dailyData = (clone $query)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as order_count, ' . $revenueSql . ' as revenue')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayData = $dailyData[$dateStr] ?? null;

            $result[] = [
                'date' => $dateStr,
                'orders' => (int) ($dayData->order_count ?? 0),
                'revenue' => (float) ($dayData->revenue ?? 0),
                'formatted_revenue' => $this->fmtMarket($dayData->revenue ?? 0, $marketId),
            ];
        }

        $totalOrders = array_sum(array_column($result, 'orders'));
        $totalRevenue = array_sum(array_column($result, 'revenue'));
        $aov = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        return [
            'daily' => $result,
            'total_orders' => $totalOrders,
            'total_revenue' => $totalRevenue,
            'formatted_total_revenue' => $marketId ? $this->fmtMarket($totalRevenue, $marketId) : $this->fmtBaseToMarket($totalRevenue, $marketId),
            'aov' => round($aov, 2),
            'formatted_aov' => $marketId ? $this->fmtMarket($aov, $marketId) : $this->fmtBaseToMarket($aov, $marketId),
            'currency_symbol' => $this->fmtSymbol($marketId),
        ];
    }

    /**
     * Get top stores ranked by revenue/orders.
     */
    public function getTopStores(int $days = 7, int $limit = 10, string $sortBy = 'revenue', ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $storeRevenueExpr = $marketId
            ? 'SUM(order_items.subtotal)'
            : 'SUM(' . $this->baseConversionSql('order_items.subtotal') . ')';

        $query = Store::select('stores.*')
            ->selectRaw($storeRevenueExpr . ' as total_revenue')
            ->selectRaw('COUNT(order_items.id) as total_orders')
            ->selectRaw('SUM(order_items.quantity) as total_items_sold')
            ->join('order_items', 'stores.id', '=', 'order_items.store_id')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->where('order_items.fulfillment_status', FulfillmentStatusEnum::FULFILLED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->where('stores.verification_status', StoreVerificationStatusEnum::APPROVED())
            ->where('stores.visibility_status', StoreVisibilityStatusEnum::VISIBLE());

        if ($marketId) {
            $query->where('orders.market_id', $marketId);
        }

        $orderColumn = match ($sortBy) {
            'orders' => 'total_orders',
            default => 'total_revenue',
        };

        $topStores = $query->groupBy('stores.id')
            ->orderBy($orderColumn, 'desc')
            ->limit($limit)
            ->get();

        return $topStores->map(function ($store) use ($marketId) {
            return [
                'id' => $store->id,
                'name' => $store->name,
                'total_revenue' => $marketId ? $this->fmtMarket($store->total_revenue, $marketId) : $this->fmtBaseToMarket($store->total_revenue, $marketId),
                'total_revenue_raw' => $store->total_revenue,
                'total_orders' => $store->total_orders,
                'total_items_sold' => $store->total_items_sold,
                'image' => $store?->getFirstMediaUrl(SpatieMediaCollectionName::STORE_LOGO()) ?: null,
            ];
        })->toArray();
    }

    /**
     * Get top markets ranked by revenue/orders.
     */
    public function getTopMarkets(int $days = 7, int $limit = 10, string $sortBy = 'revenue'): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = Market::select('markets.*')
            ->selectRaw('SUM(orders.total_payable) as total_revenue')
            ->selectRaw('COUNT(orders.id) as total_orders')
            ->selectRaw('SUM(CASE WHEN orders.fulfillment_status = ? THEN 1 ELSE 0 END) as delivered_orders', [FulfillmentStatusEnum::FULFILLED()])
            ->join('orders', 'markets.id', '=', 'orders.market_id')
            ->whereBetween('orders.created_at', [$startDate, $endDate])
            ->where('markets.status', MarketStatusEnum::ACTIVE());

        $orderColumn = match ($sortBy) {
            'orders' => 'total_orders',
            'growth' => 'total_revenue',
            default => 'total_revenue',
        };

        $topMarkets = $query->groupBy('markets.id')
            ->orderBy($orderColumn, 'desc')
            ->limit($limit)
            ->get();

        return $topMarkets->map(function ($market) {
            $onTimeRate = $market->total_orders > 0
                ? round(($market->delivered_orders / $market->total_orders) * 100, 1)
                : 0;

            return [
                'id' => $market->id,
                'name' => $market->name,
                'code' => $market->code,
                'total_revenue' => $this->fmtForMarket($market->total_revenue, $market),
                'total_revenue_raw' => $market->total_revenue,
                'total_orders' => $market->total_orders,
                'delivered_orders' => $market->delivered_orders,
                'delivery_rate' => $onTimeRate,
            ];
        })->toArray();
    }

    /**
     * Get order status funnel data.
     */
    public function getOrderFunnelData(int $days = 7, ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $query = Order::whereBetween('created_at', [$startDate, $endDate]);

        if ($marketId) {
            $query->where('market_id', $marketId);
        }

        $rows = $query
            ->select(
                'fulfillment_status',
                DB::raw('COUNT(*) as count'),
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled_count',
                [OrderStatusEnum::CANCELLED()],
            )
            ->groupBy('fulfillment_status')
            ->get();

        $totalOrders = (int) $rows->sum('count');
        $cancelled = (int) $rows->sum('cancelled_count');
        $fulfillmentCounts = $rows->pluck('count', 'fulfillment_status')
            ->map(fn($count) => (int) $count)
            ->toArray();

        $placed = $totalOrders;
        $fulfilling = $fulfillmentCounts[FulfillmentStatusEnum::PARTIALLY_FULFILLED()] ?? 0;
        $delivered = $fulfillmentCounts[FulfillmentStatusEnum::FULFILLED()] ?? 0;

        return [
            'funnel' => [
                ['stage' => 'Placed', 'count' => $placed],
                ['stage' => 'Fulfilling', 'count' => $fulfilling],
                ['stage' => 'Delivered', 'count' => $delivered],
                ['stage' => 'Cancelled', 'count' => $cancelled],
            ],
            'status_breakdown' => $fulfillmentCounts,
            'conversion_rate' => $placed > 0 ? round(($delivered / $placed) * 100, 1) : 0,
            'cancellation_rate' => $placed > 0 ? round(($cancelled / $placed) * 100, 1) : 0,
        ];
    }

    /**
     * Get alerts and action items for the admin dashboard.
     */
    public function getAlertsData(?int $marketId = null): array
    {
        $alerts = [];

        $unassignedQuery = Order::where('status', OrderStatusEnum::OPEN())
            ->where('fulfillment_status', FulfillmentStatusEnum::UNFULFILLED())
            ->where('created_at', '<', Carbon::now()->subMinutes(15));
        if ($marketId) {
            $unassignedQuery->where('market_id', $marketId);
        }
        $unassignedCount = $unassignedQuery->count();
        if ($unassignedCount > 0) {
            $alerts[] = [
                'severity' => 'critical',
                'message' => __('labels.unassigned_orders_alert', ['count' => $unassignedCount]),
                'count' => $unassignedCount,
                'type' => 'unassigned_orders',
                'action_route' => 'admin.orders.index',
            ];
        }

        $lowStockQuery = StoreLocationInventory::query()
            ->where('tracked', true)
            ->where('is_fulfillment_location', true)
            ->where('low_stock_threshold', '>', 0)
            ->where('stock', '>', 0)
            ->whereRaw('(stock - reserved) < low_stock_threshold');
        if ($marketId) {
            $lowStockQuery->whereHas('storeLocation.store.markets', fn($q) => $q->where('markets.id', $marketId));
        }
        $lowStockCount = $lowStockQuery->distinct('product_variant_id')->count('product_variant_id');
        if ($lowStockCount > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => __('labels.low_stock_alert', ['count' => $lowStockCount]),
                'count' => $lowStockCount,
                'type' => 'low_stock',
                'action_route' => 'admin.products.index',
            ];
        }

        $pendingPayoutsQuery = SellerStatement::where('settlement_status', SellerSettlementStatusEnum::PENDING())
            ->where('entry_type', 'credit')
            ->where('posted_at', '<', Carbon::now()->subDays(7));
        if ($marketId) {
            $pendingPayoutsQuery->whereHas('seller.stores.markets', fn($q) => $q->where('markets.id', $marketId));
        }
        $pendingPayoutsCount = $pendingPayoutsQuery->distinct('seller_id')->count('seller_id');
        if ($pendingPayoutsCount > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => __('labels.pending_payouts_alert', ['count' => $pendingPayoutsCount]),
                'count' => $pendingPayoutsCount,
                'type' => 'pending_payouts',
                'action_route' => 'admin.sellers.index',
            ];
        }

        $pendingSellers = Seller::where('verification_status', SellerVerificationStatusEnum::NotApproved())->count();
        if ($pendingSellers > 0) {
            $alerts[] = [
                'severity' => 'info',
                'message' => __('labels.pending_sellers_alert', ['count' => $pendingSellers]),
                'count' => $pendingSellers,
                'type' => 'pending_sellers',
                'action_route' => 'admin.sellers.index',
            ];
        }

        $pendingProductsQuery = Product::where('verification_status', ProductVarificationStatusEnum::PENDING());
        if ($marketId) {
            $pendingProductsQuery->whereHas('seller.stores.markets', fn($q) => $q->where('markets.id', $marketId));
        }
        $pendingProductsCount = $pendingProductsQuery->count();
        if ($pendingProductsCount > 0) {
            $alerts[] = [
                'severity' => 'warning',
                'message' => __('labels.pending_products_alert', ['count' => $pendingProductsCount]),
                'count' => $pendingProductsCount,
                'type' => 'pending_products',
                'action_route' => 'admin.products.index',
            ];
        }

        $severityOrder = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($alerts, fn($a, $b) => $severityOrder[$a['severity']] <=> $severityOrder[$b['severity']]);

        return ['alerts' => $alerts, 'total' => count($alerts)];
    }

    /**
     * Get customer insights data.
     */
    public function getCustomerInsightsData(int $days = 30, ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $ordersQuery = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where('fulfillment_status', FulfillmentStatusEnum::FULFILLED());

        if ($marketId) {
            $ordersQuery->where('market_id', $marketId);
        }

        $previousCustomerSql = 'CASE WHEN EXISTS (
            SELECT 1 FROM orders AS previous_orders
            WHERE previous_orders.user_id = orders.user_id
            AND previous_orders.fulfillment_status = ?
            AND previous_orders.created_at < ?';
        $previousCustomerBindings = [
            FulfillmentStatusEnum::FULFILLED(),
            $startDate,
        ];

        if ($marketId) {
            $previousCustomerSql .= ' AND previous_orders.market_id = ?';
            $previousCustomerBindings[] = $marketId;
        }

        $previousCustomerSql .= ') THEN 1 ELSE 0 END as is_returning';

        $currentCustomers = (clone $ordersQuery)
            ->select('user_id')
            ->selectRaw('COUNT(*) as order_count, SUM(total_payable) as revenue')
            ->selectRaw($previousCustomerSql, $previousCustomerBindings)
            ->groupBy('user_id')
            ->toBase();

        $customerTotals = DB::query()
            ->fromSub($currentCustomers, 'current_customers')
            ->selectRaw(
                'COUNT(*) as total_customers,
                SUM(CASE WHEN order_count > 1 THEN 1 ELSE 0 END) as repeat_customers,
                COALESCE(SUM(revenue) / NULLIF(SUM(order_count), 0), 0) as avg_basket_size,
                SUM(CASE WHEN is_returning = 0 THEN 1 ELSE 0 END) as new_customers,
                SUM(CASE WHEN is_returning = 1 THEN 1 ELSE 0 END) as returning_customers,
                COALESCE(SUM(CASE WHEN is_returning = 0 THEN revenue ELSE 0 END), 0) as new_revenue,
                COALESCE(SUM(CASE WHEN is_returning = 1 THEN revenue ELSE 0 END), 0) as returning_revenue'
            )
            ->first();

        $totalCustomers = (int) ($customerTotals->total_customers ?? 0);
        $repeatCustomers = (int) ($customerTotals->repeat_customers ?? 0);

        $repeatRate = $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0;

        $avgBasketSize = (float) ($customerTotals->avg_basket_size ?? 0);
        $newCustomers = (int) ($customerTotals->new_customers ?? 0);
        $returningCustomers = (int) ($customerTotals->returning_customers ?? 0);
        $newRevenue = (float) ($customerTotals->new_revenue ?? 0);
        $returningRevenue = (float) ($customerTotals->returning_revenue ?? 0);
        $splitTotal = $newRevenue + $returningRevenue;

        $churnThreshold = Carbon::now()->subDays(30);
        $churnRiskQuery = Order::query()
            ->where('fulfillment_status', FulfillmentStatusEnum::FULFILLED())
            ->where('created_at', '<', $churnThreshold);
        if ($marketId) {
            $churnRiskQuery->where('market_id', $marketId);
        }

        $frequentCustomers = $churnRiskQuery
            ->whereNotExists(function ($query) use ($churnThreshold, $marketId) {
                $query->selectRaw('1')
                    ->from('orders as recent_orders')
                    ->whereColumn('recent_orders.user_id', 'orders.user_id')
                    ->where('recent_orders.fulfillment_status', FulfillmentStatusEnum::FULFILLED())
                    ->where('recent_orders.created_at', '>=', $churnThreshold)
                    ->when($marketId, fn($q) => $q->where('recent_orders.market_id', $marketId));
            })
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) >= 3')
            ->toBase();

        $churnRisk = DB::query()
            ->fromSub($frequentCustomers, 'churn_customers')
            ->count();

        return [
            'total_customers' => $totalCustomers,
            'repeat_customers' => $repeatCustomers,
            'repeat_rate' => $repeatRate,
            'avg_basket_size' => $this->fmtMarket($avgBasketSize, $marketId),
            'avg_basket_size_raw' => round($avgBasketSize, 2),
            'aov' => $this->fmtMarket($avgBasketSize, $marketId),
            'new_customers' => $newCustomers,
            'returning_customers' => $returningCustomers,
            'new_revenue' => $this->fmtMarket($newRevenue, $marketId),
            'new_revenue_raw' => round($newRevenue, 2),
            'returning_revenue' => $this->fmtMarket($returningRevenue, $marketId),
            'returning_revenue_raw' => round($returningRevenue, 2),
            'new_revenue_share' => $splitTotal > 0 ? round(($newRevenue / $splitTotal) * 100, 1) : 0,
            'returning_revenue_share' => $splitTotal > 0 ? round(($returningRevenue / $splitTotal) * 100, 1) : 0,
            'churn_risk' => $churnRisk,
        ];
    }

    /**
     * Get market health/comparison data.
     */
    public function getMarketHealthData(int $days = 7, ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $previousStart = Carbon::now()->subDays($days * 2)->startOfDay();
        $previousEnd = Carbon::now()->subDays($days)->endOfDay();

        $marketsQuery = Market::active();
        if ($marketId) {
            $marketsQuery->where('id', $marketId);
        }

        $markets = $marketsQuery->orderByDesc('is_default')->orderBy('priority')->get();

        $marketData = $markets->map(function ($market) use ($startDate, $endDate, $previousStart, $previousEnd) {
            $currentOrders = Order::where('market_id', $market->id)
                ->whereBetween('created_at', [$startDate, $endDate]);
            $currentTotal = (clone $currentOrders)->count();
            $currentDelivered = (clone $currentOrders)->where('fulfillment_status', FulfillmentStatusEnum::FULFILLED())->count();
            $currentRevenue = (clone $currentOrders)->where('fulfillment_status', FulfillmentStatusEnum::FULFILLED())->sum('total_payable');

            $previousRevenue = Order::where('market_id', $market->id)
                ->whereBetween('created_at', [$previousStart, $previousEnd])
                ->where('fulfillment_status', FulfillmentStatusEnum::FULFILLED())
                ->sum('total_payable');

            $revenueGrowth = $previousRevenue > 0
                ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 1)
                : ($currentRevenue > 0 ? 100 : 0);

            $storeCount = DB::table('store_market')->where('market_id', $market->id)->count();

            $deliveryRate = $currentTotal > 0
                ? round(($currentDelivered / $currentTotal) * 100, 1)
                : 0;

            return [
                'id' => $market->id,
                'name' => $market->name,
                'code' => $market->code,
                'total_orders' => $currentTotal,
                'delivered_orders' => $currentDelivered,
                'delivery_rate' => $deliveryRate,
                'revenue' => $this->fmtForMarket($currentRevenue, $market),
                'revenue_raw' => $currentRevenue,
                'revenue_growth' => $revenueGrowth,
                'store_count' => $storeCount,
            ];
        })->toArray();

        return [
            'markets' => $marketData,
            'total_markets' => count($marketData),
        ];
    }

    /**
     * Return analytics (market-scoped via the returned item's order.market_id).
     * Return rate = returns opened in the period ÷ items delivered in the period.
     */
    public function getReturnAnalyticsData(int $days = 30, ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $returnScope = fn($q) => $marketId
            ? $q->whereHas('order', fn($oq) => $oq->where('market_id', $marketId))
            : $q;

        $returnTotals = $returnScope(
            OrderItemReturn::whereBetween('created_at', [$startDate, $endDate])
        )->selectRaw(
            'COUNT(*) as total_returns,
            SUM(CASE WHEN reason_type = ? THEN 1 ELSE 0 END) as seller_fault,
            SUM(CASE WHEN reason_type = ? THEN 1 ELSE 0 END) as customer_fault,
            COALESCE(SUM(refund_amount), 0) as total_refund',
            [
                ReturnReasonTypeEnum::SELLER_FAULT(),
                ReturnReasonTypeEnum::CUSTOMER_FAULT(),
            ],
        )->first();

        $totalReturns = (int) ($returnTotals->total_returns ?? 0);
        $sellerFault = (int) ($returnTotals->seller_fault ?? 0);
        $customerFault = (int) ($returnTotals->customer_fault ?? 0);
        $totalRefund = (float) ($returnTotals->total_refund ?? 0);

        $deliveredItems = OrderItem::where('fulfillment_status', FulfillmentStatusEnum::FULFILLED())
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($marketId, fn($q) => $q->whereHas('order', fn($oq) => $oq->where('market_id', $marketId)))
            ->count();

        $returnRate = $deliveredItems > 0 ? round(($totalReturns / $deliveredItems) * 100, 1) : 0;
        $faultDenom = $sellerFault + $customerFault;

        $topSellerRows = $returnScope(OrderItemReturn::whereBetween('created_at', [$startDate, $endDate]))
            ->select('seller_id', DB::raw('COUNT(*) as returns_count'), DB::raw('SUM(refund_amount) as refund_total'))
            ->groupBy('seller_id')
            ->orderByDesc('returns_count')
            ->limit(5)
            ->get();

        $sellerNames = $topSellerRows->isEmpty()
            ? collect()
            : Seller::whereIn('id', $topSellerRows->pluck('seller_id'))
                ->with('user:id,name')
                ->get()
                ->keyBy('id');

        $topSellers = $topSellerRows->map(fn($row) => [
            'seller_id' => $row->seller_id,
            'name' => $sellerNames->get($row->seller_id)?->user?->name ?? ('#' . $row->seller_id),
            'returns_count' => (int) $row->returns_count,
            'refund_total' => $this->fmtMarket($row->refund_total, $marketId),
            'refund_total_raw' => round((float) $row->refund_total, 2),
        ])->toArray();

        return [
            'total_returns' => $totalReturns,
            'delivered_items' => $deliveredItems,
            'return_rate' => $returnRate,
            'seller_fault' => $sellerFault,
            'customer_fault' => $customerFault,
            'seller_fault_share' => $faultDenom > 0 ? round(($sellerFault / $faultDenom) * 100, 1) : 0,
            'customer_fault_share' => $faultDenom > 0 ? round(($customerFault / $faultDenom) * 100, 1) : 0,
            'total_refund' => $this->fmtMarket($totalRefund, $marketId),
            'total_refund_raw' => round((float) $totalRefund, 2),
            'top_sellers' => $topSellers,
        ];
    }

    /**
     * Revenue by product category (market-scoped). Sums delivered order-item
     * subtotals grouped by the product's primary category, in market currency.
     */
    public function getRevenueByCategoryData(int $days = 30, int $limit = 8, ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $rows = OrderItem::query()
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('order_items.fulfillment_status', FulfillmentStatusEnum::FULFILLED())
            ->whereBetween('order_items.created_at', [$startDate, $endDate])
            ->when($marketId, fn($q) => $q->whereExists(function ($sub) use ($marketId) {
                $sub->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.id', 'order_items.order_id')
                    ->where('orders.market_id', $marketId);
            }))
            ->groupBy('products.category_id', 'categories.title')
            ->select(
                'products.category_id',
                DB::raw('COALESCE(categories.title, "Uncategorized") as category_name'),
                DB::raw('SUM(order_items.subtotal) as revenue')
            )
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get();

        $total = (float) $rows->sum('revenue');

        $categories = $rows->map(fn($row) => [
            'category_id' => $row->category_id,
            'name' => $row->category_name,
            'revenue' => $this->fmtMarket($row->revenue, $marketId),
            'revenue_raw' => round((float) $row->revenue, 2),
            'share' => $total > 0 ? round(((float) $row->revenue / $total) * 100, 1) : 0,
        ])->toArray();

        return [
            'categories' => $categories,
            'total_revenue' => $this->fmtMarket($total, $marketId),
            'total_revenue_raw' => round($total, 2),
        ];
    }

    /**
     * Catalog moderation health — approval vs rejection of seller-submitted
     * products. Approval rate measured over adjudicated products only.
     */
    public function getCatalogModerationData(?int $marketId = null): array
    {
        $totals = Product::query()
            ->when($marketId, fn($q) =>
                $q->whereHas('seller.stores.markets', fn($mq) => $mq->where('markets.id', $marketId))
            )
            ->selectRaw(
                'SUM(CASE WHEN verification_status = ? THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN verification_status = ? THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN verification_status = ? THEN 1 ELSE 0 END) as rejected',
                [
                    ProductVarificationStatusEnum::PENDING(),
                    ProductVarificationStatusEnum::APPROVED(),
                    ProductVarificationStatusEnum::REJECTED(),
                ],
            )
            ->first();

        $pending = (int) ($totals->pending ?? 0);
        $approved = (int) ($totals->approved ?? 0);
        $rejected = (int) ($totals->rejected ?? 0);

        $adjudicated = $approved + $rejected;

        return [
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'approval_rate' => $adjudicated > 0 ? round(($approved / $adjudicated) * 100, 1) : 0,
            'rejection_rate' => $adjudicated > 0 ? round(($rejected / $adjudicated) * 100, 1) : 0,
        ];
    }

    /**
     * Global (NOT market-scoped) ops snapshot: pending wallet deposits and
     * platform-wide low / out-of-stock variant counts. Base currency.
     */
    public function getGlobalOpsData(): array
    {
        $pendingDeposits = DB::table('wallet_transactions')
            ->join('users', 'users.id', '=', 'wallet_transactions.user_id')
            ->whereNull('users.deleted_at')
            ->where('wallet_transactions.transaction_type', 'deposit')
            ->where('wallet_transactions.status', 'pending')
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(wallet_transactions.amount), 0) as amount')
            ->first();
        $pendingDepositsCount = (int) ($pendingDeposits->count ?? 0);
        $pendingDepositsAmount = (float) ($pendingDeposits->amount ?? 0);

        $stock = StoreLocationInventory::query()
            ->where('tracked', true)
            ->where('is_fulfillment_location', true)
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN low_stock_threshold > 0 AND stock > 0 AND (stock - reserved) < low_stock_threshold THEN product_variant_id END) as low_stock,
                COUNT(DISTINCT CASE WHEN (stock - reserved) <= 0 THEN product_variant_id END) as out_of_stock'
            )
            ->first();

        return [
            'pending_deposits' => $pendingDepositsCount,
            'pending_deposits_amount' => $this->currencyService->format($pendingDepositsAmount),
            'low_stock' => (int) ($stock->low_stock ?? 0),
            'out_of_stock' => (int) ($stock->out_of_stock ?? 0),
        ];
    }

    /**
     * Get all active markets for the market selector.
     */
    public function getActiveMarkets()
    {
        return Market::active()
            ->orderByDesc('is_default')
            ->orderBy('priority')
            ->get(['id', 'code', 'name', 'is_default'])
            ->toArray();
    }

    /**
     * Get seller settlements summary data.
     */
    public function getSellerSettlementsData(int $days = 1, ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $baseQuery = SellerStatement::whereBetween('posted_at', [$startDate, $endDate]);

        if ($marketId) {
            $baseQuery->whereHas('seller', function ($q) use ($marketId) {
                $q->whereHas('stores', function ($sq) use ($marketId) {
                    $sq->whereHas('markets', fn($mq) => $mq->where('markets.id', $marketId));
                });
            });
        }

        $periodTotals = $baseQuery->selectRaw(
            'SUM(CASE WHEN settlement_status = ? THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN settlement_status = ? AND entry_type = ? THEN amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN settlement_status = ? THEN 1 ELSE 0 END) as settled_count,
            SUM(CASE WHEN settlement_status = ? AND entry_type = ? THEN amount ELSE 0 END) as settled_amount',
            [
                SellerSettlementStatusEnum::PENDING(),
                SellerSettlementStatusEnum::PENDING(),
                'credit',
                SellerSettlementStatusEnum::SETTLED(),
                SellerSettlementStatusEnum::SETTLED(),
                'credit',
            ],
        )->first();

        $pendingCount = (int) ($periodTotals->pending_count ?? 0);
        $pendingAmount = (float) ($periodTotals->pending_amount ?? 0);
        $settledCount = (int) ($periodTotals->settled_count ?? 0);
        $settledAmount = (float) ($periodTotals->settled_amount ?? 0);

        $outstandingQuery = SellerStatement::where('settlement_status', SellerSettlementStatusEnum::PENDING())
            ->where('entry_type', 'credit');
        if ($marketId) {
            $outstandingQuery->whereHas('seller', function ($q) use ($marketId) {
                $q->whereHas('stores', function ($sq) use ($marketId) {
                    $sq->whereHas('markets', fn($mq) => $mq->where('markets.id', $marketId));
                });
            });
        }
        $totalOutstanding = (float) $outstandingQuery->sum('amount');

        return [
            'pending_count' => $pendingCount,
            'pending_amount' => $this->fmtBaseToMarket($pendingAmount, $marketId),
            'settled_count' => $settledCount,
            'settled_amount' => $this->fmtBaseToMarket($settledAmount, $marketId),
            'total_outstanding' => $this->fmtBaseToMarket($totalOutstanding, $marketId),
            'raw_pending' => $pendingAmount,
            'raw_settled' => $settledAmount,
            'raw_outstanding' => $totalOutstanding,
        ];
    }

    /**
     * Get seller withdrawals summary data.
     */
    public function getWithdrawalsData(int $days = 1, ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $sellerQuery = SellerWithdrawalRequest::whereBetween('created_at', [$startDate, $endDate]);
        if ($marketId) {
            $sellerQuery->whereHas('seller', function ($q) use ($marketId) {
                $q->whereHas('stores', function ($sq) use ($marketId) {
                    $sq->whereHas('markets', fn($mq) => $mq->where('markets.id', $marketId));
                });
            });
        }

        $periodTotals = $sellerQuery->selectRaw(
            'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = ? THEN amount ELSE 0 END) as approved_amount,
            SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected_count',
            [
                SellerWithdrawalStatusEnum::PENDING(),
                SellerWithdrawalStatusEnum::APPROVED(),
                SellerWithdrawalStatusEnum::REJECTED(),
            ],
        )->first();

        $sellerPending = (int) ($periodTotals->pending_count ?? 0);
        $sellerApprovedAmount = (float) ($periodTotals->approved_amount ?? 0);
        $sellerRejected = (int) ($periodTotals->rejected_count ?? 0);

        $oldestPendingQuery = SellerWithdrawalRequest::where('status', SellerWithdrawalStatusEnum::PENDING());
        $oldestSeller = $oldestPendingQuery->orderBy('created_at', 'asc')->value('created_at');

        $oldestDays = 0;
        if ($oldestSeller) {
            $oldestDays = Carbon::parse($oldestSeller)->diffInDays(Carbon::now());
        }

        return [
            'seller_pending' => $sellerPending,
            'total_pending' => $sellerPending,
            'approved_amount' => $this->fmtBaseToMarket($sellerApprovedAmount, $marketId),
            'seller_approved_amount' => $this->fmtBaseToMarket($sellerApprovedAmount, $marketId),
            'total_rejected' => $sellerRejected,
            'oldest_pending_days' => $oldestDays,
            'raw_approved' => $sellerApprovedAmount,
        ];
    }

    /**
     * Get ad campaigns summary data (basic overview).
     */
    public function getAdCampaignsData(int $days = 1, ?int $marketId = null): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $activeCampaigns = AdCampaign::where('status', AdCampaignStatusEnum::RUNNING());
        if ($marketId) {
            $activeCampaigns->whereHas('seller', function ($q) use ($marketId) {
                $q->whereHas('stores', function ($sq) use ($marketId) {
                    $sq->whereHas('markets', fn($mq) => $mq->where('markets.id', $marketId));
                });
            });
        }
        $activeCount = $activeCampaigns->count();

        $pendingCount = AdCampaign::where('status', AdCampaignStatusEnum::PENDING_APPROVAL())->count();

        $statsQuery = AdCampaignStat::whereBetween('stat_date', [$startDate, $endDate]);
        if ($marketId) {
            $statsQuery->whereHas('campaign', function ($q) use ($marketId) {
                $q->whereHas('seller', function ($sq) use ($marketId) {
                    $sq->whereHas('stores', function ($stq) use ($marketId) {
                        $stq->whereHas('markets', fn($mq) => $mq->where('markets.id', $marketId));
                    });
                });
            });
        }

        $totalSpend = (float) (clone $statsQuery)->sum('spent');
        $totalClicks = (int) (clone $statsQuery)->sum('clicks');
        $totalImpressions = (int) (clone $statsQuery)->sum('impressions');

        $topCampaigns = AdCampaignStat::select('campaign_id', DB::raw('SUM(clicks) as total_clicks'), DB::raw('SUM(spent) as total_spent'))
            ->whereBetween('stat_date', [$startDate, $endDate])
            ->when($marketId, function ($q) use ($marketId) {
                $q->whereHas('campaign', function ($cq) use ($marketId) {
                    $cq->whereHas('seller', function ($sq) use ($marketId) {
                        $sq->whereHas('stores', function ($stq) use ($marketId) {
                            $stq->whereHas('markets', fn($mq) => $mq->where('markets.id', $marketId));
                        });
                    });
                });
            })
            ->groupBy('campaign_id')
            ->orderByDesc('total_clicks')
            ->limit(3)
            ->get();

        $topCampaignsList = $topCampaigns->map(function ($stat) use ($marketId) {
            $campaign = AdCampaign::with('product')->find($stat->campaign_id);
            return [
                'name' => $campaign?->product?->title ?? 'Campaign #' . $stat->campaign_id,
                'clicks' => (int) $stat->total_clicks,
                'spent' => $this->fmtBaseToMarket((float) $stat->total_spent, $marketId),
            ];
        })->toArray();

        return [
            'active_campaigns' => $activeCount,
            'pending_approval' => $pendingCount,
            'total_spend' => $this->fmtBaseToMarket($totalSpend, $marketId),
            'total_clicks' => $totalClicks,
            'total_impressions' => $totalImpressions,
            'top_campaigns' => $topCampaignsList,
            'raw_spend' => $totalSpend,
        ];
    }

    /**
     * Get seller subscription summary data.
     */
    public function getSubscriptionsData(int $days = 1, ?int $marketId = null): array
    {
        $now = Carbon::now();
        $startDate = Carbon::now()->subDays($days)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $activeQuery = SellerSubscription::where('status', SellerSubscriptionStatusEnum::ACTIVE());
        if ($marketId) {
            $activeQuery->whereHas('seller', function ($q) use ($marketId) {
                $q->whereHas('stores', function ($sq) use ($marketId) {
                    $sq->whereHas('markets', fn($mq) => $mq->where('markets.id', $marketId));
                });
            });
        }
        $activeCount = $activeQuery->count();

        $expiringQuery = SellerSubscription::where('status', SellerSubscriptionStatusEnum::ACTIVE())
            ->whereBetween('end_date', [$now, $now->copy()->addDays(7)]);
        if ($marketId) {
            $expiringQuery->whereHas('seller', function ($q) use ($marketId) {
                $q->whereHas('stores', function ($sq) use ($marketId) {
                    $sq->whereHas('markets', fn($mq) => $mq->where('markets.id', $marketId));
                });
            });
        }
        $expiringSoon = $expiringQuery->count();

        $revenueQuery = SubscriptionTransaction::where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate]);
        if ($marketId) {
            $revenueQuery->whereHas('seller', function ($q) use ($marketId) {
                $q->whereHas('stores', function ($sq) use ($marketId) {
                    $sq->whereHas('markets', fn($mq) => $mq->where('markets.id', $marketId));
                });
            });
        }
        $subscriptionRevenue = (float) $revenueQuery->sum('amount');

        $popularPlan = SellerSubscription::where('status', SellerSubscriptionStatusEnum::ACTIVE())
            ->select('plan_id', DB::raw('COUNT(*) as sub_count'))
            ->groupBy('plan_id')
            ->orderByDesc('sub_count')
            ->first();

        $popularPlanName = null;
        if ($popularPlan) {
            $popularPlanName = SubscriptionPlan::where('id', $popularPlan->plan_id)->value('name');
        }

        return [
            'active_count' => $activeCount,
            'expiring_soon' => $expiringSoon,
            'revenue' => $this->fmtBaseToMarket($subscriptionRevenue, $marketId),
            'popular_plan' => $popularPlanName ?? 'N/A',
            'raw_revenue' => $subscriptionRevenue,
        ];
    }
}
