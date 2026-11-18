<?php

namespace App\Http\Controllers\Seller\Reports;

use App\Enums\SellerPermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\Store;
use App\Services\DatatableExportRegistry;
use App\Services\Reports\SellerReportService;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SellerReportController extends Controller
{
    use ChecksPermissions, PanelAware;

    public function __construct(
        protected SellerReportService $reportService,
        protected DatatableExportRegistry $exportRegistry
    ) {}

    protected function authorizeReport(string $specificPermission): void
    {
        $user = auth()->user();
        abort_unless($user, 403, __('labels.permission_denied'));

        $canAccess = $this->isSellerOwner($user)
            || $this->hasPermission(SellerPermissionEnum::REPORT_VIEW())
            || $this->hasPermission($specificPermission);

        abort_unless($canAccess, 403, __('labels.permission_denied'));
    }

    protected function getSellerId(): int
    {
        $seller = auth()->user()?->seller();
        abort_unless($seller, 403, __('labels.permission_denied'));
        return (int) $seller->id;
    }

    /**
     * Returns only the active markets that the seller's stores are enrolled in,
     * rather than every active market in the system. This drives the currency
     * selector on report filter forms.
     */
    protected function getSellerMarkets(int $sellerId): \Illuminate\Database\Eloquent\Collection
    {
        return Market::whereHas('stores', fn ($q) => $q->where('seller_id', $sellerId))
            ->where('status', 'active')
            ->orderBy('priority', 'asc')
            ->get();
    }

    public function sales(Request $request): View
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_SALES_VIEW());
        $sellerId = $this->getSellerId();

        $markets = $this->getSellerMarkets($sellerId);
        $stores = Store::where('seller_id', $sellerId)->orderBy('name', 'asc')->get();

        $columns = [
            ['data' => 'sales_date', 'label' => __('labels.date'), 'orderable' => true],
            ['data' => 'total_orders', 'label' => __('labels.orders'), 'orderable' => true],
            ['data' => 'units_sold', 'label' => __('labels.units_sold'), 'orderable' => true],
            ['data' => 'gross_sales', 'label' => __('labels.gross_sales'), 'orderable' => true],
            ['data' => 'commission_deducted', 'label' => __('labels.commission_earned'), 'orderable' => true],
            ['data' => 'net_earnings', 'label' => __('labels.net_earnings'), 'orderable' => true],
            ['data' => 'returns_count', 'label' => __('labels.returns'), 'orderable' => true],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'orders', 'seller');

        return view('seller.reports.sales', compact('markets', 'stores', 'columns', 'exportOptions'));
    }

    public function salesDatatable(Request $request): JsonResponse
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_SALES_VIEW());
        $sellerId = $this->getSellerId();

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;
        $orderIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        $columnMap = [0 => 'sales_date', 1 => 'total_orders', 2 => 'units_sold', 3 => 'gross_sales', 4 => 'commission_deducted', 5 => 'net_earnings', 6 => 'returns_count'];
        $orderColumn = $columnMap[$orderIndex] ?? 'sales_date';

        try {
            $result = $this->reportService->getSalesData($sellerId, $request->all(), $start, $length, $search, $orderColumn, $orderDir);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Seller sales datatable error: ' . $e->getMessage());
            return response()->json([
                'draw' => (int) $request->get('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => 'Failed to load',
            ], 500);
        }
    }

    public function salesChart(Request $request): JsonResponse
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_SALES_VIEW());
        $sellerId = $this->getSellerId();

        return response()->json($this->reportService->getSalesChart($sellerId, $request->all()));
    }

    public function products(Request $request): View
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_PRODUCT_VIEW());
        $sellerId = $this->getSellerId();

        $markets = $this->getSellerMarkets($sellerId);

        $columns = [
            ['data' => 'title', 'label' => __('labels.product'), 'orderable' => true],
            ['data' => 'category', 'label' => __('labels.category'), 'orderable' => false],
            ['data' => 'units_sold', 'label' => __('labels.units_sold'), 'orderable' => true],
            ['data' => 'gross_sales', 'label' => __('labels.gross_sales'), 'orderable' => true],
            ['data' => 'commission_deducted', 'label' => __('labels.commission_earned'), 'orderable' => true],
            ['data' => 'net_earnings', 'label' => __('labels.net_earnings'), 'orderable' => true],
            ['data' => 'returns_count', 'label' => __('labels.returns'), 'orderable' => true],
            ['data' => 'return_rate', 'label' => __('labels.return_rate'), 'orderable' => false],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'products', 'seller');

        return view('seller.reports.products', compact('markets', 'columns', 'exportOptions'));
    }

    public function productsDatatable(Request $request): JsonResponse
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_PRODUCT_VIEW());
        $sellerId = $this->getSellerId();

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;
        $orderIndex = $request->get('order')[0]['column'] ?? 2;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';

        $columnMap = [0 => 'title', 2 => 'units_sold', 3 => 'gross_sales', 4 => 'commission_deducted', 5 => 'net_earnings', 6 => 'returns_count'];
        $orderColumn = $columnMap[$orderIndex] ?? 'units_sold';

        try {
            $result = $this->reportService->getProductsData($sellerId, $request->all(), $start, $length, $search, $orderColumn, $orderDir);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Seller products datatable error: ' . $e->getMessage());
            return response()->json([
                'draw' => (int) $request->get('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => 'Failed to load',
            ], 500);
        }
    }

    public function productsChart(Request $request): JsonResponse
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_PRODUCT_VIEW());
        $sellerId = $this->getSellerId();

        return response()->json($this->reportService->getProductsChart($sellerId, $request->all()));
    }

    public function settlements(Request $request): View
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_SETTLEMENT_VIEW());
        $sellerId = $this->getSellerId();

        $markets = $this->getSellerMarkets($sellerId);

        $columns = [
            ['data' => 'posted_at', 'label' => __('labels.date'), 'orderable' => true],
            ['data' => 'entry_type', 'label' => __('labels.entry_type'), 'orderable' => false],
            ['data' => 'order_id', 'label' => __('labels.order_id'), 'orderable' => false],
            ['data' => 'amount', 'label' => __('labels.amount'), 'orderable' => true],
            ['data' => 'settlement_status', 'label' => __('labels.status'), 'orderable' => false],
            ['data' => 'settled_at', 'label' => __('labels.settled_on'), 'orderable' => false],
            ['data' => 'reference', 'label' => __('labels.reference_id'), 'orderable' => false],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'earnings', 'seller');

        return view('seller.reports.settlements', compact('markets', 'columns', 'exportOptions'));
    }

    public function settlementsDatatable(Request $request): JsonResponse
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_SETTLEMENT_VIEW());
        $sellerId = $this->getSellerId();

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;

        try {
            $result = $this->reportService->getSettlementsData($sellerId, $request->all(), $start, $length, $search);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Seller settlements datatable error: ' . $e->getMessage());
            return response()->json([
                'draw' => (int) $request->get('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => 'Failed to load',
            ], 500);
        }
    }

    public function settlementsChart(Request $request): JsonResponse
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_SETTLEMENT_VIEW());
        $sellerId = $this->getSellerId();

        return response()->json($this->reportService->getSettlementsChart($sellerId, $request->all()));
    }

    public function returns(Request $request): View
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_RETURN_VIEW());
        $sellerId = $this->getSellerId();

        $markets = $this->getSellerMarkets($sellerId);

        $columns = [
            ['data' => 'order_id', 'label' => __('labels.order_id'), 'orderable' => false],
            ['data' => 'item_title', 'label' => __('labels.product'), 'orderable' => false],
            ['data' => 'reason', 'label' => __('labels.return_reason'), 'orderable' => false],
            ['data' => 'refund_amount', 'label' => __('labels.refund_amount'), 'orderable' => true],
            ['data' => 'status', 'label' => __('labels.status'), 'orderable' => false],
            ['data' => 'fault', 'label' => __('labels.fault'), 'orderable' => false],
            ['data' => 'created_at', 'label' => __('labels.created_at'), 'orderable' => true],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'returns', 'seller');

        return view('seller.reports.returns', compact('markets', 'columns', 'exportOptions'));
    }

    public function returnsDatatable(Request $request): JsonResponse
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_RETURN_VIEW());
        $sellerId = $this->getSellerId();

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;
        $orderColumnIndex = $request->get('order')[0]['column'] ?? null;
        $orderDir = $request->get('order')[0]['dir'] ?? 'desc';
        $columns = ['order_id', 'item_title', 'reason', 'refund_amount', 'status', 'fault', 'created_at'];
        $orderColumn = $orderColumnIndex !== null && isset($columns[$orderColumnIndex]) ? $columns[$orderColumnIndex] : null;

        try {
            $result = $this->reportService->getReturnsData($sellerId, $request->all(), $start, $length, $search, $orderColumn, $orderDir);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Seller returns datatable error: ' . $e->getMessage());
            return response()->json([
                'draw' => (int) $request->get('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => 'Failed to load',
            ], 500);
        }
    }

    public function returnsChart(Request $request): JsonResponse
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_RETURN_VIEW());
        $sellerId = $this->getSellerId();

        return response()->json($this->reportService->getReturnsChart($sellerId, $request->all()));
    }

    public function stores(Request $request): View
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_STORE_VIEW());
        $sellerId = $this->getSellerId();

        $markets = $this->getSellerMarkets($sellerId);

        $columns = [
            ['data' => 'name', 'label' => __('labels.store'), 'orderable' => true],
            ['data' => 'total_orders', 'label' => __('labels.orders'), 'orderable' => true],
            ['data' => 'units_sold', 'label' => __('labels.units_sold'), 'orderable' => true],
            ['data' => 'gross_sales', 'label' => __('labels.gross_sales'), 'orderable' => true],
            ['data' => 'net_earnings', 'label' => __('labels.net_earnings'), 'orderable' => true],
            ['data' => 'returns_count', 'label' => __('labels.returns'), 'orderable' => true],
            ['data' => 'return_rate', 'label' => __('labels.return_rate'), 'orderable' => false],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'orders', 'seller');

        return view('seller.reports.stores', compact('markets', 'columns', 'exportOptions'));
    }

    public function storesDatatable(Request $request): JsonResponse
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_STORE_VIEW());
        $sellerId = $this->getSellerId();

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;

        try {
            $result = $this->reportService->getStoresData($sellerId, $request->all(), $start, $length, $search);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Seller stores datatable error: ' . $e->getMessage());
            return response()->json([
                'draw' => (int) $request->get('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'message' => 'Failed to load',
            ], 500);
        }
    }

    public function storesChart(Request $request): JsonResponse
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_STORE_VIEW());
        $sellerId = $this->getSellerId();

        return response()->json($this->reportService->getStoresChart($sellerId, $request->all()));
    }

    public function taxes(Request $request): View
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_TAX_VIEW());
        $sellerId = $this->getSellerId();

        $markets = $this->getSellerMarkets($sellerId);

        $columns = [
            ['data' => 'tax_group', 'label' => __('labels.tax_group'), 'orderable' => true],
            ['data' => 'total_orders', 'label' => __('labels.orders'), 'orderable' => true],
            ['data' => 'total_items', 'label' => __('labels.items'), 'orderable' => false],
            ['data' => 'taxable_amount', 'label' => __('labels.taxable_amount'), 'orderable' => true],
            ['data' => 'tax_collected', 'label' => __('labels.tax_collected'), 'orderable' => true],
        ];

        $exportOptions = $this->exportRegistry->frontendOptions(auth()->user(), 'orders', 'seller');

        return view('seller.reports.taxes', compact('markets', 'columns', 'exportOptions'));
    }

    public function taxesDatatable(Request $request): JsonResponse
    {
        $this->authorizeReport(SellerPermissionEnum::REPORT_TAX_VIEW());
        $sellerId = $this->getSellerId();

        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $search = $request->get('search')['value'] ?? null;

        try {
            $result = $this->reportService->getTaxesData($sellerId, $request->all(), $start, $length, $search);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Seller tax datatable error: ' . $e->getMessage());
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
        $this->authorizeReport(SellerPermissionEnum::REPORT_TAX_VIEW());
        $sellerId = $this->getSellerId();

        return response()->json($this->reportService->getTaxesChart($sellerId, $request->all()));
    }
}
