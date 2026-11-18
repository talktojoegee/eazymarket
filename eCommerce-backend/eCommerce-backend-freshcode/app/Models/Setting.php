<?php

namespace App\Models;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\SettingTypeEnum;
use App\Services\SettingService;
use App\Support\SettingSecrets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Setting extends Model
{
    protected $primaryKey = 'variable';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['variable', 'value'];

    public function getValueAttribute($value)
    {
        $decoded = json_decode($value, true);

        if (! is_array($decoded)) {
            return $decoded;
        }

        return SettingSecrets::reveal((string) ($this->attributes['variable'] ?? ''), $decoded);
    }

    public static function systemSettings()
    {
        $settingService = app(SettingService::class);
        $resource = $settingService->getSettingByVariable('system');

        return $resource ? ($resource->toArray(request())['value'] ?? []) : [];
    }

    public static function isDemoModeEnabled(): bool
    {
        try {
            $settings = self::systemSettings();

            return (bool) ($settings['demoMode'] ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function isSubscriptionEnabled(): bool
    {
        try {
            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Returns the current application version.
     * Priority: config('app.version') > latest applied SystemUpdate > '0.0.0'
     */
    public static function getCurrentVersion(): string
    {
        return once(function (): string {
            $lastApplied = SystemUpdate::where('status', 'applied')->orderByDesc('id')->first();
            if ($lastApplied && is_string($lastApplied->version) && $lastApplied->version !== '') {
                return $lastApplied->version;
            }
            $fromConfig = config('app.version');
            if (is_string($fromConfig) && $fromConfig !== '') {
                return $fromConfig;
            }

            return '0.0.0';
        });
    }

    /**
     * Returns an array of enabled payment gateway keys (matching PaymentTypeEnum values).
     *
     * @return string[]
     */
    public static function getEnabledPaymentGateway($onlyGateway = false): array
    {
        try {
            $settingService = app(SettingService::class);
            $resource = $settingService->getSettingByVariable(SettingTypeEnum::PAYMENT());
            $paymentSettings = $resource ? ($resource->toArray(request())['value'] ?? []) : [];

            $gateways = [
                PaymentTypeEnum::STRIPE(),
                PaymentTypeEnum::RAZORPAY(),
                PaymentTypeEnum::PAYSTACK(),
                PaymentTypeEnum::FLUTTERWAVE(),
                PaymentTypeEnum::XENDIT(),
                PaymentTypeEnum::MERCADO_PAGO(),
            ];

            if (! $onlyGateway) {
                $gateways[] = PaymentTypeEnum::COD();
                $gateways[] = PaymentTypeEnum::WALLET();
            }

            return array_values(array_filter($gateways, fn ($gateway) => ! empty($paymentSettings[$gateway])));
        } catch (\Throwable $e) {
            Log::error('Failed to load enabled payment gateways: '.$e->getMessage());

            return [];
        }
    }

    public static function posWalkinUserId(): ?int
    {
        $values = app(SettingService::class)->getSettingValues('pos_settings');

        return $values['walkin_user_id'] ?? null;
    }

    public static function currencySymbol(): string
    {
        $setting = self::where('variable', 'system')->first();
        $val = $setting?->value;

        return is_array($val) ? ($val['currency_symbol'] ?? '') : '';
    }
}
