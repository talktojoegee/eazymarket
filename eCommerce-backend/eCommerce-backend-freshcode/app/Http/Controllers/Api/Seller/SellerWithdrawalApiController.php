<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerWithdrawalRequest;
use App\Models\Wallet;
use App\Services\WithdrawalService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

#[Group('Seller Withdrawal')]
class SellerWithdrawalApiController extends Controller
{
    use AuthorizesRequests;
    public function __construct(WithdrawalService $withdrawalService)
    {
        $this->withdrawalService = $withdrawalService;
    }

    protected WithdrawalService $withdrawalService;

    /**
     * Create a withdrawal request
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createWithdrawalRequest(Request $request): JsonResponse
    {
        try {
            $this->authorize('requestWithdrawal', Wallet::class);
            $validated = $request->validate([
                'amount' => 'required|numeric|min:1|max:99999999|decimal:0,2',
                'note' => 'nullable|string|max:500',
            ]);

            $user = $request->user();
            $seller = $user?->seller();
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found') ?? 'Seller not found', null, 404);
            }

            $result = $this->withdrawalService->createWithdrawalRequest(
                $seller->id,
                [
                    'amount' => $validated['amount'],
                    'note' => $validated['note'] ?? null,
                ],
                'seller'
            );

            return ApiResponseType::sendJsonResponse(
                success: $result['success'],
                message: $result['message'],
                data: $result['data']
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_error'),
                data: $e->errors()
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.error_occurred'),
                data: [],
            );
        }
    }

    /**
     * Get withdrawal requests for the authenticated seller
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getWithdrawalRequests(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewWithdrawal', Wallet::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?? __('messages.unauthorized_action'), [], 403);
        }

        $user = $request->user();
        $seller = $user?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found') ?? 'Seller not found', null, 404);
        }

        $filters = $request->only(['status', 'from_date', 'to_date', 'per_page', 'sort', 'order']);
        $filters['seller_id'] = $seller->id;

        $result = $this->withdrawalService->getWithdrawalRequests($filters, 'seller');

        return ApiResponseType::sendJsonResponse(
            success: $result['success'],
            message: $result['message'],
            data: $result['data']
        );
    }

    /**
     * Get a single withdrawal request
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function getWithdrawalRequest(Request $request, int $id): JsonResponse
    {
        try {
            $this->authorize('viewWithdrawal', Wallet::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?? __('messages.unauthorized_action'), [], 403);
        }

        $user = $request->user();
        $seller = $user?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found') ?? 'Seller not found', null, 404);
        }

        $owned = SellerWithdrawalRequest::whereKey($id)->where('seller_id', $seller->id)->exists();

        if (! $owned) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.unauthorized_action'),
                data: []
            );
        }

        $result = $this->withdrawalService->getWithdrawalRequest($id, 'seller');

        if ($result['success'] && $result['data']->seller_id !== $seller->id) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.unauthorized_action'),
                data: []
            );
        }

        return ApiResponseType::sendJsonResponse(
            success: $result['success'],
            message: $result['message'],
            data: $result['data']
        );
    }

    /**
     * Get processed withdrawal requests (history) for the authenticated seller
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewWithdrawal', Wallet::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied') ?? __('messages.unauthorized_action'), [], 403);
        }

        $user = $request->user();
        $seller = $user?->seller();
        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found') ?? 'Seller not found', null, 404);
        }

        $filters = $request->only(['from_date', 'to_date', 'per_page', 'sort', 'order']);
        $filters['seller_id'] = $seller->id;
        $filters['status'] = [\App\Enums\Seller\SellerWithdrawalStatusEnum::APPROVED(), \App\Enums\Seller\SellerWithdrawalStatusEnum::REJECTED()];

        $result = $this->withdrawalService->getWithdrawalRequests($filters, 'seller');

        return ApiResponseType::sendJsonResponse(
            success: $result['success'],
            message: $result['message'],
            data: $result['data']
        );
    }
}
