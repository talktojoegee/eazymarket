<?php

use App\Http\Middleware\CheckInstallation;
use App\Http\Middleware\DetectMarket;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\StorageCorsMiddleware;
use App\Http\Middleware\ValidateAdmin;
use App\Http\Middleware\ValidateSeller;
use App\Http\Middleware\EnsureSellerHasSubscription;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\SellerQueryTokenAuthenticate;
use App\Http\Middleware\SetAdminMarket;
use App\Http\Middleware\SetSellerApiStore;
use App\Http\Middleware\SetSellerStore;
use App\Services\PanelNavigationService;
use App\Types\Api\ApiResponseType;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use App\Http\Middleware\VerifyLicense;
use App\Http\Middleware\EnsureSubscriptionFeatureSelected;
use App\Http\Middleware\SanitizeUploadedFilenames;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: [
            __DIR__ . '/../routes/api.php',
        ],
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    // Register application and package service providers (Laravel 11/12 style)
    ->withProviders(require __DIR__ . '/providers.php')
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prependToPriorityList(
            \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            SellerQueryTokenAuthenticate::class,
        );

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('admin/*') || $request->is('admin')) {
                return route('admin.login');
            } elseif ($request->is('seller/*') || $request->is('seller')) {
                return route('seller.login');
            }
            return route('login');
        });
        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin/login') || $request->is('admin')
                || $request->is('seller/login') || $request->is('seller')) {
                $user = $request->user();
                $panel = $user->access_panel->value === 'admin' ? 'admin' : 'seller';

                return app(PanelNavigationService::class)->landingUrl($user, $panel);
            }
            return url('/');
        });
        $middleware->alias([
            'validate.admin' => ValidateAdmin::class,
            'validate.seller' => ValidateSeller::class,
            'ensure.seller.subscription' => EnsureSellerHasSubscription::class,
            'seller.query.token' => SellerQueryTokenAuthenticate::class,
            'permission' => CheckPermission::class,
            'locale' => SetLocale::class,
            'storage.cors' => StorageCorsMiddleware::class,
            'maintenance' => CheckMaintenanceMode::class,
            'license' => VerifyLicense::class,
            'ensure.subscription.feature' => EnsureSubscriptionFeatureSelected::class,
            'detect.market' => DetectMarket::class,
            'admin.market' => SetAdminMarket::class,
            'seller.store' => SetSellerStore::class,
            'seller.api.store' => SetSellerApiStore::class,
            'coming.soon' => \App\Http\Middleware\ComingSoon::class,
            'dev.only' => \App\Http\Middleware\DevOnly::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            StorageCorsMiddleware::class,
        ], prepend: [
            SanitizeUploadedFilenames::class,
            CheckMaintenanceMode::class,
            CheckInstallation::class,
            VerifyLicense::class,
        ]);

        $middleware->api(prepend: [
            SanitizeUploadedFilenames::class,
            CheckMaintenanceMode::class,
            VerifyLicense::class,
        ], append: [
            // Resolves the customer's market from X-Market header / market cookie /
            // user_market pivot / country header / IP, with fallback to the seeded
            // default. Binds the result on the request so listing controllers can
            // do $request->attributes->get('market') without re-resolving. Cheap
            // (one cached lookup per request); seller / delivery-boy routes just
            // never read the attribute.
            DetectMarket::class,
            SetLocale::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if ($request->is('api/*')) {
                return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
            }
        });
    })->create();
