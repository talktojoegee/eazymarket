<?php

namespace App\Http\Resources\Setting;

use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Traits\PanelAware;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Crypt;

class ShippingSettingResource extends JsonResource
{
    use PanelAware;

    /**
     * Transform the resource into an array.
     *
     * Only non-sensitive fields are exposed publicly (customer/seller apps).
     * Credentials + webhook secret are returned ONLY in the admin panel — the
     * customer must never see our Shiprocket critical data.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'variable' => $this->variable,
            'value' => [
                'manualEnabled' => (bool) ($this->value['manualEnabled'] ?? true),
                'manualTitle' => !empty($this->value['manualTitle']) ? $this->value['manualTitle'] :'Manual Shipping',
                'manual_value' => FulfillmentMethodEnum::MANUAL(),
                'manualDispatchSlaDays' => (int) ($this->value['manualDispatchSlaDays'] ?? 0),
                'shiprocketEnabled' => (bool) ($this->value['shiprocketEnabled'] ?? false),
                'shiprocketTitle' => !empty($this->value['shiprocketTitle']) ? $this->value['shiprocketTitle'] :'Shiprocket Ship',
                'shiprocket_value' => FulfillmentMethodEnum::SHIPROCKET(),
                'shiprocketRateMode' => !empty($this->value['shiprocketRateMode']) ? $this->value['shiprocketRateMode'] :'carrier_calculated',
                'shiprocketFlatRate' => (float) ($this->value['shiprocketFlatRate'] ?? 0),
                'shiprocketFreeAbove' => (float) ($this->value['shiprocketFreeAbove'] ?? 0),
            ],
        ];

        // Admin-only: credentials, channel, webhook secret, parcel defaults +
        // wallet alert.
        if ($this->getPanel() === 'admin') {
            $data['value'] = array_merge($data['value'], [
                'shiprocketEmail' => $this->value['shiprocketEmail'] ?? '',
                'shiprocketPassword' => $this->revealPassword($this->value['shiprocketPassword'] ?? ''),
                'shiprocketChannelId' => $this->value['shiprocketChannelId'] ?? '',
                'shiprocketWebhookSecret' => $this->value['shiprocketWebhookSecret'] ?? '',
                'shiprocketDefaultWeight' => (float) ($this->value['shiprocketDefaultWeight'] ?? 0.5),
                'shiprocketDefaultLength' => (float) ($this->value['shiprocketDefaultLength'] ?? 10),
                'shiprocketDefaultBreadth' => (float) ($this->value['shiprocketDefaultBreadth'] ?? 10),
                'shiprocketDefaultHeight' => (float) ($this->value['shiprocketDefaultHeight'] ?? 10),
                'shiprocketWalletAlert' => (float) ($this->value['shiprocketWalletAlert'] ?? 0),
            ]);
        }

        return $data;
    }

    private function revealPassword(mixed $stored): string
    {
        if (! is_string($stored) || $stored === '') {
            return '';
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return $stored;
        }
    }
}
