<?php

namespace App\Types\Settings;

use App\Enums\WhatsAppTemplateEventEnum;
use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

class WhatsAppSettingType implements SettingInterface
{
    use SettingTrait;

    public bool $enabled = false;

    public string $phoneNumberId = '';

    public string $businessAccountId = '';

    public string $accessToken = '';

    public string $appSecret = '';

    public string $webhookVerifyToken = '';

    public string $defaultLanguage = 'en_US';

    public string $graphApiVersion = 'v26.0';

    public array $templateMappings = [];

    protected static function getValidationRules(): array
    {
        return [
            'enabled' => 'nullable|boolean',
            'phoneNumberId' => 'required_if:enabled,1|nullable|string|max:191',
            'businessAccountId' => 'required_if:enabled,1|nullable|string|max:191',
            'accessToken' => 'required_if:enabled,1|nullable|string|max:4096',
            'appSecret' => 'nullable|string|max:512',
            'webhookVerifyToken' => 'nullable|string|max:255',
            'defaultLanguage' => ['required_if:enabled,1', 'nullable', 'string', 'max:35', 'regex:/^[a-z]{2,3}(?:_[A-Z]{2,4})?$/'],
            'graphApiVersion' => ['required', 'string', 'regex:/^v[0-9]{1,3}\.[0-9]+$/'],
            'templateMappings' => 'nullable|array|max:500',
            'templateMappings.*.templateName' => 'nullable|string|max:512|regex:/^[a-z0-9_]*$/',
            'templateMappings.*.language' => ['nullable', 'string', 'max:35', 'regex:/^[a-z]{2,3}(?:_[A-Z]{2,4})?$/'],
            'templateMappings.*.key' => 'nullable|string|max:191',
            'templateMappings.*.event' => 'nullable|in:'.implode(',', array_map(fn (WhatsAppTemplateEventEnum $event) => $event->value, WhatsAppTemplateEventEnum::cases())),
            'templateMappings.*.format' => 'nullable|in:positional,named',
            'templateMappings.*.parameters' => 'nullable|array|max:30',
            'templateMappings.*.parameters.*' => 'string|max:80',
            'templateMappings.*.active' => 'nullable|boolean',
            'templateMappings.*.variables' => 'nullable|array|max:30',
            'templateMappings.*.variables.*' => 'nullable|string|max:80',
            'templateMappings.*.status' => 'nullable|string|max:30',
            'templateMappings.*.quality' => 'nullable|string|max:30',
            'templateMappings.*.category' => 'nullable|string|max:30',
            'templateMappings.*.rejectedReason' => 'nullable|string|max:2000',
            'templateMappings.*.syncedAt' => 'nullable|string|max:40',
        ];
    }
}
