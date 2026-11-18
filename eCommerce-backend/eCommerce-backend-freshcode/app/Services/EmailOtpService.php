<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\LoginOtpNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class EmailOtpService
{
    public const TTL_SECONDS = 600;
    private const MAX_ATTEMPTS = 3;

    private function cacheKey(string $email, string $purpose = 'login'): string
    {
        return 'login_otp:' . $purpose . ':' . strtolower(trim($email));
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function sendToUser(User $user, string $purpose = 'login'): array
    {
        if (empty($user->email)) {
            return ['success' => false, 'message' => 'passwords.user'];
        }

        $code = $this->generateCode();

        try {
            $user->notify(new LoginOtpNotification($code, $purpose));
        } catch (\Throwable $e) {
            Log::error('Login OTP email failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'labels.something_went_wrong'];
        }

        Cache::put($this->cacheKey($user->email, $purpose), [
            'otp' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ], self::TTL_SECONDS);

        return ['success' => true, 'message' => 'passwords.otp_sent_email'];
    }

    public function verify(string $email, string $code, string $purpose = 'login'): bool
    {
        $key = $this->cacheKey($email, $purpose);
        $data = Cache::get($key);

        if (!$data) {
            return false;
        }

        if (($data['attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
            return false;
        }

        if (!Hash::check($code, $data['otp'])) {
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;
            $remaining = isset($data['expires_at'])
                ? (int) $data['expires_at'] - now()->getTimestamp()
                : self::TTL_SECONDS;

            if ($remaining <= 0) {
                Cache::forget($key);

                return false;
            }

            Cache::put($key, $data, $remaining);

            return false;
        }

        Cache::forget($key);
        return true;
    }
}
