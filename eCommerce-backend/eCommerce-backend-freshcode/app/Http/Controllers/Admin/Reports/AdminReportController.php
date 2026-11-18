<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Market;
use App\Models\Seller;
use App\Models\Store;
use App\Services\DatatableExportRegistry;
use App\Services\Reports\AdminReportService;
use App\Traits\ChecksPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReportController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        protected AdminReportService $reportService,
        protected DatatableExportRegistry $exportRegistry
    ) {}

    public function categories(Request $request): View
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $markets = Market::where('status', 'active')->orderBy('priority', 'asc')->get();
        $categories = Category::orderBy('title', 'asc')->get();

        $columns = [
            ['data' => 'category', 'label' => __('labels.categories'), 'orderable' => true],
            ['data' => 'slug', 'label' => __('labels.slug'), 'orderable' => false],
            ['data' => 'units_sold', 'label' => __('labels.units_sold'), 'orderable' => true],
            ['data' => 'total_orders', 'label' => __('labels.orders'), 'orderable' => true],
            ['data' => 'total_gmv', 'label' => __('labels.gross_sales'), 'orderable' => true],
            ['data' => 'total_commission', 'label' => __('labels.commission_earned'), 'orderable' => true],
            ['data' => 'returns_count', 'label' => __('labels.returns'), 'orderable' => true],
            ['data' => 'return_rate', 'label' => __('labels.return_rate'), 'orderable' => false],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'categories', 'admin');

        return view('admin.reports.categories', compact('markets', 'categories', 'columns', 'exportOptions'));
    }

    public function categoriesDatatable(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;
        $orderIndex = $request->get('order')[0]['column'] ?? 2;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        $columnMap = [0 => 'title', 2 => 'units_sold', 3 => 'total_orders', 4 => 'total_gmv', 5 => 'total_commission', 6 => 'returns_count'];
        $orderColumn = $columnMap[$orderIndex] ?? 'units_sold';

        $result = $this->reportService->getCategoriesData($request->all(), $start, $length, $search, $orderColumn, $orderDir);

        return response()->json($result);
    }

    public function categoriesChart(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        return response()->json($this->reportService->getCategoriesChart($request->all()));
    }

    public function brands(Request $request): View
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $markets = Market::where('status', 'active')->orderBy('priority', 'asc')->get();
        $brands = Brand::orderBy('title', 'asc')->get();

        $columns = [
            ['data' => 'brand', 'label' => __('labels.brand'), 'orderable' => true],
            ['data' => 'units_sold', 'label' => __('labels.units_sold'), 'orderable' => true],
            ['data' => 'total_orders', 'label' => __('labels.orders'), 'orderable' => true],
            ['data' => 'total_gmv', 'label' => __('labels.gross_sales'), 'orderable' => true],
            ['data' => 'total_commission', 'label' => __('labels.commission_earned'), 'orderable' => true],
            ['data' => 'products_count', 'label' => __('labels.products'), 'orderable' => true],
            ['data' => 'returns_count', 'label' => __('labels.returns'), 'orderable' => true],
            ['data' => 'return_rate', 'label' => __('labels.return_rate'), 'orderable' => false],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'brands', 'admin');

        return view('admin.reports.brands', compact('markets', 'brands', 'columns', 'exportOptions'));
    }

    public function brandsDatatable(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;
        $orderIndex = $request->get('order')[0]['column'] ?? 1;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        $columnMap = [0 => 'title', 1 => 'units_sold', 2 => 'total_orders', 3 => 'total_gmv', 4 => 'total_commission', 5 => 'products_count', 6 => 'returns_count'];
        $orderColumn = $columnMap[$orderIndex] ?? 'units_sold';

        $result = $this->reportService->getBrandsData($request->all(), $start, $length, $search, $orderColumn, $orderDir);

        return response()->json($result);
    }

    public function brandsChart(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        return response()->json($this->reportService->getBrandsChart($request->all()));
    }

    public function products(Request $request): View
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $markets = Market::where('status', 'active')->orderBy('priority', 'asc')->get();
        $categories = Category::orderBy('title', 'asc')->get();
        $brands = Brand::orderBy('title', 'asc')->get();
        $sellers = Seller::with('user')->get();

        $columns = [
            ['data' => 'product', 'label' => __('labels.product'), 'orderable' => true],
            ['data' => 'category', 'label' => __('labels.category'), 'orderable' => false],
            ['data' => 'brand', 'label' => __('labels.brand'), 'orderable' => false],
            ['data' => 'seller', 'label' => __('labels.seller'), 'orderable' => false],
            ['data' => 'units_sold', 'label' => __('labels.units_sold'), 'orderable' => true],
            ['data' => 'total_orders', 'label' => __('labels.orders'), 'orderable' => true],
            ['data' => 'total_gmv', 'label' => __('labels.gross_sales'), 'orderable' => true],
            ['data' => 'total_commission', 'label' => __('labels.commission_earned'), 'orderable' => true],
            ['data' => 'returns_count', 'label' => __('labels.returns'), 'orderable' => true],
            ['data' => 'return_rate', 'label' => __('labels.return_rate'), 'orderable' => false],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'products', 'admin');

        return view('admin.reports.products', compact('markets', 'categories', 'brands', 'sellers', 'columns', 'exportOptions'));
    }

    public function productsDatatable(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;
        $orderIndex = $request->get('order')[0]['column'] ?? 4;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        $columnMap = [0 => 'title', 4 => 'units_sold', 5 => 'total_orders', 6 => 'total_gmv', 7 => 'total_commission', 8 => 'returns_count'];
        $orderColumn = $columnMap[$orderIndex] ?? 'units_sold';

        $result = $this->reportService->getProductsData($request->all(), $start, $length, $search, $orderColumn, $orderDir);

        return response()->json($result);
    }

    public function productsChart(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        return response()->json($this->reportService->getProductsChart($request->all()));
    }

    public function sellers(Request $request): View
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $markets = Market::where('status', 'active')->orderBy('priority', 'asc')->get();
        $sellers = Seller::with('user')->get();

        $columns = [
            ['data' => 'name', 'label' => __('labels.seller'), 'orderable' => true],
            ['data' => 'total_orders', 'label' => __('labels.orders'), 'orderable' => true],
            ['data' => 'units_sold', 'label' => __('labels.units_sold'), 'orderable' => true],
            ['data' => 'total_gmv', 'label' => __('labels.gross_sales'), 'orderable' => true],
            ['data' => 'total_commission', 'label' => __('labels.commission_earned'), 'orderable' => true],
            ['data' => 'seller_payout', 'label' => __('labels.net_earnings'), 'orderable' => true],
            ['data' => 'returns_count', 'label' => __('labels.returns'), 'orderable' => true],
            ['data' => 'return_rate', 'label' => __('labels.return_rate'), 'orderable' => false],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'sellers', 'admin');

        return view('admin.reports.sellers', compact('markets', 'sellers', 'columns', 'exportOptions'));
    }

    public function sellersDatatable(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;
        $orderIndex = $request->get('order')[0]['column'] ?? 1;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        $columnMap = [0 => 'name', 1 => 'total_orders', 2 => 'units_sold', 3 => 'total_gmv', 4 => 'total_commission', 5 => 'seller_payout', 6 => 'returns_count'];
        $orderColumn = $columnMap[$orderIndex] ?? 'total_orders';

        $result = $this->reportService->getSellersData($request->all(), $start, $length, $search, $orderColumn, $orderDir);

        return response()->json($result);
    }

    public function sellersChart(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        return response()->json($this->reportService->getSellersChart($request->all()));
    }

    public function stores(Request $request): View
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $markets = Market::where('status', 'active')->orderBy('priority', 'asc')->get();
        $sellers = Seller::with('user')->get();
        $stores = Store::orderBy('name', 'asc')->get();

        $columns = [
            ['data' => 'name', 'label' => __('labels.store'), 'orderable' => true],
            ['data' => 'seller_name', 'label' => __('labels.seller'), 'orderable' => false],
            ['data' => 'total_orders', 'label' => __('labels.orders'), 'orderable' => true],
            ['data' => 'units_sold', 'label' => __('labels.units_sold'), 'orderable' => true],
            ['data' => 'total_gmv', 'label' => __('labels.gross_sales'), 'orderable' => true],
            ['data' => 'total_commission', 'label' => __('labels.commission_earned'), 'orderable' => true],
            ['data' => 'returns_count', 'label' => __('labels.returns'), 'orderable' => true],
            ['data' => 'return_rate', 'label' => __('labels.return_rate'), 'orderable' => false],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'orders', 'admin');

        return view('admin.reports.stores', compact('markets', 'sellers', 'stores', 'columns', 'exportOptions'));
    }

    public function storesDatatable(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;
        $orderIndex = $request->get('order')[0]['column'] ?? 2;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        $columnMap = [0 => 'name', 2 => 'total_orders', 3 => 'units_sold', 4 => 'total_gmv', 5 => 'total_commission', 6 => 'returns_count'];
        $orderColumn = $columnMap[$orderIndex] ?? 'total_orders';

        $result = $this->reportService->getStoresData($request->all(), $start, $length, $search, $orderColumn, $orderDir);

        return response()->json($result);
    }

    public function storesChart(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        return response()->json($this->reportService->getStoresChart($request->all()));
    }

    public function earnings(Request $request): View
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $markets = Market::where('status', 'active')->orderBy('priority', 'asc')->get();

        $columns = [
            ['data' => 'order_date', 'label' => __('labels.date'), 'orderable' => true],
            ['data' => 'market_name', 'label' => __('labels.market'), 'orderable' => true],
            ['data' => 'total_orders', 'label' => __('labels.orders'), 'orderable' => true],
            ['data' => 'total_commission', 'label' => __('labels.commission_earned'), 'orderable' => true],
            ['data' => 'total_platform_fee', 'label' => __('labels.platform_fees'), 'orderable' => true],
            ['data' => 'total_cod_fee', 'label' => __('labels.cod_charges'), 'orderable' => true],
            ['data' => 'net_earnings', 'label' => __('labels.net_earnings'), 'orderable' => false],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'earnings', 'admin');

        return view('admin.reports.earnings', compact('markets', 'columns', 'exportOptions'));
    }

    public function earningsDatatable(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;
        $orderIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        $columnMap = [0 => 'order_date', 1 => 'market_name', 2 => 'total_orders', 3 => 'total_commission', 4 => 'total_platform_fee', 5 => 'total_cod_fee'];
        $orderColumn = $columnMap[$orderIndex] ?? 'order_date';

        $result = $this->reportService->getEarningsData($request->all(), $start, $length, $search, $orderColumn, $orderDir);

        return response()->json($result);
    }

    public function earningsChart(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        return response()->json($this->reportService->getEarningsChart($request->all()));
    }

    public function taxes(Request $request): View
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $markets = Market::where('status', 'active')->orderBy('priority', 'asc')->get();

        $columns = [
            ['data' => 'tax_group', 'label' => __('labels.tax_group'), 'orderable' => true],
            ['data' => 'market_name', 'label' => __('labels.market'), 'orderable' => true],
            ['data' => 'total_orders', 'label' => __('labels.orders'), 'orderable' => true],
            ['data' => 'total_items', 'label' => __('labels.items'), 'orderable' => false],
            ['data' => 'taxable_amount', 'label' => __('labels.taxable_amount'), 'orderable' => true],
            ['data' => 'tax_collected', 'label' => __('labels.tax_collected'), 'orderable' => true],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'orders', 'admin');

        return view('admin.reports.taxes', compact('markets', 'columns', 'exportOptions'));
    }

    public function taxesDatatable(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;
        $orderIndex = $request->get('order')[0]['column'] ?? 5;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        $columnMap = [0 => 'tax_group_name', 1 => 'market_name', 2 => 'total_orders', 4 => 'taxable_amount', 5 => 'tax_collected'];
        $orderColumn = $columnMap[$orderIndex] ?? 'tax_collected';

        try {
            $result = $this->reportService->getTaxesData($request->all(), $start, $length, $search, $orderColumn, $orderDir);
            return response()->json($result);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Admin Tax Report datatable error: ' . $e->getMessage());
            return response()->json([
                'draw' => (int) $request->get('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => 'Failed to load',
            ], 500);
        }
    }

    public function taxesChart(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::REPORT_VIEW()), 403);

        return response()->json($this->reportService->getTaxesChart($request->all()));
    }
}
