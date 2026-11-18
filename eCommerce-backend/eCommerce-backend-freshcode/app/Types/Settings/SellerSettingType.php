<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class SellerSettingType implements SettingInterface
{
    use SettingTrait;

    public string $termsCondition = '';
    public string $privacyPolicy = '';
    public string $sellerPanelFontFamily = '';

    protected static function getValidationRules(): array
    {
        return [
            'termsCondition' => 'nullable|string',
            'privacyPolicy' => 'nullable|string',
            'sellerPanelFontFamily' => ['nullable', 'string', 'max:80', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} -]{0,79}$/u'],
        ];
    }
}
