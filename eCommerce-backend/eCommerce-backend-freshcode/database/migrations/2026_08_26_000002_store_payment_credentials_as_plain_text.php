<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KEYS = [
        'stripeSecretKey',
        'stripeWebhookSecretKey',
        'razorpaySecretKey',
        'razorpayWebhookSecret',
        'paystackSecretKey',
        'paystackWebhookSecret',
        'flutterwaveSecretKey',
        'flutterwaveEncryptionKey',
        'flutterwaveWebhookSecret',
        'xenditSecretKey',
        'xenditWebhookVerificationToken',
    ];

    public function up(): void
    {
        $values = $this->paymentValues();
        if ($values === null) {
            return;
        }

        foreach (self::KEYS as $key) {
            if (! isset($values[$key]) || ! is_string($values[$key]) || $values[$key] === '') {
                continue;
            }

            try {
                $values[$key] = Crypt::decryptString($values[$key]);
            } catch (DecryptException) {
            }
        }

        $this->save($values);
    }

    public function down(): void
    {
        $values = $this->paymentValues();
        if ($values === null) {
            return;
        }

        foreach (self::KEYS as $key) {
            if (! isset($values[$key]) || ! is_string($values[$key]) || $values[$key] === '') {
                continue;
            }

            try {
                Crypt::decryptString($values[$key]);
            } catch (DecryptException) {
                $values[$key] = Crypt::encryptString($values[$key]);
            }
        }

        $this->save($values);
    }

    private function paymentValues(): ?array
    {
        $raw = DB::table('settings')->where('variable', 'payment')->value('value');
        if (! is_string($raw)) {
            return null;
        }

        $values = json_decode($raw, true);

        return is_array($values) ? $values : null;
    }

    private function save(array $values): void
    {
        DB::table('settings')->where('variable', 'payment')->update([
            'value' => json_encode($values, JSON_THROW_ON_ERROR),
        ]);
    }
};
