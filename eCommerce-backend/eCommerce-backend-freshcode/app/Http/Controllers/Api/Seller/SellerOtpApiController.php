<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\OtpService;
use App\Traits\AuthTrait;
use App\Types\Api\ApiResponseType;
use App\Types\Settings\AuthenticationSettingType;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

#[Group('Seller Authentication')]
class SellerOtpApiController extends Controller
{
    use AuthTrait;

    /**
     * Send Login Otp
     */
    #[BodyParameter('identifier', description: 'Seller email address or mobile number.', type: 'string')]
    public function sendLoginOtp(Request $request): JsonResponse
    {
        $request->validate(['identifier' => 'required|string']);

        ['user' => $user, 'channel' => $channel] = $this->resolveIdentifier($request->input('identifier'));

        if (!$user) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_a_seller') ?? 'No seller account found for that identifier.', []);
        }

        if ($gate = $this->checkSellerAccess($user)) {
            return $gate;
        }

        return $this->dispatchOtp($user, $channel, 'login');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * Verify Login otp
     */
    #[BodyParameter('identifier', description: 'Seller email address or mobile number.', type: 'string')]
    #[BodyParameter('otp', description: 'OTP received over email or SMS.', required: false, type: 'string')]
    #[BodyParameter('idToken', description: 'Firebase phone idToken when the SMS gateway is firebase.', required: false, type: 'string')]
    public function verifyLoginOtp(Request $request): JsonResponse
    {
        if ($request->filled('idToken')) {
            $resolved = $this->resolveFirebaseUser($request);
            if ($resolved instanceof JsonResponse) {
                return $resolved;
            }
            $user = $resolved;
        } else {
            $request->validate([
                'identifier' => 'required|string',
                'otp' => 'required|string|size:6',
            ]);

            ['user' => $user, 'channel' => $channel] = $this->resolveIdentifier($request->input('identifier'));

            if (!$user) {
                return ApiResponseType::sendJsonResponse(false, __('labels.not_a_seller') ?? 'No seller account found for that identifier.', []);
            }

            if (!$this->verifyOtpForChannel($user, $channel, $request->input('otp'))) {
                return ApiResponseType::sendJsonResponse(false, 'passwords.otp_invalid', []);
            }
        }

        if ($gate = $this->checkSellerAccess($user)) {
            return $gate;
        }

        return $this->finalizeLogin($request, $user);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * Send Forget Password Otp
     */
    #[BodyParameter('identifier', description: 'Seller email address or mobile number.', type: 'string')]
    public function sendForgotOtp(Request $request): JsonResponse
    {
        $request->validate(['identifier' => 'required|string']);

        ['user' => $user, 'channel' => $channel] = $this->resolveIdentifier($request->input('identifier'));

        if (!$user) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.identifier', []);
        }

        if ($gate = $this->checkSellerAccess($user)) {
            return $gate;
        }

        return $this->dispatchOtp($user, $channel, 'reset');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * Verify forget password OTP
     */
    #[BodyParameter('identifier', description: 'Seller email address or mobile number.', type: 'string')]
    #[BodyParameter('otp', description: 'OTP received over email or SMS.', required: false, type: 'string')]
    #[BodyParameter('idToken', description: 'Firebase phone idToken when the SMS gateway is firebase.', required: false, type: 'string')]
    public function verifyForgotOtp(Request $request): JsonResponse
    {
        if ($request->filled('idToken')) {
            $resolved = $this->resolveFirebaseUser($request);
            if ($resolved instanceof JsonResponse) {
                return $resolved;
            }
            $user = $resolved;
            $identifier = $user->mobile;
        } else {
            $request->validate([
                'identifier' => 'required|string',
                'otp' => 'required|string|size:6',
            ]);

            ['user' => $user, 'channel' => $channel] = $this->resolveIdentifier($request->input('identifier'));

            if (!$user) {
                return ApiResponseType::sendJsonResponse(false, 'passwords.identifier', []);
            }

            if (!$this->verifyOtpForChannel($user, $channel, $request->input('otp'))) {
                return ApiResponseType::sendJsonResponse(false, 'passwords.otp_invalid', []);
            }

            $identifier = $request->input('identifier');
        }

        if ($gate = $this->checkSellerAccess($user)) {
            return $gate;
        }

        return ApiResponseType::sendJsonResponse(true, 'passwords.verified', [
            'token' => Password::broker()->createToken($user),
            'identifier' => $identifier,
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * Reset password
     */
    #[BodyParameter('identifier', description: 'Seller email address or mobile number.', type: 'string')]
    #[BodyParameter('token', description: 'Reset token returned by verify-otp.', type: 'string')]
    #[BodyParameter('password', description: 'New password (min 8, confirmed).', type: 'string')]
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string',
            'token' => 'required|string',
            'password' => 'required|min:8|confirmed',
        ]);

        ['user' => $user] = $this->resolveIdentifier($request->input('identifier'));

        if (!$user) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.identifier', []);
        }

        if ($gate = $this->checkSellerAccess($user)) {
            return $gate;
        }

        $broker = Password::broker();

        if (!$broker->tokenExists($user, $request->input('token'))) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.token', []);
        }

        $user->forceFill([
            'password' => Hash::make($request->input('password')),
        ])->setRememberToken(Str::random(60));
        $user->save();
        $user->revokeTokens();

        $broker->deleteToken($user);

        return ApiResponseType::sendJsonResponse(true, 'passwords.reset', []);
    }

    protected function dispatchOtp(User $user, string $channel, string $purpose): JsonResponse
    {
        if ($channel === 'email') {
            $result = app(EmailOtpService::class)->sendToUser($user, $purpose);

            return ApiResponseType::sendJsonResponse(
                (bool) ($result['success'] ?? false),
                $result['message'] ?? 'passwords.otp_sent_email',
                ['channel' => 'email']
            );
        }

        if (AuthenticationSettingType::smsGateway() === 'firebase') {
            return ApiResponseType::sendJsonResponse(true, 'passwords.otp_sent', [
                'channel' => 'mobile',
                'gateway' => 'firebase',
            ]);
        }

        $result = app(OtpService::class)->sendOtp($user->mobile);

        if (!($result['success'] ?? false)) {
            return ApiResponseType::sendJsonResponse(false, $result['message'] ?? 'passwords.throttled', []);
        }

        return ApiResponseType::sendJsonResponse(true, $result['message'] ?? 'passwords.otp_sent', [
            'channel' => 'mobile',
            'gateway' => 'custom',
            'expires_in' => $result['expires_in'] ?? null,
        ]);
    }

    protected function verifyOtpForChannel(User $user, string $channel, ?string $otp): bool
    {
        if ($otp === null || $otp === '') {
            return false;
        }

        return $channel === 'email'
            ? app(EmailOtpService::class)->verify($user->email, $otp)
            : (app(OtpService::class)->verifyOtp($user->mobile, $otp)['success'] ?? false);
    }

    protected function resolveFirebaseUser(Request $request): JsonResponse|User
    {
        $request->validate(['idToken' => 'required|string']);

        $verified = $this->verifyFirebasePhoneToken($request->input('idToken'));

        if ($verified instanceof JsonResponse) {
            return $verified;
        }

        $mobile = app(OtpService::class)->sanitizeMobile($verified['mobile']);
        $user = User::where('mobile', $mobile)->first();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(false, __('labels.not_a_seller') ?? 'No seller account found for that number.', []);
        }

        return $user;
    }

    /**
     * @return array{user: ?User, channel: string}
     */
    protected function resolveIdentifier(string $identifier): array
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return ['user' => null, 'channel' => 'email'];
        }

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return ['user' => User::where('email', $identifier)->first(), 'channel' => 'email'];
        }

        $mobile = app(OtpService::class)->sanitizeMobile($identifier);

        return [
            'user' => $mobile !== '' ? User::where('mobile', $mobile)->first() : null,
            'channel' => 'mobile',
        ];
    }
}
