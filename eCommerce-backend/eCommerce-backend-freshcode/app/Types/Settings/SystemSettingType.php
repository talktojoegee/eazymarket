<?php

namespace App\Types\Settings;

use App\Enums\ReferEarnMethodEnum;
use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SystemSettingType implements SettingInterface
{
    use SettingTrait;

    public const DEFAULT_WALLET_POLICY = '<p>Wallet funds can be used for eligible purchases on the platform. Recharges are credited after payment confirmation, are non-transferable, and remain subject to the platform terms.</p>';

    public const DEFAULT_WALLET_RECHARGE_PRESET_AMOUNTS = [100, 200, 500, 1000];

    public const DEFAULT_PRODUCT_APPROVAL_CRITICAL_FIELDS = [
        'category_id',
        'brand_id',
        'title',
        'type',
        'short_description',
        'description',
        'indicator',
        'variants',
        'media',
        'variant_images',
    ];

    public string $appName = '';

    public string $sellerSupportNumber = '';

    public string $sellerSupportEmail = '';

    public string $systemTimezone = '';

    public string $copyrightDetails = '';

    public string $logo = '';

    public string $darkLogo = '';

    public string $favicon = '';

    public string $companyAddress = '';

    public string $adminSignature = '';

    public bool $enableThirdPartyStoreSync = false;

    public bool $Shopify = false;

    public bool $Woocommerce = false;

    public bool $etsy = false;

    public string $checkoutType = 'multi_store';

    public float $minimumCartAmount = 0.0;

    public float $maximumItemsAllowedInCart = 0.0;

    public int $orderAutoArchiveDays = 10;

    public string $orderNumberPrefix = 'ORD-';

    public bool $autoSettleEnabled = false;

    public int $settlementClearingDays = 0;

    public string $lowStockLimit = '';

    public string $maximumDistanceToNearestStore = '';

    public bool $enableWallet = false;

    public float $welcomeWalletBalanceAmount = 0;

    public string $walletPolicy = self::DEFAULT_WALLET_POLICY;

    public array $walletRechargePresetAmounts = self::DEFAULT_WALLET_RECHARGE_PRESET_AMOUNTS;

    public bool $sellerAppMaintenanceMode = false;

    public string $sellerAppMaintenanceMessage = '';

    public bool $webMaintenanceMode = false;

    public string $webMaintenanceMessage = '';

    public bool $demoMode = false;

    public string $adminDemoModeMessage = '';

    public string $sellerDemoModeMessage = '';

    public string $customerDemoModeMessage = '';

    public string $customerLocationDemoModeMessage = '';

    public string $deliveryBoyDemoModeMessage = '';

    public bool $referEarnStatus = false;

    public string $referEarnMethodUser = '';

    public string $referEarnBonusUser = '';

    public string $referEarnMaximumBonusAmountUser = '';

    public string $referEarnMethodReferral = '';

    public string $referEarnBonusReferral = '';

    public string $referEarnMaximumBonusAmountReferral = '';

    public string $referEarnMinimumOrderAmount = '';

    public string $referEarnNumberOfTimesBonus = '';

    public string $currency = 'USD';

    public string $currencySymbol = '$';

    public string $countryCode = '';

    public string $defaultISO2 = '';

    public array $productApprovalCriticalFields = self::DEFAULT_PRODUCT_APPROVAL_CRITICAL_FIELDS;

    /**
     * Get Laravel validation rules for the properties
     *
     * @return array<string, array<string>>
     */
    protected static function getValidationRules(): array
    {
        return [
            'appName' => ['required', 'max:100'],
            'sellerSupportEmail' => ['nullable', 'email', 'max:255'],
            'sellerSupportNumber' => ['nullable', 'regex:/^\+?[1-9]\d{1,14}$/'],
            'systemTimezone' => ['required'],
            'copyrightDetails' => ['required', 'max:255'],
            'logo' => ['required', 'image', 'mimes:png,webp'],
            'darkLogo' => ['nullable', 'image', 'mimes:png,webp'],
            'favicon' => ['required', 'image', 'mimes:png,jpg,jpeg,webp'],
            'companyAddress' => ['nullable', 'string', 'max:500'],
            'adminSignature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp'],
            'checkoutType' => ['required', 'in:multi_store,single_store'],
            'minimumCartAmount' => ['required', 'integer', 'min:0'],
            'maximumItemsAllowedInCart' => ['required', 'numeric', 'min:1'],
            'orderAutoArchiveDays' => ['required', 'integer', 'min:1', 'max:365'],
            'orderNumberPrefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]*$/'],
            'autoSettleEnabled' => ['nullable', 'boolean'],
            'settlementClearingDays' => ['nullable', 'integer', 'min:0', 'max:365'],
            'lowStockLimit' => ['required', 'numeric', 'min:0'],
            'maximumDistanceToNearestStore' => ['nullable', 'numeric', 'min:0'],
            'enableWallet' => ['nullable', 'boolean'],
            'welcomeWalletBalanceAmount' => ['required_if:enableWallet,true', 'numeric', 'min:0'],
            'walletPolicy' => ['required', 'string', 'max:20000'],
            'walletRechargePresetAmounts' => ['required', 'array', 'min:1', 'max:4'],
            'walletRechargePresetAmounts.*' => ['required', 'numeric', 'min:0.01', 'distinct'],
            'sellerAppMaintenanceMode' => ['nullable', 'boolean'],
            'sellerAppMaintenanceMessage' => ['required_if:sellerAppMaintenanceMode,true', 'max:255'],
            'webMaintenanceMode' => ['nullable', 'boolean'],
            'webMaintenanceMessage' => ['required_if:webMaintenanceMode,true', 'max:255'],
            'demoMode' => ['nullable', 'boolean'],
            'adminDemoModeMessage' => ['required_if:demoMode,true', 'max:255'],
            'sellerDemoModeMessage' => ['required_if:demoMode,true', 'max:255'],
            'customerDemoModeMessage' => ['required_if:demoMode,true', 'max:255'],
            'customerLocationDemoModeMessage' => ['required_if:demoMode,true', 'max:255'],
            'deliveryBoyDemoModeMessage' => ['required_if:demoMode,true', 'max:255'],
            'referEarnStatus' => ['nullable', 'boolean'],
            'referEarnMethodUser' => ['required_if:referEarnStatus,on', new Enum(ReferEarnMethodEnum::class)],
            'referEarnBonusUser' => ['required_if:referEarnStatus,on', 'numeric', 'min:0'],
            'referEarnMaximumBonusAmountUser' => ['required_if:referEarnStatus,on', 'nullable', 'numeric', 'min:0'],
            'referEarnMethodReferral' => ['required_if:referEarnStatus,on', new Enum(ReferEarnMethodEnum::class)],
            'referEarnBonusReferral' => ['required_if:referEarnStatus,on', 'numeric', 'min:0'],
            'referEarnMaximumBonusAmountReferral' => ['required_if:referEarnStatus,on', 'nullable', 'numeric', 'min:0'],
            'referEarnMinimumOrderAmount' => ['required_if:referEarnStatus,on', 'nullable', 'numeric', 'min:0'],
            'referEarnNumberOfTimesBonus' => ['required_if:referEarnStatus,on', 'nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'exists:countries,currency'],
            'currencySymbol' => ['nullable', 'string'],
            'countryCode' => ['nullable', 'string', 'max:10'],
            'defaultISO2' => ['required', 'string', 'exists:countries,iso2'],
            'productApprovalCriticalFields' => ['nullable', 'array', 'max:10'],
            'productApprovalCriticalFields.*' => ['string', 'distinct', Rule::in(self::DEFAULT_PRODUCT_APPROVAL_CRITICAL_FIELDS)],
        ];
    }
}
