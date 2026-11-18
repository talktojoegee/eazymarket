<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\SettingTypeEnum;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserRegistered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AppleCallbackRequest;
use App\Http\Requests\Auth\GoogleCallbackRequest;
use App\Http\Requests\User\VerifyUserRequest;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\SettingService;
use App\Services\SocialAuthService;
use App\Traits\AuthTrait;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Auth\UserRecord;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

#[Group('Auth')]
class AuthApiController extends Controller
{
    use AuthTrait;

    public function __construct(
        protected SettingService $settingService,
        protected SocialAuthService $socialAuthService,
    ) {
    }

    /**
     * Verify if a user exists by email or mobile.
     *
     * Mobile lookups match the national number stored in `users.mobile`
     * (derived from `value`, or from `country_code` + `value`), plus a couple
     * of digit fallbacks.
     *
     * Soft-deleted accounts inside the 30-day deletion recovery window count as
     * existing and are flagged `pending_deletion` / `restorable`, so the client
     * routes the user to sign-in/restore rather than a fresh registration.
     * Once the window passes the identifiers are released and `exists` is false.
     *
     * @param VerifyUserRequest $request
     * @return JsonResponse
     */
    public function verifyUser(VerifyUserRequest $request): JsonResponse
    {
        try {
            $type        = $request->input('type');
            $value       = $request->input('value');

            $user = null;

            if ($type === 'email') {
                $user = User::withTrashed()
                    ->where('email', $value)
                    ->orderByRaw('deleted_at IS NULL DESC')
                    ->first();
            } elseif ($type === 'mobile') {
                $user = User::withTrashed()
                    ->where('mobile', $value)
                    ->orderByRaw('deleted_at IS NULL DESC')
                    ->first();
            }

            $pendingDeletion = $user !== null && $user->trashed() && $user->isPendingDeletion();
            $exists = $user !== null && (! $user->trashed() || $pendingDeletion);

            $responseData = [
                'exists'           => $exists,
                'pending_deletion' => $pendingDeletion,
                'restorable'       => $pendingDeletion,
                'type'             => $type,
                'value'            => $value,
            ];

            return ApiResponseType::sendJsonResponse(
                $exists,
                $exists ? 'labels.user_found' : 'labels.user_not_found',
                $responseData
            );

        } catch (\Exception $e) {
            Log::error('verifyUser failed: '.$e->getMessage());

            return ApiResponseType::sendJsonResponse(
                false,
                'labels.something_went_wrong',
                []
            );
        }
    }

    /**
     * Build the set of mobile strings to match against `users.mobile`, which
     * stores the national number. The primary candidate is the normalised
     * national number (derived from the value, or from country_code + value);
     * the raw value and digits are kept as fallbacks. Duplicates and empty
     * entries are filtered out.
     *
     * @return array<int, string>
     */
    protected function buildMobileCandidates(string $value, ?string $countryCode): array
    {
        $rawDigits = preg_replace('/\D+/', '', $value);

        $otp = app(\App\Services\OtpService::class);
        $candidates = [
            $otp->normalizeMobile($value),
            $rawDigits,
            $value,
        ];

        if (!empty($countryCode)) {
            $codeDigits = preg_replace('/\D+/', '', $countryCode);

            if ($codeDigits !== '' && $rawDigits !== '') {
                $candidates[] = $otp->normalizeMobile('+' . $codeDigits . $rawDigits);
            }
        }

        return array_values(array_unique(array_filter($candidates, fn ($c) => $c !== '' && $c !== null)));
    }



    /**
     * Sign in (or register) with a Google Firebase ID token.
     *
     * Single round-trip: verifies the token, looks up the user by email or
     * firebase_uid, creates them on the fly if new. `mobile` and `password`
     * are left null — the app can ask the user to set them later.
     *
     * @param GoogleCallbackRequest $request
     * @return JsonResponse
     */
    public function googleCallback(GoogleCallbackRequest $request): JsonResponse
    {
        try {
            $authSetting = $this->settingService->getSettingByVariable(SettingTypeEnum::AUTHENTICATION());
            $authConfig  = $authSetting?->value ?? [];
            if (empty($authConfig['googleLogin'])) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.google_login_not_enabled',
                    data: [],
                );
            }

            $firebase = $this->resolveFirebaseUser($request->input('idToken'));
            if ($firebase instanceof JsonResponse) {
                return $firebase;
            }

            $result = $this->socialAuthService->loginOrRegisterFromGoogle(
                $firebase['user'],
                $request->input('friends_code'),
                [
                    'country' => $request->input('country'),
                    'iso_2'   => $request->input('iso_2'),
                ],
            );

            return $this->respondWithToken($request, $result['user'], $result['is_new']);
        } catch (AuthenticationException $e) {
            Log::error('Social login authentication error: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.authentication_error',
                data: [],
            );
        } catch (FailedToVerifyToken $e) {
            Log::error('Social login token verification failed: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.invalid_firebase_token',
                data: [],
            );
        } catch (\Exception $e) {
            Log::error('googleCallback failed: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.something_went_wrong',
                data: [],
            );
        }
    }

    /**
     * Sign in (or register) with an Apple Firebase ID token.
     *
     * Apple sometimes withholds the user's email; we therefore key on the
     * Firebase UID first and fall back to email only when it's present. A
     * brand-new Apple-no-email user is created with `firebase_uid` set and
     * `email` / `mobile` / `password` all null.
     *
     * @param AppleCallbackRequest $request
     * @return JsonResponse
     */
    public function appleCallback(AppleCallbackRequest $request): JsonResponse
    {
        try {
            $authSetting = $this->settingService->getSettingByVariable(SettingTypeEnum::AUTHENTICATION());
            $authConfig  = $authSetting?->value ?? [];
            if (empty($authConfig['appleLogin'])) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.apple_login_not_enabled',
                    data: [],
                );
            }

            $firebase = $this->resolveFirebaseUser($request->input('idToken'));
            if ($firebase instanceof JsonResponse) {
                return $firebase;
            }

            $result = $this->socialAuthService->loginOrRegisterFromApple(
                $firebase['user'],
                $firebase['claims'],
                $request->input('friends_code'),
                [
                    'country' => $request->input('country'),
                    'iso_2'   => $request->input('iso_2'),
                ],
            );

            return $this->respondWithToken($request, $result['user'], $result['is_new']);
        } catch (AuthenticationException $e) {
            Log::error('Social login authentication error: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.authentication_error',
                data: [],
            );
        } catch (FailedToVerifyToken $e) {
            Log::error('Social login token verification failed: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.invalid_firebase_token',
                data: [],
            );
        } catch (\Exception $e) {
            Log::error('appleCallback failed: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.something_went_wrong',
                data: [],
            );
        }
    }

    /**
     * Bootstrap Firebase Auth, verify the supplied ID token, and resolve the
     * UserRecord + raw claims. Returns a short-circuit JsonResponse when the
     * bootstrap fails — the caller should `return` it directly. Throws
     * FailedToVerifyToken / AuthenticationException, which the caller's
     * try/catch already handles.
     *
     * @return JsonResponse|array{user: UserRecord, claims: array<string, mixed>}
     */
    protected function resolveFirebaseUser(string $idToken): JsonResponse|array
    {
        $firebaseAuth = $this->getFirebaseAuth();
        if ($firebaseAuth['success'] === false) {
            return ApiResponseType::sendJsonResponse(
                $firebaseAuth['success'],
                $firebaseAuth['message'],
                $firebaseAuth['data'],
            );
        }
        $firebaseAuth = $firebaseAuth['data'];

        $verifiedIdToken = $firebaseAuth->verifyIdToken($idToken);
        $uid             = $verifiedIdToken->claims()->get('sub');

        return [
            'user'   => $firebaseAuth->getUser($uid),
            'claims' => $verifiedIdToken->claims()->all(),
        ];
    }

    /**
     * Send an email login OTP to an existing customer.
     */
    public function sendEmailOtp(Request $request): JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.user', []);
        }

        $result = app(EmailOtpService::class)->sendToUser($user);

        return ApiResponseType::sendJsonResponse(
            (bool) ($result['success'] ?? false),
            $result['message'] ?? 'passwords.otp_sent_email',
            []
        );
    }

    /**
     * Verify an email login OTP and sign the existing customer in with a token.
     */
    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.user', []);
        }

        if (!app(EmailOtpService::class)->verify($user->email, $request->input('otp'))) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.otp_invalid', []);
        }

        return $this->respondWithToken($request, $user, false);
    }

    /**
     * Finalize a social sign-in: store FCM token, fire the right event, and
     * return the Sanctum token + user resource in the standard envelope.
     */
    protected function respondWithToken(Request $request, User $user, bool $isNew): JsonResponse
    {
        $this->storeFcmToken($request, $user);

        event($isNew ? new UserRegistered($user) : new UserLoggedIn($user));

        $tokenName = $user->email ?? $user->firebase_uid ?? 'api-token';

        $data = (new UserResource($user))->resolve($request);
        $data['is_register'] = $isNew;

        return response()->json([
            'success'      => true,
            'message'      => __($isNew ? 'labels.registration_successful' : 'labels.login_successful'),
            'access_token' => $user->createToken($tokenName)->plainTextToken,
            'token_type'   => 'Bearer',
            'data'         => $data,
        ]);
    }
}
