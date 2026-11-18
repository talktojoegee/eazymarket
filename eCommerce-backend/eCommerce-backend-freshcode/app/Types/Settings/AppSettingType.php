<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class AppSettingType implements SettingInterface
{
    use SettingTrait;
    public string $customerAppstoreLink = '';
    public string $customerPlaystoreLink = '';
    public string $customerAppScheme = '';
    public string $customerMapKey = '';
    public string $customerAppFontFamily = '';
    public string $sellerAppstoreLink = '';
    public string $sellerPlaystoreLink = '';
    public string $sellerAppScheme = '';
    public string $sellerMapKey = '';
    public string $sellerAppFontFamily = '';
    protected static function getValidationRules(): array
    {
        return [
            'customerAppstoreLink' => 'nullable|url',
            'customerPlaystoreLink' => 'nullable|url',
            'customerAppScheme' => ['nullable', 'regex:/^[a-zA-Z][a-zA-Z0-9.+-]*$/'],
            'customerMapKey' => 'nullable|string|max:255',
            'customerAppFontFamily' => ['nullable', 'string', 'max:80', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} -]{0,79}$/u'],
            'sellerAppstoreLink' => 'nullable|url',
            'sellerPlaystoreLink' => 'nullable|url',
            'sellerAppScheme' => ['nullable', 'regex:/^[a-zA-Z][a-zA-Z0-9.+-]*$/'],
            'sellerMapKey' => 'nullable|string|max:255',
            'sellerAppFontFamily' => ['nullable', 'string', 'max:80', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} -]{0,79}$/u'],
        ];
    }
}
