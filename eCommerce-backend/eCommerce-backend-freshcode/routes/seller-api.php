<?php

use App\Http\Controllers\Api\Seller\SellerAuthApiController;
use App\Http\Controllers\Api\Seller\SellerOtpApiController;
use App\Http\Controllers\Api\Seller\SellerAttributeApiController;
use App\Http\Controllers\Api\Seller\SellerAttributeValueApiController;
use App\Http\Controllers\Api\Seller\SellerProfileApiController;
use App\Http\Controllers\Api\Seller\SellerStoreApiController;
use App\Http\Controllers\Api\Seller\SellerStoreLocationApiController;
use App\Http\Controllers\Api\Seller\SellerProductApiController;
use App\Http\Controllers\Api\Seller\SellerTaxClassApiController;
use App\Http\Controllers\Api\Seller\SellerDashboardApiController;
use App\Http\Controllers\Api\Seller\SellerOrderApiController;
use App\Http\Controllers\Api\Seller\SellerWalletApiController;
use App\Http\Controllers\Api\Seller\SellerWithdrawalApiController;
use App\Http\Controllers\Api\Seller\SellerEarningApiController;
use App\Http\Controllers\Api\Seller\SellerBrandApiController;
use App\Http\Controllers\Api\Seller\SellerProductFaqApiController;
use App\Http\Controllers\Api\Seller\SellerRoleApiController;
use App\Http\Controllers\Api\Seller\SellerPermissionApiController;
use App\Http\Controllers\Api\Seller\SubscriptionPlanApiController as SellerSubscriptionPlanApiController;
use App\Http\Controllers\Api\Seller\SellerSystemUserApiController;
use App\Http\Controllers\Api\Seller\SellerAddonGroupApiController;
use App\Http\Controllers\Api\Seller\SellerProductAddonApiController;
use App\Http\Controllers\Api\Seller\SellerStoreAddonItemApiController;
use App\Http\Controllers\Api\Seller\SellerAdCampaignApiController;
use App\Http\Controllers\Api\Seller\SellerAdWalletApiController;
use App\Http\Controllers\Api\Seller\SellerNotificationApiController;
use App\Http\Controllers\Api\Seller\SellerPosCustomerApiController;
use App\Http\Controllers\Api\Seller\SellerPosOrderApiController;
use App\Http\Controllers\Api\Seller\SellerPosProductApiController;
use App\Http\Controllers\Api\Seller\SellerCatalogApiController;
use App\Http\Controllers\Api\Seller\SellerCurrencyApiController;
use App\Http\Controllers\Api\Seller\SellerShippingApiController;
use App\Http\Controllers\Api\Seller\SellerWatchBuyProfileApiController;
use App\Http\Controllers\Api\Seller\SellerWatchBuyReelApiController;
use App\Http\Controllers\Api\Seller\SellerWatchBuyStatusApiController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\GeoController;
use App\Http\Controllers\GlobalAttributeController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductConditionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImageUploadController;
use App\Http\Controllers\ReturnRequestController;
use App\Http\Controllers\SellerEarningController;
use App\Http\Controllers\ShippingProfileController;
use App\Http\Controllers\ShippingZoneController;
use App\Http\Controllers\StoreAddonItemController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StorePackageController;
use App\Http\Controllers\TaxClassController;
use App\Http\Controllers\TaxRateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Seller API — mirrors the seller panel (routes/seller-route.php)
|--------------------------------------------------------------------------
|
| Store scope: every authenticated route runs through `seller.api.store`,
| which resolves the active store from the `X-Store-Id` header (falling back
| to the seller's first store) and pins it for the request. A store id the
| seller does not own is rejected with 403. Clients read `GET seller/context`
| once and then send the chosen id as `X-Store-Id` on every later call.
|
| Route names deliberately live under `seller.api.` so PanelAware resolves the
| seller panel for the panel controllers reused below.
|
| Some endpoints point at the panel controllers rather than API-specific ones:
| those methods already return JSON and are panel-scoped, so reusing them keeps
| the app and the panel behaviourally identical (Shiprocket booking, NDR
| actions, return decisions, bulk product upload).
|
*/

Route::prefix('seller')->name('seller-api.')->group(function () {
    Route::post('register', [SellerAuthApiController::class, 'createSeller'])->name('register');
    Route::post('login', [SellerAuthApiController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('auth/phone/callback', [SellerAuthApiController::class, 'phoneCallback'])->name('phone_callback');
    Route::post('forget-password', [SellerAuthApiController::class, 'forgotPassword'])->middleware('throttle:password-reset')->name('forgot_password');

    Route::post('login/send-otp', [SellerOtpApiController::class, 'sendLoginOtp'])->middleware('throttle:otp-send')->name('login.send_otp');
    Route::post('login/verify-otp', [SellerOtpApiController::class, 'verifyLoginOtp'])->middleware('throttle:otp-verify')->name('login.verify_otp');
    Route::post('forget-password/send-otp', [SellerOtpApiController::class, 'sendForgotOtp'])->middleware('throttle:password-reset')->name('forget_password.send_otp');
    Route::post('forget-password/verify-otp', [SellerOtpApiController::class, 'verifyForgotOtp'])->middleware('throttle:otp-verify')->name('forget_password.verify_otp');
    Route::post('reset-password', [SellerOtpApiController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('reset_password');
});

Route::middleware(['auth:sanctum', 'validate.seller', 'seller.api.store'])
    ->prefix('seller')
    ->name('seller.api.')
    ->group(function () {
        Route::post('logout', [SellerAuthApiController::class, 'logout'])->name('logout');
        Route::delete('delete-account', [SellerAuthApiController::class, 'deleteAccount'])->name('delete_account');
        Route::get('currency-conversion', [SellerCurrencyApiController::class, 'conversion'])->name('currency_conversion');

        Route::prefix('watch-and-buy')->name('watch-and-buy.')->group(function () {
            Route::get('profile', [SellerWatchBuyProfileApiController::class, 'show'])->name('profile.show');
            Route::post('profile', [SellerWatchBuyProfileApiController::class, 'store'])->name('profile.store');
            Route::get('eligible-products', [SellerWatchBuyReelApiController::class, 'eligibleProducts'])->name('eligible-products');
            Route::get('reels', [SellerWatchBuyReelApiController::class, 'index'])->name('reels.index');
            Route::post('reels', [SellerWatchBuyReelApiController::class, 'store'])->name('reels.store');
            Route::get('reels/{reel}', [SellerWatchBuyReelApiController::class, 'show'])->name('reels.show');
            Route::post('reels/{reel}', [SellerWatchBuyReelApiController::class, 'update'])->name('reels.update');
            Route::delete('reels/{reel}', [SellerWatchBuyReelApiController::class, 'destroy'])->name('reels.destroy');
            Route::put('reels/{reel}/publish', [SellerWatchBuyReelApiController::class, 'publish'])->name('reels.publish');
            Route::get('statuses', [SellerWatchBuyStatusApiController::class, 'index'])->name('statuses.index');
            Route::post('statuses', [SellerWatchBuyStatusApiController::class, 'store'])->name('statuses.store');
            Route::delete('statuses/{status}', [SellerWatchBuyStatusApiController::class, 'destroy'])->name('statuses.destroy');
        });

        // Active store + switchable stores for the header selector.
        Route::get('context', [SellerStoreApiController::class, 'context'])->name('context');

        // Browser/app FCM token sync.
        Route::post('devices/sync', [DeviceTokenController::class, 'sync'])->name('devices.sync');
        Route::delete('devices', [DeviceTokenController::class, 'forget'])->name('devices.forget');

        // Profile
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [SellerProfileApiController::class, 'index'])->name('index');
            Route::post('/', [SellerProfileApiController::class, 'update'])->name('update');
            Route::post('/password', [SellerProfileApiController::class, 'changePassword'])->name('password');
        });

        // Dashboard
        Route::get('dashboard', [SellerDashboardApiController::class, 'overview'])->name('dashboard.overview');
        Route::get('dashboard/summary', [SellerDashboardApiController::class, 'summary'])->name('dashboard.summary');
        Route::get('dashboard/chart', [SellerDashboardApiController::class, 'chart'])->name('dashboard.chart');

        // Markets the seller can sell in (picker lookup)
        Route::get('markets/search', [MarketController::class, 'searchTom'])->name('markets.search');

        // Notifications
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [SellerNotificationApiController::class, 'index'])->name('index');
            Route::get('/unread-count', [SellerNotificationApiController::class, 'unreadCount'])->name('unread_count');
            Route::post('/mark-all-read', [SellerNotificationApiController::class, 'markAllAsRead'])->name('mark_all_read');
            Route::get('/{id}', [SellerNotificationApiController::class, 'show'])->name('show');
            Route::post('/{id}/read', [SellerNotificationApiController::class, 'markAsRead'])->name('read');
            Route::post('/{id}/unread', [SellerNotificationApiController::class, 'markAsUnread'])->name('unread');
            Route::delete('/{id}', [SellerNotificationApiController::class, 'destroy'])->name('destroy');
        });

        // Stores
        Route::prefix('stores')->name('stores.')->group(function () {
            Route::get('/', [SellerStoreApiController::class, 'index'])->name('index');
            Route::get('/enums', [SellerStoreApiController::class, 'getStoresEnums'])->name('enums');
            Route::get('/list', [SellerStoreApiController::class, 'list'])->name('list');
            Route::get('/with-locations', [SellerStoreApiController::class, 'withLocations'])->name('with_locations');
            Route::get('/search', [StoreController::class, 'search'])->name('search');
            Route::post('/switch', [SellerStoreApiController::class, 'switchStore'])->name('switch');
            Route::post('/', [SellerStoreApiController::class, 'store'])->name('store');
            Route::get('/{id}', [SellerStoreApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/{id}', [SellerStoreApiController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [SellerStoreApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::get('/{id}/configuration', [SellerStoreApiController::class, 'configuration'])->whereNumber('id')->name('configuration');
            Route::post('/{id}/configuration', [SellerStoreApiController::class, 'updateConfiguration'])->whereNumber('id')->name('configuration.update');
        });

        // Ship-from locations (cross-store, scoped by X-Store-Id)
        Route::prefix('locations')->name('locations.')->group(function () {
            Route::get('/', [SellerStoreLocationApiController::class, 'index'])->name('index');
            Route::post('/', [SellerStoreLocationApiController::class, 'store'])->name('store');
            Route::get('/{id}', [SellerStoreLocationApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/{id}', [SellerStoreLocationApiController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [SellerStoreLocationApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
            Route::post('/{id}/promote-default', [SellerStoreLocationApiController::class, 'promoteDefault'])->whereNumber('id')->name('promote_default');
            Route::post('/{id}/shiprocket-sync', [SellerStoreLocationApiController::class, 'shiprocketSync'])->whereNumber('id')->name('shiprocket_sync');
        });

        // Geo lookups for address/zone pickers
        Route::prefix('geo')->name('geo.')->group(function () {
            Route::get('/states', [GeoController::class, 'states'])->name('states');
            Route::get('/cities', [GeoController::class, 'cities'])->name('cities');
        });

        // Shipping: profiles → zones → rates, plus parcel presets
        Route::prefix('shipping')->name('shipping.')->group(function () {
            Route::get('/enums', [SellerShippingApiController::class, 'enums'])->name('enums');
            Route::get('/products-search', [ShippingProfileController::class, 'productsSearch'])->name('products_search');

            Route::prefix('packages')->name('packages.')->group(function () {
                Route::get('/', [SellerShippingApiController::class, 'packages'])->name('index');
                Route::post('/', [StorePackageController::class, 'store'])->name('store');
                Route::get('/{packageId}', [SellerShippingApiController::class, 'package'])->whereNumber('packageId')->name('show');
                Route::post('/{packageId}', [StorePackageController::class, 'update'])->whereNumber('packageId')->name('update');
                Route::delete('/{packageId}', [StorePackageController::class, 'destroy'])->whereNumber('packageId')->name('destroy');
                Route::post('/{packageId}/promote-default', [StorePackageController::class, 'promoteDefault'])->whereNumber('packageId')->name('promote_default');
            });

            Route::get('/', [SellerShippingApiController::class, 'profiles'])->name('index');
            Route::post('/', [ShippingProfileController::class, 'allStore'])->name('store');
            Route::get('/{profileId}', [SellerShippingApiController::class, 'profile'])->whereNumber('profileId')->name('show');
            Route::post('/{profileId}', [ShippingProfileController::class, 'allUpdate'])->whereNumber('profileId')->name('update');
            Route::delete('/{profileId}', [ShippingProfileController::class, 'allDestroy'])->whereNumber('profileId')->name('destroy');

            Route::prefix('{profileId}/zones')->name('zones.')->whereNumber('profileId')->group(function () {
                Route::post('/', [ShippingZoneController::class, 'store'])->name('store');
                Route::get('/{zoneId}', [ShippingZoneController::class, 'show'])->whereNumber('zoneId')->name('show');
                Route::post('/{zoneId}', [ShippingZoneController::class, 'update'])->whereNumber('zoneId')->name('update');
                Route::delete('/{zoneId}', [ShippingZoneController::class, 'delete'])->whereNumber('zoneId')->name('destroy');
            });
        });

        // Categories (platform-wide taxonomy — read-only for sellers)
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [SellerCatalogApiController::class, 'categories'])->name('index');
            Route::get('/tree', [CategoryController::class, 'tree'])->name('tree');
            Route::get('/search', [CategoryController::class, 'search'])->name('search');
            Route::get('/{id}', [CategoryController::class, 'show'])->whereNumber('id')->name('show');
        });

        // Product conditions (platform-wide — read-only for sellers)
        Route::prefix('product-conditions')->name('product_conditions.')->group(function () {
            Route::get('/', [SellerCatalogApiController::class, 'productConditions'])->name('index');
            Route::get('/search', [ProductConditionController::class, 'search'])->name('search');
            Route::get('/{id}', [ProductConditionController::class, 'show'])->whereNumber('id')->name('show');
        });

        // Tax rates (platform-wide — read-only for sellers)
        Route::prefix('tax-rates')->name('tax_rates.')->group(function () {
            Route::get('/', [SellerCatalogApiController::class, 'taxRates'])->name('index');
            Route::get('/search', [TaxRateController::class, 'search'])->name('search');
            Route::get('/{id}', [TaxRateController::class, 'show'])->whereNumber('id')->name('show');
        });

        // Products
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/', [SellerProductApiController::class, 'index'])->name('index');
            Route::get('/enums', [SellerProductApiController::class, 'getProductEnums'])->name('enums');
            Route::get('/search', [SellerProductApiController::class, 'search'])->name('search');
            Route::post('/bulk-upload', [ProductController::class, 'bulkUpload'])->name('bulk_upload');
            Route::post('/images-upload', [ProductImageUploadController::class, 'imagesUpload'])->name('images_upload');
            Route::get('/images-upload/status/{token}', [ProductImageUploadController::class, 'imagesUploadStatus'])->name('images_upload.status');
            Route::post('/', [SellerProductApiController::class, 'store'])->name('store');
            Route::get('/{id}', [SellerProductApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::get('/{id}/visibility', [SellerProductApiController::class, 'visibility'])->whereNumber('id')->name('visibility');
            Route::get('/{id}/pricing', [ProductController::class, 'getProductPricing'])->whereNumber('id')->name('pricing');
            Route::post('/{id}', [SellerProductApiController::class, 'update'])->whereNumber('id')->name('update');
            Route::post('/{id}/update-status', [SellerProductApiController::class, 'updateStatus'])->whereNumber('id')->name('update_status');
            Route::delete('/{id}', [SellerProductApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        // Product FAQs
        Route::prefix('product-faqs')->name('product_faqs.')->group(function () {
            Route::get('/', [SellerProductFaqApiController::class, 'index'])->name('index');
            Route::get('/{id}', [SellerProductFaqApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/', [SellerProductFaqApiController::class, 'store'])->name('store');
            Route::post('/{id}', [SellerProductFaqApiController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [SellerProductFaqApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        // Attributes
        Route::prefix('attributes')->name('attributes.')->group(function () {
            Route::get('/', [SellerAttributeApiController::class, 'index'])->name('index');
            Route::get('/search', [GlobalAttributeController::class, 'search'])->name('search');
            Route::get('/{id}', [SellerAttributeApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/', [SellerAttributeApiController::class, 'store'])->name('store');
            Route::post('/{id}', [SellerAttributeApiController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [SellerAttributeApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        Route::prefix('attribute-values')->name('attribute_values.')->group(function () {
            Route::get('/', [SellerAttributeValueApiController::class, 'index'])->name('index');
            Route::get('/{id}', [SellerAttributeValueApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/', [SellerAttributeValueApiController::class, 'store'])->name('store');
            Route::post('/{id}', [SellerAttributeValueApiController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [SellerAttributeValueApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        // Tax classes (platform-wide — read-only for sellers)
        Route::prefix('tax-classes')->name('tax_classes.')->group(function () {
            Route::get('/', [SellerTaxClassApiController::class, 'index'])->name('index');
            Route::get('/search', [TaxClassController::class, 'search'])->name('search');
            Route::get('/{id}', [SellerTaxClassApiController::class, 'show'])->whereNumber('id')->name('show');
        });

        // Brands (platform-wide — read-only for sellers)
        Route::prefix('brands')->name('brands.')->group(function () {
            Route::get('/', [SellerBrandApiController::class, 'index'])->name('index');
            Route::get('/groups', [BrandController::class, 'groups'])->name('groups');
            Route::get('/search', [BrandController::class, 'search'])->name('search');
            Route::get('/{id}', [SellerBrandApiController::class, 'show'])->whereNumber('id')->name('show');
        });

        // Orders, shipments and returns
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [SellerOrderApiController::class, 'index'])->name('index');
            Route::get('/enums', [SellerOrderApiController::class, 'enums'])->name('enums');

            // Returns — declared before /{id} so "returns" is never read as an id.
            Route::get('/returns', [SellerOrderApiController::class, 'returns'])->name('returns.index');
            Route::get('/returns/{returnId}', [SellerOrderApiController::class, 'returnDetail'])->whereNumber('returnId')->name('returns.show');
            Route::post('/returns/{returnId}/approve', [OrderController::class, 'sellerReturnApprove'])->whereNumber('returnId')->name('returns.approve');
            Route::post('/returns/{returnId}/decline', [OrderController::class, 'sellerReturnDecline'])->whereNumber('returnId')->name('returns.decline');
            Route::post('/returns/{returnId}/decision', [ReturnRequestController::class, 'decision'])->whereNumber('returnId')->name('returns.decision');

            // Shipments — literal segment declared before /{id}.
            Route::prefix('shipments')->name('shipments.')->group(function () {
                Route::post('/{shipmentId}/transition', [OrderController::class, 'transitionShipment'])->whereNumber('shipmentId')->name('transition');
                Route::post('/{shipmentId}/retry-booking', [OrderController::class, 'retryShipmentBooking'])->whereNumber('shipmentId')->name('retry_booking');
                Route::post('/{shipmentId}/update-lines', [OrderController::class, 'updateShipmentLines'])->whereNumber('shipmentId')->name('update_lines');
                Route::post('/{shipmentId}/ndr-action', [OrderController::class, 'ndrShipmentAction'])->whereNumber('shipmentId')->name('ndr_action');
                Route::post('/{shipmentId}/sync', [OrderController::class, 'syncShipment'])->whereNumber('shipmentId')->name('sync');
                Route::post('/{shipmentId}/edit', [OrderController::class, 'editShipment'])->whereNumber('shipmentId')->name('edit');
                Route::get('/{shipmentId}/packing-slip', [OrderController::class, 'packingSlip'])->whereNumber('shipmentId')->name('packing_slip');
            });

            Route::get('/{id}', [SellerOrderApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::get('/{id}/items', [OrderController::class, 'getOrderItemsForExpand'])->whereNumber('id')->name('items');
            Route::get('/{id}/fulfillment', [OrderController::class, 'sellerFulfillment'])->whereNumber('id')->name('fulfillment');
            Route::get('/{id}/earnings', [SellerOrderApiController::class, 'earnings'])->whereNumber('id')->name('earnings');
            Route::get('/{id}/returns', [SellerOrderApiController::class, 'orderReturns'])->whereNumber('id')->name('order_returns');
            Route::post('/{id}/cancel-item', [OrderController::class, 'cancelItem'])->whereNumber('id')->name('cancel_item');
            Route::post('/{id}/fulfill', [OrderController::class, 'fulfill'])->whereNumber('id')->name('fulfill');
            Route::post('/{id}/fulfillment-status', [OrderController::class, 'setFulfillmentStatus'])->whereNumber('id')->name('fulfillment_status');
            Route::post('/{id}/restock', [OrderController::class, 'sellerRestockItems'])->whereNumber('id')->name('restock');
        });

        // Wallet
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('/', [SellerWalletApiController::class, 'show'])->name('show');
            Route::get('/transactions', [SellerWalletApiController::class, 'transactions'])->name('transactions');
            Route::get('/transactions/{id}', [SellerWalletApiController::class, 'transaction'])->whereNumber('id')->name('transactions.show');
        });

        // Withdrawals
        Route::prefix('withdrawals')->name('withdrawals.')->group(function () {
            Route::get('/', [SellerWithdrawalApiController::class, 'getWithdrawalRequests'])->name('index');
            Route::get('/history', [SellerWithdrawalApiController::class, 'history'])->name('history');
            Route::post('/', [SellerWithdrawalApiController::class, 'createWithdrawalRequest'])->name('store');
            Route::get('/{id}', [SellerWithdrawalApiController::class, 'getWithdrawalRequest'])->whereNumber('id')->name('show');
        });

        // Earnings / settlements
        Route::prefix('commissions')->name('commissions.')->group(function () {
            Route::get('/', [SellerEarningApiController::class, 'unsettled'])->name('index');
            Route::get('/summary', [SellerEarningApiController::class, 'summary'])->name('summary');
            Route::get('/debits', [SellerEarningApiController::class, 'unsettledDebits'])->name('debits');
            Route::get('/history', [SellerEarningApiController::class, 'history'])->name('history');
            Route::get('/export', [SellerEarningController::class, 'export'])->name('export');
            Route::get('/statement', [SellerEarningController::class, 'statement'])->name('statement');
            Route::get('/{id}/detail', [SellerEarningApiController::class, 'detail'])->whereNumber('id')->name('detail');
        });

        // Roles, permissions and staff accounts
        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [SellerRoleApiController::class, 'index'])->name('index');
            Route::get('/list', [SellerRoleApiController::class, 'getRoles'])->name('list');
            Route::get('/{id}', [SellerRoleApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/', [SellerRoleApiController::class, 'store'])->name('store');
            Route::post('/{id}', [SellerRoleApiController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [SellerRoleApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        Route::prefix('permissions')->name('permissions.')->group(function () {
            Route::get('/{role}', [SellerPermissionApiController::class, 'index'])->name('index');
            Route::post('/', [SellerPermissionApiController::class, 'store'])->name('store');
        });

        Route::prefix('system-users')->name('system_users.')->group(function () {
            Route::get('/', [SellerSystemUserApiController::class, 'index'])->name('index');
            Route::get('/{id}', [SellerSystemUserApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/', [SellerSystemUserApiController::class, 'store'])->name('store');
            Route::post('/{id}', [SellerSystemUserApiController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [SellerSystemUserApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        // Addon groups
        Route::prefix('addon-groups')->name('addon_groups.')->group(function () {
            Route::get('/', [SellerAddonGroupApiController::class, 'index'])->name('index');
            Route::get('/enums', [SellerAddonGroupApiController::class, 'enums'])->name('enums');
            Route::get('/{id}', [SellerAddonGroupApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/', [SellerAddonGroupApiController::class, 'store'])->name('store');
            Route::post('/{id}', [SellerAddonGroupApiController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [SellerAddonGroupApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        // Product addon attachments (store × variant × addon_group × addon_item)
        Route::prefix('product-addons')->name('product_addons.')->group(function () {
            Route::get('/', [SellerProductAddonApiController::class, 'index'])->name('index');
            Route::get('/lookup/products', [SellerProductAddonApiController::class, 'productLookup'])->name('lookup.products');
            Route::get('/lookup/products/{product}/variants', [SellerProductAddonApiController::class, 'variantLookup'])->whereNumber('product')->name('lookup.variants');
            Route::get('/lookup/variants-bulk', [SellerProductAddonApiController::class, 'variantsBulkLookup'])->name('lookup.variants_bulk');
            Route::get('/lookup/addon-groups', [SellerProductAddonApiController::class, 'addonGroupLookup'])->name('lookup.addon_groups');
            Route::post('/', [SellerProductAddonApiController::class, 'store'])->name('store');
            Route::post('/show', [SellerProductAddonApiController::class, 'show'])->name('show');
            Route::get('/{id}/matrix', [SellerProductAddonApiController::class, 'matrix'])->whereNumber('id')->name('matrix');
            Route::post('/{variant}/{group}', [SellerProductAddonApiController::class, 'update'])->whereNumber('variant')->whereNumber('group')->name('update');
            Route::delete('/{variant}/{group}', [SellerProductAddonApiController::class, 'destroy'])->whereNumber('variant')->whereNumber('group')->name('destroy');
        });

        // Store addon inventory
        Route::prefix('store-addon-items')->name('store_addon_items.')->group(function () {
            Route::get('/', [SellerStoreAddonItemApiController::class, 'index'])->name('index');
            Route::get('/lookup/stores', [SellerStoreAddonItemApiController::class, 'stores'])->name('lookup.stores');
            Route::get('/lookup/addon-groups', [SellerStoreAddonItemApiController::class, 'addonGroups'])->name('lookup.addon_groups');
            Route::get('/lookup/groups/{groupId}/items', [SellerStoreAddonItemApiController::class, 'itemsForGroup'])->whereNumber('groupId')->name('lookup.items_for_group');
            Route::get('/lookup/state', [SellerStoreAddonItemApiController::class, 'state'])->name('lookup.state');
            Route::get('/lookup/state-matrix', [StoreAddonItemController::class, 'inventoryStateMatrix'])->name('lookup.state_matrix');
            Route::get('/{id}', [SellerStoreAddonItemApiController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/bulk', [SellerStoreAddonItemApiController::class, 'bulkStore'])->name('bulk_store');
            Route::post('/{id}', [SellerStoreAddonItemApiController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [SellerStoreAddonItemApiController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        // Subscriptions (gated: coming soon)
        Route::prefix('subscription')->name('subscription.')->middleware('coming.soon:subscription')->group(function () {
            Route::post('/check-eligibility', [SellerSubscriptionPlanApiController::class, 'checkEligibility'])->name('eligibility.check');
            Route::post('/buy', [SellerSubscriptionPlanApiController::class, 'buy'])->name('buy');
            Route::get('/current', [SellerSubscriptionPlanApiController::class, 'current'])->name('current');
            Route::get('/history', [SellerSubscriptionPlanApiController::class, 'history'])->name('history');
            Route::get('/{id}/status', [SellerSubscriptionPlanApiController::class, 'status'])->whereNumber('id')->name('status');
        });

        // Ad wallet + campaigns (gated: coming soon)
        Route::middleware('coming.soon:ads')->group(function () {
            Route::prefix('ad-wallet')->name('ad_wallet.')->group(function () {
                Route::get('/', [SellerAdWalletApiController::class, 'show'])->name('show');
                Route::post('/topup/gateway', [SellerAdWalletApiController::class, 'topupGateway'])->name('topup.gateway');
                Route::post('/topup/earnings', [SellerAdWalletApiController::class, 'topupFromEarnings'])->name('topup.earnings');
                Route::get('/transactions', [SellerAdWalletApiController::class, 'transactions'])->name('transactions');
                Route::get('/transactions/{id}', [SellerAdWalletApiController::class, 'transaction'])->whereNumber('id')->name('transactions.show');
            });

            Route::prefix('ad-campaigns')->name('ad_campaigns.')->group(function () {
                Route::get('/', [SellerAdCampaignApiController::class, 'index'])->name('index');
                Route::get('/config', [SellerAdCampaignApiController::class, 'config'])->name('config');
                Route::get('/{id}', [SellerAdCampaignApiController::class, 'show'])->whereNumber('id')->name('show');
                Route::post('/', [SellerAdCampaignApiController::class, 'store'])->name('store');
                Route::post('/{id}/pause', [SellerAdCampaignApiController::class, 'pause'])->whereNumber('id')->name('pause');
                Route::post('/{id}/resume', [SellerAdCampaignApiController::class, 'resume'])->whereNumber('id')->name('resume');
            });
        });

        // POS (gated: coming soon)
        Route::prefix('pos')->name('pos.')->middleware('coming.soon:pos')->group(function () {
            Route::get('/customers', [SellerPosCustomerApiController::class, 'search'])->name('customers.search');
            Route::post('/customers', [SellerPosCustomerApiController::class, 'quickRegister'])->name('customers.register');
            Route::get('/products', [SellerPosProductApiController::class, 'search'])->name('products.search');
            Route::post('/orders', [SellerPosOrderApiController::class, 'store'])->name('orders.store');
            Route::get('/orders/{id}/receipt', [SellerPosOrderApiController::class, 'receipt'])->whereNumber('id')->name('orders.receipt');
        });
    });
