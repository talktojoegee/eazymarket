<?php

use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\Admin\LandingPageBuilderController;
use App\Http\Controllers\CountryController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Payments\RazorpayController;
use App\Http\Controllers\Payments\StripeController;
use App\Http\Controllers\Payments\PaystackController;
use App\Http\Controllers\Payments\FlutterwaveController;
use App\Http\Controllers\LicenseRevalidateController;
use App\Http\Controllers\Seller\AdWalletController;
use App\Http\Controllers\Seller\PosController;
use App\Http\Controllers\Seller\PosPaymentSessionController;
use App\Http\Controllers\Seller\SubscriptionPlanController;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\PolicyController;
use Illuminate\Support\Facades\Route;
use App\Services\SettingService;
use App\Services\PanelNavigationService;
use App\Enums\SettingTypeEnum;


require __DIR__ . "/admin-route.php";
require __DIR__ . "/seller-route.php";

Route::get('/landing-pages/{slug}', [LandingPageBuilderController::class, 'showPublic'])
    ->name('landing-pages.show');
Route::get('/go/{slug}', [LandingPageBuilderController::class, 'showPublic'])
    ->name('landing-pages.go');

//if (app()->environment('local')) {
//    Route::prefix('dev/shiprocket-tester')->group(function () {
//        Route::get('/', [\App\Http\Controllers\Dev\ShiprocketTesterController::class, 'index'])->name('dev.shiprocket-tester');
//        Route::post('/toggle-fake', [\App\Http\Controllers\Dev\ShiprocketTesterController::class, 'toggleFake']);
//        Route::get('/{sellerOrderId}/snapshot', [\App\Http\Controllers\Dev\ShiprocketTesterController::class, 'snapshot']);
//        Route::post('/{sellerOrderId}/create-parcel', [\App\Http\Controllers\Dev\ShiprocketTesterController::class, 'createParcel']);
//        Route::post('/{sellerOrderId}/create-return', [\App\Http\Controllers\Dev\ShiprocketTesterController::class, 'createReturn']);
//        Route::post('/shipments/{shipmentId}/event', [\App\Http\Controllers\Dev\ShiprocketTesterController::class, 'fireEvent']);
//        Route::post('/shipments/{shipmentId}/sync', [\App\Http\Controllers\Dev\ShiprocketTesterController::class, 'syncShipment']);
//    });
//
//    Route::prefix('dev/order-tester')->group(function () {
//        Route::get('/', [\App\Http\Controllers\Dev\OrderTesterController::class, 'index'])->name('dev.order-tester');
//        Route::post('/place', [\App\Http\Controllers\Dev\OrderTesterController::class, 'placeOrder']);
//        Route::post('/{orderId}/capture', [\App\Http\Controllers\Dev\OrderTesterController::class, 'simulateCapture']);
//        Route::post('/{orderId}/retry', [\App\Http\Controllers\Dev\OrderTesterController::class, 'retryPayment']);
//        Route::post('/{orderId}/cancel-payment', [\App\Http\Controllers\Dev\OrderTesterController::class, 'cancelPayment']);
//        Route::post('/{orderId}/fail', [\App\Http\Controllers\Dev\OrderTesterController::class, 'simulateFail']);
//        Route::get('/{orderId}/snapshot', [\App\Http\Controllers\Dev\OrderTesterController::class, 'snapshot']);
//    });
//}

// Route to run reward settlement command
Route::get('/reward-settlement', function () {
    try {
        $output = Artisan::call('referral:settle');
        return response()->json([
            'message' => 'Referral settlement process completed.',
            'output' => Artisan::output(),
        ]);
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
})->middleware('dev.only');

Route::prefix('subscription-payment')->name('subscription-payment.')->middleware('coming.soon:subscription')->group(function () {
    Route::get('razorpay/{transaction}', [RazorpayController::class, 'showSubscriptionPaymentPage'])
        ->name('razorpay');
    Route::get('stripe/{transaction}', [StripeController::class, 'showSubscriptionPaymentPage'])
        ->name('stripe');
    Route::get('paystack/{transaction}', [PaystackController::class, 'showSubscriptionPaymentPage'])
        ->name('paystack');
    Route::get('flutterwave/{transaction}', [FlutterwaveController::class, 'showSubscriptionPaymentPage'])
        ->name('flutterwave');

    Route::get('status/{transaction}', [SubscriptionPlanController::class, 'paymentStatus'])->name('status');
});

Route::prefix('wallet-payment')->name('wallet-payment.')->middleware('coming.soon:ads')->group(function () {
    Route::get('razorpay/{transaction}', [RazorpayController::class, 'showWalletPaymentPage'])
        ->name('razorpay');
    Route::get('stripe/{transaction}', [StripeController::class, 'showWalletPaymentPage'])
        ->name('stripe');
    Route::get('paystack/{transaction}', [PaystackController::class, 'showWalletPaymentPage'])
        ->name('paystack');
    Route::get('flutterwave/{transaction}', [FlutterwaveController::class, 'showWalletPaymentPage'])
        ->name('flutterwave');

    Route::get('status/{transaction}', [AdWalletController::class, 'paymentStatus'])
        ->name('status');
});

Route::get('/', function () {
    // Check Demo Mode from system settings
    try {
        /** @var SettingService $settingService */
        $settingService = app(SettingService::class);
        $resource = $settingService->getSettingByVariable(SettingTypeEnum::SYSTEM());
        $settings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];
        $isDemo = (bool)($settings['demoMode'] ?? false);
    } catch (\Throwable) {
        $isDemo = false;
    }
    if ($isDemo) {
        return view('new-welcome');
    }

    if (auth()->check()) {
        $panel = auth()->user()->access_panel->value === 'admin' ? 'admin' : 'seller';

        return redirect()->to(app(PanelNavigationService::class)->landingUrl(auth()->user(), $panel));
    }
    return redirect()->route('seller.login');
});

Route::get('/migrate', function () {
    try {
        Artisan::call('migrate');
        return "Migrations completed successfully.";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
})->middleware('dev.only');

Route::get('/storage-link', function () {
    try {
        Artisan::call('storage:link');
        return "Storage link created successfully.";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
})->middleware('dev.only');


Route::get('/optimize-clean', function () {
    try {
        Artisan::call('config:cache');
        Artisan::call('cache:clear');
        Artisan::call('optimize:clear');
        return "Optimization cache cleared successfully.";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
})->middleware('dev.only');

Route::get('/clear-queue', function () {
    try {
        Artisan::call('queue:flush');
        Artisan::call('queue:clear');
        return "Queue cleared successfully.";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
})->middleware('dev.only');

Route::get('/rollback', function () {
    try {
        Artisan::call('migrate:rollback', ['--step' => 0]);
        return "Rollback of last migration completed successfully.";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
})->middleware('dev.only');

Route::get('/subscription-usage-sync', function () {
    try {
        Artisan::call('subscription:sync-usage');
        return "Subscription usage synced successfully.";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
})->middleware('dev.only');
Route::get('/subscription-expire', function () {
    try {
        Artisan::call('subscription:expire');
        return "Subscription usage synced successfully.";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
})->middleware('dev.only');

Route::get('/seed-default-plan', function () {
    try {
        Artisan::call('db:seed', ['--class' => 'DefaultSubscriptionPlanSeeder']);
        return "DefaultSubscriptionPlanSeeder executed successfully.";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
})->middleware('dev.only');
Route::get('/seed-wallet-type', function () {
    try {
        Artisan::call('db:seed', ['--class' => 'BackfillWalletTypesSeeder']);
        return "BackfillWalletTypesSeeder executed successfully.";
    } catch (\Exception $e) {
        return "Error: " . $e->getMessage();
    }
})->middleware('dev.only');

Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $user = User::find($id);

    if (!$user || !hash_equals((string)$hash, sha1((string)$user->getEmailForVerification()))) {
        return response()->view('auth.email-verified', [
            'status' => 'invalid',
        ], 403);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->view('auth.email-verified', [
            'status' => 'already',
            'email' => $user->email,
        ]);
    }

    if ($user->markEmailAsVerified()) {
        event(new Verified($user));
    }

    return response()->view('auth.email-verified', [
        'status' => 'verified',
        'email' => $user->email,
    ]);
})
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// Customer Password Reset Routes
Route::get('forgot-password', [PasswordResetController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('reset-password/{token}', [PasswordResetController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');

Route::middleware('auth')->group(function () {
    Route::get('countries', [CountryController::class, 'index']);
    Route::get('currency', [CountryController::class, 'getCurrency']);

    // New currency search backed by the `currencies` table (seeded from countries).
    // Drives the reusable <x-tom-select-currency> component.
    Route::get('currencies/search', [CurrencyController::class, 'search'])->name('currencies.search');
});

// Language Switcher
Route::get('language/{locale}', function ($locale) {
    $language = app(\App\Services\LanguageService::class)->findActive($locale);
    if ($language) {
        session()->put('locale', $language->code);
    }
    return redirect()->back();
})->name('set.language');

Route::get('order-invoice', [OrderController::class, 'orderInvoice']);

// Public Policy Preview Route
Route::get('/policies/{policy}', [PolicyController::class, 'show'])
    ->whereIn('policy', \App\Enums\PoliciesEnum::values())
    ->name('policies.show');

// License revalidation page (when app is not licensed)
Route::get('/license/revalidate', [LicenseRevalidateController::class, 'form'])->name('license.revalidate');
Route::post('/license/revalidate/verify', [LicenseRevalidateController::class, 'verify'])
    ->middleware('throttle:5,1')
    ->name('license.revalidate.verify');

// ── POS Customer-Facing Display ──────────────────
Route::prefix('pos/display')->name('pos.public.cfd.')->middleware('coming.soon:pos')->group(function () {
    Route::get('{token}', [PosController::class, 'cfdShow'])->name('show');
    Route::get('{token}/state', [PosController::class, 'cfdPull'])->name('pull');
});

// ── POS Online Payment ──────────────────────────
Route::prefix('pay/pos')->name('pos.public.payment.')->middleware('coming.soon:pos')->group(function () {
    Route::get('{token}', [PosPaymentSessionController::class, 'showPaymentPage'])->name('show');
    Route::get('{token}/status', [PosPaymentSessionController::class, 'publicStatus'])->name('status');
    Route::post('{token}/init/{gateway}', [PosPaymentSessionController::class, 'initGateway'])->name('init');
    Route::post('{token}/verify', [PosPaymentSessionController::class, 'verify'])->name('verify');
    Route::get('{token}/return/{gateway}', [PosPaymentSessionController::class, 'returnFromGateway'])->name('return');
});

// ── Documentation & Knowledge Base ───────────────
// Serves the self-contained static site in /documentation (install docs,
// Admin KB and Seller KB). Friendly shortcuts:
// Friendly shortcuts (forward an optional ?lang= so the KB can open in the
// reader's language).
Route::get('/docs', fn() => redirect('/documentation/hypercommerce-docs.html' . (request('lang') ? '?lang=' . urlencode(request('lang')) : '')));
Route::get('/admin-guide', fn() => redirect('/documentation/admin-knowledge-base.html' . (request('lang') ? '?lang=' . urlencode(request('lang')) : '')));
Route::get('/seller-guide', fn() => redirect('/documentation/seller/seller-knowledge-base.html' . (request('lang') ? '?lang=' . urlencode(request('lang')) : '')));

Route::get('/documentation/{path?}', function (string $path = '') {
    $base = realpath(base_path('documentation'));
    abort_unless($base !== false, 404);

    if ($path === '') {
        return redirect('/documentation/hypercommerce-docs.html');
    }

    // Block path traversal and dot-files/dot-directories.
    if (preg_match('#(^|/)\.#', $path)) {
        abort(404);
    }

    $full = realpath($base . DIRECTORY_SEPARATOR . $path);
    abort_unless(
        $full !== false
        && str_starts_with($full, $base . DIRECTORY_SEPARATOR)
        && is_file($full),
        404
    );

    $types = [
        'html' => 'text/html', 'js' => 'application/javascript', 'css' => 'text/css',
        'json' => 'application/json', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
        'webp' => 'image/webp', 'ico' => 'image/x-icon', 'woff2' => 'font/woff2',
        'woff' => 'font/woff', 'ttf' => 'font/ttf',
    ];
    $ext = strtolower(pathinfo($full, PATHINFO_EXTENSION));
    abort_unless(isset($types[$ext]), 404);

    return response()->file($full, [
        'Content-Type' => $types[$ext],
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0',
    ]);
})->where('path', '.*')->name('documentation');
