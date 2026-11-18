<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EmailOtpService;
use App\Services\OtpService;
use App\Services\PanelNavigationService;
use App\Services\PanelReauthenticationService;
use App\Traits\AuthTrait;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use App\Types\Settings\AuthenticationSettingType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OtpLoginController extends Controller
{
    use AuthTrait, PanelAware;

    /**
     * Show the OTP login screen.
     */
    public function showOtpLogin(): View
    {
        return view($this->panelView('auth.login-otp'), [
            'smsGateway' => AuthenticationSettingType::smsGateway(),
        ]);
    }

    /**
     * Send a login OTP. Channel is email when the account has one, else mobile.
     */
    public function sendLoginOtp(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string',
            'reauthentication' => 'nullable|boolean',
            'reauthentication_token' => 'nullable|string',
        ]);

        ['user' => $user, 'channel' => $channel] = $this->resolveIdentifier($request->input('identifier'));

        if ($request->boolean('reauthentication')) {
            $expectedUser = app(PanelReauthenticationService::class)
                ->user($request->input('reauthentication_token'), $this->getPanel());
            if (! $expectedUser || ! $user || $expectedUser->id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => __('labels.different_reauthentication_account'),
                    'data' => [],
                ], 403);
            }
        }

        if (! $user || ! $this->validateUserPanel($user)) {
            return ApiResponseType::sendJsonResponse(true, 'passwords.otp_sent', [
                'channel' => $channel ?: (str_contains($request->input('identifier'), '@') ? 'email' : 'mobile'),
            ]);
        }

        if ($channel === 'email') {
            $result = app(EmailOtpService::class)->sendToUser($user);

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

        if (! ($result['success'] ?? false)) {
            return ApiResponseType::sendJsonResponse(false, $result['message'] ?? 'passwords.throttled', []);
        }

        return ApiResponseType::sendJsonResponse(true, 'passwords.otp_sent', [
            'channel' => 'mobile',
            'gateway' => 'custom',
        ]);
    }

    /**
     * Verify the login OTP (email code, mobile OTP, or Firebase idToken) and
     * sign the user in.
     */
    public function verifyLoginOtp(Request $request): JsonResponse
    {
        if ($request->filled('idToken')) {
            return $this->verifyLoginFirebase($request);
        }

        $request->validate([
            'identifier' => 'required|string',
            'otp' => 'required|string|size:6',
            'reauthentication' => 'nullable|boolean',
            'reauthentication_token' => 'nullable|string',
        ]);

        ['user' => $user, 'channel' => $channel] = $this->resolveIdentifier($request->input('identifier'));

        if (! $user || ! $this->validateUserPanel($user)) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.identifier', []);
        }

        $verified = $channel === 'email'
            ? app(EmailOtpService::class)->verify($user->email, $request->input('otp'))
            : (app(OtpService::class)->verifyOtp($user->mobile, $request->input('otp'))['success'] ?? false);

        if (! $verified) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.otp_invalid', []);
        }

        return $this->loginAndRedirect($user, $request);
    }

    /**
     * Verify a Firebase phone idToken and sign the user in.
     */
    protected function verifyLoginFirebase(Request $request): JsonResponse
    {
        $request->validate([
            'idToken' => 'required|string',
            'reauthentication' => 'nullable|boolean',
            'reauthentication_token' => 'nullable|string',
        ]);

        $verified = $this->verifyFirebasePhoneToken($request->input('idToken'));

        if ($verified instanceof JsonResponse) {
            return $verified;
        }

        $mobile = app(OtpService::class)->sanitizeMobile($verified['mobile']);
        $user = User::where('mobile', $mobile)->first();

        if (! $user || ! $this->validateUserPanel($user)) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.identifier', []);
        }

        return $this->loginAndRedirect($user, $request);
    }

    /**
     * Resolve the account and the OTP channel from what the user typed: an
     * email address -> email code, anything else -> mobile OTP.
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

    /**
     * Establish the session and return the post-login redirect URL.
     */
    protected function loginAndRedirect(User $user, Request $request): JsonResponse
    {
        Auth::login($user);

        if ($response = $this->validatePanelReauthentication($request, $user, $this->getPanel())) {
            return $response;
        }

        $request->session()->regenerate();

        $reauthenticated = $request->boolean('reauthentication');
        $redirect = $reauthenticated
            ? null
            : redirect()->intended(
                app(PanelNavigationService::class)->landingUrl($user, $this->getPanel())
            )->getTargetUrl();

        return ApiResponseType::sendJsonResponse(true, 'passwords.login_verified', [
            'redirect' => $redirect,
            'reauthenticated' => $reauthenticated,
            'csrf_token' => csrf_token(),
        ]);
    }
}
