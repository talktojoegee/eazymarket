<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Http\Controllers\Controller;
use App\Services\CurrencyService;
use App\Services\DashboardService;
use App\Services\WalletService;
use App\Traits\ChecksPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use ChecksPermissions;

    protected WalletService $walletService;
    protected CurrencyService $currencyService;
    protected DashboardService $dashboardService;
    protected bool $viewPermission = true;
    protected $seller;


    public function __construct(
        WalletService    $walletService,
        CurrencyService  $currencyService,
        DashboardService $dashboardService
    )
    {
        $user = auth()->user();
        $this->seller = $user?->seller();
        $this->walletService = $walletService;
        $this->currencyService = $currencyService;
        $this->dashboardService = $dashboardService;
        $this->viewPermission = $this->hasPermission(SellerPermissionEnum::DASHBOARD_VIEW()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
    }

    /**
     * Display the seller dashboard with dynamic data.
     */
    public function index(Request $request): View
    {
        abort_unless($this->viewPermission, 403);
        $seller = $this->seller;


        if (!$seller) {
            abort(404, __('messages.seller_not_found'));
        }

        $storeId = app(\App\Services\SellerStoreService::class)->selectedId();
        $sid = $seller->id;
        $ds = $this->dashboardService;

        $walletResult = $this->walletService->getWallet($seller->user->id, WalletTypeEnum::SELLER);
        $wallet = $walletResult['success'] ? $walletResult['data'] : null;
        $walletBalance = $wallet ? $wallet->balance : 0;
        $blockedBalance = $wallet ? $wallet->blocked_balance : 0;

        $salesSummary = $ds->getSellerSalesSummary($sid, 30, $storeId);
        $salesByMarket = $ds->getSellerSalesByMarket($sid, 30, $storeId);
        $topProducts = $ds->getSellerTopProducts($sid, 30, 5, $storeId);

        $deadProducts = $ds->getSellerDeadProducts($sid, 30, 5, $storeId);
        $actionItems = $ds->getSellerActionItems($sid, $storeId);
        $conversionRateData = $ds->getConversionRateData(sellerId: $sid, days: 30, storeId: $storeId);

        $customerInsights = $ds->getSellerCustomerInsights($sid, 30, $storeId);
        $returnAnalytics = $ds->getSellerReturnAnalytics($sid, 30, $storeId);

        $earnings = $ds->getSellerEarningsSummary($sid, $storeId);

        $currencyService = $this->currencyService;
        $viewPermission = $this->viewPermission;

        return view('seller.dashboard', compact(
            'walletBalance',
            'blockedBalance',
            'salesSummary',
            'salesByMarket',
            'topProducts',
            'deadProducts',
            'actionItems',
            'conversionRateData',
            'customerInsights',
            'returnAnalytics',
            'earnings',
            'currencyService',
            'viewPermission'
        ));
    }


    /**
     * Get dashboard chart data as JSON for AJAX requests.
     */
    public function getChartData(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission, 403);
        $user = $request->user();
        $seller = $user->seller();

        if (!$seller) {
            return response()->json(['error' => 'Seller not found'], 404);
        }

        $storeId = app(\App\Services\SellerStoreService::class)->selectedId();

        return response()->json([
            'sales_summary' => $this->dashboardService->getSellerSalesSummary($seller->id, 30, $storeId),
        ]);
    }

    /**
     * Get dashboard data via AJAX for period switches.
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        abort_unless($this->viewPermission, 403);
        $user = $request->user();
        $seller = $user->seller();

        if (!$seller) {
            return response()->json(['error' => 'Seller not found'], 404);
        }

        $type = $request->input('type');
        $days = max(1, min(365, (int) $request->input('days', 30)));
        $storeId = app(\App\Services\SellerStoreService::class)->selectedId();

        $data = match ($type) {
            'sales_summary' => $this->dashboardService->getSellerSalesSummary($seller->id, $days, $storeId),
            'customer_insights' => $this->dashboardService->getSellerCustomerInsights($seller->id, $days, $storeId),
            default => null,
        };

        if ($data === null) {
            return response()->json(['error' => 'Invalid data type requested'], 400);
        }

        return response()->json($data);
    }
}
