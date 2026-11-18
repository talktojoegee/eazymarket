<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\AdminPermissionEnum;
use App\Enums\GuardNameEnum;
use App\Enums\SellerPermissionEnum;
use App\Enums\SettingTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\UpdateEmailRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\User\UserResource;
use App\Models\Setting;
use App\Models\User;
use App\Services\ProfileService;
use App\Services\SettingService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

#[Group('Users')]
class UserApiController extends Controller
{
    use ChecksPermissions;

    protected ProfileService $profileService;

    protected SettingService $settingService;

    public function __construct(ProfileService $profileService, SettingService $settingService)
    {
        $this->profileService = $profileService;
        $this->settingService = $settingService;
    }

    /**
     * Update user profile
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'labels.user_not_authenticated',
                    []
                );
            }

            $validated = $request->validated();
            $updatedUser = $this->profileService->updateProfile($user, $validated, $request);

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.profile_updated_successfully',
                new UserResource($updatedUser)
            );

        } catch (\Exception $e) {
            Log::error('Profile update failed: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                false,
                'labels.something_went_wrong',
                []
            );
        }
    }

    /**
     * Get user profile
     */
    public function getProfile(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'labels.user_not_authenticated',
                    []
                );
            }

            $assignedPermissions = [];
            try {
                if (! empty($user->access_panel?->value) && $user->access_panel->value === 'seller') {
                    $seller = $user->seller();
                    if ($seller) {
                        if ((int) $seller->user_id === (int) $user->id) {
                            $assignedPermissions = SellerPermissionEnum::values();
                        } else {
                            if (function_exists('setPermissionsTeamId')) {
                                setPermissionsTeamId($seller->id);
                            }
                            $assignedPermissions = $user->getAllPermissions()->pluck('name')->toArray();
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Failed determining seller permissions on getProfile: '.$e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => __('labels.profile_retrieved_successfully'),
                'data' => new UserResource($user),
                'assigned_permissions' => $assignedPermissions,
            ]);

        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                false,
                'labels.something_went_wrong',
                []
            );
        }
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        try {
            if (Setting::isDemoModeEnabled()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.delete_account_not_allowed_in_demo_mode', []);
            }
            $user = $request->user();

            $user->tokens()->delete();

            $user->delete();

            $restoreBefore = $user->fresh()?->deletionGraceEndsAt();

            return ApiResponseType::sendJsonResponse(true, __('labels.account_deleted_successfully'), [
                'restorable'     => true,
                'restore_before' => $restoreBefore?->toIso8601String(),
                'grace_days'     => User::deletionGraceDays(),
            ]);
        } catch (\Exception $e) {
            Log::error('Account deletion failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(false, __('labels.something_went_wrong'), []);
        }
    }

    /**
     * Update user email
     */
    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        try {
            if (Setting::isDemoModeEnabled()) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    __('labels.demo_mode_message_placeholder'),
                    [],
                    403
                );
            }

            $user = $request->user();

            $newEmail = $request->validated()['email'];

            if ($user->email !== null && strcasecmp($user->email, $newEmail) === 0) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    'labels.email_already_matches',
                    ['email' => $user->email]
                );
            }

            $updated = $this->profileService->updateEmail($user, $newEmail);

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.email_update_verification_sent',
                new UserResource($updated)
            );
        } catch (\Throwable $e) {
            Log::error('updateEmail failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(
                false,
                'labels.something_went_wrong',
                [$e->getMessage()]
            );
        }
    }

    /**
     * Resend the email verification notification to the user's current
     * email address. No-op if the email is already verified.
     */
    public function resendEmailVerification(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (! $user) {
                return ApiResponseType::sendJsonResponse(false, 'labels.user_not_authenticated', []);
            }
            if (empty($user->email)) {
                return ApiResponseType::sendJsonResponse(false, 'labels.email_not_set', []);
            }
            if ($user->hasVerifiedEmail()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.email_already_verified', []);
            }

            $user->sendEmailVerificationNotification();

            return ApiResponseType::sendJsonResponse(true, 'labels.email_update_verification_sent', []);
        } catch (\Throwable $e) {
            Log::error('resendEmailVerification failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', []);
        }
    }

    /**
     * Change password for the authenticated user via API
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            if (Setting::isDemoModeEnabled()) {
                return ApiResponseType::sendJsonResponse(false, 'labels.change_password_not_allowed_in_demo_mode', []);
            }
            $user = Auth::user();
            if (! $user) {
                return ApiResponseType::sendJsonResponse(false, 'labels.user_not_authenticated', []);
            }

            $user->password = Hash::make($request->input('password'));
            $user->save();
            $user->revokeTokens(keepCurrent: true);

            return ApiResponseType::sendJsonResponse(true, __('labels.password_updated_successfully'), []);
        } catch (\Throwable $e) {
            Log::error('Password update failed: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(false, __('labels.password_update_failed', []), []);
        }
    }

    public function search(Request $request): JsonResponse
    {
        if ($request->has('market_ids')) {
            $marketInput = $request->input('market_ids');
            if (is_numeric($marketInput)) {
                $request->merge(['market_ids' => [(int) $marketInput]]);
            } elseif (is_string($marketInput)) {
                $request->merge([
                    'market_ids' => array_values(array_filter(array_map('trim', explode(',', $marketInput)))),
                ]);
            }
        }

        $validated = $request->validate([
            'search' => 'required|string|min:2|max:100',
            'type' => 'required|string|in:rider,seller,customer',
            'market_ids' => 'nullable|array',
            'market_ids.*' => 'integer|exists:markets,id',
        ]);

        $search = $validated['search'];
        $type = $validated['type'];
        $marketIds = collect($validated['market_ids'] ?? [])->filter()->values()->all();

        $isSellerPanel = $this->getPanel() === 'seller';
        $actingSeller = $isSellerPanel ? Auth::user()?->seller() : null;

        if ($isSellerPanel) {
            if ($type !== 'customer' || ! $actingSeller) {
                return ApiResponseType::sendJsonResponse(false, __('labels.unauthorized_action'), [], 403);
            }
        } else {
            $required = match ($type) {
                'customer' => AdminPermissionEnum::CUSTOMER_VIEW(),
                'seller' => AdminPermissionEnum::SELLER_VIEW(),
                default => AdminPermissionEnum::NOTIFICATION_VIEW(),
            };

            if (! $this->hasPermission($required)) {
                return ApiResponseType::sendJsonResponse(false, __('labels.unauthorized_action'), [], 403);
            }
        }

        $query = User::query();

        if ($type === 'customer') {
            $query->where(function ($q) {
                $q->whereNull('access_panel')
                    ->orWhere('access_panel', GuardNameEnum::WEB());
            })->whereDoesntHave('deliveryBoy');

            if ($actingSeller) {
                $query->whereHas('orders.sellerOrders', function ($q) use ($actingSeller) {
                    $q->where('seller_id', $actingSeller->id);
                });
            }
            if (! empty($marketIds)) {
                $query->inMarkets($marketIds);
            }
        } elseif ($type === 'seller') {
            $query->where('access_panel', GuardNameEnum::SELLER());
            if (! empty($marketIds)) {
                $query->whereIn('users.id', function ($sub) use ($marketIds) {
                    $sub->select('sellers.user_id')
                        ->from('sellers')
                        ->join('stores', 'stores.seller_id', '=', 'sellers.id')
                        ->join('store_market', 'store_market.store_id', '=', 'stores.id')
                        ->whereIn('store_market.market_id', $marketIds);
                });
            }
        } elseif ($type === 'rider') {
            $query->whereHas('deliveryBoy');
        }

        $query->where(function ($q) use ($search, $type) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('mobile', 'like', "%{$search}%");

            if ($type === 'rider') {
                $q->orWhereHas('deliveryBoy', function ($dq) use ($search) {
                    $dq->where('full_name', 'like', "%{$search}%");
                });
            }
        });

        $users = $query->take(50)->get();

        $results = $users->map(function ($user) use ($isSellerPanel) {
            $contact = $isSellerPanel
                ? $this->maskContact($user->mobile)
                : (!empty($user->email) ? ($user->email . " | ") : "") . $user->mobile;

            return [
                'id' => $user->id,
                'value' => $user->id,
                'text' => $user->name.' ('.$contact.')',
            ];
        });

        return response()->json($results);
    }

    private function maskContact(?string $mobile): string
    {
        $mobile = (string) $mobile;

        return strlen($mobile) > 4 ? str_repeat('*', strlen($mobile) - 4).substr($mobile, -4) : $mobile;
    }
}
