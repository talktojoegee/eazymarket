<?php

namespace App\Providers;

use App\Enums\SettingTypeEnum;
use App\Http\Controllers\Admin\BulkUpload\BulkUploadController;
use App\Models\DeliveryBoyAssignment;
use App\Models\OrderItemReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerStatement;
use App\Models\SellerSubscription;
use App\Models\Setting;
use App\Models\ShippingProfile;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Models\StorePackage;
use App\Models\SubscriptionTransaction;
use App\Models\WalletTransaction;
use App\Observers\DeliveryBoyAssignmentObserver;
use App\Observers\OrderItemReturnObserver;
use App\Observers\ProductSearchObserver;
use App\Observers\ProductVariantSearchObserver;
use App\Observers\SellerStatementObserver;
use App\Observers\SellerSubscriptionObserver;
use App\Observers\ShippingProfileObserver;
use App\Observers\StoreLocationObserver;
use App\Observers\StoreObserver;
use App\Observers\StorePackageObserver;
use App\Observers\SubscriptionTransactionObserver;
use App\Observers\WalletTransactionObserver;
use App\Observers\WatchBuyAttachmentObserver;
use App\OpenApi\ScrambleDocumentation;
use App\Services\BroadcastSettingsService;
use App\Services\CurrencyService;
use App\Services\Fx\Contracts\FxRateProvider;
use App\Services\Fx\Providers\FrankfurterProvider;
use App\Services\LanguageService;
use App\Services\MarketService;
use App\Services\SellerStoreService;
use App\Services\SettingService;
use App\Services\Shipping\ShiprocketClient;
use App\Services\Shipping\Testing\FakeShiprocketClient;
use App\Support\DatabaseSchema;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerLibphonenumberAutoloader();

        if (! $this->app->environment('production')
            && FakeShiprocketClient::enabled()) {
            $this->app->bind(
                ShiprocketClient::class,
                FakeShiprocketClient::class,
            );
        }

        $this->app->singleton(CurrencyService::class, function ($app) {
            return new CurrencyService($app->make(SettingService::class));
        });

        $this->app->scoped(MarketService::class);

        $this->app->scoped(LanguageService::class);

        $this->app->scoped(SellerStoreService::class);

        $this->app->scoped(SettingService::class);

        $this->app->scoped(DatabaseSchema::class);

        $this->app->bind(FxRateProvider::class, function () {
            $driver = config('services.fx.default', 'frankfurter');

            return match ($driver) {
                'frankfurter' => new FrankfurterProvider(
                    baseUrl: (string) config('services.fx.frankfurter.base_url'),
                    timeoutSeconds: (int) config('services.fx.frankfurter.timeout', 8),
                ),
                default => throw new \RuntimeException("Unknown FX provider: {$driver}"),
            };
        });
    }

    /**
     * Expose the in-repo `libphonenumber\` package (see app/Libs/libphonenumber)
     * without relying on a composer dump-autoload step on customer deploys.
     * Registered here because AppServiceProvider boots before any controller
     * that parses Firebase phone callbacks.
     */
    private function registerLibphonenumberAutoloader(): void
    {
        $base = app_path('Libs/libphonenumber');

        spl_autoload_register(function (string $class) use ($base): void {
            if (! str_starts_with($class, 'libphonenumber\\')) {
                return;
            }

            $relative = substr($class, strlen('libphonenumber\\'));
            $path = $base.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, $relative).'.php';

            if (is_file($path)) {
                require_once $path;
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        $this->registerBulkUploadRouteMacro();

        app(LanguageService::class)->registerEnglishGroupAliases();

        $this->applyBroadcastSettings();

        require base_path('routes/channels.php');

        [$systemSettings, $appSettings, $webSettings, $sellerSettings] = $this->loadSettings();

        $this->applySystemTimezone($systemSettings);

        $panel = $this->detectPanel();
        $menuSeller = config('menu.seller', []);
        $menuAdmin = config('menu.admin', []);
        try {
            if (app(DatabaseSchema::class)->hasTable('settings') && ! (bool) app(SettingService::class)->getSettingValues('watch_and_buy')['enabled']) {
                unset($menuSeller['watch_and_buy']);
            }
        } catch (\Throwable $e) {
            Log::warning($e->getMessage());
        }

        $this->shareViewData([
            'systemSettings' => $systemSettings,
            'appSettings' => $appSettings,
            'webSettings' => $webSettings,
            'sellerSettings' => $sellerSettings,
            'menuSeller' => $menuSeller,
            'menuAdmin' => $menuAdmin,
            'panel' => $panel,
        ]);

        $this->configureScramble();

        $this->forceHttpsInProduction();

        $this->registerObservers();

        $this->configureRateLimiting();
    }

    private function registerBulkUploadRouteMacro(): void
    {
        Route::macro('bulkUpload', function (string $module): void {
            Route::prefix($module.'/bulk-upload')
                ->name($module.'.bulk-upload.')
                ->group(function () use ($module) {
                    Route::get('/', [BulkUploadController::class, 'page'])->defaults('module', $module)->name('page');
                    Route::post('/preview', [BulkUploadController::class, 'preview'])->defaults('module', $module)->name('preview');
                    Route::post('/confirm', [BulkUploadController::class, 'confirm'])->defaults('module', $module)->name('confirm');
                    Route::get('/template', [BulkUploadController::class, 'template'])->defaults('module', $module)->name('template');
                    Route::get('/export', [BulkUploadController::class, 'export'])->defaults('module', $module)->name('export');
                    Route::get('/export/count', [BulkUploadController::class, 'exportCount'])->defaults('module', $module)->name('export.count');
                    Route::get('/jobs', [BulkUploadController::class, 'jobsIndex'])->defaults('module', $module)->name('jobs.index');
                    Route::get('/jobs/datatable', [BulkUploadController::class, 'jobsDatatable'])->defaults('module', $module)->name('jobs.datatable');
                    Route::get('/jobs/{id}', [BulkUploadController::class, 'jobShow'])->whereNumber('id')->defaults('module', $module)->name('jobs.show');
                    Route::get('/jobs/{id}/status', [BulkUploadController::class, 'jobStatus'])->whereNumber('id')->defaults('module', $module)->name('jobs.status');
                    Route::post('/jobs/{id}/cancel', [BulkUploadController::class, 'cancel'])->whereNumber('id')->defaults('module', $module)->name('jobs.cancel');
                    Route::get('/jobs/{id}/errors', [BulkUploadController::class, 'downloadErrors'])->whereNumber('id')->defaults('module', $module)->name('jobs.errors');
                    if ($module === 'products') {
                        Route::get('/references/{type}', [BulkUploadController::class, 'referenceCsv'])->defaults('module', $module)->name('references');
                        Route::post('/preview/{token}/index', [BulkUploadController::class, 'buildProductPreview'])->whereUuid('token')->defaults('module', $module)->name('preview.index');
                        Route::get('/preview/{token}/products', [BulkUploadController::class, 'productPreviewProducts'])->whereUuid('token')->defaults('module', $module)->name('preview.products');
                        Route::get('/preview/{token}/attributes', [BulkUploadController::class, 'productPreviewAttributes'])->whereUuid('token')->defaults('module', $module)->name('preview.attributes');
                        Route::get('/preview/{token}/products/{previewProduct}', [BulkUploadController::class, 'productPreviewDetail'])->whereUuid('token')->whereNumber('previewProduct')->defaults('module', $module)->name('preview.products.show');
                        Route::delete('/preview/{token}', [BulkUploadController::class, 'destroyProductPreview'])->whereUuid('token')->defaults('module', $module)->name('preview.destroy');
                    }
                });
        });
    }

    /**
     * Define rate limiters for the OTP endpoints. Keyed by both the target
     * account and the client IP so neither bombing a single account nor
     * brute-forcing codes from one host is possible, and so an attacker cannot
     * bypass the per-OTP attempt cap by repeatedly resending. The target is
     * whichever identifier the route submits — mobile, email or identifier —
     * since the six OTP routes do not agree on one field name.
     */
    private function configureRateLimiting(): void
    {
        $otpKey = static function (Request $request): string {
            $mobile = (string) preg_replace('/\D+/', '', (string) $request->input('mobile', ''));

            if ($mobile !== '') {
                return 'm:'.$mobile;
            }

            $identifier = strtolower(trim((string) ($request->input('email')
                ?? $request->input('identifier')
                ?? '')));

            return $identifier !== '' ? 'id:'.$identifier : 'ip:'.$request->ip();
        };

        RateLimiter::for('otp-send', function (Request $request) use ($otpKey) {
            return [
                Limit::perMinute(3)->by($otpKey($request)),
                Limit::perHour(10)->by($otpKey($request)),
                Limit::perMinute(15)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) use ($otpKey) {
            return [
                Limit::perMinute(10)->by($otpKey($request)),
                Limit::perHour(30)->by($otpKey($request)),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        $identifierKey = static function (Request $request): string {
            $identifier = (string) ($request->input('email')
                ?? $request->input('mobile')
                ?? $request->input('identifier')
                ?? '');
            $identifier = strtolower(trim($identifier));

            return $identifier !== '' ? 'id:'.$identifier : 'ip:'.$request->ip();
        };

        RateLimiter::for('login', function (Request $request) use ($identifierKey) {
            return [
                Limit::perMinute(5)->by($identifierKey($request)),
                Limit::perHour(30)->by($identifierKey($request)),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('user-exists', function (Request $request) use ($identifierKey) {
            return [
                Limit::perMinute(5)->by($identifierKey($request)),
                Limit::perMinute(10)->by('ip:'.$request->ip()),
                Limit::perHour(60)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request) use ($identifierKey) {
            return [
                Limit::perMinute(3)->by($identifierKey($request)),
                Limit::perHour(10)->by($identifierKey($request)),
                Limit::perMinute(15)->by('ip:'.$request->ip()),
            ];
        });
    }

    /**
     * Load system and app settings from DB (if available), with safe guards.
     *
     * @return array{0: array, 1: array}
     */
    private function loadSettings(): array
    {
        $systemSettings = [];
        $appSettings = [];
        $webSettings = [];
        $sellerSettings = [];
        try {
            if (app(DatabaseSchema::class)->hasTable('settings')) {
                $settingService = app(SettingService::class);
                $resource = $settingService->getSettingByVariable('system');
                $systemSettings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];

                try {
                    $appResource = $settingService->getSettingByVariable('app');
                    $appSettings = $appResource ? ($appResource->toArray(request())['value'] ?? []) : [];
                } catch (\Throwable $e) {
                    Log::warning($e->getMessage());
                }

                try {
                    $webSettings = $settingService->getSettingValues(SettingTypeEnum::WEB());
                } catch (\Throwable $e) {
                    Log::warning($e->getMessage());
                }

                try {
                    $sellerSettings = $settingService->getSettingValues(SettingTypeEnum::SELLER());
                } catch (\Throwable $e) {
                    Log::warning($e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning($e->getMessage());
        }

        return [$systemSettings, $appSettings, $webSettings, $sellerSettings];
    }

    private function applyBroadcastSettings(): void
    {
        try {
            if (app(DatabaseSchema::class)->hasTable('settings') && Setting::where('variable', SettingTypeEnum::BROADCAST_DRIVER())->exists()) {
                app(BroadcastSettingsService::class)->apply();
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to apply broadcast settings: '.$e->getMessage());
        }
    }

    /**
     * Apply the admin-configured timezone to PHP and Laravel config.
     * Falls back to UTC when missing or invalid so Carbon/now() never break.
     */
    private function applySystemTimezone(array $systemSettings): void
    {
        try {
            $timezone = $systemSettings['systemTimezone'] ?? null;

            if (! is_string($timezone) || $timezone === '' || ! in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                $timezone = 'UTC';
            }

            Config::set('app.timezone', $timezone);
            date_default_timezone_set($timezone);
        } catch (\Throwable $e) {
            Log::warning('Failed to apply system timezone: '.$e->getMessage());
            Config::set('app.timezone', 'UTC');
            date_default_timezone_set('UTC');
        }
    }

    /**
     * Detect current panel (admin or seller).
     */
    private function detectPanel(): string
    {
        return (request()->is('seller/*') || request()->routeIs('seller.*')) ? 'seller' : 'admin';
    }

    /**
     * Share base data and user to views.
     */
    private function shareViewData(array $data): void
    {
        if (Auth::check()) {
            $data['user'] = Auth::user();
        }

        view()->share($data);

        View::composer('*', function ($view) {
            if (Auth::check()) {
                $view->with('user', Auth::user());
            }
        });
    }

    /**
     * Configure Scramble (OpenAPI) security scheme.
     */
    private function configureScramble(): void
    {
        Scramble::configure()
            ->withOperationTransformers(ScrambleDocumentation::class)
            ->withDocumentTransformers(function (OpenApi $openApi) {
                ScrambleDocumentation::document($openApi);
            });
    }

    /**
     * Force HTTPS scheme in production environment.
     */
    private function forceHttpsInProduction(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Register all model observers with safe guards.
     */
    private function registerObservers(): void
    {
        $pairs = [
            WalletTransaction::class => WalletTransactionObserver::class,
            SellerStatement::class => SellerStatementObserver::class,
            DeliveryBoyAssignment::class => DeliveryBoyAssignmentObserver::class,
            OrderItemReturn::class => OrderItemReturnObserver::class,
            Store::class => StoreObserver::class,
            StoreLocation::class => StoreLocationObserver::class,
            ShippingProfile::class => ShippingProfileObserver::class,
            StorePackage::class => StorePackageObserver::class,
            SubscriptionTransaction::class => SubscriptionTransactionObserver::class,
            SellerSubscription::class => SellerSubscriptionObserver::class,
            Product::class => ProductSearchObserver::class,
            ProductVariant::class => ProductVariantSearchObserver::class,
        ];

        foreach ($pairs as $model => $observer) {
            try {
                /** @var class-string $model */
                $model::observe($observer);
            } catch (\Throwable $e) {
                Log::warning('Failed to register '.class_basename($observer).': '.$e->getMessage());
            }
        }

        foreach ([Product::class, ProductVariant::class, Store::class] as $model) {
            $model::observe(WatchBuyAttachmentObserver::class);
        }
    }
}
