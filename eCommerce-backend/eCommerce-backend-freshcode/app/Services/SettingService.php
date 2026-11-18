<?php

namespace App\Services;

use App\Enums\SettingTypeEnum;
use App\Enums\UpdateTypeEnum;
use App\Http\Resources\Setting\AdvertisementSettingResource;
use App\Http\Resources\Setting\AppSettingResource;
use App\Http\Resources\Setting\AuthenticationSettingResource;
use App\Http\Resources\Setting\BroadcastDriverSettingResource;
use App\Http\Resources\Setting\DeliveryBoySettingResource;
use App\Http\Resources\Setting\EmailSettingResource;
use App\Http\Resources\Setting\HomeGeneralSettingResource;
use App\Http\Resources\Setting\NotificationSettingResource;
use App\Http\Resources\Setting\PaymentSettingResource;
use App\Http\Resources\Setting\PosSettingResource;
use App\Http\Resources\Setting\SellerSettingResource;
use App\Http\Resources\Setting\ShippingSettingResource;
use App\Http\Resources\Setting\StorageSettingResource;
use App\Http\Resources\Setting\SubscriptionSettingResource;
use App\Http\Resources\Setting\SystemSettingResource;
use App\Http\Resources\Setting\SystemUpdateSettingResource;
use App\Http\Resources\Setting\WatchAndBuySettingResource;
use App\Http\Resources\Setting\WhatsAppSettingResource;
use App\Http\Resources\Setting\WebSettingResource;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Setting;
use App\Support\SettingSecrets;
use App\Support\SupportDateTime;
use App\Types\Settings\BroadcastDriverSettingType;
use App\Types\Settings\WatchAndBuySettingType;
use App\Types\Settings\WhatsAppSettingType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ReflectionClass;

class SettingService
{
    private array $resolvedValues = [];

    private bool $cacheLoaded = false;

    /**
     * Get all settings with their appropriate resource transformations
     */
    public function getAllSettings(): Collection
    {
        return collect(SettingTypeEnum::values())
            ->map(fn (string $variable) => $this->getSettingByVariable($variable))
            ->filter()
            ->values();
    }

    /**
     * Get a specific setting by variable with resource transformation
     */
    public function getSettingByVariable(string $variable): ?JsonResource
    {
        if (! in_array($variable, SettingTypeEnum::values())) {
            return null;
        }

        $values = $this->getCachedSettingValues($variable);

        $setting = new Setting;
        $setting->variable = $variable;
        $setting->setAttribute('value', json_encode($values));

        return $this->transformSetting($setting);
    }

    /**
     * Build cache key for a given setting variable
     */
    private function cacheKey(string $variable): string
    {
        return 'settings:'.$variable;
    }

    /**
     * @return array Raw setting values (cached).
     */
    public function getSettingValues(string $variable): array
    {
        return $this->getCachedSettingValues($variable);
    }

    /**
     * Fetch setting values from cache or compute and store defaults if missing.
     */
    private function getCachedSettingValues(string $variable): array
    {
        $this->loadCachedValues();

        if (array_key_exists($variable, $this->resolvedValues)) {
            return $this->resolvedValues[$variable];
        }

        $existing = Setting::where('variable', $variable)->first();
        $values = $existing
            ? (is_array($existing->value) ? $existing->value : [])
            : ($this->getDefaultValuesFromType($variable) ?: $this->getDefaultValuesFromResource($variable));

        Cache::forever($this->cacheKey($variable), $values);

        return $this->resolvedValues[$variable] = $values;
    }

    private function loadCachedValues(): void
    {
        if ($this->cacheLoaded) {
            return;
        }

        $this->cacheLoaded = true;
        $variables = [...SettingTypeEnum::values(), 'support', 'support_timekeeping'];
        $keys = array_map($this->cacheKey(...), $variables);
        $cached = Cache::many($keys);

        foreach ($variables as $index => $variable) {
            $value = $cached[$keys[$index]] ?? null;
            if (is_array($value)) {
                $this->resolvedValues[$variable] = $value;
            }
        }
    }

    /**
     * Clear cache for a specific setting variable.
     */
    public function clearSettingCache(string $variable): void
    {
        unset($this->resolvedValues[$variable]);
        Cache::forget($this->cacheKey($variable));
    }

    /**
     * Get setting from database or create default if not exists
     */
    public function getOrCreateDefaultSetting(string $variable): Setting
    {
        $setting = Setting::where('variable', $variable)->first();

        if (! $setting) {
            $setting = $this->createDefaultSetting($variable);
        }

        return $setting;
    }

    /**
     * Transform setting using appropriate resource based on setting variable
     */
    public function transformSetting(Setting $setting): JsonResource
    {
        return match ($setting->variable) {
            'system' => new SystemSettingResource($setting),
            'web' => new WebSettingResource($setting),
            'app' => new AppSettingResource($setting),
            'email' => new EmailSettingResource($setting),
            'payment' => new PaymentSettingResource($setting),
            'storage' => new StorageSettingResource($setting),
            'notification' => new NotificationSettingResource($setting),
            'authentication' => new AuthenticationSettingResource($setting),
            'delivery_boy' => new DeliveryBoySettingResource($setting),
            'seller' => new SellerSettingResource($setting),
            'home_general_settings' => new HomeGeneralSettingResource($setting),
            'subscription' => new SubscriptionSettingResource($setting),
            'system_update_settings' => new SystemUpdateSettingResource($setting),
            'advertisement' => new AdvertisementSettingResource($setting),
            'pos_settings' => new PosSettingResource($setting),
            'shipping' => new ShippingSettingResource($setting),
            'watch_and_buy' => new WatchAndBuySettingResource($setting),
            'broadcast_driver' => new BroadcastDriverSettingResource($setting),
            'whatsapp' => new WhatsAppSettingResource($setting),
            default => throw new \InvalidArgumentException("Unsupported setting type: {$setting->variable}")
        };
    }

    /**
     * Create a default setting instance (not saved to DB)
     */
    private function createDefaultSetting(string $variable): Setting
    {
        $defaultValues = $this->getDefaultValuesFromType($variable);

        if (empty($defaultValues)) {
            $defaultValues = $this->getDefaultValuesFromResource($variable);
        }

        $setting = new Setting;
        $setting->variable = $variable;
        $setting->setAttribute('value', json_encode($defaultValues));

        return $setting;
    }

    /**
     * Get default values from setting type class using reflection
     */
    private function getDefaultValuesFromType(string $variable): array
    {
        $className = match ($variable) {
            'watch_and_buy' => WatchAndBuySettingType::class,
            'broadcast_driver' => BroadcastDriverSettingType::class,
            'whatsapp' => WhatsAppSettingType::class,
            default => 'App\\Types\\Settings\\'.ucfirst($variable).'SettingType',
        };

        if (! class_exists($className)) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($className);
            $instance = $reflection->newInstance();
            $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

            $defaultValues = [];
            foreach ($properties as $property) {
                $defaultValues[$property->getName()] = $property->getValue($instance);
            }

            return $defaultValues;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Extract default values from resource by creating a temporary setting with empty value
     * and then parsing the resource's toArray method to get the default values
     */
    private function getDefaultValuesFromResource(string $variable): array
    {
        try {
            $tempSetting = new Setting;
            $tempSetting->variable = $variable;
            $tempSetting->setAttribute('value', json_encode([]));

            $resource = $this->createResourceInstance($variable, $tempSetting);

            if (! $resource) {
                return [];
            }

            $request = new Request;
            $resourceArray = $resource->toArray($request);

            if (isset($resourceArray['value']) && is_array($resourceArray['value'])) {
                return $resourceArray['value'];
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create a resource instance based on variable
     */
    private function createResourceInstance(string $variable, Setting $setting): ?JsonResource
    {
        return match ($variable) {
            'system' => new SystemSettingResource($setting),
            'web' => new WebSettingResource($setting),
            'app' => new AppSettingResource($setting),
            'email' => new EmailSettingResource($setting),
            'payment' => new PaymentSettingResource($setting),
            'storage' => new StorageSettingResource($setting),
            'notification' => new NotificationSettingResource($setting),
            'authentication' => new AuthenticationSettingResource($setting),
            'delivery_boy' => new DeliveryBoySettingResource($setting),
            'seller' => new SellerSettingResource($setting),
            'subscription' => new SubscriptionSettingResource($setting),
            'home_general_settings' => new HomeGeneralSettingResource($setting),
            'system_update_settings' => new SystemUpdateSettingResource($setting),
            'advertisement' => new AdvertisementSettingResource($setting),
            'pos_settings' => new PosSettingResource($setting),
            'shipping' => new ShippingSettingResource($setting),
            'watch_and_buy' => new WatchAndBuySettingResource($setting),
            'broadcast_driver' => new BroadcastDriverSettingResource($setting),
            'whatsapp' => new WhatsAppSettingResource($setting),
            default => null
        };
    }

    /**
     * Save or update a setting
     */
    public function saveSetting(string $variable, array $values): Setting
    {
        $existing = $this->getSettingValues($variable);

        if ($variable === 'system') {
            SupportDateTime::preserveStorageTimezone($existing['systemTimezone'] ?? config('app.timezone', 'UTC'));
        }

        foreach (SettingSecrets::keysFor($variable) as $secretKey) {
            if (! array_key_exists($secretKey, $values)) {
                continue;
            }

            if ($values[$secretKey] === SettingSecrets::MASK || $values[$secretKey] === '') {
                $values[$secretKey] = $existing[$secretKey] ?? $values[$secretKey];
            }
        }

        $setting = Setting::updateOrCreate(
            ['variable' => $variable],
            ['value' => json_encode(SettingSecrets::conceal($variable, $values))]
        );

        Cache::forever($this->cacheKey($variable), $values);
        $this->resolvedValues[$variable] = $values;

        if ($variable === 'system' && app()->has(CurrencyService::class)) {
            app(CurrencyService::class)->clearCache();
        }

        if ($variable === 'system' && ! empty($values['currency'])) {
            $this->syncBaseCurrency((string) $values['currency']);
        }

        return $setting;
    }

    /**
     * Mark the given ISO code as the single base currency.
     */
    private function syncBaseCurrency(string $code): void
    {
        $code = strtoupper(trim($code));

        if ($code === '' || ! Currency::where('code', $code)->exists()) {
            return;
        }

        DB::transaction(function () use ($code) {
            Currency::where('code', '!=', $code)
                ->where('is_base', true)
                ->update(['is_base' => false]);

            Currency::where('code', $code)->update(['is_base' => true]);
        });
    }

    /**
     * Check if a setting variable is valid
     */
    public function isValidSettingVariable(string $variable): bool
    {
        return in_array($variable, SettingTypeEnum::values());
    }

    /**
     * Get raw setting value without resource transformation
     */
    public function getRawSetting(string $variable): ?Setting
    {
        return Setting::where('variable', $variable)->first();
    }

    /**
     * Convenience accessor for the shipping-provider config (currently the
     * Shiprocket integration). Returns the `shipping` settings group values.
     */
    public function shiprocketConfig(): array
    {
        $resource = $this->getSettingByVariable('shipping');
        return $resource ? ($resource->toArray(request())['value'] ?? []) : [];
    }

    /**
     * Brand / app name used in OTP messages, emails, and similar customer-facing
     * copy. Reads `appName` from the system setting; falls back to the framework
     * `config('app.name')` when the setting hasn't been filled in yet.
     */
    public function getBrandName(): string
    {
        $systemConfig = $this->getRawSetting(SettingTypeEnum::SYSTEM())?->value ?? [];
        $appName = trim((string) ($systemConfig['appName'] ?? ''));

        return $appName !== '' ? $appName : (string) config('app.name');
    }

    /**
     * Resolve the currency symbol (from the currency), plus the phonecode and
     * default ISO2 taken from the explicitly chosen default country. A currency
     * can map to many countries, so the ISO2/phonecode must come from the admin's
     * selected country rather than being guessed from the currency.
     *
     * @return array{currencySymbol: string, countryCode: string, defaultISO2: string}
     */
    public function resolveCurrencyMeta(?string $currency, ?string $iso2): array
    {
        $symbol = ! empty($currency)
            ? (string) (Currency::find($currency)?->symbol ?? '')
            : '';

        if ($symbol === '' && ! empty($currency)) {
            $symbol = (string) (Country::where('currency', $currency)->value('currency_symbol') ?? '');
        }

        $country = ! empty($iso2)
            ? Country::where('iso2', $iso2)->first()
            : null;

        return [
            'currencySymbol' => $symbol,
            'countryCode' => (string) ($country?->phonecode ?? ''),
            'defaultISO2' => (string) ($country?->iso2 ?? ''),
        ];
    }

    public static function getUpdateType($currentVersion, $latestVersion, bool $forceUpdate): ?string
    {
        if (version_compare($currentVersion, $latestVersion, '<')) {
            return $forceUpdate
                ? UpdateTypeEnum::FORCE_UPDATE()
                : UpdateTypeEnum::SOFT_UPDATE();
        }

        return null;
    }
}
