<?php

use App\Http\Controllers\Admin\AdCampaignController as AdminAdCampaignController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BlogCategoryController;
use App\Http\Controllers\Admin\BlogController;
use App\Http\Controllers\Admin\Reports\AdminReportController;
use App\Http\Controllers\Admin\ClientLanguageController;
use App\Http\Controllers\Admin\CronMonitorController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvoiceBuilderController;
use App\Http\Controllers\Admin\LandingPageBuilderController;
use App\Http\Controllers\Admin\DynamicSellerFieldController;
use App\Http\Controllers\Admin\GlobalSearchController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\OnboardingPageController;
use App\Http\Controllers\Admin\SplashScreenController;
use App\Http\Controllers\Admin\PosDashboardController as AdminPosDashboardController;
use App\Http\Controllers\Admin\ProductApprovalController;
use App\Http\Controllers\Admin\PromoController;
use App\Http\Controllers\Admin\ReferralController;
use App\Http\Controllers\Admin\SellerLandingController;
use App\Http\Controllers\Admin\SellerWithdrawalController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\WhatsAppSettingController;
use App\Http\Controllers\Admin\SubscriptionFeatureController;
use App\Http\Controllers\Admin\SubscriptionPlanController;
use App\Http\Controllers\Admin\SupportController;
use App\Http\Controllers\Admin\SystemUpdateController;
use App\Http\Controllers\Admin\WalletTransactionController;
use App\Http\Controllers\Admin\WatchBuyProfileController;
use App\Http\Controllers\Admin\WatchBuyReelController;
use App\Http\Controllers\Admin\WatchBuyStatusController;
use App\Http\Controllers\Api\User\UserApiController;
use App\Http\Controllers\AppNotificationController;
use App\Http\Controllers\BadgeController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\DatatableExportController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\GlobalAttributeController;
use App\Http\Controllers\HomeBuilderController;
use App\Http\Controllers\HomeNavbarItemController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OtpLoginController;
use App\Http\Controllers\PanelSessionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductFaqController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SellerEarningController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreLocationController;
use App\Http\Controllers\SupportBroadcastAuthController;
use App\Http\Controllers\SystemUserController;
use App\Http\Controllers\TaxClassController;
use App\Http\Controllers\TaxRateController;
use App\Http\Middleware\RestrictDemoUserWrites;
use App\Services\PanelNavigationService;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('session/status', PanelSessionController::class)->name('session.status');

    Route::middleware(['guest'])->group(function () {
        Route::get('/', [AuthController::class, 'loginAdmin'])->name('login');
        Route::get('login', [AuthController::class, 'loginAdmin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.post');

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

    Route::middleware(['auth', AuthenticateSession::class, 'validate.admin', 'ensure.subscription.feature', 'admin.market', RestrictDemoUserWrites::class,
    ])->group(function () {
        Route::get('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('global-search', GlobalSearchController::class)
            ->middleware('throttle:60,1')
            ->name('global-search');

        // Browser FCM token sync for the admin panel.
        Route::post('devices/sync', [DeviceTokenController::class, 'sync'])->name('devices.sync');
        Route::delete('devices', [DeviceTokenController::class, 'forget'])->name('devices.forget');
        Route::post('broadcasting/auth', SupportBroadcastAuthController::class)->name('broadcasting.auth');

        Route::get('/', fn () => redirect()->to(
            app(PanelNavigationService::class)->landingUrl(auth()->user(), 'admin')
        ))->name('index');
        Route::view('no-permissions', 'admin.no-permissions')->name('no-permissions');
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('dashboard/data', [DashboardController::class, 'getDashboardData'])->name('dashboard.data');
        Route::prefix('datatable-exports')->name('datatable-exports.')->group(function () {
            Route::post('estimate', [DatatableExportController::class, 'estimate'])->withoutMiddleware(RestrictDemoUserWrites::class)->name('estimate');
            Route::post('/', [DatatableExportController::class, 'store'])->withoutMiddleware(RestrictDemoUserWrites::class)->name('store');
            Route::get('{datatableExport}/download', [DatatableExportController::class, 'download'])->name('download');
        });

        Route::prefix('support')->name('support.')->group(function () {
            Route::post('broadcasting/auth', SupportBroadcastAuthController::class)
                ->middleware('permission:support.view')->name('broadcasting.auth');
            Route::get('/', [SupportController::class, 'index'])->middleware('permission:support.view')->name('index');
            Route::get('dashboard', [SupportController::class, 'dashboard'])->middleware('permission:support.view')->name('dashboard');
            Route::get('dashboard/data', [SupportController::class, 'dashboardData'])->middleware('permission:support.view')->name('dashboard.data');
            Route::get('dashboard/queue', [SupportController::class, 'dashboardQueue'])->middleware('permission:support.view')->name('dashboard.queue');
            Route::get('dashboard/team', [SupportController::class, 'dashboardTeam'])->middleware('permission:support.view')->name('dashboard.team');
            Route::get('dashboard/ratings', [SupportController::class, 'dashboardRatings'])->middleware('permission:support.view')->name('dashboard.ratings');
            Route::get('inbox', [SupportController::class, 'inbox'])->middleware('permission:support.view')->name('inbox');
            Route::put('availability', [SupportController::class, 'updateAvailability'])->middleware('permission:support.manage')->name('availability.update');
            Route::get('realtime', [SupportController::class, 'realtime'])->middleware('permission:support.view')->name('realtime');
            Route::get('threads/{thread}', [SupportController::class, 'show'])->middleware('permission:support.view')->name('threads.show');
            Route::get('threads/{thread}/messages', [SupportController::class, 'messages'])->middleware('permission:support.view')->name('threads.messages');
            Route::get('threads/{thread}/updates', [SupportController::class, 'updates'])->middleware('permission:support.view')->name('threads.updates');
            Route::post('threads/{thread}/read', [SupportController::class, 'read'])->middleware('permission:support.view')->name('threads.read');
            Route::post('threads/{thread}/typing', [SupportController::class, 'typing'])->middleware(['permission:support.reply', 'throttle:120,1'])->name('threads.typing');
            Route::delete('threads/{thread}/messages', [SupportController::class, 'clearThread'])->middleware('permission:support.manage')->name('threads.messages.clear');
            Route::post('tickets/{ticket}/messages', [SupportController::class, 'message'])->middleware('permission:support.reply')->name('messages.store');
            Route::delete('messages/{message}', [SupportController::class, 'deleteMessage'])->middleware('permission:support.manage')->name('messages.destroy');
            Route::post('tickets/{ticket}/assign', [SupportController::class, 'assign'])->middleware('permission:support.assign')->name('tickets.assign');
            Route::post('tickets/{ticket}/status', [SupportController::class, 'status'])->middleware('permission:support.manage')->name('tickets.status');
            Route::post('tickets/{ticket}/callbacks', [SupportController::class, 'callback'])->middleware('permission:support.manage')->name('callbacks.store');
            Route::post('tickets/{ticket}/resolve', [SupportController::class, 'resolve'])->middleware('permission:support.manage')->name('tickets.resolve');
            Route::post('tickets/{ticket}/close', [SupportController::class, 'close'])->middleware('permission:support.manage')->name('tickets.close');
            Route::get('topics', [SupportController::class, 'topics'])->middleware('permission:support.manage')->name('topics.index');
            Route::post('topics', [SupportController::class, 'storeTopic'])->middleware('permission:support.manage')->name('topics.store');
            Route::put('topics/{topic}', [SupportController::class, 'updateTopic'])->middleware('permission:support.manage')->name('topics.update');
            Route::get('attachments/{media}', [SupportController::class, 'attachment'])->middleware('permission:support.view')->name('attachments.show');
        });

        Route::middleware('coming.soon:pos')->group(function () {
            Route::get('pos-dashboard', [AdminPosDashboardController::class, 'index'])->name('pos-dashboard');
            Route::get('pos-dashboard/data', [AdminPosDashboardController::class, 'getData'])->name('pos-dashboard.data');
        });

        // Subscription enable/disable decision page (gated: coming soon)
        Route::middleware('coming.soon:subscription')->group(function () {
            Route::get('subscription-feature', [SubscriptionFeatureController::class, 'index'])->name('subscription-feature');
            Route::post('subscription-feature', [SubscriptionFeatureController::class, 'store'])->name('subscription-feature.store');
        });

        // profile
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'index'])->name('index');
            Route::get('/edit', [ProfileController::class, 'edit'])->name('edit');
            Route::post('/update', [ProfileController::class, 'update'])->name('update');
            Route::post('/password-update', [ProfileController::class, 'changePassword'])
                ->middleware('throttle:5,1')
                ->name('password.update');
        });

        // settings
        Route::prefix('seller-landing')->name('seller-landing.')->group(function () {
            Route::get('/', [SellerLandingController::class, 'index'])->name('index');
            Route::post('/', [SellerLandingController::class, 'store'])->name('store');
        });

        Route::prefix('landing-page-builder')->name('landing-page-builder.')->group(function () {
            Route::get('/', [LandingPageBuilderController::class, 'index'])
                ->name('index');
            Route::get('/datatable', [LandingPageBuilderController::class, 'datatable'])
                ->name('datatable');
            Route::get('/create', [LandingPageBuilderController::class, 'create'])
                ->name('create');
            Route::post('/assets', [LandingPageBuilderController::class, 'uploadAssets'])
                ->name('assets.store');
            Route::post('/', [LandingPageBuilderController::class, 'store'])
                ->name('store');
            Route::get('/{document}/edit', [LandingPageBuilderController::class, 'edit'])
                ->name('edit');
            Route::put('/{document}', [LandingPageBuilderController::class, 'update'])
                ->name('update');
            Route::delete('/{document}', [LandingPageBuilderController::class, 'destroy'])
                ->name('destroy');
        });

        Route::prefix('settings')->namespace('Settings')->name('settings.')->group(function () {
            Route::get('/', [SettingController::class, 'index'])->name('index');
            Route::post('authentication/test-sms', [SettingController::class, 'testSms'])
                ->middleware('throttle:6,1')
                ->name('authentication.test-sms');
            Route::post('authentication/test-firebase-connection', [SettingController::class, 'testFirebaseConnection'])
                ->middleware('throttle:6,1')
                ->name('authentication.test-firebase-connection');
            Route::post('authentication/test-firebase-identity', [SettingController::class, 'testFirebaseIdentity'])
                ->middleware('throttle:6,1')
                ->name('authentication.test-firebase-identity');
            Route::post('notification/test-self', [SettingController::class, 'testNotificationSelf'])
                ->middleware('throttle:6,1')
                ->name('notification.test-self');
            Route::post('email/test-smtp', [SettingController::class, 'testSmtp'])
                ->middleware('throttle:6,1')
                ->name('email.test-smtp');
            Route::post('payment/test', [SettingController::class, 'testPayment'])
                ->middleware('throttle:10,1')
                ->name('payment.test');
            Route::post('broadcast-driver/test', [SettingController::class, 'testBroadcastConnection'])
                ->middleware(['permission:setting.broadcast_driver.edit', 'throttle:10,1'])
                ->name('broadcast-driver.test');
            Route::post('whatsapp/sync-templates', [WhatsAppSettingController::class, 'syncTemplates'])
                ->middleware(['permission:setting.whatsapp.edit', 'throttle:6,1'])
                ->name('whatsapp.sync-templates');
            Route::post('whatsapp/store-templates', [WhatsAppSettingController::class, 'storeTemplates'])
                ->middleware(['permission:setting.whatsapp.edit', 'throttle:10,1'])
                ->name('whatsapp.store-templates');
            Route::post('whatsapp/test-message', [WhatsAppSettingController::class, 'testMessage'])
                ->middleware(['permission:setting.whatsapp.edit', 'throttle:6,1'])
                ->name('whatsapp.test-message');
            Route::get('whatsapp/message-status', [WhatsAppSettingController::class, 'messageStatus'])
                ->middleware(['permission:setting.whatsapp.view', 'throttle:60,1'])
                ->name('whatsapp.message-status');
            Route::post('shipping/test-connection', [SettingController::class, 'testShiprocket'])
                ->middleware('throttle:6,1')
                ->name('shipping.test-connection');
            Route::post('shipping/shiprocket-test-mode', [SettingController::class, 'toggleShiprocketTestMode'])
                ->name('shipping.shiprocket-test-mode');
            Route::get('shipping', [SettingController::class, 'shippingIndex'])->name('shipping.index');
            Route::get('shipping/{provider}', [SettingController::class, 'shippingProvider'])->name('shipping.provider');
            Route::get('{setting}', [SettingController::class, 'show'])->name('show');
            Route::post('store', [SettingController::class, 'store'])->name('store');
        });

        Route::prefix('watch-and-buy')->name('watch-and-buy.')->group(function () {
            Route::get('blogs', [BlogController::class, 'index'])->name('blogs.index');
            Route::get('blogs/datatable', [BlogController::class, 'datatable'])->name('blogs.datatable');
            Route::get('blogs/product-options', [BlogController::class, 'productOptions'])->name('blogs.product-options');
            Route::get('blogs/create', [BlogController::class, 'create'])->name('blogs.create');
            Route::post('blogs', [BlogController::class, 'store'])->name('blogs.store');
            Route::get('blogs/{blog}/edit', [BlogController::class, 'edit'])->name('blogs.edit');
            Route::post('blogs/{blog}', [BlogController::class, 'update'])->name('blogs.update');
            Route::delete('blogs/{blog}', [BlogController::class, 'destroy'])->name('blogs.destroy');
            Route::get('blog-categories', [BlogCategoryController::class, 'index'])->name('blog-categories.index');
            Route::get('blog-categories/datatable', [BlogCategoryController::class, 'datatable'])->name('blog-categories.datatable');
            Route::post('blog-categories', [BlogCategoryController::class, 'store'])->name('blog-categories.store');
            Route::post('blog-categories/{blogCategory}', [BlogCategoryController::class, 'update'])->name('blog-categories.update');
            Route::delete('blog-categories/{blogCategory}', [BlogCategoryController::class, 'destroy'])->name('blog-categories.destroy');
            Route::get('statuses', [WatchBuyStatusController::class, 'index'])->name('statuses.index');
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
            Route::post('reels/{reel}/block', [WatchBuyReelController::class, 'block'])->name('reels.block');
            Route::post('reels/{reel}/unblock', [WatchBuyReelController::class, 'unblock'])->name('reels.unblock');
            Route::get('profiles', [WatchBuyProfileController::class, 'index'])->name('profiles.index');
            Route::get('profiles/datatable', [WatchBuyProfileController::class, 'datatable'])->name('profiles.datatable');
            Route::get('profiles/options', [WatchBuyProfileController::class, 'options'])->name('profiles.options');
            Route::get('profiles/seller-options', [WatchBuyProfileController::class, 'sellerOptions'])->name('profiles.seller-options');
            Route::post('profiles', [WatchBuyProfileController::class, 'store'])->name('profiles.store');
            Route::get('profiles/{profile}/edit', [WatchBuyProfileController::class, 'edit'])->name('profiles.edit');
            Route::post('profiles/{profile}', [WatchBuyProfileController::class, 'update'])->name('profiles.update');
            Route::post('profiles/{profile}/suspend', [WatchBuyProfileController::class, 'suspend'])->name('profiles.suspend');
            Route::post('profiles/{profile}/unsuspend', [WatchBuyProfileController::class, 'unsuspend'])->name('profiles.unsuspend');
            Route::get('official-profile', [WatchBuyProfileController::class, 'official'])->name('official-profile.edit');
            Route::post('official-profile', [WatchBuyProfileController::class, 'updateOfficial'])->name('official-profile.update');
        });

        // system updates
        Route::prefix('system-updates')->name('system-updates.')->group(function () {
            Route::get('/', [SystemUpdateController::class, 'index'])->name('index');
            Route::post('/', [SystemUpdateController::class, 'store'])->name('store');
            Route::post('/preview', [SystemUpdateController::class, 'preview'])
                ->middleware('throttle:6,10,system-update-preview:')->name('preview');
            Route::get('/datatable', [SystemUpdateController::class, 'datatable'])->name('datatable');
            // Live log endpoints
            Route::get('/latest', [SystemUpdateController::class, 'latest'])->name('latest');
            Route::get('/{update}/log', [SystemUpdateController::class, 'showLog'])->name('log');
        });

        // categories
        Route::prefix('categories')->namespace('Categories')->name('categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::post('/sort', [CategoryController::class, 'updateSort'])->name('sort.update');
            Route::post('/', [CategoryController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [CategoryController::class, 'show'])->name('edit');
            Route::post('/{id}', [CategoryController::class, 'update'])->name('update');
            Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('delete');
            Route::get('/datatable', [CategoryController::class, 'getCategories'])->name('datatable');
            Route::get('/tree', [CategoryController::class, 'tree'])->name('tree');
            Route::get('/search', [CategoryController::class, 'search'])->name('search');
        });
        Route::bulkUpload('categories');

        // brands
        Route::prefix('brands')->namespace('Brands')->name('brands.')->group(function () {
            Route::get('/', [BrandController::class, 'index'])->name('index');
            Route::post('/', [BrandController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [BrandController::class, 'show'])->name('edit');
            Route::get('/{id}/visibility', [BrandController::class, 'visibility'])->name('visibility');
            Route::post('/{id}', [BrandController::class, 'update'])->name('update');
            Route::delete('/{id}', [BrandController::class, 'destroy'])->name('delete');
            Route::get('/datatable', [BrandController::class, 'getBrands'])->name('datatable');
            Route::get('/groups', [BrandController::class, 'groups'])->name('groups');
            Route::get('/search', [BrandController::class, 'search'])->name('search');
        });
        Route::bulkUpload('brands');

        // badges
        Route::prefix('badges')->name('badges.')->group(function () {
            Route::get('/', [BadgeController::class, 'index'])->name('index');
            Route::get('/datatable', [BadgeController::class, 'getList'])->name('datatable');
            Route::get('/list', [BadgeController::class, 'listAll'])->name('list');
            Route::get('/search', [BadgeController::class, 'search'])->name('search');
            Route::post('/', [BadgeController::class, 'store'])->name('store');
            Route::post('/{id}', [BadgeController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [BadgeController::class, 'destroy'])->whereNumber('id')->name('delete');
            Route::post('/products/bulk-assign', [BadgeController::class, 'bulkAssign'])->name('products.bulk-assign');
            Route::post('/products/bulk-remove', [BadgeController::class, 'bulkRemove'])->name('products.bulk-remove');
        });

        Route::prefix('onboarding')->name('onboarding.')->group(function () {
            Route::get('/', [OnboardingPageController::class, 'index'])->name('index');
            Route::get('/datatable', [OnboardingPageController::class, 'datatable'])->name('datatable');
            Route::get('/preview', [OnboardingPageController::class, 'preview'])->name('preview');
            Route::patch('/enabled/{surface}', [OnboardingPageController::class, 'updateEnabled'])->whereIn('surface', ['customer_app', 'seller_app'])->name('enabled');
            Route::patch('/skip-enabled', [OnboardingPageController::class, 'updateSkipEnabled'])->name('skip-enabled');
            Route::get('/{id}', [OnboardingPageController::class, 'show'])->whereNumber('id')->name('show');
            Route::post('/', [OnboardingPageController::class, 'store'])->name('store');
            Route::post('/{id}', [OnboardingPageController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [OnboardingPageController::class, 'destroy'])->whereNumber('id')->name('delete');
        });

        Route::prefix('splash-screen')->name('splash-screen.')->group(function () {
            Route::get('/', [SplashScreenController::class, 'index'])->name('index');
            Route::post('/', [SplashScreenController::class, 'save'])->name('save');
            Route::delete('/{surface}/{slot}', [SplashScreenController::class, 'destroy'])
                ->whereIn('surface', ['customer_app', 'seller_app'])
                ->whereIn('slot', ['current', 'staged'])
                ->name('delete');
        });

        // promos
        Route::prefix('promos')->name('promos.')->group(function () {
            Route::get('/', [PromoController::class, 'index'])->name('index');
            Route::post('/', [PromoController::class, 'store'])->name('store');
            Route::get('/datatable', [PromoController::class, 'datatable'])->name('datatable');
            Route::get('/{id}', [PromoController::class, 'show'])->name('show');
            Route::put('/{id}', [PromoController::class, 'update'])->name('update');
            Route::delete('/{id}', [PromoController::class, 'destroy'])->name('destroy');
        });

        // customers (web panel users)
        Route::prefix('customers')->name('customers.')->group(function () {
            Route::get('/', [CustomerController::class, 'index'])->name('index');
            Route::get('/datatable', [CustomerController::class, 'datatable'])->name('datatable');
            Route::get('/export', [CustomerController::class, 'export'])->name('export');
            Route::get('/{id}/show', [CustomerController::class, 'show'])->name('show');
            Route::get('/{id}/orders-datatable', [CustomerController::class, 'ordersDatatable'])->name('orders-datatable');
            Route::get('/{id}/wallet-datatable', [CustomerController::class, 'walletDatatable'])->name('wallet-datatable');
            Route::get('/{id}/notifications-datatable', [CustomerController::class, 'notificationsDatatable'])->name('notifications-datatable');
        });

        Route::get('/users/search', [UserApiController::class, 'search'])->name('users.search');

        // sellers
        Route::prefix('sellers')->name('sellers.')->group(function () {
            Route::get('/dynamic-fields/datatable', [DynamicSellerFieldController::class, 'datatable'])->name('dynamic-fields.datatable');
            Route::get('/dynamic-fields/country/{countryIso2}', [DynamicSellerFieldController::class, 'forCountry'])->name('dynamic-fields.country');
            Route::get('/dynamic-fields', [DynamicSellerFieldController::class, 'index'])->name('dynamic-fields.index');
            Route::post('/dynamic-fields', [DynamicSellerFieldController::class, 'store'])->name('dynamic-fields.store');
            Route::put('/dynamic-fields/{field}', [DynamicSellerFieldController::class, 'update'])->name('dynamic-fields.update');
            Route::delete('/dynamic-fields/{field}', [DynamicSellerFieldController::class, 'destroy'])->name('dynamic-fields.destroy');
            Route::get('/', [SellerController::class, 'index'])->name('index');
            Route::post('/', [SellerController::class, 'store'])->name('store');
            Route::get('/create', [SellerController::class, 'create'])->name('create');
            Route::get('/{id}/edit', [SellerController::class, 'edit'])->name('edit');
            Route::post('/{id}', [SellerController::class, 'update'])->name('update');
            Route::delete('/{id}', [SellerController::class, 'destroy'])->name('delete');
            Route::get('/datatable', [SellerController::class, 'getSellers'])->name('datatable');
            Route::get('/search', [SellerController::class, 'search'])->name('search')->name('search');
            Route::get('/{id}/show', [SellerController::class, 'show'])->name('show');
            Route::get('/{id}/statements-datatable', [SellerController::class, 'statementsDatatable'])->name('statements-datatable');
            Route::get('/{id}/feedback-datatable', [SellerController::class, 'feedbackDatatable'])->name('feedback-datatable');
            Route::get('/{id}/notifications-datatable', [SellerController::class, 'notificationsDatatable'])->name('notifications-datatable');
            Route::get('/{id}/products-datatable', [SellerController::class, 'productsDatatable'])->name('products-datatable');
            Route::get('/{id}/locations-datatable', [SellerController::class, 'locationsDatatable'])->name('locations-datatable');
            Route::get('/{id}/shipping-profiles-datatable', [SellerController::class, 'shippingProfilesDatatable'])->name('shipping-profiles-datatable');
            Route::get('/{id}/orders-datatable', [SellerController::class, 'ordersDatatable'])->name('orders-datatable');
            Route::get('/{id}/returns-datatable', [SellerController::class, 'returnsDatatable'])->name('returns-datatable');
            Route::get('/{id}/withdrawals-datatable', [SellerController::class, 'withdrawalsDatatable'])->name('withdrawals-datatable');
        });

        // subscription plans (gated: coming soon)
        Route::prefix('subscription-plans')->name('subscription-plans.')->middleware('coming.soon:subscription')->group(function () {
            Route::get('/', [SubscriptionPlanController::class, 'index'])->name('index');
            Route::post('/', [SubscriptionPlanController::class, 'store'])->name('store');
            Route::get('/create', [SubscriptionPlanController::class, 'create'])->name('create');
            Route::get('/datatable', [SubscriptionPlanController::class, 'datatable'])->name('datatable');
            // Subscribers list (sellers who purchased plans)
            Route::get('/subscribers', [SubscriptionPlanController::class, 'subscribers'])->name('subscribers');
            Route::get('/subscribers/datatable', [SubscriptionPlanController::class, 'subscribersDatatable'])->name('subscribers.datatable');
            Route::get('/subscribers/{id}', [SubscriptionPlanController::class, 'subscriberShow'])->name('subscribers.show');
            Route::get('view', function () {
                return view('admin.subscription-plans.view');
            });
            Route::get('/{id}/edit', [SubscriptionPlanController::class, 'edit'])->name('edit');
            Route::post('/{id}', [SubscriptionPlanController::class, 'update'])->name('update');
            Route::delete('/{id}', [SubscriptionPlanController::class, 'destroy'])->name('destroy');
        });

        // taxes
        Route::prefix('tax-rates')->namespace('TaxRates')->name('tax-rates.')->group(function () {
            Route::get('/', [TaxRateController::class, 'index'])->name('index');
            Route::post('/', [TaxRateController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [TaxRateController::class, 'show'])->name('edit');
            Route::post('/{id}', [TaxRateController::class, 'update'])->name('update');
            Route::delete('/{id}', [TaxRateController::class, 'destroy'])->name('delete');
            Route::get('/datatable', [TaxRateController::class, 'getTaxRates'])->name('datatable');
            Route::get('/search', [TaxRateController::class, 'search'])->name('search');
        });

        // tax classes
        Route::prefix('tax-classes')->namespace('TaxClasses')->name('tax-classes.')->group(function () {
            Route::post('/', [TaxClassController::class, 'store'])->name('store');
            Route::get('/get-tax-classes', [TaxClassController::class, 'getTaxClasses'])->name('datatable');
            // Keep /search above the /{id} catch-alls so the literal segment
            // wins over the wildcard match (otherwise GET /search falls into
            // POST /{id} and Laravel returns 405).
            Route::get('/search', [TaxClassController::class, 'search'])->name('search');
            Route::get('/{id}/edit', [TaxClassController::class, 'show'])->name('edit');
            Route::post('/{id}', [TaxClassController::class, 'update'])->name('update');
            Route::delete('/{id}', [TaxClassController::class, 'destroy'])->name('delete');
        });

        // Roles and Permissions
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
        Route::prefix('permissions')->namespace('Permissions')->name('permissions.')->group(function () {
            Route::post('/', [PermissionController::class, 'store'])->name('store');
        });

        // System Users
        Route::prefix('system-users')->namespace('systemUsers')->name('system-users.')->group(function () {
            Route::get('/', [SystemUserController::class, 'index'])->name('index');
            Route::post('/', [SystemUserController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [SystemUserController::class, 'show'])->name('show');
            Route::post('/{id}', [SystemUserController::class, 'update'])->name('update');
            Route::delete('/{id}', [SystemUserController::class, 'destroy'])->name('destroy');
            Route::get('/datatable', [SystemUserController::class, 'getSystemUsers'])->name('datatable');
        });

        // seller stores
        Route::prefix('sellers/store')->name('sellers.store.')->group(function () {
            Route::get('/', [StoreController::class, 'index'])->name('index');
            Route::get('/', [StoreController::class, 'index'])->name('index');
            Route::get('/datatable', [StoreController::class, 'getStores'])->name('datatable');
            Route::get('/search', [StoreController::class, 'search'])->name('search');
            Route::get('/view/{id}', [StoreController::class, 'index'])->name('show.index');
            Route::get('/{id}', [StoreController::class, 'show'])->name('show');
            Route::post('/{id}/verify', [StoreController::class, 'verify'])->name('verify');
            Route::post('/{id}/toggle-recommended', [StoreController::class, 'toggleRecommended'])->name('toggle-recommended');

            // Store locations (Shopify-style fulfillment points)
            Route::prefix('{storeId}/locations')->name('locations.')->group(function () {
                Route::get('/', [StoreLocationController::class, 'index'])->name('index');
                Route::get('/create', [StoreLocationController::class, 'create'])->name('create');
                Route::post('/', [StoreLocationController::class, 'store'])->name('store');
                Route::get('/{locationId}/edit', [StoreLocationController::class, 'edit'])->name('edit');
                Route::post('/{locationId}', [StoreLocationController::class, 'update'])->name('update');
                Route::delete('/{locationId}', [StoreLocationController::class, 'destroy'])->name('delete');
                Route::post('/{locationId}/promote-default', [StoreLocationController::class, 'promoteDefault'])->name('promote-default');
                Route::post('/{locationId}/shiprocket-sync', [StoreLocationController::class, 'shiprocketSync'])->name('shiprocket-sync');
            });
        });

        // Global cross-seller store-location list + Shiprocket status
        Route::get('store-locations', [StoreLocationController::class, 'adminGlobalIndex'])->name('store-locations.index');

        // product Faqs
        Route::prefix('faqs')->name('faqs.')->group(function () {
            Route::get('/', [FaqController::class, 'index'])->name('index');
            Route::post('/', [FaqController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [FaqController::class, 'edit'])->name('edit');
            Route::post('/{id}', [FaqController::class, 'update'])->name('update');
            Route::delete('/{id}', [FaqController::class, 'destroy'])->name('delete');
            Route::get('/datatable', [FaqController::class, 'getFaqs'])->name('datatable');
        });

        // wallet transactions and deposits
        Route::prefix('wallet')->name('wallet.')->group(function () {
            // All transactions
            Route::get('/transactions', [WalletTransactionController::class, 'transactions'])->name('transactions');
            Route::get('/transactions/datatable', [WalletTransactionController::class, 'transactionsDatatable'])->name('transactions.datatable');

            // Pending deposits
            Route::get('/deposits', [WalletTransactionController::class, 'deposits'])->name('deposits');
            Route::get('/deposits/datatable', [WalletTransactionController::class, 'depositsDatatable'])->name('deposits.datatable');
            Route::post('/deposits/{id}/process', [WalletTransactionController::class, 'processDeposit'])->name('deposits.process');
        });

        // Refer & Earn — referrals & earnings
        Route::prefix('referrals')->name('referrals.')->group(function () {
            Route::get('/', [ReferralController::class, 'index'])->name('index');
            Route::get('/datatable', [ReferralController::class, 'datatable'])->name('datatable');
            Route::get('/earnings', [ReferralController::class, 'earnings'])->name('earnings');
            Route::get('/earnings/datatable', [ReferralController::class, 'earningsDatatable'])->name('earnings.datatable');
        });

        Route::get('products/search', [ProductController::class, 'search'])->name('products.search');

        // Markets (replaces delivery-zones in v1 ecommerce model)
        Route::prefix('markets')->name('markets.')->group(function () {
            Route::get('/', [MarketController::class, 'index'])->name('index');
            // Global header selector — switches the market the whole admin panel is scoped to.
            Route::post('/switch', [MarketController::class, 'switchAdminMarket'])->name('switch');
            Route::get('/datatable', [MarketController::class, 'datatable'])->name('datatable');
            Route::get('/search-tom', [MarketController::class, 'searchTom'])->name('search-tom');
            Route::get('/create', [MarketController::class, 'create'])->name('create');
            Route::post('/', [MarketController::class, 'store'])->name('store');
            // FX rates — listed inline on the markets index, refreshed on demand.
            // Declared before /{id}/* routes so 'fx' isn't swallowed as an id.
            Route::get('/fx/rates', [MarketController::class, 'fxRates'])->name('fx.rates');
            Route::post('/fx/refresh', [MarketController::class, 'fxRefresh'])->name('fx.refresh');
            Route::get('/{id}/edit', [MarketController::class, 'edit'])->name('edit');
            Route::post('/{id}', [MarketController::class, 'update'])->name('update');
            Route::delete('/{id}', [MarketController::class, 'destroy'])->name('delete');
        });

        Route::prefix('languages')->name('languages.')->group(function () {
            Route::get('/', [LanguageController::class, 'index'])->middleware('permission:language.view')->name('index');
            Route::get('/panel', [LanguageController::class, 'panel'])->middleware('permission:language.view')->name('panel');
            Route::get('/client/{surface}', [ClientLanguageController::class, 'show'])->middleware('permission:language.view')->name('clients.show');
            Route::get('/client/{surface}/datatable', [ClientLanguageController::class, 'datatable'])->middleware('permission:language.view')->name('clients.datatable');
            Route::get('/client/{surface}/create', [ClientLanguageController::class, 'create'])->middleware('permission:language.create')->name('clients.create');
            Route::post('/client/{surface}', [ClientLanguageController::class, 'store'])->middleware('permission:language.create')->name('clients.store');
            Route::get('/client/{surface}/quick-add/{code}/download', [ClientLanguageController::class, 'downloadQuickAdd'])->middleware('permission:language.view')->name('clients.quick-add.download');
            Route::get('/client/{surface}/{language}/export', [ClientLanguageController::class, 'export'])->middleware('permission:language.view')->name('clients.export');
            Route::get('/client/{surface}/{language}/edit', [ClientLanguageController::class, 'edit'])->middleware('permission:language.edit')->name('clients.edit');
            Route::post('/client/{surface}/{language}', [ClientLanguageController::class, 'update'])->middleware('permission:language.edit')->name('clients.update');
            Route::delete('/client/{surface}/{language}', [ClientLanguageController::class, 'destroy'])->middleware('permission:language.delete')->name('clients.delete');
            Route::get('/client/{surface}/{language}/translations/datatable', [ClientLanguageController::class, 'translationsDatatable'])->middleware('permission:language.edit')->name('clients.translations.datatable');
            Route::post('/client/{surface}/{language}/translations', [ClientLanguageController::class, 'saveTranslations'])->middleware('permission:language.edit')->name('clients.translations.update');
            Route::post('/client/{surface}/{language}/import', [ClientLanguageController::class, 'import'])->middleware('permission:language.edit')->name('clients.import');
            Route::post('/client/{surface}/{language}/toggle', [ClientLanguageController::class, 'toggle'])->middleware('permission:language.edit')->name('clients.toggle');
            Route::post('/client/{surface}/{language}/default', [ClientLanguageController::class, 'makeDefault'])->middleware('permission:language.edit')->name('clients.default');
            Route::get('/datatable', [LanguageController::class, 'datatable'])->middleware('permission:language.view')->name('datatable');
            Route::get('/create', [LanguageController::class, 'create'])->middleware('permission:language.create')->name('create');
            Route::post('/', [LanguageController::class, 'store'])->middleware('permission:language.create')->name('store');
            Route::get('/quick-add/{code}/download', [LanguageController::class, 'downloadQuickAdd'])->middleware('permission:language.view')->name('quick-add.download');
            Route::get('/{language}/translations/datatable', [LanguageController::class, 'translationsDatatable'])->middleware('permission:language.edit')->name('translations.datatable');
            Route::get('/{language}/edit', [LanguageController::class, 'edit'])->middleware('permission:language.edit')->name('edit');
            Route::post('/{language}', [LanguageController::class, 'update'])->middleware('permission:language.edit')->name('update');
            Route::delete('/{language}', [LanguageController::class, 'destroy'])->middleware('permission:language.delete')->name('delete');
            Route::post('/{language}/toggle', [LanguageController::class, 'toggle'])->middleware('permission:language.edit')->name('toggle');
            Route::post('/{language}/translations', [LanguageController::class, 'saveTranslations'])->middleware('permission:language.edit')->name('translations.update');
            Route::post('/{language}/import', [LanguageController::class, 'import'])->middleware('permission:language.edit')->name('import');
            Route::get('/{language}/export', [LanguageController::class, 'export'])->middleware('permission:language.view')->name('export');
        });

        // Legacy delivery-zone + zones/preview routes were removed when the v1
        // ecommerce model superseded hyperlocal delivery. The model + service
        // still exist as @deprecated stubs for other code that hasn't migrated
        // off them yet; the admin UI is gone.

        // Home page builder
        Route::prefix('home-builder')->name('home-builder.')->group(function () {
            Route::get('/', [HomeBuilderController::class, 'index'])->name('index');
            Route::get('/layout', [HomeBuilderController::class, 'show'])->name('show');
            Route::post('/preview-catalog', [HomeBuilderController::class, 'previewCatalog'])->name('preview-catalog');
            Route::post('/save', [HomeBuilderController::class, 'save'])->name('save');
            // Global ("All") home appearance — reads/writes the home_general_settings keys
            Route::get('/global-appearance', [HomeBuilderController::class, 'globalAppearanceShow'])->name('global-appearance.show');
            Route::post('/global-appearance', [HomeBuilderController::class, 'globalAppearanceSave'])->name('global-appearance.save');
            Route::post('/sections/{section}/media', [HomeBuilderController::class, 'uploadSectionMedia'])->name('section-media.store');
            Route::delete('/sections/{section}/media', [HomeBuilderController::class, 'deleteSectionMedia'])->name('section-media.delete');
            Route::post('/items/{item}/image', [HomeBuilderController::class, 'uploadItemImage'])->name('item-image.store');
            Route::delete('/items/{item}/image', [HomeBuilderController::class, 'deleteItemImage'])->name('item-image.delete');
        });

        Route::prefix('home-navbar-items')->name('home-navbar-items.')->group(function () {
            Route::get('/', [HomeNavbarItemController::class, 'index'])->name('index');
            Route::post('/', [HomeNavbarItemController::class, 'store'])->name('store');
            Route::post('/order', [HomeNavbarItemController::class, 'order'])->name('order');
            Route::get('/{homeNavbarItem}', [HomeNavbarItemController::class, 'show'])->name('show');
            Route::post('/{homeNavbarItem}', [HomeNavbarItemController::class, 'update'])->name('update');
            Route::delete('/{homeNavbarItem}', [HomeNavbarItemController::class, 'destroy'])->name('destroy');
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

        Route::prefix('app-notifications')->name('app-notifications.')->group(function () {
            Route::get('/', [AppNotificationController::class, 'index'])->name('index');
            Route::post('/', [AppNotificationController::class, 'store'])->name('store');
            Route::get('/datatable', [AppNotificationController::class, 'datatable'])->name('datatable');
            Route::get('/search-recipients', [AppNotificationController::class, 'searchRecipients'])->name('search-recipients');
            Route::get('/search-targets', [AppNotificationController::class, 'searchTargets'])->name('search-targets');
            Route::get('/{id}', [AppNotificationController::class, 'show'])->name('show');
        });

        // Seller Withdrawal Routes
        Route::prefix('seller-withdrawals')->name('seller-withdrawals.')->group(function () {
            Route::get('/', [SellerWithdrawalController::class, 'index'])->name('index');
            Route::get('/datatable', [SellerWithdrawalController::class, 'getWithdrawalRequests'])->name('datatable');
            Route::post('/{id}/process', [SellerWithdrawalController::class, 'processWithdrawalRequest'])->name('process');
            Route::get('/history', [SellerWithdrawalController::class, 'history'])->name('history');
            Route::get('/history/datatable', [SellerWithdrawalController::class, 'getWithdrawalHistory'])->name('history.datatable');
            Route::get('/{id}', [SellerWithdrawalController::class, 'show'])->name('show');
        });

        // Commission Settlement Routes
        Route::prefix('commissions')->name('commissions.')->group(function () {
            Route::get('/', [SellerEarningController::class, 'index'])->name('index');
            // Summary cards
            Route::get('/summary', [SellerEarningController::class, 'summary'])->name('summary');
            // CSV export + print statement
            Route::get('/export', [SellerEarningController::class, 'export'])->name('export');
            Route::get('/statement', [SellerEarningController::class, 'statement'])->name('statement');
            // Per-seller rollup (Phase 3)
            Route::get('/sellers/datatable', [SellerEarningController::class, 'sellersRollup'])->name('sellers.datatable');
            Route::post('/sellers/{sellerId}/settle', [SellerEarningController::class, 'settleSeller'])->whereNumber('sellerId')->name('sellers.settle');
            // Holds (Phase 3b)
            Route::post('/{id}/hold', [SellerEarningController::class, 'hold'])->whereNumber('id')->name('hold');
            Route::post('/{id}/unhold', [SellerEarningController::class, 'unhold'])->whereNumber('id')->name('unhold');
            // Credits
            Route::get('/datatable', [SellerEarningController::class, 'getUnsettledCommissions'])->name('datatable');
            Route::post('/{id}/settle', [SellerEarningController::class, 'settleCommission'])->name('settle');
            Route::post('/settle-all', [SellerEarningController::class, 'settleAllCommissions'])->name('settle-all');
            // Manually run the auto-settle cron (settle matured credits now)
            Route::post('/auto-settle/run', [SellerEarningController::class, 'runAutoSettle'])->name('auto-settle.run');
            // Debits
            Route::get('/debits/datatable', [SellerEarningController::class, 'getUnsettledDebits'])->name('debits.datatable');
            Route::post('/debits/{id}/settle', [SellerEarningController::class, 'settleDebit'])->name('debits.settle');
            Route::post('/debits/settle-all', [SellerEarningController::class, 'settleAllDebits'])->name('debits.settle-all');
            // History
            Route::get('/history', [SellerEarningController::class, 'history'])->name('history');
            Route::get('/history/datatable', [SellerEarningController::class, 'getSettledCommissions'])->name('history.datatable');
            // Detail (single settlement breakdown)
            Route::get('/{id}/detail', [SellerEarningController::class, 'commissionDetail'])->whereNumber('id')->name('detail');
        });

        // orders
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::get('/list-datatable', [OrderController::class, 'getOrdersGrouped'])->name('list_datatable');
            Route::get('/list-items/{id}', [OrderController::class, 'getOrderItemsForExpand'])->name('list_items');
            Route::get('/popup-status', [OrderController::class, 'getPopupOrderStatuses'])->name('popup_status');
            Route::get('invoice', [OrderController::class, 'orderInvoice'])->name('invoice');
            Route::get('invoice-builder', [InvoiceBuilderController::class, 'index'])->middleware('permission:orders.edit')->name('invoice-builder');
            Route::post('invoice-builder', [InvoiceBuilderController::class, 'save'])->middleware('permission:orders.edit')->name('invoice-builder.save');
            Route::post('invoice-builder/preview', [InvoiceBuilderController::class, 'preview'])->middleware('permission:orders.edit')->name('invoice-builder.preview');
            Route::get('/returns', [OrderController::class, 'adminReturnsIndex'])->name('returns.index');
            Route::get('/returns/datatable', [OrderController::class, 'adminReturnsDatatable'])->name('returns.datatable');
            Route::get('/{id}', [OrderController::class, 'show'])->name('show');
            Route::post('/{id}/note', [OrderController::class, 'adminAddNote'])->name('add_note');
            Route::post('/{id}/mark-payment-received', [OrderController::class, 'adminMarkPaymentReceived'])->name('mark_payment_received');
            Route::post('/seller-orders/{id}/fulfill', [OrderController::class, 'fulfill'])->name('seller_order_fulfill');
            Route::post('/seller-orders/{id}/fulfillment-status', [OrderController::class, 'setFulfillmentStatus'])->name('set_fulfillment_status');
            Route::post('/shipments/{shipmentId}/transition', [OrderController::class, 'transitionShipment'])->name('shipment_transition');
            Route::post('/shipments/{shipmentId}/retry-booking', [OrderController::class, 'retryShipmentBooking'])->name('shipment_retry_booking');
            Route::post('/shipments/{shipmentId}/update-lines', [OrderController::class, 'updateShipmentLines'])->name('shipment_update_lines');
            Route::post('/shipments/{shipmentId}/ndr-action', [OrderController::class, 'ndrShipmentAction'])->name('shipment_ndr_action');
            Route::post('/shipments/{shipmentId}/sync', [OrderController::class, 'syncShipment'])->name('shipment_sync');
            Route::post('/shipments/{shipmentId}/edit', [OrderController::class, 'editShipment'])->name('shipment_edit');
            Route::get('/shipments/{shipmentId}/packing-slip', [OrderController::class, 'packingSlip'])->name('packing_slip');
            Route::get('/{id}/cancel', [OrderController::class, 'adminCancelOrder'])->name('cancel');
            Route::post('/{id}/cancel', [OrderController::class, 'adminCancelOrderSubmit'])->name('cancel_submit');
            Route::get('/{id}/refund', [OrderController::class, 'adminRefundOrder'])->name('refund');
            Route::post('/{id}/refund', [OrderController::class, 'adminRefundOrderSubmit'])->name('refund_submit');
            Route::get('/returns/{returnId}/review', [OrderController::class, 'adminReturnReview'])->name('return_review');
            Route::post('/returns/{returnId}/approve', [OrderController::class, 'adminReturnApprove'])->name('return_approve');
            Route::post('/returns/{returnId}/decline', [OrderController::class, 'adminDeclineReturn'])->name('return_decline');
            Route::post('/returns/{returnId}/cancel', [OrderController::class, 'adminReturnCancel'])->name('return_cancel');
            // Direct (admin-created) return.
            Route::get('/{id}/returns/create', [OrderController::class, 'adminReturnCreatePage'])->name('returns.create_page');
            Route::post('/{id}/returns', [OrderController::class, 'adminCreateReturn'])->name('returns.create');
            Route::post('/{id}/restock', [OrderController::class, 'adminRestockItems'])->name('restock');
            Route::post('/{id}/reopen', [OrderController::class, 'adminReopenOrder'])->name('reopen');
        });

        Route::bulkUpload('products');

        // products
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('/datatable', [ProductController::class, 'getProducts'])->name('datatable');
            Route::get('/search', [ProductController::class, 'search'])->name('search');
            Route::get('/lookup/attributes', [ProductController::class, 'attributeOptions'])->name('lookup.attributes');
            Route::get('/lookup/kit-components', [ProductController::class, 'kitComponentOptions'])->name('lookup.kit-components');
            Route::get('/lookup/attributes/{attribute}/values', [ProductController::class, 'attributeValueOptions'])->whereNumber('attribute')->name('lookup.attribute-values');
            Route::get('/attributes-by-seller', [ProductController::class, 'getAttributesBySeller'])->name('attributes-by-seller');
            Route::get('/tax-classes-by-seller', [ProductController::class, 'getTaxClassesBySeller'])->name('tax-classes-by-seller');
            // Admin acts on behalf of a seller (seller picked via in-form Tom Select).
            Route::get('/create', [ProductController::class, 'create'])->name('create');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [ProductController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::get('/{id}/visibility', [ProductController::class, 'visibility'])->whereNumber('id')->name('visibility');
            Route::post('/{id}', [ProductController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [ProductController::class, 'destroy'])->whereNumber('id')->name('delete');
            Route::get('/{id}/pricing', [ProductController::class, 'getProductPricing'])->name('pricing');
            Route::post('/{id}/verification-status', [ProductController::class, 'updateVerificationStatus'])->name('update-verification-status');
            Route::post('/{id}/update-status', [ProductController::class, 'updateStatus'])->name('update-status');
            Route::get('/{id}', [ProductController::class, 'show'])->whereNumber('id')->name('show');
        });

        Route::prefix('attributes')->name('attributes.')->group(function () {
            Route::get('/', [GlobalAttributeController::class, 'index'])->name('index');
            Route::get('/create', [GlobalAttributeController::class, 'create'])->name('create');
            Route::post('/', [GlobalAttributeController::class, 'store'])->name('store');
            Route::get('/datatable', [GlobalAttributeController::class, 'getAttributes'])->name('datatable');
            Route::get('/search', [GlobalAttributeController::class, 'search'])->name('search');
            Route::get('/{id}/edit', [GlobalAttributeController::class, 'edit'])->whereNumber('id')->name('edit');
            Route::post('/{id}', [GlobalAttributeController::class, 'update'])->whereNumber('id')->name('update');
            Route::delete('/{id}', [GlobalAttributeController::class, 'destroy'])->whereNumber('id')->name('delete');
        });

        Route::prefix('product-approvals')->name('product-approvals.')->group(function () {
            Route::get('/', [ProductApprovalController::class, 'index'])->name('index');
            Route::get('/datatable', [ProductApprovalController::class, 'getPendingProducts'])->name('datatable');
            Route::post('/{id}/approve', [ProductApprovalController::class, 'approve'])->whereNumber('id')->name('approve');
            Route::post('/{id}/reject', [ProductApprovalController::class, 'reject'])->whereNumber('id')->name('reject');
        });

        Route::get('/stores/list', [StoreController::class, 'StoreList'])->name('stores.list');
        Route::get('/stores/with-locations', [StoreController::class, 'StoresWithLocations'])->name('stores.with-locations');

        // product Faqs
        Route::prefix('product-faqs')->name('product_faqs.')->group(function () {
            Route::get('/', [ProductFaqController::class, 'index'])->name('index');
            Route::get('/datatable', [ProductFaqController::class, 'getProductFaqs'])->name('datatable');
            //            Route::get('/search', [ProductFaqController::class, 'search'])->name('search');
        });

        // cron monitor
        Route::prefix('cron-monitor')->name('cron-monitor.')->group(function () {
            Route::get('/', [CronMonitorController::class, 'index'])->name('index');
            Route::get('/status', [CronMonitorController::class, 'status'])->name('status');
            Route::post('/run', [CronMonitorController::class, 'run'])->name('run');
            Route::get('/history', [CronMonitorController::class, 'history'])->name('history');
        });

        // ad campaigns — approval & moderation (gated: coming soon)
        Route::prefix('ads/campaigns')->name('ads.campaigns.')->middleware('coming.soon:ads')->group(function () {
            Route::get('/', [AdminAdCampaignController::class, 'index'])->name('index');
            Route::get('/datatable', [AdminAdCampaignController::class, 'getCampaigns'])->name('datatable');
            Route::get('/dashboard', [AdminAdCampaignController::class, 'dashboard'])->name('dashboard');
            Route::get('/dashboard/data', [AdminAdCampaignController::class, 'getDashboardData'])->name('dashboard.data');
            Route::post('/{id}/action', [AdminAdCampaignController::class, 'action'])->name('action');
        });

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/categories', [AdminReportController::class, 'categories'])->name('categories');
            Route::get('/categories/datatable', [AdminReportController::class, 'categoriesDatatable'])->name('categories.datatable');
            Route::get('/categories/chart', [AdminReportController::class, 'categoriesChart'])->name('categories.chart');

            Route::get('/brands', [AdminReportController::class, 'brands'])->name('brands');
            Route::get('/brands/datatable', [AdminReportController::class, 'brandsDatatable'])->name('brands.datatable');
            Route::get('/brands/chart', [AdminReportController::class, 'brandsChart'])->name('brands.chart');

            Route::get('/products', [AdminReportController::class, 'products'])->name('products');
            Route::get('/products/datatable', [AdminReportController::class, 'productsDatatable'])->name('products.datatable');
            Route::get('/products/chart', [AdminReportController::class, 'productsChart'])->name('products.chart');

            Route::get('/sellers', [AdminReportController::class, 'sellers'])->name('sellers');
            Route::get('/sellers/datatable', [AdminReportController::class, 'sellersDatatable'])->name('sellers.datatable');
            Route::get('/sellers/chart', [AdminReportController::class, 'sellersChart'])->name('sellers.chart');

            Route::get('/stores', [AdminReportController::class, 'stores'])->name('stores');
            Route::get('/stores/datatable', [AdminReportController::class, 'storesDatatable'])->name('stores.datatable');
            Route::get('/stores/chart', [AdminReportController::class, 'storesChart'])->name('stores.chart');

            Route::get('/earnings', [AdminReportController::class, 'earnings'])->name('earnings');
            Route::get('/earnings/datatable', [AdminReportController::class, 'earningsDatatable'])->name('earnings.datatable');
            Route::get('/earnings/chart', [AdminReportController::class, 'earningsChart'])->name('earnings.chart');

            Route::get('/taxes', [AdminReportController::class, 'taxes'])->name('taxes');
            Route::get('/taxes/datatable', [AdminReportController::class, 'taxesDatatable'])->name('taxes.datatable');
            Route::get('/taxes/chart', [AdminReportController::class, 'taxesChart'])->name('taxes.chart');
        });
    });
});
