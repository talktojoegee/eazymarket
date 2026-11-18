<?php

namespace App\Services;

use App\Enums\SettingTypeEnum;
use App\Enums\UserLoginTypeEnum;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserRegistered;
use App\Libs\libphonenumber\NumberParseException;
use App\Libs\libphonenumber\PhoneNumberUtil;
use App\Models\User;
use App\Models\UserOtp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function __construct(
        protected SmsService     $smsService,
        protected SettingService $settingService,
        protected WalletService  $walletService,
    )
    {
    }

    /**
     * Generate a cryptographically secure 6-digit OTP code.
     */
    private function generateOtpCode(): string
    {
        return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Canonical mobile normalisation used by every OTP/auth code path so the
     * value stored at send time and the value looked up at verify/login time
     * always match.
     *
     * Returns the NATIONAL number (digits only, WITHOUT the country calling
     * code) via libphonenumber. The country code is stored separately in
     * `users.country_code`, and a unique index on `users.mobile` guarantees one
     * account per number. No truncation — the full national number is kept.
     *
     * IMPORTANT: reliable national extraction needs a country-code source —
     * either the input already carries one (`+91…` / `0091…`) or the admin's
     * system calling code is configured (see normaliseMobileInput). Without
     * either, a bare number is split by a best-effort guess.
     */
    public function normalizeMobile(string $mobile): string
    {
        $input = trim($mobile);
        if ($input === '') {
            return '';
        }

        try {
            return PhoneNumberUtil::getInstance()
                ->parse($this->normaliseMobileInput($input))
                ->getNationalNumber();
        } catch (NumberParseException $e) {
            // Unparseable input — fall back to digits only (no truncation).
            return (string) preg_replace('/\D+/', '', $input);
        }
    }

    /**
     * @deprecated Use normalizeMobile(). Kept as a thin alias for backward
     * compatibility with existing callers/tests.
     */
    public function sanitizeMobile(string $mobile): string
    {
        return $this->normalizeMobile($mobile);
    }

    /**
     * Pre-process a raw mobile input so PhoneNumberUtil can parse it.
     * Falls back to the system-wide phone calling code when the input
     * lacks an international prefix (`+` or `00`).
     */
    private function normaliseMobileInput(string $mobile): string
    {
        $trimmed = trim($mobile);
        if ($trimmed === '') {
            return $trimmed;
        }

        if (str_starts_with($trimmed, '+') || str_starts_with($trimmed, '00')) {
            return $trimmed;
        }

        $systemConfig = $this->settingService
            ->getRawSetting(SettingTypeEnum::SYSTEM())?->value ?? [];
        $callingCode = preg_replace('/\D+/', '', (string)($systemConfig['countryCode'] ?? ''));

        if ($callingCode === '') {
            return $trimmed;
        }

        return '+' . $callingCode . preg_replace('/\D+/', '', $trimmed);
    }

    /**
     * Generate and store OTP for a mobile number
     */
    public function generateOtp(string $mobile): array
    {
        try {
            $mobile = $this->normalizeMobile($mobile);

            $otpCode = $this->generateOtpCode();
            $expiresAt = now()->addMinutes(10);

            $userOtp = UserOtp::updateOrCreate(
                ['mobile' => $mobile],
                [
                    'otp' => Hash::make($otpCode),
                    'expires_at' => $expiresAt,
                    'attempts' => 0,
                    'verified_at' => null
                ]
            );

            Log::info('OTP generated', [
                'user_otp_id' => $userOtp->id,
                'expires_at' => $userOtp->expires_at,
            ]);

            return [
                'success' => true,
                'otp_code' => $otpCode,
                'expires_at' => $expiresAt,
                'message' => 'OTP generated successfully'
            ];

        } catch (\Exception $e) {
            Log::error('OTP generation failed', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate OTP'
            ];
        }
    }

    /**
     * Generate OTP and send via SMS
     */
    public function sendOtp(string $mobile): array
    {
        try {
            $parsed = PhoneNumberUtil::getInstance()->parse(
                $this->normaliseMobileInput($mobile)
            );
        } catch (NumberParseException $e) {
            Log::warning('OTP parse failed', ['input' => $mobile, 'error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Invalid mobile number.'];
        }

        // Key the OTP by the national number (country code tracked separately).
        $storageMobile = $parsed->getNationalNumber();

        $otpResult = $this->generateOtp($storageMobile);
        if (!$otpResult['success']) {
            return $otpResult;
        }

        $authConfig = $this->settingService
            ->getRawSetting(SettingTypeEnum::AUTHENTICATION())?->value ?? [];

        if (empty($authConfig)) {

            Log::warning('Authentication settings not found, using defaults');
            return [
                'success' => false,
                'message' => 'Authentication settings not found, using defaults',
            ];
        }
        $template = $authConfig['customSmsTextFormatData']
            ?: 'Your OTP is: {otp}. Valid for {minutes} minutes.';

        $message = strtr($template, [
            '{otp}' => $otpResult['otp_code'],
            '{minutes}' => '10',
            '{brand}' => $this->settingService->getBrandName(),
        ]);

        $smsResult = $this->smsService->sendSms($parsed, $message);

        if (!$smsResult['success']) {
            Log::error('OTP SMS sending failed', [
                'mobile' => $parsed->format(),
                'error' => $smsResult['message'],
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send OTP: ' . $smsResult['message'],
            ];
        }

        return [
            'success' => true,
            'message' => 'OTP sent successfully',
            'expires_in' => 600,
        ];
    }

    /**
     * Verify OTP code for a mobile number
     */
    public function verifyOtp(string $mobile, string $otpCode): array
    {
        $mobile = $this->normalizeMobile($mobile);

        $userOtp = UserOtp::forMobile($mobile)
            ->active()
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$userOtp) {
            Log::warning('OTP verification failed - no active OTP');

            return [
                'success' => false,
                'message' => 'No active OTP found. Please request a new OTP.'
            ];
        }

        if ($userOtp->isExpired()) {
            return [
                'success' => false,
                'message' => 'OTP has expired. Please request a new OTP.'
            ];
        }

        if ($userOtp->maxAttemptsReached()) {
            return [
                'success' => false,
                'message' => 'Maximum verification attempts reached. Please request a new OTP.'
            ];
        }

        if (!Hash::check((string)$otpCode, (string)$userOtp->otp)) {
            $userOtp->incrementAttempts();
            $remainingAttempts = max(0, 3 - $userOtp->attempts);

            return [
                'success' => false,
                'message' => "Invalid OTP. {$remainingAttempts} attempt(s) remaining.",
                'remaining_attempts' => $remainingAttempts
            ];
        }

        $userOtp->markAsVerified();

        return [
            'success' => true,
            'message' => 'OTP verified successfully'
        ];
    }

    /**
     * Clean up expired OTPs (can be scheduled)
     */
    public function cleanExpiredOtps(): int
    {
        return UserOtp::where('expires_at', '<', now()->subDay())->delete();
    }

    /**
     * Anon-flow user resolution after a successful OTP verification.
     *
     * Branches:
     *   - Existing user (including soft-deleted → restored): `status=existing`
     *   - New user, insufficient registration data: `status=pending`
     *   - New user + full registration payload: `status=created`
     *
     * Fires UserLoggedIn / UserRegistered events, grants welcome wallet on
     * create, and leaves token minting to the controller so the response
     * envelope stays identical to the legacy shape.
     *
     * @param array{name?: ?string, email?: ?string, password?: ?string, country?: ?string, iso_2?: ?string, friends_code?: ?string} $data
     * @return array{status: string, user?: \App\Models\User, mobile?: string}
     */
    public function resolveMobileUser(string $mobile, array $data): array
    {
        $user = User::withTrashed()->where('mobile', $mobile)->first();
        if ($user && $user->trashed()) {
            if ($user->isPendingDeletion()) {
                $user->restore();
            } else {
                $user->anonymizeAndRelease();
                $user = null;
            }
        }

        if ($user) {
            if (empty($user->mobile_verified_at)) {
                $user->forceFill(['mobile_verified_at' => now()])->save();
            }
            event(new UserLoggedIn($user));

            return ['status' => 'existing', 'user' => $user];
        }

        if (empty($data['name']) || empty($data['password'])) {
            return ['status' => 'pending', 'mobile' => $mobile];
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'mobile' => $mobile,
            'iso_2' => $data['iso_2'] ?? null,
            'password' => Hash::make($data['password']),
            'country' => $data['country'] ?? null,
            'mobile_verified_at' => now(),
            'friends_code' => $data['friends_code'] ?? null,
            'logged_in_type' => UserLoginTypeEnum::PLATFORM(),
        ]);

        $this->grantWelcomeWallet($user);

        event(new UserRegistered($user));

        return ['status' => 'created', 'user' => $user];
    }

    /**
     * Credit the welcome wallet bonus if system settings enable one. Failures
     * are logged but swallowed — a broken wallet pipeline must never block
     * signup.
     */
    protected function grantWelcomeWallet(User $user): void
    {
        try {
            $systemSettings = $this->settingService
                ->getSettingByVariable(SettingTypeEnum::SYSTEM())
                ?->toArray(request())['value'] ?? [];
            $welcomeAmount = (float)($systemSettings['welcomeWalletBalanceAmount'] ?? 0);

            if ($welcomeAmount > 0) {
                $this->walletService->addBalance($user->id, [
                    'amount' => $welcomeAmount,
                    'payment_method' => 'system',
                    'description' => __('labels.welcome_wallet_bonus') ?? 'Welcome bonus added to wallet',
                ]);
            }
        } catch (\Throwable $th) {
            Log::error('Welcome wallet credit failed for user ' . $user->id . ': ' . $th->getMessage());
        }
    }
}
