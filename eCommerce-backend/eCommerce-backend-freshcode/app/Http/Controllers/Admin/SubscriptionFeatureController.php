<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingTypeEnum;
use App\Models\Setting;
use App\Http\Controllers\Controller;
use App\Services\SettingService;
use App\Types\Api\ApiResponseType;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\View\View;

class SubscriptionFeatureController extends Controller
{
    use AuthorizesRequests;

    public function __construct(protected SettingService $settingService)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Setting::class);

        $resource = $this->settingService->getSettingByVariable(SettingTypeEnum::SUBSCRIPTION());
        $settings = $resource?->toArray($request)['value'] ?? [];

        return view('admin.subscription-feature', [
            'settings' => $settings,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('updateSetting', [Setting::class, SettingTypeEnum::SUBSCRIPTION()]);

        $resource = $this->settingService->getSettingByVariable(SettingTypeEnum::SUBSCRIPTION());
        $current = $resource?->toArray($request)['value'] ?? [];

        $validated = $request->validate([
            'enableSubscription' => 'required|in:0,1',
        ]);

        $requestedEnable = (int)$validated['enableSubscription'] === 1;

        $currentValue = $current['enableSubscription'] ?? null;

        if ($currentValue === true && $requestedEnable === false) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.subscription_feature_cannot_disable'), data: []
            );
        }

        if ($currentValue === $requestedEnable) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.subscription_feature_already_disabled'), data: []
            );
        }

        $newValues = array_merge($current, [
            'enableSubscription' => $requestedEnable,
        ]);

        $this->settingService->saveSetting(SettingTypeEnum::SUBSCRIPTION(), $newValues);

        if ($requestedEnable) {
            Artisan::call('subscription:sync-usage');
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.subscription_feature_successfully_saved'),
            data: [
                'redirect_url' => route('admin.dashboard'),
            ]
        );
    }
}
