<?php

namespace App\Types\Settings;

use App\Enums\SettingTypeEnum;
use App\Enums\SmsMobileFormatEnum;
use App\Interfaces\SettingInterface;
use App\Services\SettingService;
use App\Traits\SettingTrait;
use Illuminate\Validation\Rules\Enum;

class AuthenticationSettingType implements SettingInterface
{
    use SettingTrait;

    public string $identifierMode = 'both';

    public bool $customSms = false;

    public string $customSmsUrl = '';

    public string $customSmsMethod = '';

    public string $googleRecaptchaSiteKey = '';

    public string $customSmsMobileFormat = '';

    public string $customSmsTextFormatData = '';

    public array $customSmsHeaderKey = [];

    public array $customSmsHeaderValue = [];

    public array $customSmsParamsKey = [];

    public array $customSmsParamsValue = [];

    public array $customSmsBodyKey = [];

    public array $customSmsBodyValue = [];

    public bool $firebase = false;

    public string $fireBaseApiKey = '';

    public string $fireBaseAuthDomain = '';

    public string $fireBaseDatabaseURL = '';

    public string $fireBaseProjectId = '';

    public string $fireBaseStorageBucket = '';

    public string $fireBaseMessagingSenderId = '';

    public string $fireBaseAppId = '';

    public string $fireBaseMeasurementId = '';

    public string $serviceAccountFile = '';

    public bool $googleLogin = false;

    public bool $appleLogin = false;

    public string $smsGateway = '';

    protected static function getValidationRules(): array
    {
        return [
            'identifierMode' => ['nullable', 'in:email_primary,mobile_primary,both'],
            'customSms' => 'nullable|boolean',
            'customSmsUrl' => 'required_if:customSms,true|nullable|url',
            'customSmsMethod' => 'required_if:customSms,true|nullable|in:GET,POST,PUT,PATCH',
            'customSmsMobileFormat' => ['nullable', new Enum(SmsMobileFormatEnum::class)],
            'customSmsTextFormatData' => 'nullable|string',
            'customSmsHeaderKey' => 'nullable|array',
            'customSmsHeaderValue' => 'nullable|array',
            'customSmsParamsKey' => 'nullable|array',
            'customSmsParamsValue' => 'nullable|array',
            'customSmsBodyKey' => 'nullable|array',
            'customSmsBodyValue' => 'nullable|array',
            'firebase' => 'nullable|boolean',
            'fireBaseApiKey' => 'required_if:firebase,true|nullable|string',
            'fireBaseAuthDomain' => 'required_if:firebase,true|nullable|string',
            'fireBaseDatabaseURL' => 'nullable|url',
            'fireBaseProjectId' => 'required_if:firebase,true|nullable|string',
            'fireBaseStorageBucket' => 'nullable|string',
            'fireBaseMessagingSenderId' => 'required_if:firebase,true|nullable|string',
            'fireBaseAppId' => 'required_if:firebase,true|nullable|string',
            'fireBaseMeasurementId' => 'nullable|string',
            'serviceAccountFile' => 'nullable|string',
            'googleRecaptchaSiteKey' => 'nullable|string',
            'googleLogin' => 'boolean',
            'appleLogin' => 'boolean',
            'smsGateway' => 'nullable|string|in:custom,firebase',
        ];
    }

    /**
     * Custom validation to ensure SMS gateway priority is respected
     */
    public static function validateMutuallyExclusiveSettings(array $data): array
    {
        $errors = [];

        $pairs = [
            ['customSmsHeaderKey', 'customSmsHeaderValue'],
            ['customSmsParamsKey', 'customSmsParamsValue'],
            ['customSmsBodyKey', 'customSmsBodyValue'],
        ];

        foreach ($pairs as [$keyField, $valueField]) {
            $keys = is_array($data[$keyField] ?? null) ? $data[$keyField] : [];
            $values = is_array($data[$valueField] ?? null) ? $data[$valueField] : [];

            if (count($keys) !== count($values)) {
                $errors[$valueField] = __('labels.sms_gateway_pair_count_mismatch');
            }
        }

        return $errors;
    }

    /**
     * Get SMS gateway based on enabled services with priority logic
     * Custom SMS has priority over Firebase when both are enabled
     */
    public static function getSmsGateway(array $data): string
    {
        $customSmsEnabled = isset($data['customSms']) && filter_var($data['customSms'], FILTER_VALIDATE_BOOLEAN);
        $firebaseEnabled = isset($data['firebase']) && filter_var($data['firebase'], FILTER_VALIDATE_BOOLEAN);

        if ($customSmsEnabled) {
            return 'custom';
        } elseif ($firebaseEnabled) {
            return 'firebase';
        }

        return '';
    }

    public static function currentMode(): string
    {
        try {
            $value = app(SettingService::class)
                ->getRawSetting(SettingTypeEnum::AUTHENTICATION())?->value ?? [];
        } catch (\Throwable $e) {
            $value = [];
        }

        $mode = $value['identifierMode'] ?? 'both';

        return in_array($mode, ['email_primary', 'mobile_primary', 'both'], true) ? $mode : 'both';
    }

    public static function recoveryChannels(): array
    {
        return match (self::currentMode()) {
            'mobile_primary' => ['mobile'],
            'email_primary' => ['email'],
            default => ['email', 'mobile'],
        };
    }

    public static function isEmailRequired(): bool
    {
        return in_array(self::currentMode(), ['email_primary', 'both'], true);
    }

    public static function isMobileRequired(): bool
    {
        return in_array(self::currentMode(), ['mobile_primary', 'both'], true);
    }

    public static function smsGateway(): string
    {
        try {
            $value = app(SettingService::class)
                ->getRawSetting(SettingTypeEnum::AUTHENTICATION())?->value ?? [];
        } catch (\Throwable $e) {
            $value = [];
        }

        $selected = $value['smsGateway'] ?? '';
        if (in_array($selected, ['custom', 'firebase'], true)) {
            return $selected;
        }

        return self::getSmsGateway($value);
    }

    protected static function getValidationMessages(): array
    {
        return [
            'customSms.boolean' => 'The custom SMS option must be true or false.',

            'customSmsUrl.required_if' => 'The SMS provider URL is required when Custom SMS is enabled.',
            'customSmsUrl.url' => 'The SMS provider URL must be a valid URL (including http:// or https://).',

            'customSmsMethod.required_if' => 'The HTTP method is required when Custom SMS is enabled.',
            'customSmsMethod.in' => 'The SMS request method must be one of GET, POST, PUT, or PATCH.',

            'customSmsMobileFormat.enum' => 'The selected mobile number format is invalid.',

            'customSmsTextFormatData.string' => 'The message format must be a valid string.',

            'customSmsHeaderKey.array' => 'HTTP header keys must be an array.',
            'customSmsHeaderValue.array' => 'HTTP header values must be an array.',

            'customSmsParamsKey.array' => 'Query parameter keys must be an array.',
            'customSmsParamsValue.array' => 'Query parameter values must be an array.',

            'customSmsBodyKey.array' => 'Request body keys must be an array.',
            'customSmsBodyValue.array' => 'Request body values must be an array.',

            'firebase.boolean' => 'The Firebase option must be true or false.',

            'fireBaseApiKey.required_if' => 'Firebase API Key is required when Firebase is enabled.',
            'fireBaseAuthDomain.required_if' => 'Firebase Auth Domain is required when Firebase is enabled.',
            'fireBaseDatabaseURL.required_if' => 'Firebase Database URL is required when Firebase is enabled.',
            'fireBaseDatabaseURL.url' => 'Firebase Database URL must be a valid URL.',
            'fireBaseProjectId.required_if' => 'Firebase Project ID is required when Firebase is enabled.',
            'fireBaseStorageBucket.required_if' => 'Firebase Storage Bucket is required when Firebase is enabled.',
            'fireBaseMessagingSenderId.required_if' => 'Firebase Messaging Sender ID is required when Firebase is enabled.',
            'fireBaseAppId.required_if' => 'Firebase App ID is required when Firebase is enabled.',
            'fireBaseMeasurementId.required_if' => 'Firebase Measurement ID is required when Firebase is enabled.',

            'googleRecaptchaSiteKey.string' => 'The Google reCAPTCHA site key must be a valid string.',

            'appleLogin.boolean' => 'Apple Login must be enabled or disabled (true/false).',
            'googleLogin.boolean' => 'Google Login must be enabled or disabled (true/false).',

            'smsGateway.in' => 'The SMS Gateway must be either "custom" or "firebase".',
            'identifierMode.in' => 'The account identifier mode must be email primary, mobile primary, or both.',
        ];
    }
}
