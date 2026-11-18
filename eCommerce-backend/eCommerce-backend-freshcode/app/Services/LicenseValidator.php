<?php

namespace App\Services;

use App\Types\Api\ApiResponseType;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class LicenseValidator
{
    protected Client $http;

    public function __construct(?Client $http = null)
    {
        $this->http = $http ?: new Client([
            'timeout' => (float) config('license.timeout', 5),
            'connect_timeout' => (float) config('license.connect_timeout', 5),
        ]);
    }

    public function validate(string $purchaseCode, string $domainUrl): array
    {
        $endpoint = config('license.endpoint', 'https://validator.infinitietech.com/home/validator');

        try {
            $response = $this->http->get($endpoint, [
                'query' => [
                    'purchase_code' => $purchaseCode,
                    'domain_url' => $domainUrl,
                ],
                'http_errors' => false,
            ]);
        } catch (Throwable $e) {
            Log::warning('License validator unavailable: '.$e->getMessage());

            return $this->unavailable();
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            Log::warning('License validator returned HTTP '.$response->getStatusCode());

            return $this->unavailable();
        }

        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::warning('License validator returned malformed JSON: '.$e->getMessage());

            return $this->unavailable();
        }

        if (! is_array($data) || ! array_key_exists('error', $data)) {
            return $this->unavailable();
        }

        if ((bool) $data['error']) {
            return $this->result(false, (string) ($data['message'] ?? 'License verification failed.'), $data, false);
        }

        if (trim((string) ($data['token'] ?? '')) === '') {
            return $this->unavailable();
        }

        return $this->result(true, (string) ($data['message'] ?? 'License verified successfully.'), $data, false);
    }

    public static function signature(string $purchaseCode, string $domainUrl, string $token): string
    {
        $key = config('app.key', 'app-key-missing');

        return hash_hmac('sha256', $purchaseCode.'|'.$domainUrl.'|'.$token, $key);
    }

    private function unavailable(): array
    {
        return $this->result(false, 'Unable to reach license server.', [], true);
    }

    private function result(bool $success, string $message, array $data, bool $unavailable): array
    {
        return ApiResponseType::toArray($success, $message, $data) + [
            'unavailable' => $unavailable,
        ];
    }
}
