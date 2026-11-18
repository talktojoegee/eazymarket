<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class WebSettingType implements SettingInterface
{
    use SettingTrait;

    public string $siteName = '';
    public string $siteCopyright = '';
    public string $customerWebUrl = '';
    public string $googleFontFamily = '';
    public string $adminPanelFontFamily = '';

    public string $supportNumber = "";
    public string $supportEmail = "";

    public string $address = '';
    public string $shortDescription = '';
    public string $siteHeaderLogo = '';
    public string $siteHeaderDarkLogo = '';
    public string $siteFooterLogo = '';
    public string $siteFavicon = '';
    public string $headerScript = '';
    public string $footerScript = '';
    public string $googleMapKey = '';
    public string $mapIframe = '';
    public bool $appDownloadSection = false;
    public string $appSectionTitle = '';
    public string $appSectionTagline = '';
    public string $appSectionPlaystoreLink = '';
    public string $appSectionAppstoreLink = '';
    public string $appSectionShortDescription = '';
    public string $facebookLink = '';
    public string $instagramLink = '';
    public string $xLink = '';
    public string $youtubeLink = '';
    public string $shippingFeatureSection = '';
    public string $shippingFeatureSectionTitle = '';
    public string $shippingFeatureSectionDescription = '';
    public string $returnFeatureSection = '';
    public string $returnFeatureSectionTitle = '';
    public string $returnFeatureSectionDescription = '';
    public string $safetySecurityFeatureSection = '';
    public string $safetySecurityFeatureSectionTitle = '';
    public string $safetySecurityFeatureSectionDescription = '';
    public string $supportFeatureSection = '';
    public string $supportFeatureSectionTitle = '';
    public string $supportFeatureSectionDescription = '';
    public string $metaKeywords = '';
    public string $metaDescription = '';
    public string $alternateSiteName = '';
    public string $businessLegalName = '';
    public string $defaultSeoImage = '';
    public string $googleSiteVerification = '';
    public string $bingSiteVerification = '';
    public bool $merchantFeedEnabled = false;
    public string $merchantTargetCountry = '';
    public array $merchantTargetCountries = [];
    public string $merchantContentLanguage = 'en';
    public bool $merchantAdultContent = false;
    public string $returnPolicyCountry = '';
    public string $returnPolicyCategory = '';
    public ?int $returnPolicyDays = null;
    public string $returnPolicyMethod = '';
    public string $returnFees = '';
    public ?float $returnShippingFee = null;

    public string $returnRefundPolicy = '';
    public string $shippingPolicy = '';
    public string $privacyPolicy = '';
    public string $termsCondition = '';
    public string $aboutUs = '';
    public string $pwaName = '';
    public string $pwaDescription = '';
    public string $pwaLogo192x192 = '';
    public string $pwaLogo512x512 = '';
    public string $pwaLogo144x144 = '';

    protected static function getValidationRules(): array
    {
        return [
            'siteName' => 'required|string|max:255',
            'customerWebUrl' => 'nullable|url',
            'googleFontFamily' => ['nullable', 'string', 'max:80', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} -]{0,79}$/u'],
            'adminPanelFontFamily' => ['nullable', 'string', 'max:80', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} -]{0,79}$/u'],
            'siteCopyright' => 'required|string|max:255',
            'supportNumber' => 'required|string|max:20',
            'supportEmail' => 'required|email|max:255',
            'address' => 'required|string|max:255',
            'shortDescription' => 'required|string|max:500',
            'siteHeaderLogo' => 'required|image|mimes:png,webp',
            'siteHeaderDarkLogo' => 'required|image|mimes:png,webp',
            'siteFooterLogo' => 'required|image|mimes:png,webp',
            'siteFavicon' => 'required|mimes:png,jpg,jpeg,webp,ico',
            'headerScript' => 'nullable|string',
            'footerScript' => 'nullable|string',
            'googleMapKey' => 'nullable|string|max:255',
            'mapIframe' => 'nullable|string',
            'appDownloadSection' => 'nullable|boolean',
            'appSectionTitle' => 'nullable|string|max:255',
            'appSectionTagline' => 'nullable|string|max:255',
            'appSectionPlaystoreLink' => 'nullable|string|max:255',
            'appSectionAppstoreLink' => 'nullable|string|max:255',
            'appSectionShortDescription' => 'nullable|string|max:500',
            'facebookLink' => 'nullable|string|max:255',
            'instagramLink' => 'nullable|string|max:255',
            'xLink' => 'nullable|string|max:255',
            'youtubeLink' => 'nullable|string|max:255',
            'shippingFeatureSection' => 'nullable|string|max:255',
            'shippingFeatureSectionTitle' => 'nullable|string|max:255',
            'shippingFeatureSectionDescription' => 'nullable|string|max:500',
            'returnFeatureSection' => 'nullable|string|max:255',
            'returnFeatureSectionTitle' => 'nullable|string|max:255',
            'returnFeatureSectionDescription' => 'nullable|string|max:500',
            'safetySecurityFeatureSection' => 'nullable|string|max:255',
            'safetySecurityFeatureSectionTitle' => 'nullable|string|max:255',
            'safetySecurityFeatureSectionDescription' => 'nullable|string|max:500',
            'supportFeatureSection' => 'nullable|string|max:255',
            'supportFeatureSectionTitle' => 'nullable|string|max:255',
            'supportFeatureSectionDescription' => 'nullable|string|max:500',
            'metaKeywords' => 'nullable|string|max:255',
            'metaDescription' => 'nullable|string|max:500',
            'alternateSiteName' => 'nullable|string|max:255',
            'businessLegalName' => 'nullable|string|max:255',
            'defaultSeoImage' => 'nullable|url|max:2048',
            'googleSiteVerification' => 'nullable|string|max:255',
            'bingSiteVerification' => 'nullable|string|max:255',
            'merchantFeedEnabled' => 'nullable|boolean',
            'merchantTargetCountry' => 'nullable|string|size:2',
            'merchantTargetCountries' => 'nullable|array|max:100',
            'merchantTargetCountries.*' => 'string|size:2|distinct',
            'merchantContentLanguage' => 'nullable|string|max:10',
            'merchantAdultContent' => 'nullable|boolean',
            'returnPolicyCountry' => 'nullable|string|size:2',
            'returnPolicyCategory' => 'nullable|in:finite,unlimited,not_permitted',
            'returnPolicyDays' => 'nullable|integer|min:1|max:365',
            'returnPolicyMethod' => 'nullable|in:mail,in_store,kiosk',
            'returnFees' => 'nullable|in:free,customer_responsibility,fixed',
            'returnShippingFee' => 'nullable|numeric|min:0',
            'returnRefundPolicy' => 'nullable|string',
            'shippingPolicy' => 'nullable|string',
            'privacyPolicy' => 'nullable|string',
            'termsCondition' => 'nullable|string',
            'aboutUs' => 'nullable|string',
            'pwaName' => 'required|string|max:255',
            'pwaDescription' => 'required|string|max:500',
            'pwaLogo192x192' => 'nullable|image|mimes:png,jpeg,webp|max:2048',
            'pwaLogo512x512' => 'nullable|image|mimes:png,jpeg,webp|max:2048',
            'pwaLogo144x144' => 'nullable|image|mimes:png,jpeg,webp|max:2048',
        ];
    }
}
