<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use App\Services\DashboardService;
use App\Services\MarketService;
use App\Services\WalletService;
use App\Models\Seller;
use App\Models\User;
use App\Models\Order;
use App\Traits\ChecksPermissions;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    use ChecksPermissions;
    protected DashboardService $dashboardService;
    protected CurrencyService $currencyService;
    protected WalletService $walletService;
    protected bool $viewPermission = true;

    public function __construct(
        DashboardService $dashboardService,
        CurrencyService  $currencyService,
        WalletService    $walletService
    )
    {
        $this->dashboardService = $dashboardService;
        $this->currencyService = $currencyService;
        $this->walletService = $walletService;
        $this->viewPermission = $this->hasPermission(AdminPermissionEnum::DASHBOARD_VIEW());
    }

    /**
     * Resolve the market the dashboard is scoped to. There is no "all markets"
     * view — the market comes from the global header selector (session-backed),
     * falling back to the system default, so every widget (and its currency)
     * is always scoped to exactly one market.
     */
    private function resolveMarketId(Request $request): ?int
    {
        try {
            return app(MarketService::class)->adminSelectedId();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Display the admin dashboard with dynamic data.
     */
    public function index(Request $request): View
    {
        abort_unless($this->viewPermission, 403);

        $currencyService = $this->currencyService;
        $dashboardService = $this->dashboardService;
        $marketId = $this->resolveMarketId($request);

        $revenueVsOrders = $dashboardService->getRevenueVsOrdersData(days: 30, marketId: $marketId);
        $gmvData = $dashboardService->getGmvData(days: 30, marketId: $marketId);
        $enhancedCommissionsData = $dashboardService->getEnhancedCommissionsData(days: 30, type: 'all', marketId: $marketId);
        $revenueByCategory = $dashboardService->getRevenueByCategoryData(days: 30, marketId: $marketId);
        $topSellers = $dashboardService->getTopSellers(days: 30, limit: 5, marketId: $marketId);

        $conversionRateData = $dashboardService->getAdminConversionRateData(days: 30, marketId: $marketId);
        $orderFunnel = $dashboardService->getOrderFunnelData(days: 30, marketId: $marketId);
        $returnAnalytics = $dashboardService->getReturnAnalyticsData(days: 30, marketId: $marketId);

        $customerInsights = $dashboardService->getCustomerInsightsData(days: 30, marketId: $marketId);

        $sellerSettlements = $dashboardService->getSellerSettlementsData(days: 1, marketId: $marketId);
        $withdrawals = $dashboardService->getWithdrawalsData(days: 1, marketId: $marketId);
        $catalogModeration = $dashboardService->getCatalogModerationData(marketId: $marketId);
        $globalOps = $dashboardService->getGlobalOpsData();

        $viewPermission = $this->viewPermission;
        $homeBuilderPermission = $this->hasPermission(AdminPermissionEnum::HOME_BUILDER_VIEW());
        return view('admin.dashboard', compact(
            'currencyService',
            'revenueVsOrders',
            'gmvData',
            'enhancedCommissionsData',
            'revenueByCategory',
            'topSellers',
            'conversionRateData',
            'orderFunnel',
            'returnAnalytics',
            'customerInsights',
            'sellerSettlements',
            'withdrawals',
            'catalogModeration',
            'globalOps',
            'marketId',
            'viewPermission',
            'homeBuilderPermission'
        ));
    }

    /**
     * Get dashboard data via AJAX for dynamic updates.
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission, 403);

        $type = $request->input('type');
        $days = max(1, min(365, (int) $request->input('days', 7)));
        $limit = max(1, min(100, (int) $request->input('limit', 5)));
        $commissionType = $request->input('commission_type', 'all');
        $marketId = $this->resolveMarketId($request);
        $rankBy = $request->input('rank_by', 'revenue');

        $data = match ($type) {
            'revenue_vs_orders' => $this->dashboardService->getRevenueVsOrdersData(days: $days, marketId: $marketId),
            'gmv' => $this->dashboardService->getGmvData(days: $days, marketId: $marketId),
            'commissions' => $this->dashboardService->getEnhancedCommissionsData(days: $days, type: $commissionType, marketId: $marketId),
            'revenue_by_category' => $this->dashboardService->getRevenueByCategoryData(days: $days, marketId: $marketId),
            'top_sellers' => $this->dashboardService->getTopSellers(days: $days, limit: $limit, sortBy: $rankBy, marketId: $marketId),
            'sales' => $this->dashboardService->getAdminConversionRateData(days: $days, marketId: $marketId),
            'order_funnel' => $this->dashboardService->getOrderFunnelData(days: $days, marketId: $marketId),
            'return_analytics' => $this->dashboardService->getReturnAnalyticsData(days: $days, marketId: $marketId),
            'customer_insights' => $this->dashboardService->getCustomerInsightsData(days: $days, marketId: $marketId),
            'catalog_moderation' => $this->dashboardService->getCatalogModerationData(marketId: $marketId),
            default => null,
        };

        if ($data === null) {
            return response()->json(['error' => 'Invalid data type requested'], 400);
        }

        return response()->json($data);
    }
}
