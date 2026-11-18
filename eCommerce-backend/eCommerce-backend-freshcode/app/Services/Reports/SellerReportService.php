<?php

namespace App\Services\Reports;

use App\Enums\Order\ReturnReasonCodeEnum;
use App\Models\Market;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemReturn;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerStatement;
use App\Models\Store;
use App\Services\CurrencyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerReportService
{
    public function __construct(
        protected CurrencyService $currencyService,
        protected AdminReportService $adminReportService
    ) {}

    public function resolveDateRange(array $filters): array
    {
        return $this->adminReportService->resolveDateRange($filters);
    }

    public function resolveMarket(array $filters): ?Market
    {
        return $this->adminReportService->resolveMarket($filters);
    }

    public function resolveMarketIds(array $filters): array
    {
        return $this->adminReportService->resolveMarketIds($filters);
    }

    public function resolveCurrencySymbol(array $filters = []): string
    {
        return $this->adminReportService->resolveCurrencySymbol($filters);
    }

    public function formatAmount(float $amount, array $filters = []): string
    {
        return $this->adminReportService->formatAmount($amount, $filters);
    }

    protected function getCurrencyConversionSql(string $column, ?Market $singleMarket = null): string
    {
        if ($singleMarket) {
            return $column;
        }
        return "{$column} / COALESCE(NULLIF(orders.currency_rate, 0), 1)";
    }

    protected function applyCompletedOrderCriteria($join, ?array $marketIds = null): void
    {
        $join->where('order_items.status', '!=', 'cancelled')
            ->whereExists(function ($sub) use ($marketIds) {
                $sub->select(DB::raw(1))
                    ->from('orders')
                    ->whereColumn('orders.id', 'order_items.order_id')
                    ->where('orders.status', '!=', 'cancelled')
                    ->where(function ($q) {
                        $q->where('orders.fulfillment_status', 'fulfilled')
                            ->orWhereIn('orders.status', ['archived', 'completed'])
                            ->orWhere('order_items.fulfillment_status', 'fulfilled');
                    });
                if (!empty($marketIds)) {
                    $sub->whereIn('orders.market_id', $marketIds);
                }
            });
    }

    protected function applyCompletedOrderWhere($query, ?array $marketIds = null): void
    {
        $query->where('order_items.status', '!=', 'cancelled')
            ->where('orders.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('orders.fulfillment_status', 'fulfilled')
                    ->orWhereIn('orders.status', ['archived', 'completed'])
                    ->orWhere('order_items.fulfillment_status', 'fulfilled');
            });
        if (!empty($marketIds)) {
            $query->whereIn('orders.market_id', $marketIds);
        }
    }

    protected function getActiveItemCondition(): string
    {
        return "order_items.status NOT IN ('cancelled', 'returned', 'refunded') AND NOT EXISTS (SELECT 1 FROM order_item_returns oir WHERE oir.order_item_id = order_items.id AND oir.return_status NOT IN ('declined', 'cancelled', 'seller_rejected'))";
    }

    protected function getReturnedItemCondition(): string
    {
        return "order_items.status IN ('returned', 'refunded') OR EXISTS (SELECT 1 FROM order_item_returns oir WHERE oir.order_item_id = order_items.id AND oir.return_status NOT IN ('declined', 'cancelled', 'seller_rejected'))";
    }

    public function getSalesData(int $sellerId, array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);
        $storeId = !empty($filters['store_id']) ? (int) $filters['store_id'] : null;

        $storeIds = DB::table('stores')->where('seller_id', $sellerId)->pluck('id')->all();
        if (empty($storeIds)) {
            return [
                'draw' => request()->get('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ];
        }

        $grossSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);
        $netSql = $this->getCurrencyConversionSql('order_items.seller_commission_amount', $market);
        $activeCond = $this->getActiveItemCondition();
        $returnedCond = $this->getReturnedItemCondition();

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('stores', 'stores.id', '=', 'order_items.store_id')
            ->whereIn('order_items.store_id', $storeIds)
            ->whereBetween('order_items.created_at', [$from, $to]);

        $this->applyCompletedOrderWhere($query, $marketIds);

        $query->select(
                DB::raw('DATE(order_items.created_at) as sales_date'),
                DB::raw("COUNT(DISTINCT CASE WHEN {$activeCond} THEN orders.id END) as total_orders"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.quantity ELSE 0 END), 0) as units_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$grossSql} ELSE 0 END), 0) as gross_sales"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as commission_deducted"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$netSql} ELSE 0 END), 0) as net_earnings"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$returnedCond} THEN order_items.id END) as returns_count")
            )
            ->groupBy(DB::raw('DATE(order_items.created_at)'));

        if ($storeId) {
            $query->where('order_items.store_id', $storeId);
        }

        $allRows = $query->get();
        $totalRecords = $allRows->count();

        $allowedColumns = [
            'sales_date' => 'sales_date',
            'total_orders' => 'total_orders',
            'units_sold' => 'units_sold',
            'gross_sales' => 'gross_sales',
            'commission_deducted' => 'commission_deducted',
            'net_earnings' => 'net_earnings',
            'returns_count' => 'returns_count',
        ];

        if ($orderColumn && isset($allowedColumns[$orderColumn])) {
            $query->orderBy($allowedColumns[$orderColumn], $orderDir);
        } else {
            $query->orderBy('sales_date', 'desc');
        }

        $items = $query->skip($start)->take($length)->get();

        $data = $items->map(function ($row) use ($filters) {
            return [
                'sales_date' => $row->sales_date,
                'total_orders' => number_format((int) $row->total_orders),
                'units_sold' => number_format((int) $row->units_sold),
                'gross_sales' => $this->formatAmount((float) $row->gross_sales, $filters),
                'commission_deducted' => $this->formatAmount((float) $row->commission_deducted, $filters),
                'net_earnings' => '<span class="fw-bold text-success">' . $this->formatAmount((float) $row->net_earnings, $filters) . '</span>',
                'returns_count' => number_format((int) $row->returns_count),
            ];
        });

        return [
            'draw' => request()->get('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data,
        ];
    }

    public function getSalesChart(int $sellerId, array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);
        $storeId = !empty($filters['store_id']) ? (int) $filters['store_id'] : null;

        $storeIds = DB::table('stores')->where('seller_id', $sellerId)->pluck('id')->all();
        if (empty($storeIds)) {
            return [
                'dates' => [],
                'gross_series' => [],
                'net_series' => [],
                'commission_series' => [],
                'summary' => [
                    'total_gross' => $this->formatAmount(0, $filters),
                    'total_net' => $this->formatAmount(0, $filters),
                    'total_commission' => $this->formatAmount(0, $filters),
                    'total_orders' => 0,
                ],
                'currency_symbol' => $this->resolveCurrencySymbol($filters),
            ];
        }

        $grossSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);
        $netSql = $this->getCurrencyConversionSql('order_items.seller_commission_amount', $market);
        $activeCond = $this->getActiveItemCondition();

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.store_id', $storeIds)
            ->whereBetween('order_items.created_at', [$from, $to]);

        $this->applyCompletedOrderWhere($query, $marketIds);

        $query->select(
                DB::raw('DATE(order_items.created_at) as sales_date'),
                DB::raw("COUNT(DISTINCT CASE WHEN {$activeCond} THEN orders.id END) as total_orders"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$grossSql} ELSE 0 END), 0) as gross_sales"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$netSql} ELSE 0 END), 0) as net_earnings"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as commission_deducted")
            )
            ->groupBy(DB::raw('DATE(order_items.created_at)'))
            ->orderBy('sales_date', 'asc');

        if ($storeId) {
            $query->where('order_items.store_id', $storeId);
        }

        $records = $query->get();

        return [
            'dates' => $records->pluck('sales_date')->all(),
            'gross_series' => $records->map(fn($r) => round((float) $r->gross_sales, 2))->all(),
            'net_series' => $records->map(fn($r) => round((float) $r->net_earnings, 2))->all(),
            'commission_series' => $records->map(fn($r) => round((float) $r->commission_deducted, 2))->all(),
            'summary' => [
                'total_gross' => $this->formatAmount((float) $records->sum('gross_sales'), $filters),
                'total_net' => $this->formatAmount((float) $records->sum('net_earnings'), $filters),
                'total_commission' => $this->formatAmount((float) $records->sum('commission_deducted'), $filters),
                'total_orders' => (int) $records->sum('total_orders'),
            ],
            'currency_symbol' => $this->resolveCurrencySymbol($filters),
        ];
    }

    public function getProductsData(int $sellerId, array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $grossSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);
        $netSql = $this->getCurrencyConversionSql('order_items.seller_commission_amount', $market);
        $activeCond = $this->getActiveItemCondition();
        $returnedCond = $this->getReturnedItemCondition();

        $query = DB::table('products')
            ->where('products.seller_id', $sellerId)
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.product_id', '=', 'products.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'products.id',
                'products.title',
                'categories.title as category_title',
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.quantity ELSE 0 END), 0) as units_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$grossSql} ELSE 0 END), 0) as gross_sales"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as commission_deducted"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$netSql} ELSE 0 END), 0) as net_earnings"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$returnedCond} THEN order_items.id END) as returns_count")
            )
            ->groupBy('products.id', 'products.title', 'categories.title');

        if (!empty($search)) {
            $query->where('products.title', 'like', "%{$search}%");
        }

        $totalRecords = DB::table('products')->where('seller_id', $sellerId)->count();
        $filteredRecords = $query->get()->count();

        $allowedColumns = [
            'title' => 'products.title',
            'units_sold' => 'units_sold',
            'gross_sales' => 'gross_sales',
            'commission_deducted' => 'commission_deducted',
            'net_earnings' => 'net_earnings',
            'returns_count' => 'returns_count',
        ];

        $sortBy = $filters['sort_by'] ?? null;
        if ($sortBy === 'most_selling') {
            $query->orderBy('units_sold', 'desc');
        } elseif ($sortBy === 'high_revenue') {
            $query->orderBy('gross_sales', 'desc');
        } elseif ($orderColumn && isset($allowedColumns[$orderColumn])) {
            $query->orderBy($allowedColumns[$orderColumn], $orderDir);
        } else {
            $query->orderBy('units_sold', 'desc');
        }

        $items = $query->skip($start)->take($length)->get();

        $data = $items->map(function ($row) use ($filters) {
            $returns = (int) $row->returns_count;
            $units = (int) $row->units_sold;
            $totalShipped = $units + $returns;
            $returnRate = $totalShipped > 0 ? round(($returns / $totalShipped) * 100, 2) : 0;

            return [
                'id' => $row->id,
                'title' => '<span class="fw-bold">' . e($row->title) . '</span>',
                'category' => e($row->category_title ?? '—'),
                'units_sold' => number_format($units),
                'gross_sales' => $this->formatAmount((float) $row->gross_sales, $filters),
                'commission_deducted' => $this->formatAmount((float) $row->commission_deducted, $filters),
                'net_earnings' => '<span class="fw-bold text-success">' . $this->formatAmount((float) $row->net_earnings, $filters) . '</span>',
                'returns_count' => number_format($returns),
                'return_rate' => $returnRate . '%',
            ];
        });

        return [
            'draw' => request()->get('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ];
    }

    public function getProductsChart(int $sellerId, array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $grossSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $netSql = $this->getCurrencyConversionSql('order_items.seller_commission_amount', $market);
        $activeCond = $this->getActiveItemCondition();

        $topProducts = DB::table('products')
            ->where('products.seller_id', $sellerId)
            ->join('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.product_id', '=', 'products.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'products.title',
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.quantity ELSE 0 END), 0) as units_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$grossSql} ELSE 0 END), 0) as gross_sales"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$netSql} ELSE 0 END), 0) as net_earnings")
            )
            ->groupBy('products.id', 'products.title')
            ->orderBy('gross_sales', 'desc')
            ->take(8)
            ->get();

        return [
            'products' => $topProducts->map(fn($p) => Str::limit($p->title, 20))->all(),
            'gross_series' => $topProducts->map(fn($p) => round((float) $p->gross_sales, 2))->all(),
            'net_series' => $topProducts->map(fn($p) => round((float) $p->net_earnings, 2))->all(),
            'units_series' => $topProducts->map(fn($p) => (int) $p->units_sold)->all(),
            'summary' => [
                'total_units' => (int) $topProducts->sum('units_sold'),
                'total_gross' => $this->formatAmount((float) $topProducts->sum('gross_sales'), $filters),
                'total_net' => $this->formatAmount((float) $topProducts->sum('net_earnings'), $filters),
            ],
            'currency_symbol' => $this->resolveCurrencySymbol($filters),
        ];
    }

    public function getSettlementsData(int $sellerId, array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);
        $status = $filters['settlement_status'] ?? null;

        $query = DB::table('seller_statements')
            ->where('seller_statements.seller_id', $sellerId)
            ->whereBetween('seller_statements.posted_at', [$from, $to])
            ->leftJoin('orders', 'orders.id', '=', 'seller_statements.order_id');

        if (!empty($marketIds)) {
            $query->whereIn('orders.market_id', $marketIds);
        }

        if (!empty($status)) {
            $query->where('seller_statements.settlement_status', $status);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('seller_statements.description', 'like', "%{$search}%")
                    ->orWhere('seller_statements.settlement_reference', 'like', "%{$search}%");
            });
        }

        $totalRecords = DB::table('seller_statements')->where('seller_id', $sellerId)->count();
        $filteredRecords = $query->count();

        $query->orderBy('seller_statements.posted_at', 'desc');
        $query->select('seller_statements.*', 'orders.currency_rate as order_currency_rate');
        $items = $query->skip($start)->take($length)->get();

        $data = $items->map(function ($row) use ($filters, $market) {
            $statusBadge = $row->settlement_status === 'settled'
                ? '<span class="badge bg-success-lt">Settled</span>'
                : '<span class="badge bg-warning-lt">Pending</span>';

            $amount = (float) $row->amount;
            if ($market) {
                $rate = (float) ($row->order_currency_rate ?: 1);
                $marketAmount = $rate > 0 ? $amount * $rate : $amount;
                $formattedAmount = $this->formatAmount($marketAmount, $filters);
            } else {
                $formattedAmount = $this->formatAmount($amount, $filters);
            }

            return [
                'id' => $row->id,
                'entry_type' => Str::headline($row->entry_type),
                'order_id' => $row->order_id ? '#' . $row->order_id : '—',
                'amount' => '<span class="fw-bold">' . $formattedAmount . '</span>',
                'settlement_status' => $statusBadge,
                'posted_at' => $row->posted_at ? Carbon::parse($row->posted_at)->format('Y-m-d H:i') : '—',
                'settled_at' => $row->settled_at ? Carbon::parse($row->settled_at)->format('Y-m-d H:i') : '—',
                'reference' => e($row->settlement_reference ?? '—'),
            ];
        });

        return [
            'draw' => request()->get('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ];
    }

    public function getSettlementsChart(int $sellerId, array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $query = DB::table('seller_statements')
            ->where('seller_statements.seller_id', $sellerId)
            ->whereBetween('seller_statements.posted_at', [$from, $to])
            ->leftJoin('orders', 'orders.id', '=', 'seller_statements.order_id');

        if (!empty($marketIds)) {
            $query->whereIn('orders.market_id', $marketIds);
        }

        $pending = (clone $query)->where('seller_statements.settlement_status', 'pending')->sum('seller_statements.amount');
        $settled = (clone $query)->where('seller_statements.settlement_status', 'settled')->sum('seller_statements.amount');

        $pendingFloat = (float) $pending;
        $settledFloat = (float) $settled;

        if ($market) {
            $avgRate = (clone $query)->avg('orders.currency_rate') ?: 1;
            $pendingFloat = $pendingFloat * $avgRate;
            $settledFloat = $settledFloat * $avgRate;
        }

        return [
            'labels' => ['Settled', 'Pending'],
            'series' => [round($settledFloat, 2), round($pendingFloat, 2)],
            'summary' => [
                'total_settled' => $this->formatAmount($settledFloat, $filters),
                'total_pending' => $this->formatAmount($pendingFloat, $filters),
                'total' => $this->formatAmount($settledFloat + $pendingFloat, $filters),
            ],
            'currency_symbol' => $this->resolveCurrencySymbol($filters),
        ];
    }

    public function getReturnsData(int $sellerId, array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $query = DB::table('order_item_returns')
            ->where('order_item_returns.seller_id', $sellerId)
            ->whereBetween('order_item_returns.created_at', [$from, $to])
            ->leftJoin('order_items', 'order_items.id', '=', 'order_item_returns.order_item_id')
            ->leftJoin('orders', 'orders.id', '=', 'order_item_returns.order_id')
            ->select(
                'order_item_returns.id',
                'order_item_returns.order_id',
                'order_items.title as item_title',
                'order_item_returns.reason',
                'order_item_returns.reason_code',
                'order_item_returns.refund_amount',
                'order_item_returns.return_status',
                'order_item_returns.reason_type',
                'order_item_returns.created_at',
                'orders.currency_rate'
            );

        if (!empty($marketIds)) {
            $query->whereIn('orders.market_id', $marketIds);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $cleanSearch = ltrim($search, '#');
                $q->where('order_items.title', 'like', "%{$search}%")
                    ->orWhere('order_item_returns.reason', 'like', "%{$search}%")
                    ->orWhere('order_item_returns.reason_code', 'like', "%{$search}%")
                    ->orWhere('order_item_returns.order_id', 'like', "%{$cleanSearch}%");
            });
        }

        $totalRecords = DB::table('order_item_returns')->where('seller_id', $sellerId)->count();
        $filteredRecords = $query->count();

        $allowedColumns = [
            'order_id' => 'order_item_returns.order_id',
            'item_title' => 'order_items.title',
            'reason' => 'order_item_returns.reason_code',
            'refund_amount' => 'order_item_returns.refund_amount',
            'status' => 'order_item_returns.return_status',
            'fault' => 'order_item_returns.reason_type',
            'created_at' => 'order_item_returns.created_at',
        ];

        if ($orderColumn && isset($allowedColumns[$orderColumn])) {
            $query->orderBy($allowedColumns[$orderColumn], $orderDir);
        } else {
            $query->orderBy('order_item_returns.created_at', 'desc');
        }

        $items = $query->skip($start)->take($length)->get();

        $data = $items->map(function ($row) use ($filters, $market) {
            $isSellerFault = ($row->reason_type === 'SELLER_FAULT');
            $faultBadge = $isSellerFault
                ? '<span class="badge bg-danger-lt">Seller Fault</span>'
                : '<span class="badge bg-secondary-lt">Customer Fault</span>';

            $refund = (float) $row->refund_amount;
            if (!$market && !empty($row->currency_rate) && (float) $row->currency_rate > 0) {
                $refund = $refund / (float) $row->currency_rate;
            }

            $reasonEnum = $row->reason_code ? ReturnReasonCodeEnum::tryFrom($row->reason_code) : null;
            $predefinedLabel = $reasonEnum?->label() ?? ($row->reason_code ? Str::headline($row->reason_code) : null);

            $reasonHtml = '';
            if ($predefinedLabel) {
                $reasonHtml .= '<div class="fw-semibold">' . e($predefinedLabel) . '</div>';
                if (!empty($row->reason)) {
                    $reasonHtml .= '<div class="text-secondary small mt-1"><i class="ti ti-message me-1"></i>' . e($row->reason) . '</div>';
                }
            } elseif (!empty($row->reason)) {
                $reasonHtml .= '<div>' . e($row->reason) . '</div>';
            } else {
                $reasonHtml .= '<span class="text-secondary">—</span>';
            }

            return [
                'id' => $row->id,
                'order_id' => '#' . $row->order_id,
                'item_title' => e($row->item_title ?? '—'),
                'reason' => $reasonHtml,
                'refund_amount' => $this->formatAmount($refund, $filters),
                'status' => '<span class="badge bg-info-lt">' . Str::headline($row->return_status ?? 'requested') . '</span>',
                'fault' => $faultBadge,
                'created_at' => Carbon::parse($row->created_at)->format('Y-m-d H:i'),
            ];
        });

        return [
            'draw' => request()->get('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ];
    }

    public function getReturnsChart(int $sellerId, array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $baseQuery = DB::table('order_item_returns')
            ->leftJoin('orders', 'orders.id', '=', 'order_item_returns.order_id')
            ->where('order_item_returns.seller_id', $sellerId)
            ->whereBetween('order_item_returns.created_at', [$from, $to]);

        if (!empty($marketIds)) {
            $baseQuery->whereIn('orders.market_id', $marketIds);
        }

        $reasons = (clone $baseQuery)
            ->select(
                DB::raw("COALESCE(NULLIF(TRIM(order_item_returns.reason_code), ''), 'other') as reason_key"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw("COALESCE(NULLIF(TRIM(order_item_returns.reason_code), ''), 'other')"))
            ->orderBy('count', 'desc')
            ->take(8)
            ->get();

        $sellerFaultCount = (clone $baseQuery)
            ->where('order_item_returns.reason_type', 'SELLER_FAULT')
            ->count();

        $totalReturns = (clone $baseQuery)->count();

        $reasonsList = $reasons->map(function ($r) {
            $enum = ReturnReasonCodeEnum::tryFrom($r->reason_key);
            return $enum ? $enum->label() : Str::headline($r->reason_key);
        })->values()->all();
        $countsList = $reasons->map(fn($r) => (int) $r->count)->values()->all();

        return [
            'reasons' => $reasonsList,
            'counts' => $countsList,
            'summary' => [
                'total_returns' => $totalReturns,
                'seller_fault_count' => $sellerFaultCount,
                'customer_fault_count' => $totalReturns - $sellerFaultCount,
            ],
            'currency_symbol' => $this->resolveCurrencySymbol($filters),
        ];
    }

    public function getStoresData(int $sellerId, array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $grossSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $netSql = $this->getCurrencyConversionSql('order_items.seller_commission_amount', $market);
        $activeCond = $this->getActiveItemCondition();
        $returnedCond = $this->getReturnedItemCondition();

        $query = DB::table('stores')
            ->where('stores.seller_id', $sellerId)
            ->leftJoin('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.store_id', '=', 'stores.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'stores.id',
                'stores.name',
                'stores.slug',
                DB::raw("COUNT(DISTINCT CASE WHEN {$activeCond} THEN order_items.order_id END) as total_orders"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.quantity ELSE 0 END), 0) as units_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$grossSql} ELSE 0 END), 0) as gross_sales"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$netSql} ELSE 0 END), 0) as net_earnings"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$returnedCond} THEN order_items.id END) as returns_count")
            )
            ->groupBy('stores.id', 'stores.name', 'stores.slug');

        if (!empty($search)) {
            $query->where('stores.name', 'like', "%{$search}%");
        }

        $totalRecords = DB::table('stores')->where('seller_id', $sellerId)->count();
        $filteredRecords = $query->get()->count();

        $query->orderBy('gross_sales', 'desc');
        $items = $query->skip($start)->take($length)->get();

        $data = $items->map(function ($row) use ($filters) {
            $returns = (int) $row->returns_count;
            $units = (int) $row->units_sold;
            $totalShipped = $units + $returns;
            $returnRate = $totalShipped > 0 ? round(($returns / $totalShipped) * 100, 2) : 0;

            return [
                'id' => $row->id,
                'name' => '<span class="fw-bold">' . e($row->name) . '</span>',
                'total_orders' => number_format((int) $row->total_orders),
                'units_sold' => number_format($units),
                'gross_sales' => $this->formatAmount((float) $row->gross_sales, $filters),
                'net_earnings' => '<span class="fw-bold text-success">' . $this->formatAmount((float) $row->net_earnings, $filters) . '</span>',
                'returns_count' => number_format($returns),
                'return_rate' => $returnRate . '%',
            ];
        });

        return [
            'draw' => request()->get('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ];
    }

    public function getStoresChart(int $sellerId, array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $grossSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $netSql = $this->getCurrencyConversionSql('order_items.seller_commission_amount', $market);
        $activeCond = $this->getActiveItemCondition();

        $stores = DB::table('stores')
            ->where('stores.seller_id', $sellerId)
            ->leftJoin('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.store_id', '=', 'stores.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'stores.name',
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$grossSql} ELSE 0 END), 0) as gross_sales"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$netSql} ELSE 0 END), 0) as net_earnings")
            )
            ->groupBy('stores.id', 'stores.name')
            ->orderBy('gross_sales', 'desc')
            ->get();

        return [
            'stores' => $stores->pluck('name')->all(),
            'gross_series' => $stores->map(fn($s) => round((float) $s->gross_sales, 2))->all(),
            'net_series' => $stores->map(fn($s) => round((float) $s->net_earnings, 2))->all(),
            'summary' => [
                'total_gross' => $this->formatAmount((float) $stores->sum('gross_sales'), $filters),
                'total_net' => $this->formatAmount((float) $stores->sum('net_earnings'), $filters),
            ],
            'currency_symbol' => $this->resolveCurrencySymbol($filters),
        ];
    }

    protected function getTaxGroupExpression(): string
    {
        $cases = [];
        try {
            $taxClasses = DB::table('tax_classes')
                ->leftJoin('tax_class_tax_rate', 'tax_class_tax_rate.tax_class_id', '=', 'tax_classes.id')
                ->leftJoin('tax_rates', 'tax_rates.id', '=', 'tax_class_tax_rate.tax_rate_id')
                ->select('tax_classes.id', 'tax_classes.title', 'tax_classes.market_id', DB::raw('COALESCE(SUM(tax_rates.rate), 0) as total_rate'))
                ->whereNull('tax_classes.deleted_at')
                ->groupBy('tax_classes.id', 'tax_classes.title', 'tax_classes.market_id')
                ->get();

            foreach ($taxClasses as $tc) {
                $rate = (float) $tc->total_rate;
                $cleanTitle = str_replace("'", "\\'", $tc->title);
                $marketCond = !empty($tc->market_id) ? "AND (orders.market_id = {$tc->market_id} OR orders.market_id IS NULL)" : "";
                if ($rate > 0) {
                    $cases[] = "WHEN ABS(order_items.tax_percent - {$rate}) < 0.01 {$marketCond} THEN '{$cleanTitle}'";
                } elseif (preg_match('/(\d+(?:\.\d+)?)\s*%/i', $tc->title, $matches)) {
                    $parsedRate = (float) $matches[1];
                    if ($parsedRate > 0) {
                        $cases[] = "WHEN ABS(order_items.tax_percent - {$parsedRate}) < 0.01 {$marketCond} THEN '{$cleanTitle}'";
                    }
                }
            }
        } catch (\Throwable) {
        }

        $caseSql = !empty($cases)
            ? "CASE " . implode(' ', $cases) . " ELSE CONCAT('Tax ', ROUND(order_items.tax_percent, 2), '%') END"
            : "CONCAT('Tax ', ROUND(order_items.tax_percent, 2), '%')";

        return "COALESCE(
            (SELECT tc.title FROM tax_classes tc
             JOIN product_taxes pt ON pt.tax_class_id = tc.id
             WHERE pt.product_id = order_items.product_id
               AND tc.deleted_at IS NULL
               AND (tc.market_id = orders.market_id OR tc.market_id IS NULL)
             LIMIT 1),
            {$caseSql}
        )";
    }

    public function getTaxesData(int $sellerId, array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $storeIds = DB::table('stores')->where('seller_id', $sellerId)->pluck('id')->all();
        if (empty($storeIds)) {
            return [
                'draw' => request()->get('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ];
        }

        $taxGroupExpr = $this->getTaxGroupExpression();
        $taxableSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $taxSql = $this->getCurrencyConversionSql('order_items.tax_amount', $market);

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.store_id', $storeIds)
            ->whereBetween('order_items.created_at', [$from, $to])
            ->where('order_items.tax_amount', '>', 0)
            ->where('order_items.tax_percent', '>', 0);

        $this->applyCompletedOrderWhere($query, $marketIds);
        $query->whereRaw($this->getActiveItemCondition());

        $query->select(
                DB::raw("$taxGroupExpr as tax_group_name"),
                'order_items.tax_percent',
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('COUNT(order_items.id) as total_items'),
                DB::raw("COALESCE(SUM({$taxableSql}), 0) as taxable_amount"),
                DB::raw("COALESCE(SUM({$taxSql}), 0) as tax_collected")
            )
            ->groupBy(DB::raw($taxGroupExpr), 'order_items.tax_percent');

        if (!empty($search)) {
            $query->having('tax_group_name', 'like', "%{$search}%");
        }

        $allRows = $query->get();
        $totalRecords = $allRows->count();

        $allowedColumns = [
            'tax_group' => 'tax_group_name',
            'tax_group_name' => 'tax_group_name',
            'tax_slab' => 'order_items.tax_percent',
            'tax_percent' => 'order_items.tax_percent',
            'total_orders' => 'total_orders',
            'taxable_amount' => 'taxable_amount',
            'tax_collected' => 'tax_collected',
        ];

        if ($orderColumn && isset($allowedColumns[$orderColumn])) {
            $query->orderBy($allowedColumns[$orderColumn], $orderDir);
        } else {
            $query->orderBy('tax_collected', 'desc');
        }

        $items = $query->skip($start)->take($length)->get();

        $data = $items->map(function ($row) use ($filters) {
            $taxGroupName = $row->tax_group_name ?: 'Tax';
            $percent = (float) $row->tax_percent;

            return [
                'tax_group' => '<div class="d-flex align-items-center"><span class="fw-bold me-2">' . e(Str::headline($taxGroupName)) . '</span><span class="badge bg-blue-lt">' . $percent . '%</span></div>',
                'tax_slab' => '<span class="badge bg-blue-lt fs-5">' . $percent . '%</span>',
                'total_orders' => number_format((int) $row->total_orders),
                'total_items' => number_format((int) $row->total_items),
                'taxable_amount' => $this->formatAmount((float) $row->taxable_amount, $filters),
                'tax_collected' => '<span class="fw-bold text-primary">' . $this->formatAmount((float) $row->tax_collected, $filters) . '</span>',
            ];
        });

        return [
            'draw' => request()->get('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data,
        ];
    }

    public function getTaxesChart(int $sellerId, array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $storeIds = DB::table('stores')->where('seller_id', $sellerId)->pluck('id')->all();
        if (empty($storeIds)) {
            return [
                'labels' => [],
                'collected_series' => [],
                'taxable_series' => [],
                'summary' => [
                    'total_taxable' => $this->formatAmount(0, $filters),
                    'total_tax_collected' => $this->formatAmount(0, $filters),
                ],
                'currency_symbol' => $this->resolveCurrencySymbol($filters),
            ];
        }

        $taxGroupExpr = $this->getTaxGroupExpression();
        $taxableSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $taxSql = $this->getCurrencyConversionSql('order_items.tax_amount', $market);

        $query = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.store_id', $storeIds)
            ->whereBetween('order_items.created_at', [$from, $to])
            ->where('order_items.tax_amount', '>', 0)
            ->where('order_items.tax_percent', '>', 0);

        $this->applyCompletedOrderWhere($query, $marketIds);
        $query->whereRaw($this->getActiveItemCondition());

        $records = $query->select(
                DB::raw("$taxGroupExpr as tax_group_name"),
                'order_items.tax_percent',
                DB::raw("COALESCE(SUM({$taxSql}), 0) as tax_collected"),
                DB::raw("COALESCE(SUM({$taxableSql}), 0) as taxable_amount")
            )
            ->groupBy(DB::raw($taxGroupExpr), 'order_items.tax_percent')
            ->orderBy('tax_collected', 'desc')
            ->get();

        $labels = $records->map(function ($r) {
            $name = Str::headline($r->tax_group_name ?: 'Tax');
            $pct = (float) $r->tax_percent;
            return Str::contains($name, '%') ? $name : $name . ' (' . $pct . '%)';
        })->all();

        return [
            'labels' => $labels,
            'collected_series' => $records->map(fn($r) => round((float) $r->tax_collected, 2))->all(),
            'taxable_series' => $records->map(fn($r) => round((float) $r->taxable_amount, 2))->all(),
            'summary' => [
                'total_taxable' => $this->formatAmount((float) $records->sum('taxable_amount'), $filters),
                'total_tax_collected' => $this->formatAmount((float) $records->sum('tax_collected'), $filters),
            ],
            'currency_symbol' => $this->resolveCurrencySymbol($filters),
        ];
    }
}
