<?php

namespace App\Http\Resources\Setting;

use App\Services\FirebaseService;
use App\Traits\PanelAware;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticationSettingResource extends JsonResource
{
    use PanelAware;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $smsGateway = $this->value['smsGateway'] ?? '';

        if (! in_array($smsGateway, ['custom', 'firebase'], true)) {
            if (! empty($this->value['customSms'])) {
                $smsGateway = 'custom';
            } elseif (! empty($this->value['firebase'])) {
                $smsGateway = 'firebase';
            } else {
                $smsGateway = '';
            }
        }

        $data = [
            'variable' => $this->variable,
            'value' => [
                'identifierMode' => $this->value['identifierMode'] ?? 'both',
                'customSms' => $this->value['customSms'] ?? false,
                'customSmsUrl' => $this->value['customSmsUrl'] ?? '',
                'customSmsMethod' => $this->value['customSmsMethod'] ?? '',
                'googleRecaptchaSiteKey' => $this->value['googleRecaptchaSiteKey'] ?? '',
                'firebase' => $this->value['firebase'] ?? false,
                'fireBaseApiKey' => $this->value['fireBaseApiKey'] ?? '',
                'fireBaseAuthDomain' => $this->value['fireBaseAuthDomain'] ?? '',
                'fireBaseDatabaseURL' => $this->value['fireBaseDatabaseURL'] ?? '',
                'fireBaseProjectId' => $this->value['fireBaseProjectId'] ?? '',
                'fireBaseStorageBucket' => $this->value['fireBaseStorageBucket'] ?? '',
                'fireBaseMessagingSenderId' => $this->value['fireBaseMessagingSenderId'] ?? '',
                'fireBaseAppId' => $this->value['fireBaseAppId'] ?? '',
                'fireBaseMeasurementId' => $this->value['fireBaseMeasurementId'] ?? '',
                'googleLogin' => $this->value['googleLogin'] ?? false,
                'appleLogin' => $this->value['appleLogin'] ?? false,
                'smsGateway' => $smsGateway,
            ],
        ];

        if ($this->getPanel() === 'admin') {
            $credentials = app(FirebaseService::class)->credentialMetadata();
            $data['value'] = array_merge($data['value'], [
                'customSmsMobileFormat' => $this->value['customSmsMobileFormat'] ?? '',
                'customSmsTextFormatData' => $this->value['customSmsTextFormatData'] ?? '',
                'customSmsHeaderKey' => $this->value['customSmsHeaderKey'] ?? [],
                'customSmsHeaderValue' => $this->value['customSmsHeaderValue'] ?? [],
                'customSmsParamsKey' => $this->value['customSmsParamsKey'] ?? [],
                'customSmsParamsValue' => $this->value['customSmsParamsValue'] ?? [],
                'customSmsBodyKey' => $this->value['customSmsBodyKey'] ?? [],
                'customSmsBodyValue' => $this->value['customSmsBodyValue'] ?? [],
                'serviceAccountConfigured' => $credentials['configured'],
                'serviceAccountProjectId' => $credentials['project_id'],
                'serviceAccountClientEmail' => $credentials['client_email'],
                'serviceAccountUpdatedAt' => $credentials['updated_at'],
            ]);
        }

        return $data;
    }
}
