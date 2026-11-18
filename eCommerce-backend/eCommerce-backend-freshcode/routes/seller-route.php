<?php

use App\Http\Controllers\AddonGroupController;
use App\Http\Controllers\Api\User\UserApiController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\DatatableExportController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\GlobalAttributeController;
use App\Http\Controllers\GlobalAttributeValueController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OtpLoginController;
use App\Http\Controllers\PanelSessionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductConditionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductFaqController;
use App\Http\Controllers\ProductImageUploadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReturnRequestController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Seller\AdCampaignController;
use App\Http\Controllers\Seller\AdWalletController;
use App\Http\Controllers\Seller\AuthController;
use App\Http\Controllers\Seller\DashboardController;
use App\Http\Controllers\Seller\Reports\SellerReportController;
use App\Http\Controllers\Seller\GlobalSearchController;
use App\Http\Controllers\Seller\PosController;
use App\Http\Controllers\Seller\PosDashboardController;
use App\Http\Controllers\Seller\PosPaymentSessionController;
use App\Http\Controllers\Seller\SubscriptionPlanController;
use App\Http\Controllers\Seller\WalletController;
use App\Http\Controllers\Seller\WatchBuyProfileController;
use App\Http\Controllers\Seller\WatchBuyReelController;
use App\Http\Controllers\Seller\WatchBuyStatusController;
use App\Http\Controllers\Seller\WithdrawalController;
use App\Http\Controllers\SupportBroadcastAuthController;
use App\Http\Controllers\SellerEarningController;
use App\Http\Controllers\ShippingProfileController;
use App\Http\Controllers\ShippingZoneController;
use App\Http\Controllers\StoreAddonItemController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreLocationController;
use App\Http\Controllers\StorePackageController;
use App\Http\Controllers\StoreProductVariantAddonController;
use App\Http\Controllers\SystemUserController;
use App\Http\Controllers\TaxClassController;
use App\Http\Controllers\TaxRateController;
use App\Http\Middleware\RestrictDemoUserWrites;
use App\Services\PanelNavigationService;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::prefix('seller')->name('seller.')->group(function () {
    Route::get('session/status', PanelSessionController::class)->name('session.status');

    Route::middleware(['guest'])->group(function () {
        Route::get('login', [AuthController::class, 'loginSeller'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.post');
        Route::get('/', function () {
            return view('seller.auth.login');
        })->name('login.index');

        // OTP Login Routes
        Route::get('login-otp', [OtpLoginController::class, 'showOtpLogin'])->name('login-otp');
        Route::post('login/send-otp', [OtpLoginController::class, 'sendLoginOtp'])->middleware('throttle:otp-send')->name('login.send-otp');
        Route::post('login/verify-otp', [OtpLoginController::class, 'verifyLoginOtp'])->middleware('throttle:otp-verify')->name('login.verify-otp');

        // Password Reset Routes
        Route::get('forgot-password', [PasswordResetController::class, 'showForgotPasswordForm'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->middleware('throttle:password-reset')->name('password.email');
        Route::post('forgot-password/send-otp', [PasswordResetController::class, 'sendResetOtp'])->middleware('throttle:password-reset')->name('password.send-otp');
        Route::post('forgot-password/verify-otp', [PasswordResetController::class, 'verifyResetOtp'])->middleware('throttle:otp-verify')->name('password.verify-otp');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'showResetPasswordForm'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('password.update');
    });

    Route::middleware(['seller.query.token', 'auth', AuthenticateSession::class, 'validate.seller', 'seller.store', RestrictDemoUserWrites::class,
    ])->group(function () {
        Route::prefix('datatable-exports')->name('datatable-exports.')->group(function () {
            Route::post('estimate', [DatatableExportController::class, 'estimate'])->withoutMiddleware(RestrictDemoUserWrites::class)->name('estimate');
            Route::post('/', [DatatableExportController::class, 'store'])->withoutMiddleware(RestrictDemoUserWrites::class)->name('store');
            Route::get('{datatableExport}/download', [DatatableExportController::class, 'download'])->name('download');
        });
        Route::get('/', fn () => redirect()->to(
            app(PanelNavigationService::class)->landingUrl(auth()->user(), 'seller')
        ))->name('index');
        Route::view('no-permissions', 'seller.no-permissions')->name('no-permissions');

        Route::get('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('global-search', GlobalSearchController::class)
            ->middleware('throttle:60,1')
            ->name('global-search');

        // Browser FCM token sync for the seller panel.
        Route::post('devices/sync', [DeviceTokenController::class, 'sync'])->name('devices.sync');
        Route::delete('devices', [DeviceTokenController::class, 'forget'])->name('devices.forget');
        Route::post('broadcasting/auth', SupportBroadcastAuthController::class)->name('broadcasting.auth');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/chart-data', [DashboardController::class, 'getChartData'])->name('dashboard.chart-data');
        Route::get('dashboard/data', [DashboardController::class, 'getDashboardData'])->name('dashboard.data');

        // Roles
        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [RoleController::class, 'index'])->name('index');
            Route::post('/', [RoleController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [RoleController::class, 'edit'])->name('edit');
            Route::post('/{id}', [RoleController::class, 'update'])->name('update');
            Route::delete('/{id}', [RoleController::class, 'destroy'])->name('destroy');
            Route::get('/get-roles', [RoleController::class, 'getRoles'])->name('datatable');
            Route::get('/{role}/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        });

        // permissions
        Route::prefix('permissions')->name('permissions.')->group(function () {
            Route::post('/', [PermissionController::class, 'store'])->name('store');
        });

        // profile
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'index'])->name('index');
            Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
            Route::post('/update', [ProfileController::class, 'update'])->name('update');
            Route::post('/password-update', [ProfileController::class, 'changePassword'])->name('password.update');
        });

        Route::prefix('watch-and-buy')->name('watch-and-buy.')->group(function () {
            Route::get('profile', [WatchBuyProfileController::class, 'edit'])->name('profile.edit');
            Route::post('profile', [WatchBuyProfileController::class, 'update'])->name('profile.update');
            Route::get('statuses', [WatchBuyProfileController::class, 'statuses'])->name('statuses.index');
            Route::get('statuses/product-options', [WatchBuyStatusController::class, 'productOptions'])->name('statuses.product-options');
            Route::post('statuses', [WatchBuyStatusController::class, 'store'])->name('statuses.store');
            Route::delete('statuses/{status}', [WatchBuyStatusController::class, 'destroy'])->name('statuses.destroy');
            Route::get('reels', [WatchBuyReelController::class, 'index'])->name('reels.index');
            Route::get('reels/datatable', [WatchBuyReelController::class, 'datatable'])->name('reels.datatable');
            Route::get('reels/product-options', [WatchBuyReelController::class, 'productOptions'])->name('reels.product-options');
            Route::get('reels/create', [WatchBuyReelController::class, 'create'])->name('reels.create');
            Route::post('reels', [WatchBuyReelController::class, 'store'])->name('reels.store');
            Route::get('reels/{reel}/edit', [WatchBuyReelController::class, 'edit'])->name('reels.edit');
            Route::post('reels/{reel}', [WatchBuyReelController::class, 'update'])->name('reels.update');
            Route::delete('reels/{reel}', [WatchBuyReelController::class, 'destroy'])->name('reels.destroy');
            Route::post('reels/{reel}/publish', [WatchBuyReelController::class, 'publish'])->name('reels.publish');
            Route::post('reels/{reel}/unpublish', [WatchBuyReelController::class, 'unpublish'])->name('reels.unpublish');
        });

        // System Users
        Route::prefix('system-users')->name('system-users.')->group(function () {
            Route::get('/', [SystemUserController::class, 'index'])->name('index');
            Route::post('/', [SystemUserController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [SystemUserController::class, 'show'])->name('show');
            Route::post('/{id}', [SystemUserController::class, 'update'])->name('update');
            Route::delete('/{id}', [SystemUserController::class, 'destroy'])->name('destroy');
            Route::get('/datatable', [SystemUserController::class, 'getSystemUsers'])->name('datatable');
        });

        // brands
        Route::prefix('brands')->name('brands.')->group(function () {
            Route::get('/', [BrandController::class, 'index'])->name('index');
            Route::get('/{id}/edit', [BrandController::class, 'show'])->name('edit');
            Route::get('/{id}/visibility', [BrandController::class, 'visibility'])->name('visibility');
            Route::get('/datatable', [BrandController::class, 'getBrands'])->name('datatable');
            Route::get('/groups', [BrandController::class, 'groups'])->name('groups');
            Route::get('/search', [BrandController::class, 'search'])->name('search');

        });

        // addon groups (seller-owned add-on definitions)
        Route::prefix('addon-groups')->name('addon-groups.')->group(function () {
            Route::get('/', [AddonGroupController::class, 'index'])->name('index');
            Route::get('/datatable', [AddonGroupController::class, 'datatable'])->name('datatable');
            Route::get('/create', [AddonGroupController::class, 'create'])->name('create');
            Route::post('/', [AddonGroupController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [AddonGroupController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::post('/{id}', [AddonGroupController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [AddonGroupController::class, 'destroy'])->whereNumber('id')->name('delete');
        });

        // product addon attachments (store × variant × addon_group × addon_item)
        Route::prefix('product-addons')->name('product-addons.')->group(function () {
            Route::get('/', [StoreProductVariantAddonController::class, 'index'])->name('index');
            Route::get('/datatable', [StoreProductVariantAddonController::class, 'datatable'])->name('datatable');
            Route::get('/create', [StoreProductVariantAddonController::class, 'create'])->name('create');
            Route::post('/', [StoreProductVariantAddonController::class, 'store'])->name('store');

            // AJAX lookups used by the form (Select2/TomSelect + matrix fetch)
            Route::get('/lookup/products', [StoreProductVariantAddonController::class, 'productLookup'])->name('lookup.products');
            Route::get('/lookup/products/{product}/variants', [StoreProductVariantAddonController::class, 'variantLookup'])->whereNumber('product')->name('lookup.variants');
            Route::get('/lookup/variants-bulk', [StoreProductVariantAddonController::class, 'variantsBulkLookup'])->name('lookup.variants-bulk');
            Route::get('/lookup/addon-groups', [StoreProductVariantAddonController::class, 'addonGroupLookup'])->name('lookup.addon-groups');
            Route::get('/matrix/{variant}/{group}', [StoreProductVariantAddonController::class, 'matrix'])->whereNumber('variant')->whereNumber('group')->name('matrix');

            Route::get('/{variant}/{group}/edit', [StoreProductVariantAddonController::class, 'edit'])->whereNumber('variant')->whereNumber('group')->name('edit');
            Route::post('/{variant}/{group}', [StoreProductVariantAddonController::class, 'update'])->whereNumber('variant')->whereNumber('group')->name('update');
            Route::delete('/{variant}/{group}', [StoreProductVariantAddonController::class, 'destroy'])->whereNumber('variant')->whereNumber('group')->name('delete');
        });

        // store addon inventory (store × addon_item rows, with price/cost/stock/availability)
        Route::prefix('store-addon-items')->name('store-addon-items.')->group(function () {
            Route::get('/', [StoreAddonItemController::class, 'index'])->name('index');
            Route::get('/create', [StoreAddonItemController::class, 'create'])->name('create');
            Route::get('/datatable', [StoreAddonItemController::class, 'datatable'])->name('datatable');
            Route::get('/lookup/groups/{groupId}/items', [StoreAddonItemController::class, 'itemsForGroup'])->whereNumber('groupId')->name('items-for-group');
            Route::get('/lookup/state', [StoreAddonItemController::class, 'inventoryState'])->name('state');
            Route::get('/lookup/state-matrix', [StoreAddonItemController::class, 'inventoryStateMatrix'])->name('state-matrix');
            Route::get('/{id}', [StoreAddonItemController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/', [StoreAddonItemController::class, 'store'])->name('store');
            Route::post('/bulk', [StoreAddonItemController::class, 'bulkStore'])->name('bulk-store');
            Route::post('/{id}', [StoreAddonItemController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [StoreAddonItemController::class, 'destroy'])->whereNumber('id')->name('delete');
        });

        // categories
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::get('/{id}/edit', [CategoryController::class, 'show'])->name('edit');
            Route::get('/datatable', [CategoryController::class, 'getCategories'])->name('datatable');
            Route::get('/tree', [CategoryController::class, 'tree'])->name('tree');
            Route::get('/search', [CategoryController::class, 'search'])->name('search');
        });

        // taxes
        Route::prefix('tax-rates')->name('tax-rates.')->group(function () {
            Route::get('/', [TaxRateController::class, 'index'])->name('index');
            Route::get('/{id}/edit', [TaxRateController::class, 'show'])->name('edit');
            Route::get('/datatable', [TaxRateController::class, 'getTaxRates'])->name('datatable');
            Route::get('/search', [TaxRateController::class, 'search'])->name('search');
        });

        // Subscription plans (web pages for sellers) — (gated: coming soon)
        Route::prefix('subscription-plans')->name('subscription-plans.')->middleware('coming.soon:subscription')->group(function () {
            Route::get('/', [SubscriptionPlanController::class, 'index'])->name('index');
            Route::get('/current', [SubscriptionPlanController::class, 'current'])->name('current');
            Route::get('/history', [SubscriptionPlanController::class, 'history'])->name('history');
            Route::get('/history/datatable', [SubscriptionPlanController::class, 'historyDatatable'])->name('history.datatable');
            Route::get('/{plan}', [SubscriptionPlanController::class, 'show'])
                ->whereNumber('plan')
                ->name('show');
            Route::post('/check-eligibility', [SubscriptionPlanController::class, 'checkEligibility'])->name('eligibility');
            Route::post('/buy', [SubscriptionPlanController::class, 'buy'])->name('buy');
            Route::post('/skip', [SubscriptionPlanController::class, 'skip'])->name('skip');
        });

        // tax classes
        Route::prefix('tax-classes')->name('tax-classes.')->group(function () {
            Route::get('/', [TaxClassController::class, 'index'])->name('index');
            Route::get('/{id}/edit', [TaxClassController::class, 'show'])->name('edit');
            Route::get('/get-tax-classes', [TaxClassController::class, 'getTaxClasses'])->name('datatable');
            Route::get('/search', [TaxClassController::class, 'search'])->name('search');
        });

        Route::get('markets/search-tom', [MarketController::class, 'searchTom'])->name('markets.search-tom');

        // Stores
        Route::prefix('stores')->name('stores.')->group(function () {
            Route::get('/', [StoreController::class, 'index'])->name('index');
            // Global header selector — scopes the whole seller panel to one of the seller's stores.
            Route::post('/switch', [StoreController::class, 'switchSellerStore'])->name('switch');
            Route::post('/', [StoreController::class, 'store'])->name('store');
            Route::get('/create', [StoreController::class, 'create'])->name('create');
            Route::get('/{id}/edit', [StoreController::class, 'edit'])->name('edit');
            Route::post('/{id}', [StoreController::class, 'update'])->name('update');
            Route::delete('/{id}', [StoreController::class, 'destroy'])->name('delete');
            Route::get('/datatable', [StoreController::class, 'getStores'])->name('datatable');
            Route::get('/list', [StoreController::class, 'StoreList'])->name('storesList');
            Route::get('/with-locations', [StoreController::class, 'StoresWithLocations'])->name('storesWithLocations');
            Route::get('/search', [StoreController::class, 'search'])->name('search');
            Route::get('/{id}/configuration', [StoreController::class, 'configuration'])->name('configuration');
            Route::post('/{id}/configuration', [StoreController::class, 'storeConfiguration'])->name('store_configuration');
        });

        Route::prefix('locations')->name('locations.')->group(function () {
            Route::get('/', [StoreLocationController::class, 'allIndex'])->name('index');
            Route::get('/create', [StoreLocationController::class, 'allCreate'])->name('create');
            Route::post('/', [StoreLocationController::class, 'allStore'])->name('store');
            Route::get('/{locationId}/edit', [StoreLocationController::class, 'allEdit'])->name('edit');
            Route::post('/{locationId}', [StoreLocationController::class, 'allUpdate'])->name('update');
            Route::delete('/{locationId}', [StoreLocationController::class, 'allDestroy'])->name('delete');
            Route::post('/{locationId}/promote-default', [StoreLocationController::class, 'allPromoteDefault'])->name('promote-default');
            Route::post('/{locationId}/shiprocket-sync', [StoreLocationController::class, 'allShiprocketSync'])->name('shiprocket-sync');
        });

        Route::prefix('shipping')->name('shipping.')->group(function () {
            Route::get('/', [ShippingProfileController::class, 'allIndex'])->name('index');
            Route::get('/create', [ShippingProfileController::class, 'allCreate'])->name('create');
            Route::post('/', [ShippingProfileController::class, 'allStore'])->name('store');
            Route::get('/products-search', [ShippingProfileController::class, 'productsSearch'])->name('products-search');

            Route::prefix('packages')->name('packages.')->group(function () {
                Route::get('/', [StorePackageController::class, 'index'])->name('index');
                Route::get('/create', [StorePackageController::class, 'create'])->name('create');
                Route::post('/', [StorePackageController::class, 'store'])->name('store');
                Route::get('/{packageId}/edit', [StorePackageController::class, 'edit'])->name('edit');
                Route::post('/{packageId}', [StorePackageController::class, 'update'])->name('update');
                Route::delete('/{packageId}', [StorePackageController::class, 'destroy'])->name('delete');
                Route::post('/{packageId}/promote-default', [StorePackageController::class, 'promoteDefault'])->name('promote-default');
            });

            Route::get('/{profileId}/edit', [ShippingProfileController::class, 'allEdit'])->name('edit');
            Route::post('/{profileId}', [ShippingProfileController::class, 'allUpdate'])->name('update');
            Route::delete('/{profileId}', [ShippingProfileController::class, 'allDestroy'])->name('delete');

            Route::prefix('{profileId}/zones')->name('zones.')->group(function () {
                Route::post('/', [ShippingZoneController::class, 'store'])->name('store');
                Route::get('/{zoneId}', [ShippingZoneController::class, 'show'])->name('show');
                Route::post('/{zoneId}', [ShippingZoneController::class, 'update'])->name('update');
                Route::delete('/{zoneId}', [ShippingZoneController::class, 'delete'])->name('delete');
            });

            Route::prefix('geo')->name('geo.')->group(function () {
                Route::get('/states', [GeoController::class, 'states'])->name('states');
                Route::get('/cities', [GeoController::class, 'cities'])->name('cities');
            });
        });

        Route::bulkUpload('products');

        // products
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('/create', [ProductController::class, 'create'])->name('create');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('/datatable', [ProductController::class, 'getProducts'])->name('datatable');
            Route::get('/search', [ProductController::class, 'search'])->name('search');
            Route::get('/lookup/attributes', [ProductController::class, 'attributeOptions'])->name('lookup.attributes');
            Route::get('/lookup/kit-components', [ProductController::class, 'kitComponentOptions'])->name('lookup.kit-components');
            Route::get('/lookup/attributes/{attribute}/values', [ProductController::class, 'attributeValueOptions'])->whereNumber('attribute')->name('lookup.attribute-values');
            // Separate images ZIP upload for products/variants
            Route::get('/images-upload', [ProductImageUploadController::class, 'imagesUploadPage'])->name('images-upload.page');
            Route::post('/images-upload', [ProductImageUploadController::class, 'imagesUpload'])->name('images-upload');
            Route::get('/images-upload/status/{token}', [ProductImageUploadController::class, 'imagesUploadStatus'])->name('images-upload.status');
            Route::get('/{id}/edit', [ProductController::class, 'edit'])->name('edit');
            Route::get('/{id}/visibility', [ProductController::class, 'visibility'])->name('visibility');
            Route::get('/{id}/pricing', [ProductController::class, 'getProductPricing'])->name('pricing');
            Route::post('/{id}', [ProductController::class, 'update'])->name('update');
            Route::post('/{id}/update-status', [ProductController::class, 'updateStatus'])->name('update-status');
            Route::delete('/{id}', [ProductController::class, 'destroy'])->name('delete');
            Route::get('/{id}', [ProductController::class, 'show'])->name('show');
        });

        // global attributes
        Route::prefix('attributes')->name('attributes.')->group(function () {
            Route::get('/', [GlobalAttributeController::class, 'index'])->name('index');
            Route::get('/create', [GlobalAttributeController::class, 'create'])->name('create');
            Route::post('/', [GlobalAttributeController::class, 'store'])->name('store');
            Route::get('/datatable', [GlobalAttributeController::class, 'getAttributes'])->name('datatable');
            Route::get('/search', [GlobalAttributeController::class, 'search'])->name('search');
            Route::get('/{id}/edit', [GlobalAttributeController::class, 'edit'])->name('edit');
            Route::post('/{id}', [GlobalAttributeController::class, 'update'])->name('update');
            Route::delete('/{id}', [GlobalAttributeController::class, 'destroy'])->name('delete');
        });

        // product conditions
        Route::prefix('product-conditions')->name('product_conditions.')->group(function () {
            Route::get('/', [ProductConditionController::class, 'index'])->name('index');
            Route::post('/', [ProductConditionController::class, 'store'])->name('store');
            Route::get('/datatable', [ProductConditionController::class, 'getProductConditions'])->name('datatable');
            Route::get('/search', [ProductConditionController::class, 'search'])->name('search');
            Route::get('/{id}/edit', [ProductConditionController::class, 'edit'])->name('edit');
            Route::post('/{id}', [ProductConditionController::class, 'update'])->name('update');
            Route::delete('/{id}', [ProductConditionController::class, 'destroy'])->name('delete');
        });

        Route::prefix('attribute/values')->name('attributes.values.')->group(function () {
            Route::get('/datatable', [GlobalAttributeValueController::class, 'getAllAttributeValues'])->name('datatable');
            Route::post('/', [GlobalAttributeValueController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [GlobalAttributeValueController::class, 'edit'])->name('edit');
            Route::post('/{id}', [GlobalAttributeValueController::class, 'update'])->name('update');
            Route::delete('/{id}', [GlobalAttributeValueController::class, 'destroy'])->name('delete');
        });

        // product Faqs
        Route::prefix('product-faqs')->name('product_faqs.')->group(function () {
            Route::get('/', [ProductFaqController::class, 'index'])->name('index');
            Route::get('/create', [ProductFaqController::class, 'create'])->name('create');
            Route::post('/bulk', [ProductFaqController::class, 'bulkStore'])->name('bulk-store');
            Route::post('/', [ProductFaqController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [ProductFaqController::class, 'edit'])->name('edit');
            Route::post('/{id}', [ProductFaqController::class, 'update'])->name('update');
            Route::delete('/{id}', [ProductFaqController::class, 'destroy'])->name('delete');
            Route::get('/datatable', [ProductFaqController::class, 'getProductFaqs'])->name('datatable');
            //            Route::get('/search', [ProductFaqController::class, 'search'])->name('search');
        });

        // wallet
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('/', [WalletController::class, 'index'])->name('index');
            Route::get('/transactions', [WalletController::class, 'transactions'])->name('transactions');
            Route::get('/transactions/datatable', [WalletController::class, 'getTransactions'])->name('transactions.datatable');
        });

        // ads (gated: coming soon)
        Route::prefix('ads')->name('ads.')->middleware('coming.soon:ads')->group(function () {
            Route::prefix('wallet')->name('wallet.')->group(function () {
                Route::get('/', [AdWalletController::class, 'index'])->name('index');
                Route::get('/topup', [AdWalletController::class, 'topupForm'])->name('topup');
                Route::post('/topup/gateway', [AdWalletController::class, 'topupGateway'])->name('topup.gateway');
                Route::post('/topup/earnings', [AdWalletController::class, 'topupFromEarnings'])->name('topup.earnings');
                Route::get('/transactions', [AdWalletController::class, 'transactions'])->name('transactions');
                Route::get('/transactions/datatable', [AdWalletController::class, 'getTransactions'])->name('transactions.datatable');
            });

            Route::prefix('campaigns')->name('campaigns.')->group(function () {
                Route::get('/', [AdCampaignController::class, 'index'])->name('index');
                Route::get('/create', [AdCampaignController::class, 'create'])->name('create');
                Route::post('/', [AdCampaignController::class, 'store'])->name('store');
                Route::get('/datatable', [AdCampaignController::class, 'getCampaigns'])->name('datatable');
                Route::get('/dashboard', [AdCampaignController::class, 'dashboard'])->name('dashboard');
                Route::get('/dashboard/data', [AdCampaignController::class, 'getDashboardData'])->name('dashboard.data');
                Route::post('/{id}/pause', [AdCampaignController::class, 'pause'])->name('pause');
                Route::post('/{id}/resume', [AdCampaignController::class, 'resume'])->name('resume');
            });
        });

        // withdrawals
        Route::prefix('withdrawals')->name('withdrawals.')->group(function () {
            Route::get('/', [WithdrawalController::class, 'index'])->name('index');
            Route::post('/', [WithdrawalController::class, 'store'])->name('store');
            Route::get('/history', [WithdrawalController::class, 'history'])->name('history');
            Route::get('/datatable', [WithdrawalController::class, 'getWithdrawalRequests'])->name('datatable');
            Route::get('/history/datatable', [WithdrawalController::class, 'getWithdrawalHistory'])->name('history.datatable');
        });

        // orders
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::get('/list-datatable', [OrderController::class, 'getOrdersGrouped'])->name('list_datatable');
            Route::get('/list-items/{id}', [OrderController::class, 'getOrderItemsForExpand'])->name('list_items');
            Route::get('/popup-status', [OrderController::class, 'getPopupOrderStatuses'])->name('popup_status');
            Route::get('/{id}', [OrderController::class, 'show'])->name('show');
            // Phase 1D — must be declared before any catch-all so "cancel"
            // isn't matched as a status.
            Route::post('/{id}/cancel-item', [OrderController::class, 'cancelItem'])->name('cancel_item');
            // Phase 3 — hypercommerce fulfillment.
            Route::post('/{id}/fulfill', [OrderController::class, 'fulfill'])->name('fulfill');
            Route::post('/{id}/fulfillment-status', [OrderController::class, 'setFulfillmentStatus'])->name('set_fulfillment_status');
            Route::post('/shipments/{shipmentId}/transition', [OrderController::class, 'transitionShipment'])->name('shipment_transition');
            Route::post('/shipments/{shipmentId}/retry-booking', [OrderController::class, 'retryShipmentBooking'])->name('shipment_retry_booking');
            Route::post('/shipments/{shipmentId}/update-lines', [OrderController::class, 'updateShipmentLines'])->name('shipment_update_lines');
            Route::post('/shipments/{shipmentId}/ndr-action', [OrderController::class, 'ndrShipmentAction'])->name('shipment_ndr_action');
            Route::post('/shipments/{shipmentId}/sync', [OrderController::class, 'syncShipment'])->name('shipment_sync');
            Route::post('/shipments/{shipmentId}/edit', [OrderController::class, 'editShipment'])->name('shipment_edit');
            Route::get('/shipments/{shipmentId}/packing-slip', [OrderController::class, 'packingSlip'])->name('packing_slip');
            // Seller returns + restock (additive) — declared before the
            // {id}/{status} catch-all so "returns" / "restock" aren't captured
            // as a status. Sellers may only REVIEW (accept/decline + return-ship)
            // a customer-initiated return — they cannot create one, so there are
            // intentionally no create routes here.
            Route::get('/returns/{returnId}/review', [OrderController::class, 'sellerReturnReview'])->name('return_review');
            Route::post('/returns/{returnId}/approve', [OrderController::class, 'sellerReturnApprove'])->name('return_approve');
            Route::post('/returns/{returnId}/decline', [OrderController::class, 'sellerReturnDecline'])->name('return_decline');
            Route::post('/{id}/restock', [OrderController::class, 'sellerRestockItems'])->name('restock');
        });

        // Commission Settlement Routes
        Route::prefix('commissions')->name('commissions.')->group(function () {
            Route::get('/', [SellerEarningController::class, 'index'])->name('index');
            // Summary cards
            Route::get('/summary', [SellerEarningController::class, 'summary'])->name('summary');
            // CSV export + print statement
            Route::get('/export', [SellerEarningController::class, 'export'])->name('export');
            Route::get('/statement', [SellerEarningController::class, 'statement'])->name('statement');
            // Credits
            Route::get('/datatable', [SellerEarningController::class, 'getUnsettledCommissions'])->name('datatable');
            // Debits
            Route::get('/debits/datatable', [SellerEarningController::class, 'getUnsettledDebits'])->name('debits.datatable');
            // History
            Route::get('/history', [SellerEarningController::class, 'history'])->name('history');
            Route::get('/history/datatable', [SellerEarningController::class, 'getSettledCommissions'])->name('history.datatable');
            // Detail (single settlement breakdown)
            Route::get('/{id}/detail', [SellerEarningController::class, 'commissionDetail'])->whereNumber('id')->name('detail');
        });

        // Notifications Routes
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('/datatable', [NotificationController::class, 'getNotifications'])->name('datatable');
            Route::get('/{id}', [NotificationController::class, 'show'])->name('show');
            Route::post('/{id}/mark-read', [NotificationController::class, 'markAsRead'])->name('mark-read');
            Route::post('/{id}/mark-unread', [NotificationController::class, 'markAsUnread'])->name('mark-unread');
            Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-read');
            Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
        });

        // Return requests listing / datatable
        Route::get('order-returns', [ReturnRequestController::class, 'index'])->name('returns.index');
        Route::get('order-returns/datatable', [ReturnRequestController::class, 'datatable'])->name('returns.datatable');

        // Approve / reject
        Route::post('order-returns/{id}/decision', [ReturnRequestController::class, 'decision'])->name('returns.decision');

        // ── POS (Point of Sale) ───────────────────────── (gated: coming soon)
        Route::prefix('pos')->name('pos.')->middleware('coming.soon:pos')->group(function () {
            Route::get('/dashboard', [PosDashboardController::class, 'index'])->name('dashboard');
            Route::get('/dashboard/data', [PosDashboardController::class, 'getData'])->name('dashboard.data');
            Route::get('/', [PosController::class, 'index'])->name('index');
            Route::get('/orders/{id}/receipt', [PosController::class, 'receipt'])
                ->whereNumber('id')->name('orders.receipt');

            Route::prefix('api')->name('api.')->group(function () {
                Route::get('/products', [PosController::class, 'searchProducts'])->name('products.search');
                Route::get('/products/by-barcode', [PosController::class, 'lookupByBarcode'])->name('products.barcode');
                Route::get('/categories', [PosController::class, 'categories'])->name('categories.index');
                Route::get('/customers', [PosController::class, 'searchCustomers'])->name('customers.search');
                Route::get('/users/search', [UserApiController::class, 'search'])->name('users.search');
                Route::post('/customers', [PosController::class, 'registerCustomer'])->name('customers.register');
                Route::post('/orders', [PosController::class, 'createOrder'])->name('orders.create');
                Route::get('/orders/recent', [PosController::class, 'recentOrders'])->name('orders.recent');
                Route::post('/promo/validate', [PosController::class, 'validatePromo'])->name('promo.validate');
                Route::get('/customers/{id}/wallet', [PosController::class, 'customerWallet'])->whereNumber('id')->name('customers.wallet');
                Route::post('/cfd/push', [PosController::class, 'cfdPush'])->name('cfd.push');

                Route::get('/parked', [PosController::class, 'listParkedSales'])->name('parked.list');
                Route::post('/parked', [PosController::class, 'parkSale'])->name('parked.create');
                Route::delete('/parked/{id}', [PosController::class, 'deleteParkedSale'])->whereNumber('id')->name('parked.delete');

                Route::get('/orders/{id}/refund-preview', [PosController::class, 'refundPreview'])->whereNumber('id')->name('refunds.preview');
                Route::post('/orders/{id}/refund', [PosController::class, 'createRefund'])->whereNumber('id')->name('refunds.create');
                Route::get('/orders/{id}/refunds', [PosController::class, 'refundHistory'])->whereNumber('id')->name('refunds.history');

                Route::prefix('payment-sessions')->name('payment_sessions.')->group(function () {
                    Route::post('/', [PosPaymentSessionController::class, 'create'])->name('create');
                    Route::get('/{token}', [PosPaymentSessionController::class, 'status'])->name('status');
                    Route::post('/{token}/cancel', [PosPaymentSessionController::class, 'cancel'])->name('cancel');
                });
            });
        });

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/sales', [SellerReportController::class, 'sales'])->name('sales');
            Route::get('/sales/datatable', [SellerReportController::class, 'salesDatatable'])->name('sales.datatable');
            Route::get('/sales/chart', [SellerReportController::class, 'salesChart'])->name('sales.chart');

            Route::get('/products', [SellerReportController::class, 'products'])->name('products');
            Route::get('/products/datatable', [SellerReportController::class, 'productsDatatable'])->name('products.datatable');
            Route::get('/products/chart', [SellerReportController::class, 'productsChart'])->name('products.chart');

            Route::get('/settlements', [SellerReportController::class, 'settlements'])->name('settlements');
            Route::get('/settlements/datatable', [SellerReportController::class, 'settlementsDatatable'])->name('settlements.datatable');
            Route::get('/settlements/chart', [SellerReportController::class, 'settlementsChart'])->name('settlements.chart');

            Route::get('/returns', [SellerReportController::class, 'returns'])->name('returns');
            Route::get('/returns/datatable', [SellerReportController::class, 'returnsDatatable'])->name('returns.datatable');
            Route::get('/returns/chart', [SellerReportController::class, 'returnsChart'])->name('returns.chart');

            Route::get('/stores', [SellerReportController::class, 'stores'])->name('stores');
            Route::get('/stores/datatable', [SellerReportController::class, 'storesDatatable'])->name('stores.datatable');
            Route::get('/stores/chart', [SellerReportController::class, 'storesChart'])->name('stores.chart');

            Route::get('/taxes', [SellerReportController::class, 'taxes'])->name('taxes');
            Route::get('/taxes/datatable', [SellerReportController::class, 'taxesDatatable'])->name('taxes.datatable');
            Route::get('/taxes/chart', [SellerReportController::class, 'taxesChart'])->name('taxes.chart');
        });
    });
});
