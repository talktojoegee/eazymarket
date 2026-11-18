<?php

namespace App\Services\Reports;

use App\Enums\Order\OrderItemStatusEnum;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Market;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerStatement;
use App\Models\Store;
use App\Models\TaxRate;
use App\Services\CurrencyService;
use App\Services\Fx\FxRateService;
use App\Support\PricePresenter;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminReportService
{
    public function __construct(
        protected CurrencyService $currencyService,
        protected FxRateService $fxRateService
    ) {}

    public function resolveMarket(array $filters): ?Market
    {
        $marketId = !empty($filters['market_id']) ? (int) $filters['market_id'] : null;
        if ($marketId) {
            return Market::find($marketId);
        }

        $marketIds = array_filter((array) ($filters['market_ids'] ?? []));
        if (count($marketIds) === 1) {
            return Market::find(reset($marketIds));
        }

        if (empty($marketIds) && empty($filters['market_id'])) {
            $activeMarkets = Market::where('status', 'active')->get();
            if ($activeMarkets->count() === 1) {
                return $activeMarkets->first();
            }
        }

        return null;
    }

    public function resolveMarketIds(array $filters): array
    {
        if (!empty($filters['market_id'])) {
            return [(int) $filters['market_id']];
        }

        $marketIds = array_filter((array) ($filters['market_ids'] ?? []));
        if (!empty($marketIds)) {
            return array_map('intval', $marketIds);
        }

        $activeMarkets = Market::where('status', 'active')->get();
        if ($activeMarkets->count() === 1) {
            return [$activeMarkets->first()->id];
        }

        return [];
    }

    public function resolveCurrencySymbol(array $filters): string
    {
        $market = $this->resolveMarket($filters);
        if ($market && $market->currency_code) {
            return PricePresenter::symbolFor($market->currency_code);
        }

        return $this->currencyService->getSymbol() ?: '$';
    }

    public function formatAmount(float $amount, array $filters = []): string
    {
        $market = $this->resolveMarket($filters);
        if ($market) {
            return PricePresenter::format($amount, $market, PricePresenter::symbolFor($market->currency_code));
        }

        return $this->currencyService->format($amount);
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

    protected function getActiveItemCondition(): string
    {
        return "order_items.status NOT IN ('cancelled', 'returned', 'refunded') AND NOT EXISTS (SELECT 1 FROM order_item_returns oir WHERE oir.order_item_id = order_items.id AND oir.return_status NOT IN ('declined', 'cancelled', 'seller_rejected'))";
    }

    protected function getReturnedItemCondition(): string
    {
        return "order_items.status IN ('returned', 'refunded') OR EXISTS (SELECT 1 FROM order_item_returns oir WHERE oir.order_item_id = order_items.id AND oir.return_status NOT IN ('declined', 'cancelled', 'seller_rejected'))";
    }

    public function resolveDateRange(array $filters): array
    {
        $preset = $filters['date_range'] ?? 'last_30_days';
        $timezone = config('app.timezone');

        return match ($preset) {
            'today' => [
                Carbon::now($timezone)->startOfDay(),
                Carbon::now($timezone)->endOfDay(),
            ],
            'yesterday' => [
                Carbon::now($timezone)->subDay()->startOfDay(),
                Carbon::now($timezone)->subDay()->endOfDay(),
            ],
            'last_7_days' => [
                Carbon::now($timezone)->subDays(6)->startOfDay(),
                Carbon::now($timezone)->endOfDay(),
            ],
            'this_month' => [
                Carbon::now($timezone)->startOfMonth(),
                Carbon::now($timezone)->endOfMonth(),
            ],
            'last_month' => [
                Carbon::now($timezone)->subMonth()->startOfMonth(),
                Carbon::now($timezone)->subMonth()->endOfMonth(),
            ],
            'custom' => [
                !empty($filters['date_from']) ? Carbon::parse($filters['date_from'], $timezone)->startOfDay() : Carbon::now($timezone)->subDays(29)->startOfDay(),
                !empty($filters['date_to']) ? Carbon::parse($filters['date_to'], $timezone)->endOfDay() : Carbon::now($timezone)->endOfDay(),
            ],
            default => [
                Carbon::now($timezone)->subDays(29)->startOfDay(),
                Carbon::now($timezone)->endOfDay(),
            ],
        };
    }

    public function getCategoriesData(array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);
        $categoryIds = array_filter((array) ($filters['category_ids'] ?? []));

        $gmvSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);

        $activeCond = $this->getActiveItemCondition();
        $returnedCond = $this->getReturnedItemCondition();

        $query = DB::table('categories')
            ->leftJoin('products', 'products.category_id', '=', 'categories.id')
            ->leftJoin('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.product_id', '=', 'products.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('categories as parent_cat', 'parent_cat.id', '=', 'categories.parent_id')
            ->select(
                'categories.id',
                'categories.title',
                'categories.slug',
                'categories.status',
                'parent_cat.title as parent_title',
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.quantity ELSE 0 END), 0) as units_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$gmvSql} ELSE 0 END), 0) as total_gmv"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as total_commission"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$activeCond} THEN order_items.order_id END) as total_orders"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$returnedCond} THEN order_items.id END) as returns_count")
            )
            ->groupBy('categories.id', 'categories.title', 'categories.slug', 'categories.status', 'parent_cat.title');

        if (!empty($categoryIds)) {
            $query->whereIn('categories.id', $categoryIds);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('categories.title', 'like', "%{$search}%")
                    ->orWhere('categories.slug', 'like', "%{$search}%")
                    ->orWhere('parent_cat.title', 'like', "%{$search}%");
            });
        }

        $totalRecords = DB::table('categories')->count();
        $filteredRecords = $query->get()->count();

        $allowedColumns = [
            'title' => 'categories.title',
            'units_sold' => 'units_sold',
            'total_gmv' => 'total_gmv',
            'total_commission' => 'total_commission',
            'total_orders' => 'total_orders',
            'returns_count' => 'returns_count',
        ];

        $sortBy = $filters['sort_by'] ?? null;
        if ($sortBy === 'most_selling') {
            $query->orderBy('units_sold', 'desc');
        } elseif ($sortBy === 'high_revenue') {
            $query->orderBy('total_gmv', 'desc');
        } elseif ($sortBy === 'high_commission') {
            $query->orderBy('total_commission', 'desc');
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
            $hierarchy = $row->parent_title ? $row->parent_title . ' &rsaquo; <span class="fw-bold">' . e($row->title) . '</span>' : '<span class="fw-bold">' . e($row->title) . '</span>';

            return [
                'id' => $row->id,
                'category' => $hierarchy,
                'slug' => $row->slug,
                'units_sold' => number_format($units),
                'total_orders' => number_format((int) $row->total_orders),
                'total_gmv' => $this->formatAmount((float) $row->total_gmv, $filters),
                'total_commission' => $this->formatAmount((float) $row->total_commission, $filters),
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

    public function getCategoriesChart(array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $gmvSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);
        $activeCond = $this->getActiveItemCondition();

        $topCategories = DB::table('categories')
            ->join('products', 'products.category_id', '=', 'categories.id')
            ->join('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.product_id', '=', 'products.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'categories.title',
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.quantity ELSE 0 END), 0) as units_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$gmvSql} ELSE 0 END), 0) as total_gmv"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as total_commission")
            )
            ->groupBy('categories.id', 'categories.title')
            ->orderBy('total_gmv', 'desc')
            ->take(8)
            ->get();

        $summary = [
            'total_units' => (int) $topCategories->sum('units_sold'),
            'total_gmv' => $this->formatAmount((float) $topCategories->sum('total_gmv'), $filters),
            'total_commission' => $this->formatAmount((float) $topCategories->sum('total_commission'), $filters),
        ];

        return [
            'categories' => $topCategories->pluck('title')->all(),
            'gmv_series' => $topCategories->map(fn($c) => round((float) $c->total_gmv, 2))->all(),
            'commission_series' => $topCategories->map(fn($c) => round((float) $c->total_commission, 2))->all(),
            'units_series' => $topCategories->map(fn($c) => (int) $c->units_sold)->all(),
            'summary' => $summary,
            'currency_symbol' => $this->resolveCurrencySymbol($filters),
        ];
    }

    public function getBrandsData(array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);
        $brandIds = array_filter((array) ($filters['brand_ids'] ?? []));

        $gmvSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);

        $activeCond = $this->getActiveItemCondition();
        $returnedCond = $this->getReturnedItemCondition();

        $query = DB::table('brands')
            ->leftJoin('products', 'products.brand_id', '=', 'brands.id')
            ->leftJoin('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.product_id', '=', 'products.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'brands.id',
                'brands.title',
                'brands.status',
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.quantity ELSE 0 END), 0) as units_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$gmvSql} ELSE 0 END), 0) as total_gmv"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as total_commission"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$activeCond} THEN order_items.order_id END) as total_orders"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$returnedCond} THEN order_items.id END) as returns_count"),
                DB::raw('COUNT(DISTINCT products.id) as products_count')
            )
            ->groupBy('brands.id', 'brands.title', 'brands.status');

        if (!empty($brandIds)) {
            $query->whereIn('brands.id', $brandIds);
        }

        if (!empty($search)) {
            $query->where('brands.title', 'like', "%{$search}%");
        }

        $totalRecords = DB::table('brands')->count();
        $filteredRecords = $query->get()->count();

        $allowedColumns = [
            'title' => 'brands.title',
            'units_sold' => 'units_sold',
            'total_gmv' => 'total_gmv',
            'total_commission' => 'total_commission',
            'total_orders' => 'total_orders',
            'returns_count' => 'returns_count',
            'products_count' => 'products_count',
        ];

        $sortBy = $filters['sort_by'] ?? null;
        if ($sortBy === 'most_selling') {
            $query->orderBy('units_sold', 'desc');
        } elseif ($sortBy === 'high_revenue') {
            $query->orderBy('total_gmv', 'desc');
        } elseif ($sortBy === 'high_commission') {
            $query->orderBy('total_commission', 'desc');
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
                'brand' => '<span class="fw-bold">' . e($row->title) . '</span>',
                'units_sold' => number_format($units),
                'total_orders' => number_format((int) $row->total_orders),
                'total_gmv' => $this->formatAmount((float) $row->total_gmv, $filters),
                'total_commission' => $this->formatAmount((float) $row->total_commission, $filters),
                'products_count' => number_format((int) $row->products_count),
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

    public function getBrandsChart(array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $gmvSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);
        $activeCond = $this->getActiveItemCondition();

        $topBrands = DB::table('brands')
            ->join('products', 'products.brand_id', '=', 'brands.id')
            ->join('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.product_id', '=', 'products.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'brands.title',
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.quantity ELSE 0 END), 0) as units_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$gmvSql} ELSE 0 END), 0) as total_gmv"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as total_commission")
            )
            ->groupBy('brands.id', 'brands.title')
            ->orderBy('total_gmv', 'desc')
            ->take(8)
            ->get();

        return [
            'brands' => $topBrands->pluck('title')->all(),
            'gmv_series' => $topBrands->map(fn($b) => round((float) $b->total_gmv, 2))->all(),
            'commission_series' => $topBrands->map(fn($b) => round((float) $b->total_commission, 2))->all(),
            'units_series' => $topBrands->map(fn($b) => (int) $b->units_sold)->all(),
            'summary' => [
                'total_units' => (int) $topBrands->sum('units_sold'),
                'total_gmv' => $this->formatAmount((float) $topBrands->sum('total_gmv'), $filters),
                'total_commission' => $this->formatAmount((float) $topBrands->sum('total_commission'), $filters),
            ],
            'currency_symbol' => $this->resolveCurrencySymbol($filters),
        ];
    }

    public function getProductsData(array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);
        $categoryIds = array_filter((array) ($filters['category_ids'] ?? []));
        $brandIds = array_filter((array) ($filters['brand_ids'] ?? []));
        $sellerIds = array_filter((array) ($filters['seller_ids'] ?? []));

        $gmvSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);

        $activeCond = $this->getActiveItemCondition();
        $returnedCond = $this->getReturnedItemCondition();

        $query = DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('brands', 'brands.id', '=', 'products.brand_id')
            ->leftJoin('sellers', 'sellers.id', '=', 'products.seller_id')
            ->leftJoin('users as seller_user', 'seller_user.id', '=', 'sellers.user_id')
            ->leftJoin('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.product_id', '=', 'products.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'products.id',
                'products.title',
                'products.slug',
                'categories.title as category_title',
                'brands.title as brand_title',
                'seller_user.name as seller_name',
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.quantity ELSE 0 END), 0) as units_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$gmvSql} ELSE 0 END), 0) as total_gmv"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as total_commission"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$activeCond} THEN order_items.order_id END) as total_orders"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$returnedCond} THEN order_items.id END) as returns_count")
            )
            ->groupBy('products.id', 'products.title', 'products.slug', 'categories.title', 'brands.title', 'seller_user.name');

        if (!empty($categoryIds)) {
            $query->whereIn('products.category_id', $categoryIds);
        }
        if (!empty($brandIds)) {
            $query->whereIn('products.brand_id', $brandIds);
        }
        if (!empty($sellerIds)) {
            $query->whereIn('products.seller_id', $sellerIds);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('products.title', 'like', "%{$search}%")
                    ->orWhere('categories.title', 'like', "%{$search}%")
                    ->orWhere('brands.title', 'like', "%{$search}%")
                    ->orWhere('seller_user.name', 'like', "%{$search}%");
            });
        }

        $totalRecords = DB::table('products')->count();
        $filteredRecords = $query->get()->count();

        $allowedColumns = [
            'title' => 'products.title',
            'units_sold' => 'units_sold',
            'total_gmv' => 'total_gmv',
            'total_commission' => 'total_commission',
            'total_orders' => 'total_orders',
            'returns_count' => 'returns_count',
        ];

        $sortBy = $filters['sort_by'] ?? null;
        if ($sortBy === 'most_selling') {
            $query->orderBy('units_sold', 'desc');
        } elseif ($sortBy === 'high_revenue') {
            $query->orderBy('total_gmv', 'desc');
        } elseif ($sortBy === 'high_commission') {
            $query->orderBy('total_commission', 'desc');
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
                'product' => '<span class="fw-bold">' . e($row->title) . '</span>',
                'category' => e($row->category_title ?? '—'),
                'brand' => e($row->brand_title ?? '—'),
                'seller' => e($row->seller_name ?? '—'),
                'units_sold' => number_format($units),
                'total_orders' => number_format((int) $row->total_orders),
                'total_gmv' => $this->formatAmount((float) $row->total_gmv, $filters),
                'total_commission' => $this->formatAmount((float) $row->total_commission, $filters),
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

    public function getProductsChart(array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $gmvSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);
        $activeCond = $this->getActiveItemCondition();

        $topProducts = DB::table('products')
            ->join('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.product_id', '=', 'products.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'products.title',
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.quantity ELSE 0 END), 0) as units_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$gmvSql} ELSE 0 END), 0) as total_gmv"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as total_commission")
            )
            ->groupBy('products.id', 'products.title')
            ->orderBy('total_gmv', 'desc')
            ->take(8)
            ->get();

        return [
            'products' => $topProducts->map(fn($p) => Str::limit($p->title, 20))->all(),
            'gmv_series' => $topProducts->map(fn($p) => round((float) $p->total_gmv, 2))->all(),
            'commission_series' => $topProducts->map(fn($p) => round((float) $p->total_commission, 2))->all(),
            'units_series' => $topProducts->map(fn($p) => (int) $p->units_sold)->all(),
            'summary' => [
                'total_units' => (int) $topProducts->sum('units_sold'),
                'total_gmv' => $this->formatAmount((float) $topProducts->sum('total_gmv'), $filters),
                'total_commission' => $this->formatAmount((float) $topProducts->sum('total_commission'), $filters),
            ],
            'currency_symbol' => $this->resolveCurrencySymbol($filters),
        ];
    }

    public function getSellersData(array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);
        $sellerIds = array_filter((array) ($filters['seller_ids'] ?? []));

        $gmvSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);
        $payoutSql = $this->getCurrencyConversionSql('order_items.seller_commission_amount', $market);

        $activeCond = $this->getActiveItemCondition();
        $returnedCond = $this->getReturnedItemCondition();

        $query = DB::table('sellers')
            ->join('users', 'users.id', '=', 'sellers.user_id')
            ->leftJoin('products', 'products.seller_id', '=', 'sellers.id')
            ->leftJoin('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.product_id', '=', 'products.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'sellers.id',
                'users.name',
                'users.email',
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.quantity ELSE 0 END), 0) as units_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$gmvSql} ELSE 0 END), 0) as total_gmv"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as total_commission"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$payoutSql} ELSE 0 END), 0) as seller_payout"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$activeCond} THEN order_items.order_id END) as total_orders"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$returnedCond} THEN order_items.id END) as returns_count")
            )
            ->groupBy('sellers.id', 'users.name', 'users.email');

        if (!empty($sellerIds)) {
            $query->whereIn('sellers.id', $sellerIds);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                    ->orWhere('users.email', 'like', "%{$search}%");
            });
        }

        $totalRecords = DB::table('sellers')->count();
        $filteredRecords = $query->get()->count();

        $allowedColumns = [
            'name' => 'users.name',
            'units_sold' => 'units_sold',
            'total_gmv' => 'total_gmv',
            'total_commission' => 'total_commission',
            'seller_payout' => 'seller_payout',
            'total_orders' => 'total_orders',
            'returns_count' => 'returns_count',
        ];

        $sortBy = $filters['sort_by'] ?? null;
        if ($sortBy === 'most_sales' || $sortBy === 'most_selling') {
            $query->orderBy('total_orders', 'desc');
        } elseif ($sortBy === 'high_revenue') {
            $query->orderBy('total_gmv', 'desc');
        } elseif ($sortBy === 'profitable_for_market') {
            $query->orderBy('total_commission', 'desc');
        } elseif ($orderColumn && isset($allowedColumns[$orderColumn])) {
            $query->orderBy($allowedColumns[$orderColumn], $orderDir);
        } else {
            $query->orderBy('total_orders', 'desc');
        }

        $items = $query->skip($start)->take($length)->get();

        $data = $items->map(function ($row) use ($filters) {
            $returns = (int) $row->returns_count;
            $units = (int) $row->units_sold;
            $totalShipped = $units + $returns;
            $returnRate = $totalShipped > 0 ? round(($returns / $totalShipped) * 100, 2) : 0;

            $gmvFormatted = $this->formatAmount((float) $row->total_gmv, $filters);
            $commissionFormatted = $this->formatAmount((float) $row->total_commission, $filters);
            $payoutFormatted = $this->formatAmount((float) $row->seller_payout, $filters);

            return [
                'id' => $row->id,
                'name' => '<span class="fw-bold">' . e($row->name) . '</span><div class="small text-muted">' . e($row->email) . '</div>',
                'total_orders' => number_format((int) $row->total_orders),
                'units_sold' => number_format($units),
                'total_gmv' => $gmvFormatted,
                'total_commission' => $commissionFormatted,
                'seller_payout' => $payoutFormatted,
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

    public function getSellersChart(array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $gmvSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);
        $activeCond = $this->getActiveItemCondition();

        $topSellers = DB::table('sellers')
            ->join('users', 'users.id', '=', 'sellers.user_id')
            ->join('products', 'products.seller_id', '=', 'sellers.id')
            ->join('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.product_id', '=', 'products.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'users.name',
                DB::raw("COUNT(DISTINCT CASE WHEN {$activeCond} THEN order_items.order_id END) as total_orders"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$gmvSql} ELSE 0 END), 0) as total_gmv"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as total_commission")
            )
            ->groupBy('sellers.id', 'users.name')
            ->orderBy('total_gmv', 'desc')
            ->take(8)
            ->get();

        return [
            'sellers' => $topSellers->pluck('name')->all(),
            'orders_series' => $topSellers->map(fn($s) => (int) $s->total_orders)->all(),
            'gmv_series' => $topSellers->map(fn($s) => round((float) $s->total_gmv, 2))->all(),
            'commission_series' => $topSellers->map(fn($s) => round((float) $s->total_commission, 2))->all(),
            'summary' => [
                'total_orders' => (int) $topSellers->sum('total_orders'),
                'total_gmv' => $this->formatAmount((float) $topSellers->sum('total_gmv'), $filters),
                'total_commission' => $this->formatAmount((float) $topSellers->sum('total_commission'), $filters),
            ],
            'currency_symbol' => $this->resolveCurrencySymbol($filters),
        ];
    }

    public function getStoresData(array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);
        $storeIds = array_filter((array) ($filters['store_ids'] ?? []));
        $sellerIds = array_filter((array) ($filters['seller_ids'] ?? []));

        $gmvSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);

        $activeCond = $this->getActiveItemCondition();
        $returnedCond = $this->getReturnedItemCondition();

        $query = DB::table('stores')
            ->leftJoin('sellers', 'sellers.id', '=', 'stores.seller_id')
            ->leftJoin('users as seller_user', 'seller_user.id', '=', 'sellers.user_id')
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
                'seller_user.name as seller_name',
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.quantity ELSE 0 END), 0) as units_sold"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$gmvSql} ELSE 0 END), 0) as total_gmv"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as total_commission"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$activeCond} THEN order_items.order_id END) as total_orders"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$returnedCond} THEN order_items.id END) as returns_count")
            )
            ->groupBy('stores.id', 'stores.name', 'stores.slug', 'seller_user.name');

        if (!empty($storeIds)) {
            $query->whereIn('stores.id', $storeIds);
        }
        if (!empty($sellerIds)) {
            $query->whereIn('stores.seller_id', $sellerIds);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('stores.name', 'like', "%{$search}%")
                    ->orWhere('seller_user.name', 'like', "%{$search}%");
            });
        }

        $totalRecords = DB::table('stores')->count();
        $filteredRecords = $query->get()->count();

        $allowedColumns = [
            'name' => 'stores.name',
            'units_sold' => 'units_sold',
            'total_gmv' => 'total_gmv',
            'total_commission' => 'total_commission',
            'total_orders' => 'total_orders',
            'returns_count' => 'returns_count',
        ];

        $sortBy = $filters['sort_by'] ?? null;
        if ($sortBy === 'most_sales' || $sortBy === 'most_selling') {
            $query->orderBy('total_orders', 'desc');
        } elseif ($sortBy === 'high_revenue') {
            $query->orderBy('total_gmv', 'desc');
        } elseif ($sortBy === 'profitable_for_market') {
            $query->orderBy('total_commission', 'desc');
        } elseif ($orderColumn && isset($allowedColumns[$orderColumn])) {
            $query->orderBy($allowedColumns[$orderColumn], $orderDir);
        } else {
            $query->orderBy('total_orders', 'desc');
        }

        $items = $query->skip($start)->take($length)->get();

        $data = $items->map(function ($row) use ($filters) {
            $returns = (int) $row->returns_count;
            $units = (int) $row->units_sold;
            $totalShipped = $units + $returns;
            $returnRate = $totalShipped > 0 ? round(($returns / $totalShipped) * 100, 2) : 0;

            return [
                'id' => $row->id,
                'name' => '<span class="fw-bold">' . e($row->name) . '</span>',
                'seller_name' => e($row->seller_name ?? '—'),
                'total_orders' => number_format((int) $row->total_orders),
                'units_sold' => number_format($units),
                'total_gmv' => $this->formatAmount((float) $row->total_gmv, $filters),
                'total_commission' => $this->formatAmount((float) $row->total_commission, $filters),
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

    public function getStoresChart(array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);

        $gmvSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);
        $activeCond = $this->getActiveItemCondition();

        $topStores = DB::table('stores')
            ->join('order_items', function ($join) use ($from, $to, $marketIds) {
                $join->on('order_items.store_id', '=', 'stores.id')
                    ->whereBetween('order_items.created_at', [$from, $to]);
                $this->applyCompletedOrderCriteria($join, $marketIds);
            })
            ->leftJoin('orders', 'orders.id', '=', 'order_items.order_id')
            ->select(
                'stores.name',
                DB::raw("COUNT(DISTINCT CASE WHEN {$activeCond} THEN order_items.order_id END) as total_orders"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$gmvSql} ELSE 0 END), 0) as total_gmv"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as total_commission")
            )
            ->groupBy('stores.id', 'stores.name')
            ->orderBy('total_gmv', 'desc')
            ->take(8)
            ->get();

        return [
            'stores' => $topStores->pluck('name')->all(),
            'orders_series' => $topStores->map(fn($s) => (int) $s->total_orders)->all(),
            'gmv_series' => $topStores->map(fn($s) => round((float) $s->total_gmv, 2))->all(),
            'commission_series' => $topStores->map(fn($s) => round((float) $s->total_commission, 2))->all(),
            'summary' => [
                'total_orders' => (int) $topStores->sum('total_orders'),
                'total_gmv' => $this->formatAmount((float) $topStores->sum('total_gmv'), $filters),
                'total_commission' => $this->formatAmount((float) $topStores->sum('total_commission'), $filters),
            ],
            'currency_symbol' => $this->resolveCurrencySymbol($filters),
        ];
    }

    public function getEarningsData(array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketId = $market?->id ?? (!empty($filters['market_id']) ? (int) $filters['market_id'] : null);
        $activeCond = $this->getActiveItemCondition();

        $query = DB::table('orders')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('orders.fulfillment_status', 'fulfilled')
                    ->orWhereIn('orders.status', ['archived', 'completed']);
            })
            ->leftJoin('markets', 'markets.id', '=', 'orders.market_id')
            ->leftJoin('order_items', function ($join) {
                $join->on('order_items.order_id', '=', 'orders.id')
                    ->where('order_items.status', '!=', 'cancelled');
            })
            ->select(
                DB::raw('DATE(orders.created_at) as order_date'),
                'orders.market_id',
                'markets.name as market_name',
                'markets.currency_code',
                DB::raw("COUNT(DISTINCT CASE WHEN {$activeCond} THEN orders.id END) as total_orders"),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.admin_commission_amount ELSE 0 END), 0) as total_commission"),
                DB::raw('COALESCE(SUM(DISTINCT orders.platform_fee), 0) as total_platform_fee'),
                DB::raw('COALESCE(SUM(DISTINCT orders.cod_fee), 0) as total_cod_fee')
            )
            ->groupBy(DB::raw('DATE(orders.created_at)'), 'orders.market_id', 'markets.name', 'markets.currency_code');

        if ($marketId) {
            $query->where('orders.market_id', $marketId);
        }

        $earningWay = $filters['earning_way'] ?? null;
        if ($earningWay === 'commission') {
            $query->havingRaw("COALESCE(SUM(CASE WHEN {$activeCond} THEN order_items.admin_commission_amount ELSE 0 END), 0) > 0");
        } elseif ($earningWay === 'platform_fee') {
            $query->havingRaw('COALESCE(SUM(DISTINCT orders.platform_fee), 0) > 0');
        } elseif ($earningWay === 'cod_charges') {
            $query->havingRaw('COALESCE(SUM(DISTINCT orders.cod_fee), 0) > 0');
        }

        $rawResults = $query->get();
        $totalRecords = $rawResults->count();

        $allowedColumns = [
            'order_date' => 'order_date',
            'market_name' => 'market_name',
            'total_orders' => 'total_orders',
            'total_commission' => 'total_commission',
            'total_platform_fee' => 'total_platform_fee',
            'total_cod_fee' => 'total_cod_fee',
        ];

        if ($orderColumn && isset($allowedColumns[$orderColumn])) {
            $query->orderBy($allowedColumns[$orderColumn], $orderDir);
        } else {
            $query->orderBy('order_date', 'desc');
        }

        $items = $query->skip($start)->take($length)->get();
        $allMarkets = Market::all()->keyBy('id');

        $data = $items->map(function ($row) use ($market, $allMarkets) {
            $commission = (float) $row->total_commission;
            $platformFee = (float) $row->total_platform_fee;
            $codFee = (float) $row->total_cod_fee;
            $netEarnings = $commission + $platformFee + $codFee;

            $rowMarket = $market ?? ($row->market_id && isset($allMarkets[$row->market_id]) ? $allMarkets[$row->market_id] : null);

            if ($rowMarket) {
                $commissionFormatted = PricePresenter::format($commission, $rowMarket, PricePresenter::symbolFor($rowMarket->currency_code));
                $platformFeeFormatted = PricePresenter::format($platformFee, $rowMarket, PricePresenter::symbolFor($rowMarket->currency_code));
                $codFeeFormatted = PricePresenter::format($codFee, $rowMarket, PricePresenter::symbolFor($rowMarket->currency_code));
                $netFormatted = PricePresenter::format($netEarnings, $rowMarket, PricePresenter::symbolFor($rowMarket->currency_code));
            } else {
                $commissionFormatted = $this->currencyService->format($commission);
                $platformFeeFormatted = $this->currencyService->format($platformFee);
                $codFeeFormatted = $this->currencyService->format($codFee);
                $netFormatted = $this->currencyService->format($netEarnings);
            }

            return [
                'order_date' => $row->order_date,
                'market_name' => e($row->market_name ?? '—') . ' (' . e($row->currency_code ?? '') . ')',
                'total_orders' => number_format((int) $row->total_orders),
                'total_commission' => $commissionFormatted,
                'total_platform_fee' => $platformFeeFormatted,
                'total_cod_fee' => $codFeeFormatted,
                'net_earnings' => '<span class="fw-bold text-success">' . $netFormatted . '</span>',
            ];
        });

        return [
            'draw' => request()->get('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data,
        ];
    }

    public function getEarningsChart(array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketId = $market?->id ?? (!empty($filters['market_id']) ? (int) $filters['market_id'] : null);

        $commSql = $this->getCurrencyConversionSql('order_items.admin_commission_amount', $market);
        $platSql = $this->getCurrencyConversionSql('orders.platform_fee', $market);
        $codSql = $this->getCurrencyConversionSql('orders.cod_fee', $market);
        $activeCond = $this->getActiveItemCondition();

        $query = DB::table('orders')
            ->whereBetween('orders.created_at', [$from, $to])
            ->where('orders.status', '!=', 'cancelled')
            ->where(function ($q) {
                $q->where('orders.fulfillment_status', 'fulfilled')
                    ->orWhereIn('orders.status', ['archived', 'completed']);
            })
            ->leftJoin('order_items', function ($join) {
                $join->on('order_items.order_id', '=', 'orders.id')
                    ->where('order_items.status', '!=', 'cancelled');
            })
            ->select(
                DB::raw('DATE(orders.created_at) as order_date'),
                DB::raw("COALESCE(SUM(CASE WHEN {$activeCond} THEN {$commSql} ELSE 0 END), 0) as total_commission"),
                DB::raw("COALESCE(SUM(DISTINCT {$platSql}), 0) as total_platform_fee"),
                DB::raw("COALESCE(SUM(DISTINCT {$codSql}), 0) as total_cod_fee")
            )
            ->groupBy(DB::raw('DATE(orders.created_at)'))
            ->orderBy('order_date', 'asc');

        if ($marketId) {
            $query->where('orders.market_id', $marketId);
        }

        $records = $query->get();

        $dates = $records->pluck('order_date')->all();
        $commissionSeries = $records->map(fn($r) => round((float) $r->total_commission, 2))->all();
        $platformFeeSeries = $records->map(fn($r) => round((float) $r->total_platform_fee, 2))->all();
        $codFeeSeries = $records->map(fn($r) => round((float) $r->total_cod_fee, 2))->all();

        $totalCommission = (float) $records->sum('total_commission');
        $totalPlatformFee = (float) $records->sum('total_platform_fee');
        $totalCodFee = (float) $records->sum('total_cod_fee');
        $totalNet = $totalCommission + $totalPlatformFee + $totalCodFee;

        if ($market) {
            $symbol = PricePresenter::symbolFor($market->currency_code);
            $summaryFormatted = [
                'total_commission' => PricePresenter::format($totalCommission, $market, $symbol),
                'total_platform_fee' => PricePresenter::format($totalPlatformFee, $market, $symbol),
                'total_cod_fee' => PricePresenter::format($totalCodFee, $market, $symbol),
                'total_net' => PricePresenter::format($totalNet, $market, $symbol),
            ];
        } else {
            $summaryFormatted = [
                'total_commission' => $this->currencyService->format($totalCommission),
                'total_platform_fee' => $this->currencyService->format($totalPlatformFee),
                'total_cod_fee' => $this->currencyService->format($totalCodFee),
                'total_net' => $this->currencyService->format($totalNet),
            ];
        }

        $earningWay = $filters['earning_way'] ?? null;
        if ($earningWay === 'commission') {
            $series = [
                ['name' => __('labels.commission_earned'), 'data' => $commissionSeries]
            ];
            $colors = ['#206bc4'];
        } elseif ($earningWay === 'platform_fee') {
            $series = [
                ['name' => __('labels.platform_fees'), 'data' => $platformFeeSeries]
            ];
            $colors = ['#4299e1'];
        } elseif ($earningWay === 'cod_charges') {
            $series = [
                ['name' => __('labels.cod_charges'), 'data' => $codFeeSeries]
            ];
            $colors = ['#f59f00'];
        } else {
            $series = [
                ['name' => __('labels.commission_earned'), 'data' => $commissionSeries],
                ['name' => __('labels.platform_fees'), 'data' => $platformFeeSeries],
                ['name' => __('labels.cod_charges'), 'data' => $codFeeSeries],
            ];
            $colors = ['#206bc4', '#4299e1', '#f59f00'];
        }

        return [
            'dates' => $dates,
            'series' => $series,
            'colors' => $colors,
            'commission_series' => $commissionSeries,
            'platform_fee_series' => $platformFeeSeries,
            'cod_fee_series' => $codFeeSeries,
            'summary' => $summaryFormatted,
            'currency_symbol' => $market ? PricePresenter::symbolFor($market->currency_code) : ($this->currencyService->getSymbol() ?: '$'),
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

    public function getTaxesData(array $filters, int $start = 0, int $length = 10, ?string $search = null, ?string $orderColumn = null, string $orderDir = 'desc'): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);
        $activeCond = $this->getActiveItemCondition();

        $taxGroupExpr = $this->getTaxGroupExpression();

        $query = DB::table('order_items')
            ->join('orders', function ($join) {
                $join->on('orders.id', '=', 'order_items.order_id')
                    ->where('orders.status', '!=', 'cancelled')
                    ->where(function ($q) {
                        $q->where('orders.fulfillment_status', 'fulfilled')
                            ->orWhereIn('orders.status', ['archived', 'completed'])
                            ->orWhere('order_items.fulfillment_status', 'fulfilled');
                    });
            })
            ->where('order_items.status', '!=', 'cancelled')
            ->whereRaw($activeCond)
            ->where('order_items.tax_amount', '>', 0)
            ->where('order_items.tax_percent', '>', 0)
            ->leftJoin('markets', 'markets.id', '=', 'orders.market_id')
            ->whereBetween('order_items.created_at', [$from, $to])
            ->select(
                DB::raw("$taxGroupExpr as tax_group_name"),
                'order_items.tax_percent',
                'orders.market_id',
                'markets.name as market_name',
                'markets.currency_code',
                DB::raw('COUNT(DISTINCT orders.id) as total_orders'),
                DB::raw('COUNT(order_items.id) as total_items'),
                DB::raw('COALESCE(SUM(order_items.subtotal), 0) as taxable_amount'),
                DB::raw('COALESCE(SUM(order_items.tax_amount), 0) as tax_collected')
            )
            ->groupBy(
                DB::raw($taxGroupExpr),
                'order_items.tax_percent',
                'orders.market_id',
                'markets.name',
                'markets.currency_code'
            );

        if (!empty($marketIds)) {
            $query->whereIn('orders.market_id', $marketIds);
        }

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
            'market_name' => 'market_name',
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

        $selectedMarket = $market;
        $allMarkets = Market::all()->keyBy('id');

        $data = $items->map(function ($row) use ($selectedMarket, $allMarkets) {
            $taxGroupName = $row->tax_group_name ?: 'Tax';
            $percent = (float) $row->tax_percent;
            $taxable = (float) $row->taxable_amount;
            $collected = (float) $row->tax_collected;

            $rowMarket = $selectedMarket ?? ($row->market_id && isset($allMarkets[$row->market_id]) ? $allMarkets[$row->market_id] : null);

            $taxableFormatted = $rowMarket ? PricePresenter::format($taxable, $rowMarket, PricePresenter::symbolFor($rowMarket->currency_code)) : $this->currencyService->format($taxable);
            $collectedFormatted = $rowMarket ? PricePresenter::format($collected, $rowMarket, PricePresenter::symbolFor($rowMarket->currency_code)) : $this->currencyService->format($collected);

            return [
                'tax_group' => '<div class="d-flex align-items-center"><span class="fw-bold me-2">' . e(Str::headline($taxGroupName)) . '</span><span class="badge bg-blue-lt">' . $percent . '%</span></div>',
                'tax_slab' => '<span class="badge bg-blue-lt fs-5">' . $percent . '%</span>',
                'market_name' => e($row->market_name ?? '—') . ' (' . e($row->currency_code ?? '') . ')',
                'total_orders' => number_format((int) $row->total_orders),
                'total_items' => number_format((int) $row->total_items),
                'taxable_amount' => $taxableFormatted,
                'tax_collected' => '<span class="fw-bold text-primary">' . $collectedFormatted . '</span>',
            ];
        });

        return [
            'draw' => request()->get('draw', 1),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data,
        ];
    }

    public function getTaxesChart(array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters);
        $market = $this->resolveMarket($filters);
        $marketIds = $this->resolveMarketIds($filters);
        $activeCond = $this->getActiveItemCondition();

        $taxableSql = $this->getCurrencyConversionSql('order_items.subtotal', $market);
        $taxSql = $this->getCurrencyConversionSql('order_items.tax_amount', $market);

        $taxGroupExpr = $this->getTaxGroupExpression();

        $query = DB::table('order_items')
            ->join('orders', function ($join) {
                $join->on('orders.id', '=', 'order_items.order_id')
                    ->where('orders.status', '!=', 'cancelled')
                    ->where(function ($q) {
                        $q->where('orders.fulfillment_status', 'fulfilled')
                            ->orWhereIn('orders.status', ['archived', 'completed'])
                            ->orWhere('order_items.fulfillment_status', 'fulfilled');
                    });
            })
            ->where('order_items.status', '!=', 'cancelled')
            ->whereRaw($activeCond)
            ->where('order_items.tax_amount', '>', 0)
            ->where('order_items.tax_percent', '>', 0)
            ->whereBetween('order_items.created_at', [$from, $to])
            ->select(
                DB::raw("$taxGroupExpr as tax_group_name"),
                'order_items.tax_percent',
                DB::raw("COALESCE(SUM({$taxSql}), 0) as tax_collected"),
                DB::raw("COALESCE(SUM({$taxableSql}), 0) as taxable_amount")
            )
            ->groupBy(DB::raw($taxGroupExpr), 'order_items.tax_percent')
            ->orderBy('tax_collected', 'desc');

        if (!empty($marketIds)) {
            $query->whereIn('orders.market_id', $marketIds);
        }

        $records = $query->get();

        $labels = $records->map(function ($r) {
            $name = Str::headline($r->tax_group_name ?: 'Tax');
            $pct = (float) $r->tax_percent;
            return Str::contains($name, '%') ? $name : $name . ' (' . $pct . '%)';
        })->all();
        $collectedSeries = $records->map(fn($r) => round((float) $r->tax_collected, 2))->all();
        $taxableSeries = $records->map(fn($r) => round((float) $r->taxable_amount, 2))->all();

        return [
            'labels' => $labels,
            'collected_series' => $collectedSeries,
            'taxable_series' => $taxableSeries,
            'summary' => [
                'total_taxable' => $this->formatAmount((float) $records->sum('taxable_amount'), $filters),
                'total_tax_collected' => $this->formatAmount((float) $records->sum('tax_collected'), $filters),
            ],
            'currency_symbol' => $this->resolveCurrencySymbol($filters),
        ];
    }
}
