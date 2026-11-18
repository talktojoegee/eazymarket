<?php

use App\Http\Controllers\Api\User\AdEventApiController;
use App\Http\Controllers\Api\BrandApiController;
use App\Http\Controllers\Api\BlogApiController;
use App\Http\Controllers\Api\CategoryApiController;
use App\Http\Controllers\Api\CurrencyApiController;
use App\Http\Controllers\Api\FaqApiController;
use App\Http\Controllers\Api\GeoApiController;
use App\Http\Controllers\Api\GeoDetectApiController;
use App\Http\Controllers\Api\HomeLayoutApiController;
use App\Http\Controllers\Api\HomeNavbarApiController;
use App\Http\Controllers\Api\LanguageApiController;
use App\Http\Controllers\Api\LandingPageBuilderApiController;
use App\Http\Controllers\Api\MarketApiController;
use App\Http\Controllers\Api\OnboardingPageApiController;
use App\Http\Controllers\Api\SplashScreenApiController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\Product\ProductApiController;
use App\Http\Controllers\Api\Product\ProductFaqApiController;
use App\Http\Controllers\Api\Product\ProductSidebarApiController;
use App\Http\Controllers\Api\Seller\SubscriptionPlanApiController;
use App\Http\Controllers\Api\Seller\SellerDynamicFieldApiController;
use App\Http\Controllers\Api\SellerFeedbackApiController;
use App\Http\Controllers\Api\SellerLandingPageApiController;
use App\Http\Controllers\Api\SettingApiController;
use App\Http\Controllers\Api\StoreApiController;
use App\Http\Controllers\Api\User\AddressApiController;
use App\Http\Controllers\Api\User\AuthApiController;
use App\Http\Controllers\Api\User\CartApiController;
use App\Http\Controllers\Api\User\OrderApiController;
use App\Http\Controllers\Api\User\OtpApiController;
use App\Http\Controllers\Api\User\PasswordResetApiController;
use App\Http\Controllers\Api\User\ProductReviewApiController;
use App\Http\Controllers\Api\User\PromoApiController;
use App\Http\Controllers\Api\User\ReferralApiController;
use App\Http\Controllers\Api\User\SupportApiController;
use App\Http\Controllers\Api\User\SharedListApiController;
use App\Http\Controllers\Api\User\UserApiController;
use App\Http\Controllers\Api\User\UserNotificationApiController;
use App\Http\Controllers\Api\User\WalletApiController;
use App\Http\Controllers\Api\User\WishlistApiController;
use App\Http\Controllers\Api\User\WatchBuyLikeApiController;
use App\Http\Controllers\Api\WatchBuyController;
use App\Http\Controllers\Api\WatchBuyStatusApiController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SupportBroadcastAuthController;
use App\Http\Controllers\Payments\FlutterwaveController;
use App\Http\Controllers\Payments\MercadoPagoController;
use App\Http\Controllers\Payments\PaystackController;
use App\Http\Controllers\Payments\RazorpayController;
use App\Http\Controllers\Payments\StripeController;
use App\Http\Controllers\Payments\XenditController;
use App\Http\Controllers\Webhooks\ShiprocketWebhookController;
use App\Http\Controllers\Webhooks\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

// Public carrier webhooks (no auth; secret validated inside the controller).
Route::post('webhooks/shippingrocket', [ShiprocketWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('webhooks.shiprocket');
Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->middleware('throttle:120,1')
    ->name('whatsapp.webhook.verify');
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle'])
    ->middleware('throttle:120,1')
    ->name('whatsapp.webhook.handle');

// User Auth Routes
Route::post('register', [AuthApiController::class, 'register'])->middleware('throttle:10,1')->name('register');
Route::get('splash-screen', [SplashScreenApiController::class, 'show'])->name('splash-screen.show');
Route::post('login', [AuthApiController::class, 'login'])->middleware('throttle:login')->name('login');
Route::post('verify-user', [AuthApiController::class, 'verifyUser'])->middleware('throttle:user-exists');
Route::post('auth/send-otp', [OtpApiController::class, 'sendOtp'])
    ->middleware('throttle:otp-send')
    ->name('send-otp');
Route::post('auth/verify-otp', [OtpApiController::class, 'verifyOtp'])
    ->middleware('throttle:otp-verify')
    ->name('verify-otp');

// Email OTP login (existing accounts)
Route::post('auth/email/send-otp', [AuthApiController::class, 'sendEmailOtp'])
    ->middleware('throttle:otp-send')
    ->name('email-send-otp');
Route::post('auth/email/verify-otp', [AuthApiController::class, 'verifyEmailOtp'])
    ->middleware('throttle:otp-verify')
    ->name('email-verify-otp');

// Forgot password (OTP -> token -> reset)
Route::post('forget-password/send-otp', [PasswordResetApiController::class, 'sendForgotOtp'])
    ->middleware('throttle:otp-send')
    ->name('forgot-send-otp');
Route::post('forget-password/verify-otp', [PasswordResetApiController::class, 'verifyForgotOtp'])
    ->middleware('throttle:otp-verify')
    ->name('forgot-verify-otp');
Route::post('reset-password', [PasswordResetApiController::class, 'resetPassword'])->middleware('throttle:password-reset')->name('reset-password');

Route::post('auth/google/callback', [AuthApiController::class, 'googleCallback'])->name('google-callback');
Route::post('auth/apple/callback', [AuthApiController::class, 'appleCallback'])->name('apple-callback');
Route::post('auth/phone/callback', [AuthApiController::class, 'phoneCallback'])->name('phone-callback');

// settings
Route::prefix('settings')->name('api.')->group(function () {
    Route::get('/', [SettingApiController::class, 'index'])->name('settings.index');
    Route::get('firebase-config', [SettingApiController::class, 'firebaseConfig'])->name('settings.firebase-config');
    Route::get('auth-config', [SettingApiController::class, 'authConfig'])->name('settings.auth-config');
    Route::get('check-version', [SettingApiController::class, 'checkVersion'])->name('settings.check-version');
    Route::get('/variables', [SettingApiController::class, 'settingVariables'])->name('settings.variables');
    Route::get('/{setting}', [SettingApiController::class, 'show'])->name('settings.show');
});

Route::get('seller-landing', SellerLandingPageApiController::class)->name('api.seller-landing.show');
Route::get('go/{slug}', [LandingPageBuilderApiController::class, 'show'])
    ->name('api.landing-pages.show');
Route::get('seller/registration-fields', [SellerDynamicFieldApiController::class, 'index'])->name('api.seller.registration-fields');

Route::prefix('watch-and-buy')->name('api.watch-and-buy.')->group(function () {
    Route::get('reels', [WatchBuyController::class, 'index'])->name('reels.index');
    Route::get('statuses', [WatchBuyStatusApiController::class, 'index'])->name('statuses.index');
    Route::get('profiles/{username}/statuses', [WatchBuyStatusApiController::class, 'profile'])->name('profiles.statuses');
    Route::get('profiles/{username}', [WatchBuyController::class, 'profile'])->name('profiles.show');
    Route::middleware('auth:sanctum')->group(function () {
        Route::put('reels/likes', [WatchBuyLikeApiController::class, 'update'])->name('reels.likes.update');
        Route::put('statuses/seen', [WatchBuyStatusApiController::class, 'seen'])->name('statuses.seen');
    });
});

// Subscription Plans (public) — (gated: coming soon)
Route::prefix('subscription')->middleware('coming.soon:subscription')->group(function () {
    Route::get('/plans', [SubscriptionPlanApiController::class, 'index']);
});

Route::prefix('markets')->group(function () {
    Route::get('/',         [MarketApiController::class, 'index'])->name('api.markets.index');
    Route::get('/current',  [MarketApiController::class, 'current'])->name('api.markets.current');
    Route::post('/switch',  [MarketApiController::class, 'switch'])->name('api.markets.switch');
    Route::get('/products', [ProductApiController::class, 'index'])->name('api.markets.products');
});

Route::get('/currencies', [CurrencyApiController::class, 'index'])->name('api.currencies.index');
Route::get('/languages', [LanguageApiController::class, 'languages'])->name('api.languages.index');
Route::get('/language-labels', [LanguageApiController::class, 'labels'])->name('api.language-labels.index');
Route::get('/onboarding-pages', [OnboardingPageApiController::class, 'index'])->name('api.onboarding-pages.index');

Route::get('/geo-detect', GeoDetectApiController::class)->name('api.geo-detect');


Route::get('blogs', [BlogApiController::class, 'index'])->name('api.blogs.index');
Route::get('blog-categories', [BlogApiController::class, 'categories'])->name('api.blog-categories.index');
Route::get('blogs/categories', [BlogApiController::class, 'categories'])->name('api.blogs.categories');
Route::get('blogs/{slug}', [BlogApiController::class, 'show'])->name('api.blogs.show');


Route::prefix('geo')->name('api.geo.')->group(function () {
    Route::get('/countries', [GeoApiController::class, 'countries'])->name('countries');
    //    Route::get('/states',    [GeoApiController::class, 'states'])->name('states');
    Route::get('/cities',    [GeoApiController::class, 'cities'])->name('cities');
    // Route::get('/resolve',   [GeoApiController::class, 'resolve'])->name('resolve');
    Route::get('/resolve-pincode', [GeoApiController::class, 'resolvePincode'])->name('resolve-pincode');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('broadcasting/auth', SupportBroadcastAuthController::class)
        ->name('user.support.broadcasting.auth');
    // logout
    Route::post('logout', [AuthApiController::class, 'logout']);
    Route::post('devices/sync', [DeviceTokenController::class, 'sync']);
    Route::delete('devices', [DeviceTokenController::class, 'forget']);

    // Alias for order creation at /api/orders (in addition to /api/user/orders)
    // This keeps backward compatibility with tests expecting POST /api/orders
    Route::post('orders', [OrderApiController::class, 'createOrder'])->middleware('throttle:20,1');

    // users routes
    Route::prefix('user')->name('user.')->group(function () {

        Route::prefix('support')->name('support.')->group(function () {
            Route::get('thread', [SupportApiController::class, 'thread'])->name('thread');
            Route::get('thread/messages', [SupportApiController::class, 'messages'])->name('thread.messages');
            Route::get('thread/updates', [SupportApiController::class, 'updates'])->name('thread.updates');
            Route::get('realtime', [SupportApiController::class, 'realtime'])->name('realtime');
            Route::get('topics', [SupportApiController::class, 'topics'])->name('topics');
            Route::post('sessions', [SupportApiController::class, 'storeSession'])->middleware('throttle:10,1,support-sessions:')->name('sessions.store');
            Route::post('sessions/{slug}/messages', [SupportApiController::class, 'storeMessage'])->middleware('throttle:60,1,support-messages:')->name('messages.store');
            Route::post('sessions/{slug}/typing', [SupportApiController::class, 'typing'])->middleware('throttle:120,1,support-typing:')->name('typing');
            Route::post('sessions/{slug}/read', [SupportApiController::class, 'markRead'])->name('read');
            Route::post('sessions/{slug}/callbacks', [SupportApiController::class, 'callback'])->middleware('throttle:5,1,support-callbacks:')->name('callbacks.store');
            Route::post('sessions/{slug}/resolve', [SupportApiController::class, 'resolve'])->middleware('throttle:10,1,support-resolve:')->name('resolve');
            Route::post('sessions/{slug}/rating', [SupportApiController::class, 'rating'])->middleware('throttle:10,1,support-ratings:')->name('rating');
            Route::get('attachments/{media}', [SupportApiController::class, 'attachment'])->name('attachments.show');
        });
        // delete user account
        Route::delete('/delete-account', [UserApiController::class, 'deleteAccount'])->name('delete-account');
        // change password
        Route::post('/change-password', [UserApiController::class, 'changePassword'])
            ->middleware('throttle:5,1')
            ->name('change-password');

        // User profile
        Route::patch('/addresses/{id}/default', [AddressApiController::class, 'setDefault'])->name('api.addresses.default');
        Route::apiResource('/addresses', AddressApiController::class);

        // profile
        Route::get('/profile', [UserApiController::class, 'getProfile']);
        Route::post('/profile', [UserApiController::class, 'updateProfile']);

        // email update + re-verification
        Route::post('/update-email', [UserApiController::class, 'updateEmail'])
            ->middleware('throttle:6,1')
            ->name('update-email');
        Route::post('/email/verification-notification', [UserApiController::class, 'resendEmailVerification'])
            ->middleware('throttle:6,1')
            ->name('verification.send');

        // Notifications (app user)
        Route::prefix('notifications')->group(function () {
            Route::get('/', [UserNotificationApiController::class, 'index']);
            Route::get('/unread-count', [UserNotificationApiController::class, 'unreadCount']);
            Route::post('/mark-all-read', [UserNotificationApiController::class, 'markAllAsRead']);
            Route::post('/{id}/read', [UserNotificationApiController::class, 'markAsRead']);
            Route::post('/{id}/unread', [UserNotificationApiController::class, 'markAsUnread']);
        });

        // Wallet routes
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('/', [WalletApiController::class, 'getWallet']);
            Route::post('/prepare-wallet-recharge', [WalletApiController::class, 'prepareWalletRecharge']);
            Route::get('/transactions', [WalletApiController::class, 'getTransactions']);
            Route::get('/transactions/{id}', [WalletApiController::class, 'getTransaction']);
        });

        // Wishlist routes
        Route::prefix('wishlists')->name('wishlists.')->group(function () {
            Route::get('/', [WishlistApiController::class, 'index']);
            Route::get('/titles', [WishlistApiController::class, 'getTitles']);
            Route::get('/favorite', [WishlistApiController::class, 'favorite']); // Default Favorite list + items
            Route::post('/toggle', [WishlistApiController::class, 'toggleFavorite']); // One-tap add/remove in Favorite
            Route::post('/create', [WishlistApiController::class, 'createWishlist']); // Create wishlist only
            Route::get('/{id}', [WishlistApiController::class, 'show'])->whereNumber('id');
            Route::put('/{id}', [WishlistApiController::class, 'update'])->whereNumber('id');
            Route::delete('/{id}', [WishlistApiController::class, 'destroy'])->whereNumber('id');

            // Wishlist items management
            Route::put('/items/{itemId}/move', [WishlistApiController::class, 'moveItem']);
        });

        // Cart routes
        Route::prefix('cart')->group(function () {
            Route::post('/share', [SharedListApiController::class, 'createCartShare']);
            Route::get('/shared-lists/{token}', [SharedListApiController::class, 'show'])
                ->middleware('throttle:60,1')
                ->name('api.shared-lists.show');
            Route::post('shared-lists/{token}/import', [SharedListApiController::class, 'import'])
                ->middleware('throttle:20,1')
                ->name('shared-lists.import');
            Route::get('/', [CartApiController::class, 'getCart']);
            Route::post('/add', [CartApiController::class, 'addToCart']);
            Route::post('/buy-now', [CartApiController::class, 'buyNow']);
            Route::post('/item/{cartItemId}', [CartApiController::class, 'updateCartItemQuantity']);
            Route::delete('/item/{cartItemId}', [CartApiController::class, 'removeFromCart']);
            Route::get('/item/save-for-later', [CartApiController::class, 'getSaveForLaterItems']);
            Route::post('/item/save-for-later/{cartItemId}', [CartApiController::class, 'saveForLater']);
            Route::delete('/clear-cart', [CartApiController::class, 'clearCart']);
            Route::get('/clear-cart', [CartApiController::class, 'clearCart']);
            Route::post('/sync', [CartApiController::class, 'syncCart']);
        });

        // Promo routes
        Route::prefix('promos')->middleware('throttle:30,1')->group(function () {
            Route::get('/available', [PromoApiController::class, 'getUserAvailablePromos']);
            Route::get('/validate', [PromoApiController::class, 'validatePromoCode']);
        });

        // Order routes
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderApiController::class, 'getUserOrders']);
            Route::post('/', [OrderApiController::class, 'createOrder'])->middleware('throttle:20,1');
            Route::get('/{orderSlug}', [OrderApiController::class, 'getOrder']);
            Route::post('/{orderSlug}/pay', [OrderApiController::class, 'payOrder'])->middleware('throttle:20,1');
            Route::post('/{orderSlug}/cancel-payment', [OrderApiController::class, 'cancelPayment']);
            Route::get('/{orderSlug}/tracking', [OrderApiController::class, 'getOrderTracking']);
            Route::post('/items/{orderItemId}/cancel', [OrderApiController::class, 'cancelOrderItem']);
            Route::post('/items/{orderItemId}/return', [OrderApiController::class, 'returnOrderItem']);
            Route::post('/items/{orderItemId}/return-cancel', [OrderApiController::class, 'cancelReturnRequest']);
            // Reorder: add all items from a past order back to cart
            Route::post('/{orderId}/reorder', [OrderApiController::class, 'reorder']);
        });

        // Order transaction routes
        Route::prefix('order-transactions')->name('transactions.')->group(function () {
            Route::get('/', [OrderApiController::class, 'getTransactions']);
            Route::get('/{id}', [OrderApiController::class, 'getTransaction']);
        });

        // Referral routes
        Route::prefix('referral')->name('referral.')->group(function () {
            Route::get('/', [ReferralApiController::class, 'getReferralInfo'])->name('info');
            // Route::post('/apply', [ReferralApiController::class, 'applyReferralCode'])->name('apply');
            Route::post('/submit', [ReferralApiController::class, 'submit'])->name('submit');
            Route::get('/earnings', [ReferralApiController::class, 'getEarningsHistory'])->name('earnings');
        });
    });
});

// reviews
Route::prefix('reviews')->group(function () {
    Route::get('/', [ProductReviewApiController::class, 'index']);
    Route::post('/', [ProductReviewApiController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/available-for-review', [ProductReviewApiController::class, 'getAvailableProductsForReview'])->middleware('auth:sanctum');
    Route::get('/{id}', [ProductReviewApiController::class, 'show']);
    Route::post('/{id}', [ProductReviewApiController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/{id}', [ProductReviewApiController::class, 'destroy'])->middleware('auth:sanctum');
});

// seller feedback
Route::prefix('seller-feedback')->group(function () {
    Route::get('/', [SellerFeedbackApiController::class, 'index']);
    Route::post('/', [SellerFeedbackApiController::class, 'store'])->middleware('auth:sanctum');
    Route::get('/ratings', [SellerFeedbackApiController::class, 'getSellerRatings']);
    Route::get('/{id}', [SellerFeedbackApiController::class, 'show']);
    Route::post('/{id}', [SellerFeedbackApiController::class, 'update'])->middleware('auth:sanctum');
    Route::delete('/{id}', [SellerFeedbackApiController::class, 'destroy'])->middleware('auth:sanctum');
});

// home page layout (resolved sections for the current market)
Route::get('/home-layout', [HomeLayoutApiController::class, 'index'])->name('home-layout');
Route::get('/home-layout/sections/{section}', [HomeLayoutApiController::class, 'section'])->name('home-layout.section');
Route::get('/home-tab', [HomeNavbarApiController::class, 'index'])->name('home-tab');

// get categories
Route::prefix('categories')->name('categories.')->group(function () {
    Route::get('/', [CategoryApiController::class, 'index']);
    Route::get('/sub-categories', [CategoryApiController::class, 'subCategories']);
    Route::get('/sidebar', [CategoryApiController::class, 'getCategories']);
    Route::get('/search', [CategoryApiController::class, 'search'])->name('search');
});

// get brands
Route::prefix('brands')->name('brands.')->group(function () {
    Route::get('/', [BrandApiController::class, 'index']);
    Route::get('/search', [BrandApiController::class, 'search'])->name('search');
    Route::get('/sidebar', [BrandApiController::class, 'sidebarBrands']);
});

// products
Route::prefix('products')->name('products.')->group(function () {
    //    Route::get('/', [ProductApiController::class, 'getAllProduct']);
    Route::get('/sidebar-filters', [ProductSidebarApiController::class, 'filters'])->name('products.sidebar-filters');
    Route::get('/get-types', [ProductSidebarApiController::class, 'getTypes'])->name('products.get-types');
    Route::get('/search-by-keywords', [ProductApiController::class, 'searchByKeywords']);
    Route::get('/store-wise', [ProductApiController::class, 'storeWise']);
    Route::get('/search', [ProductApiController::class, 'search'])->name('search');
    Route::get('/{slug}', [ProductApiController::class, 'show']);
    Route::get('/{slug}/faqs', [ProductFaqApiController::class, 'getByProduct']);
    Route::get('/{slug}/reviews', [ProductReviewApiController::class, 'getProductReviews']);
});

// stores
Route::prefix('stores')->name('stores.')->group(function () {
    Route::get('/', [StoreApiController::class, 'index']);
    Route::get('/search', [StoreApiController::class, 'search'])->name('search');
    Route::get('/{slug}', [StoreApiController::class, 'show']);
    //    Route::get('/zone', [StoreApiController::class, 'getStoresByZone']);
});

// Ad event tracking (public — guests also see sponsored products)
Route::prefix('ads')->name('ads.')->middleware(['coming.soon:ads', 'throttle:30,1'])->group(function () {
    Route::post('/bulk-clicks', [AdEventApiController::class, 'bulkClicks'])->name('bulk_clicks');
    Route::post('/bulk-impressions', [AdEventApiController::class, 'bulkImpressions'])->name('bulk_impressions');
});

// get faqs
Route::prefix('faqs')->name('faqs.')->group(function () {
    Route::get('/', [FaqApiController::class, 'index']);
    Route::get('/{id}', [FaqApiController::class, 'show']);
});

// get product faqs
Route::prefix('product-faqs')->name('product_faqs.')->group(function () {
    Route::get('/', [ProductFaqApiController::class, 'index']);
    Route::get('/{id}', [ProductFaqApiController::class, 'show']);
});


Route::get('payment/variables', [PaymentController::class, 'paymentVariables']);

// razorpay routes
Route::post('/webhook/razorpay', [RazorpayController::class, 'handleWebhook'])->name('razorpay.webhook');

// stripe routes
Route::post('stripe/webhook', [StripeController::class, 'handleWebhook'])->name('stripe.webhook');

// paystack routes
Route::post('paystack/webhook', [PaystackController::class, 'handleWebhook'])->name('paystack.webhook');
Route::get('paystack/callback', [PaystackController::class, 'handleCallback'])
    ->middleware('throttle:30,1')
    ->name('paystack.callback');

Route::post('flutterwave/webhook', [FlutterwaveController::class, 'handleWebhook'])->name('flutterwave.webhook');

Route::post('xendit/webhook', [XenditController::class, 'handleWebhook'])->name('xendit.webhook');

Route::post('mercado-pago/webhook', [MercadoPagoController::class, 'handleWebhook'])->name('mercado-pago.webhook');

Route::post('/test-fcm', [NotificationController::class, 'test'])->middleware('dev.only');
Route::post('/test-fcms', [NotificationController::class, 'sendBulk'])->middleware('dev.only');
//Route::post('test-email', [NotificationController::class, 'testEmail'])->name('test-email');

require __DIR__ . '/seller-api.php';
