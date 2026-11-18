<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\OtpService;
use App\Traits\AuthTrait;
use App\Types\Api\ApiResponseType;
use App\Types\Settings\AuthenticationSettingType;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

#[Group('Password Reset')]
class PasswordResetApiController extends Controller
{
    use AuthTrait;

    /**
     * Send a password-reset OTP. Channel follows the identifier entered: an
     * email address -> email code, anything else -> mobile OTP (custom SMS is
     * sent server-side; Firebase is verified on the client).
     */
    #[BodyParameter('identifier', description: 'email address or mobile number.', type: 'string')]
    public function sendForgotOtp(Request $request): JsonResponse
    {
        $request->validate(['identifier' => 'required|string']);

        ['user' => $user, 'channel' => $channel] = $this->resolveIdentifier($request->input('identifier'));

        if (!$user) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.identifier', []);
        }

        if ($channel === 'email') {
            $result = app(EmailOtpService::class)->sendToUser($user, 'reset');

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

        return ApiResponseType::sendJsonResponse(true, 'passwords.otp_sent', [
            'channel' => 'mobile',
            'gateway' => 'custom',
        ]);
    }

    /**
     * Verify the OTP (email code, mobile OTP, or Firebase idToken) and return a
     * short-lived reset token the app posts back to resetPassword().
     */
    #[BodyParameter('identifier', description: 'email address or mobile number.', type: 'string')]
    #[BodyParameter('idToken', description: 'if using firebase', type: 'string')]
    #[BodyParameter('otp', description: 'Otp if using email or custom sms', required: false, type: 'string')]
    public function verifyForgotOtp(Request $request): JsonResponse
    {
        if ($request->filled('idToken')) {
            return $this->verifyForgotFirebase($request);
        }

        $request->validate([
            'identifier' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        ['user' => $user, 'channel' => $channel] = $this->resolveIdentifier($request->input('identifier'));

        if (!$user) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.identifier', []);
        }

        $verified = $channel === 'email'
            ? app(EmailOtpService::class)->verify($user->email, $request->input('otp'), 'reset')
            : (app(OtpService::class)->verifyOtp($user->mobile, $request->input('otp'))['success'] ?? false);

        if (!$verified) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.otp_invalid', []);
        }

        return $this->issueResetToken($user, $request->input('identifier'));
    }

    /**
     * Set a new password using the reset token issued after OTP verification.
     * Mirrors the web resetPassword flow but returns JSON for the app.
     */
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

    /**
     * Verify a Firebase phone idToken and issue the reset token.
     */
    protected function verifyForgotFirebase(Request $request): JsonResponse
    {
        $request->validate(['idToken' => 'required|string']);

        $verified = $this->verifyFirebasePhoneToken($request->input('idToken'));

        if ($verified instanceof JsonResponse) {
            return $verified;
        }

        $mobile = app(OtpService::class)->sanitizeMobile($verified['mobile']);
        $user = User::where('mobile', $mobile)->first();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.identifier', []);
        }

        return $this->issueResetToken($user, $mobile);
    }

    /**
     * Mint the broker reset token and return it to the app.
     */
    protected function issueResetToken(User $user, string $identifier): JsonResponse
    {
        $token = Password::broker()->createToken($user);

        return ApiResponseType::sendJsonResponse(true, 'passwords.verified', [
            'token' => $token,
            'identifier' => $identifier,
        ]);
    }

    /**
     * Resolve the account and OTP channel from the identifier: an email
     * address -> email, anything else -> mobile.
     *
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
