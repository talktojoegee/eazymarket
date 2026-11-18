<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\SettingTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\SharedList;
use App\Services\SettingService;
use App\Services\SharedListService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedListApiController extends Controller
{
    public function __construct(
        private readonly SharedListService $sharedListService,
        private readonly SettingService $settingService,
    ) {
    }

    public function createCartShare(Request $request): JsonResponse
    {
        $sharedList = $this->sharedListService->createCartShare($request->user());

        if (! $sharedList) {
            return ApiResponseType::sendJsonResponse(false, 'labels.cart_is_empty', []);
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.shared_cart_created_successfully', [
            'token' => $sharedList->token,
            'share_url' => $this->shareUrl($sharedList->token),
        ]);
    }

    private function shareUrl(string $token): ?string
    {
        $webSettings = $this->settingService->getSettingValues(SettingTypeEnum::WEB());
        $baseUrl = trim((string) ($webSettings['customerWebUrl'] ?? ''));

        if ($baseUrl === '') {
            $baseUrl = trim((string) config('app.frontendUrl', ''));
        }

        return $baseUrl !== '' ? rtrim($baseUrl, '/').'/cart/shared-lists/'.$token : null;
    }

    public function show(string $token): JsonResponse
    {
        $sharedList = $this->findActiveSharedList($token);

        if (! $sharedList) {
            return ApiResponseType::sendJsonResponse(false, 'labels.shared_list_not_found', [], 404);
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'labels.shared_list_fetched_successfully',
            $this->sharedListService->preview($sharedList)
        );
    }

    public function import(Request $request, string $token): JsonResponse
    {
        $sharedList = $this->findActiveSharedList($token);

        if (! $sharedList) {
            return ApiResponseType::sendJsonResponse(false, 'labels.shared_list_not_found', [], 404);
        }

        $result = $this->sharedListService->import(
            $request->user(),
            $sharedList
        );

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result
        );
    }

    private function findActiveSharedList(string $token): ?SharedList
    {
        return SharedList::query()
            ->where('token', $token)
            ->whereNotNull('expired_at')
            ->where('expired_at', '>', now())
            ->first();
    }
}
