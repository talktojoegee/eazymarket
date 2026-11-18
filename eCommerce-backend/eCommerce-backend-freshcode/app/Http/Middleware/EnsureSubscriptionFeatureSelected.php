<?php

namespace App\Http\Middleware;

use App\Enums\SettingTypeEnum;
use App\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionFeatureSelected
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (ComingSoon::isComingSoon('subscription')) {
            return $next($request);
        }

        $routeName = optional($request->route())->getName();
        if (in_array($routeName, [
            'admin.subscription-feature',
            'admin.subscription-feature.store',
            'admin.logout',
            'admin.devices.sync',
            'admin.devices.forget',
        ], true)) {
            return $next($request);
        }

        if ($request->is('admin/*') || $request->is('admin')) {
            /** @var SettingService $settingService */
            $settingService = app(SettingService::class);

            $subscriptionResource = $settingService->getSettingByVariable(SettingTypeEnum::SUBSCRIPTION());
            $subscriptionSettings = $subscriptionResource ? ($subscriptionResource->toArray($request)['value'] ?? []) : [];
            $decision = $subscriptionSettings['enableSubscription'] ?? null;

            if ($decision === null) {
                return redirect()->route('admin.subscription-feature')
                    ->with('warning', __('labels.subscription_feature_decision_required'));
            }
        }

        return $next($request);
    }
}
