<?php

namespace App\Http\Controllers;

use App\Enums\SettingTypeEnum;
use App\Services\EmailOtpService;
use App\Services\OtpService;
use App\Services\SettingService;
use App\Traits\AuthTrait;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use App\Types\Settings\AuthenticationSettingType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class PasswordResetController extends Controller
{
    use AuthTrait, PanelAware;

    protected mixed $webSettings;
    public function __construct()
    {
        $webSettings = app(SettingService::class);
        $resource = $webSettings->getSettingByVariable(SettingTypeEnum::WEB());
        $this->webSettings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];
    }

    /**
     * Show the forgot password form
     */
    public function showForgotPasswordForm(): View
    {
        $system = app(SettingService::class)->getRawSetting(SettingTypeEnum::SYSTEM())?->value ?? [];

        return view($this->panelView('auth.forgot-password'), [
            'recoveryChannels' => AuthenticationSettingType::recoveryChannels(),
            'smsGateway' => AuthenticationSettingType::smsGateway(),
            'defaultCountry' => $system['defaultISO2'] ?? null,
        ]);
    }

    /**
     * Panel-prefixed route name (admin.* / seller.* / bare for customer).
     */
    protected function panelRouteName(string $suffix): string
    {
        return match ($this->getPanel()) {
            'admin' => 'admin.' . $suffix,
            'seller' => 'seller.' . $suffix,
            default => $suffix,
        };
    }

    /**
     * Send a mobile OTP for password recovery. Custom SMS is sent server-side;
     * Firebase is verified on the client, so we only confirm the account exists
     * and let the client drive the send.
     */
    public function sendResetOtp(Request $request): JsonResponse
    {
        $request->validate(['identifier' => 'required|string']);

        ['user' => $user, 'channel' => $channel] = $this->resolveIdentifier($request->input('identifier'));

        if (!$user || !$this->validateUserPanel($user)) {
            return ApiResponseType::sendJsonResponse(
                false,
                $user ? $this->getInvalidUserMessage() : 'passwords.identifier',
                []
            );
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
            return ApiResponseType::sendJsonResponse(true, 'passwords.otp_sent', ['channel' => 'mobile', 'gateway' => 'firebase']);
        }

        $result = app(OtpService::class)->sendOtp($user->mobile);

        if (!($result['success'] ?? false)) {
            return ApiResponseType::sendJsonResponse(false, $result['message'] ?? 'passwords.throttled', []);
        }

        return ApiResponseType::sendJsonResponse(true, 'passwords.otp_sent', ['channel' => 'mobile', 'gateway' => 'custom']);
    }

    /**
     * Verify the identifier (email code, mobile OTP, or Firebase idToken). On
     * success we mint Laravel's standard password-reset token and return the
     * reset-page URL, so the actual password reset uses the token flow unchanged.
     */
    public function verifyResetOtp(Request $request): JsonResponse
    {
        if ($request->filled('idToken')) {
            return $this->verifyResetFirebase($request);
        }

        $request->validate([
            'identifier' => 'required|string',
            'otp' => 'required|string|size:6',
        ]);

        ['user' => $user, 'channel' => $channel] = $this->resolveIdentifier($request->input('identifier'));

        if (!$user || !$this->validateUserPanel($user)) {
            return ApiResponseType::sendJsonResponse(
                false,
                $user ? $this->getInvalidUserMessage() : 'passwords.identifier',
                []
            );
        }

        $verified = $channel === 'email'
            ? app(EmailOtpService::class)->verify($user->email, $request->input('otp'), 'reset')
            : (app(OtpService::class)->verifyOtp($user->mobile, $request->input('otp'))['success'] ?? false);

        if (!$verified) {
            return ApiResponseType::sendJsonResponse(false, 'passwords.otp_invalid', []);
        }

        return $this->issueResetRedirect($user);
    }

    /**
     * Verify a Firebase phone idToken and issue the reset redirect.
     */
    protected function verifyResetFirebase(Request $request): JsonResponse
    {
        $request->validate(['idToken' => 'required|string']);

        $verified = $this->verifyFirebasePhoneToken($request->input('idToken'));

        if ($verified instanceof JsonResponse) {
            return $verified;
        }

        $mobile = app(OtpService::class)->sanitizeMobile($verified['mobile']);
        $user = User::where('mobile', $mobile)->first();

        if (!$user || !$this->validateUserPanel($user)) {
            return ApiResponseType::sendJsonResponse(
                false,
                $user ? $this->getInvalidUserMessage() : 'passwords.mobile',
                []
            );
        }

        return $this->issueResetRedirect($user);
    }

    /**
     * Mint the standard password-reset token and return the reset-page URL.
     */
    protected function issueResetRedirect(User $user): JsonResponse
    {
        $token = app('auth.password.broker')->createToken($user);

        $url = route($this->panelRouteName('password.reset'), [
            'token' => $token,
            'email' => $user->email,
        ]);

        return ApiResponseType::sendJsonResponse(true, 'passwords.verified', ['redirect' => $url]);
    }

    /**
     * Resolve the account and OTP channel from the identifier: an email address
     * -> email code, anything else -> mobile OTP.
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
     * Handle forgot password request
     */
    public function sendResetLinkEmail(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            if ($request->expectsJson()) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    __('passwords.user'),
                    []
                );
            }
            return back()->withErrors(['email' => __('passwords.user')]);
        }

        if (!$this->validateUserPanel($user)) {
            if ($request->expectsJson()) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    $this->getInvalidUserMessage(),
                    []
                );
            }
            return back()->withErrors(['email' => $this->getInvalidUserMessage()]);
        }

        $token = app('auth.password.broker')->createToken($user);

        $notificationClass = $this->getNotificationClass();
        $user->notify(new $notificationClass($token));

        if ($request->expectsJson()) {
            return ApiResponseType::sendJsonResponse(
                true,
                __('passwords.sent'),
                []
            );
        }

        return back()->with('status', __('passwords.sent'));
    }

    /**
     * Show the reset password form
     */
    public function showResetPasswordForm(Request $request, string $token): View
    {
        return view($this->panelView('auth.reset-password'), [
            'token' => $token,
            'email' => $request->email
        ]);
    }

    /**
     * Handle password reset
     */
    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            if ($this->getLoginRoute() === 'login') {
                return redirect($this->webSettings['customerWebUrl'] ?? config('app.frontendUrl'));
            }
            return redirect()->route($this->getLoginRoute())->with('status', __($status));
        }

        return back()->withErrors(['email' => [__($status)]]);
    }
}
