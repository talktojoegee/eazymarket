<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Models\Setting;
use App\Services\ProfileService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

#[Group('Seller Profile')]
class SellerProfileApiController
{
    public function __construct(protected ProfileService $profileService)
    {
    }

    /**
     * Authenticated seller's profile, seller record and owned stores.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $seller = $user?->seller();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.profile_fetched_successfully',
            data: [
                'user' => $this->profileService->getProfileData($user),
                'seller' => $seller,
                'stores' => $seller ? $seller->stores()->orderBy('id')->get() : [],
            ],
        );
    }

    /**
     * Update the seller's own name / profile image.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        try {
            if (Setting::isDemoModeEnabled()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.demo_mode_message_placeholder', [], 403);
            }

            $user = $request->user();
            $this->profileService->updateProfile($user, $request->validated(), $request);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.profile_updated_successfully',
                data: $this->profileService->getProfileData($user->refresh()),
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.profile_update_failed', ['error' => $e->getMessage()]),
                data: [],
            );
        }
    }

    /**
     * Change the seller's password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            if (Setting::isDemoModeEnabled()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.demo_mode_message_placeholder', [], 403);
            }

            $user = $request->user();
            $user->password = Hash::make($request->input('password'));
            $user->save();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.password_updated_successfully',
                data: [],
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.password_update_failed', ['error' => $e->getMessage()]),
                data: [],
            );
        }
    }
}
