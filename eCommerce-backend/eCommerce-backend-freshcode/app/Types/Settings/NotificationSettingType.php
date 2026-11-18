<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class NotificationSettingType implements SettingInterface
{
    use SettingTrait;

    public string $vapIdKey = '';

    public bool $enablePush = true;

    public bool $enableEmail = false;

    public bool $abandonedCartEnabled = true;

    public int $abandonedCartDelayMinutes = 1440;

    public int $abandonedCartRepeatMinutes = 180;

    protected static function getValidationRules(): array
    {
        return [
            'vapIdKey' => 'nullable',
            'enablePush' => 'boolean',
            'enableEmail' => 'boolean',
            'abandonedCartEnabled' => 'boolean',
            'abandonedCartDelayMinutes' => 'required|integer|min:1',
            'abandonedCartRepeatMinutes' => 'required|integer|min:1',
        ];
    }
}
