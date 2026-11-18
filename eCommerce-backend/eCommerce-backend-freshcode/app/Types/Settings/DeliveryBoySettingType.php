<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class DeliveryBoySettingType implements SettingInterface
{
    use SettingTrait;

    public string $termsCondition = '';
    public string $privacyPolicy = '';

    public bool $deliveryBoyReferEarnStatus = false;
    public string $deliveryBoyReferEarnBonusReferral = '0';
    public string $deliveryBoyReferEarnBonusReferee = '0';

    protected static function getValidationRules(): array
    {
        return [
            'termsCondition' => 'nullable|string',
            'privacyPolicy' => 'nullable|string',
            'deliveryBoyReferEarnStatus' => ['nullable', 'boolean'],
            'deliveryBoyReferEarnBonusReferral' => ['nullable', 'numeric', 'min:0'],
            'deliveryBoyReferEarnBonusReferee' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
