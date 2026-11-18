<?php

namespace App\Http\Controllers\Api\Seller;

use Illuminate\Support\Facades\Log;
use App\Enums\Seller\SellerSettlementStatusEnum;
use App\Models\User;
use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use App\Http\Requests\Seller\StoreSellerRequest;
use App\Services\SellerService;
use App\Traits\AuthTrait;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use App\Enums\SellerPermissionEnum;
use Dedoc\Scramble\Attributes\BodyParameter;

#[Group('Seller Authentication')]
class SellerAuthApiController
{
    use AuthTrait;

    protected string $role = 'seller';

    protected $sellerService;

    public function __construct(SellerService $sellerService)
    {
        $this->sellerService = $sellerService;
    }

    /**
     * creating sellers API
     */
    #[BodyParameter(
        'dynamic_fields',
        description: 'Country-specific Bank Details and Business Documents. Fetch the available field keys from GET /api/seller/registration-fields?country_iso2=US. Submit values using nested multipart fields such as dynamic_fields[us_bank_name]; image fields must be uploaded as file parts.',
        required: false,
        type: 'object',
        example: [
            'us_bank_name' => 'Example Bank',
            'us_account_number' => '123456789',
            'us_bank_account_type' => 'checking',
            'us_business_license' => '(uploaded image file)',
        ],
    )]
    public function createSeller(StoreSellerRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $validated['verification_status'] = SellerVerificationStatusEnum::NotApproved();
            $validated['visibility_status'] = SellerVisibilityStatusEnum::Draft();
            $seller = $this->sellerService->createSeller(
                $validated,
                $request->allFiles()
            );

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.seller_created_successfully',
                $seller,
                201
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.validation_failed' . $e->getMessage(),
                data: $e->errors(),
            );
        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: [],
                status: 500
            );
        }
    }

    /**
     * Delete Seller Account
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        try {
            if (Setting::isDemoModeEnabled()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.change_password_not_allowed_in_demo_mode', []);
            }
            $user = $request->user();
            $seller = $user?->seller();

            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null, 404);
            }

            if ((int) $seller->user_id !== (int) $user->id) {
                if (function_exists('setPermissionsTeamId')) {
                    setPermissionsTeamId($seller->id);
                }
                if (!$user->hasPermissionTo(SellerPermissionEnum::SELLER_DELETE())) {
                    return ApiResponseType::sendJsonResponse(false, __('labels.permission_denied'), [], 403);
                }
            }

            if ((float) ($seller->wallet?->balance ?? 0) > 0
                || $seller->statements()->where('settlement_status', SellerSettlementStatusEnum::PENDING())->exists()) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_delete_blocked', [
                    'items' => __('labels.unsettled_earnings'),
                ]), [], 422);
            }

            DB::beginTransaction();
            $seller->delete();
            $seller->media()->each(function ($media) {
                $media->delete();
            });
            User::find($seller->user_id)?->delete();
            DB::commit();

            return ApiResponseType::sendJsonResponse(true, __('labels.account_deleted_successfully'), []);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Seller account deletion failed: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(false, __('labels.account_deletion_failed', ['error' => '']), []);
        }
    }
}
