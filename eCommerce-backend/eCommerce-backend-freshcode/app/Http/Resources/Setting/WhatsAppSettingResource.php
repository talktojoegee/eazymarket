<?php

namespace App\Http\Resources\Setting;

use App\Support\SettingSecrets;
use App\Traits\PanelAware;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhatsAppSettingResource extends JsonResource
{
    use PanelAware;

    public function toArray(Request $request): array
    {
        $raw = is_array($this->value) ? $this->value : [];
        if ($this->getPanel() !== 'admin') {
            return ['variable' => $this->variable, 'value' => []];
        }

        $value = [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'phoneNumberId' => $raw['phoneNumberId'] ?? '',
            'businessAccountId' => $raw['businessAccountId'] ?? '',
            'defaultLanguage' => $raw['defaultLanguage'] ?? 'en_US',
            'graphApiVersion' => $raw['graphApiVersion'] ?? 'v26.0',
            'templateMappings' => $raw['templateMappings'] ?? [],
        ];

        $value['accessToken'] = SettingSecrets::mask('whatsapp', [
            'accessToken' => $raw['accessToken'] ?? '',
        ])['accessToken'];
        $maskedSecrets = SettingSecrets::mask('whatsapp', [
            'appSecret' => $raw['appSecret'] ?? '',
            'webhookVerifyToken' => $raw['webhookVerifyToken'] ?? '',
        ]);
        $value['appSecret'] = $maskedSecrets['appSecret'];
        $value['webhookVerifyToken'] = $maskedSecrets['webhookVerifyToken'];

        return ['variable' => $this->variable, 'value' => $value];
    }
}
