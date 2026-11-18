<?php

namespace App\Services\Shipping;

use App\Services\SettingService;
use App\Services\Shipping\Exceptions\ShiprocketException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * HTTP client for the Shiprocket API: config + auth (which doubles as the
 * credentials check) plus the shipment lifecycle calls (serviceability, order
 * create, AWB, pickup, label/manifest, tracking, cancel, returns). A non-2xx
 * response raises a ShiprocketException carrying the decoded body.
 * See docs-internal/shiprocket-integration-plan.md.
 */
class ShiprocketClient
{
    private const BASE = 'https://apiv2.shiprocket.in/v1/external';
    private const TOKEN_CACHE_KEY = 'shiprocket_api_token';

    public function __construct(private readonly SettingService $settings)
    {
    }

    /** The saved `shipping` settings group (Shiprocket config). */
    public function config(): array
    {
        return $this->settings->shiprocketConfig();
    }

    public function isEnabled(): bool
    {
        return (bool) ($this->config()['shiprocketEnabled'] ?? false);
    }

    /**
     * Bearer token using the SAVED credentials, cached ~9 days (token lives ~10).
     * Returns null when creds are missing or login fails.
     */
    public function authToken(): ?string
    {
        if ($cached = Cache::get(self::TOKEN_CACHE_KEY)) {
            return $cached;
        }
        $cfg = $this->config();
        $token = $this->login(
            $cfg['shiprocketEmail'] ?? null,
            $this->decrypt($cfg['shiprocketPassword'] ?? null),
        );
        if ($token) {
            Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addDays(9));
        }
        return $token;
    }

    /**
     * Verify a set of credentials (the auth endpoint IS Shiprocket's credential
     * check). Falls back to the saved config when args are omitted. Never caches
     * — this is a test path.
     *
     * @return array{success:bool,message:string}
     */
    public function testConnection(?string $email = null, ?string $password = null): array
    {
        $cfg = $this->config();
        $email = $email ?: ($cfg['shiprocketEmail'] ?? null);
        $password = $password ?: $this->decrypt($cfg['shiprocketPassword'] ?? null);

        if (!$email || !$password) {
            return ['success' => false, 'message' => 'Enter the Shiprocket API-user email and password first.'];
        }

        try {
            return $this->login($email, $password)
                ? ['success' => true, 'message' => 'Connected to Shiprocket successfully.']
                : ['success' => false, 'message' => 'Invalid Shiprocket credentials.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Could not reach Shiprocket: ' . $e->getMessage()];
        }
    }

    public function serviceability(array $params): array
    {
        return $this->get('/courier/serviceability/', $params);
    }

    public function createOrderAdhoc(array $payload): array
    {
        return $this->post('/orders/create/adhoc', $payload);
    }

    public function updateOrderAdhoc(array $payload): array
    {
        return $this->post('/orders/update/adhoc', $payload);
    }

    public function orderShow(int|string $shiprocketOrderId): array
    {
        return $this->get('/orders/show/' . rawurlencode((string) $shiprocketOrderId));
    }

    public function assignAwb(int|string $shipmentId, bool $isReturn = false): array
    {
        $body = ['shipment_id' => $shipmentId];
        if ($isReturn) {
            $body['is_return'] = 1;
        }
        return $this->post('/courier/assign/awb', $body);
    }

    public function generatePickup(array|int|string $shipmentIds): array
    {
        return $this->post('/courier/generate/pickup', ['shipment_id' => $this->ids($shipmentIds)]);
    }

    public function generateLabel(array|int|string $shipmentIds): array
    {
        return $this->post('/courier/generate/label', ['shipment_id' => $this->ids($shipmentIds)]);
    }

    public function generateManifest(array|int|string $shipmentIds): array
    {
        return $this->post('/manifests/generate', ['shipment_id' => $this->ids($shipmentIds)]);
    }

    public function track(string $awb): array
    {
        return $this->get('/courier/track/awb/' . rawurlencode($awb));
    }

    public function trackBulk(array $awbs): array
    {
        return $this->post('/courier/track/awbs', ['awbs' => array_values($awbs)]);
    }

    public function printInvoice(array|int|string $orderIds): array
    {
        return $this->post('/orders/print/invoice', ['ids' => $this->ids($orderIds)]);
    }

    public function cancelShipment(array|string $awbs): array
    {
        return $this->post('/orders/cancel/shipment/awbs', ['awbs' => $this->ids($awbs)]);
    }

    public function cancelOrder(array|int|string $orderIds): array
    {
        return $this->post('/orders/cancel', ['ids' => $this->ids($orderIds)]);
    }

    public function createReturn(array $payload): array
    {
        return $this->post('/orders/create/return', $payload);
    }

    public function ndrAction(string $awb, string $action, string $comments): array
    {
        return $this->post('/ndr/' . rawurlencode($awb) . '/action', [
            'action'   => $action,
            'comments' => $comments,
        ]);
    }

    public function addPickup(array $payload): array
    {
        return $this->post('/settings/company/addpickup', $payload);
    }

    public function pickupLocations(): array
    {
        return $this->get('/settings/company/pickup');
    }

    public function walletBalance(): ?float
    {
        try {
            $res = $this->get('/account/details/wallet-balance');
        } catch (\Throwable) {
            return null;
        }

        $balance = $res['data']['balance_amount'] ?? $res['balance_amount'] ?? null;

        return is_numeric($balance) ? (float) $balance : null;
    }

    /** Raw login → token (no cache). */
    private function login(?string $email, ?string $password): ?string
    {
        if (!$email || !$password) {
            return null;
        }
        $res = Http::acceptJson()->timeout(20)->post(self::BASE . '/auth/login', [
            'email'    => $email,
            'password' => $password,
        ]);

        return $res->successful() ? $res->json('token') : null;
    }

    private function get(string $path, array $query = []): array
    {
        return $this->send('GET', $path, $query);
    }

    private function post(string $path, array $body = []): array
    {
        return $this->send('POST', $path, $body);
    }

    private function send(string $method, string $path, array $params, bool $retry = true): array
    {
        $token = $this->authToken();
        if (!$token) {
            throw new ShiprocketException('Shiprocket authentication failed — check the API credentials.');
        }

        $request = $this->baseRequest($token);
        $url = self::BASE . $path;
        $res = $method === 'GET' ? $request->get($url, $params) : $request->post($url, $params);

        if ($res->status() === 401 && $retry) {
            Cache::forget(self::TOKEN_CACHE_KEY);
            return $this->send($method, $path, $params, false);
        }

        if ($res->failed()) {
            throw new ShiprocketException($this->errorMessage($res), (array) $res->json(), $res->status());
        }

        return (array) $res->json();
    }

    private function baseRequest(string $token): PendingRequest
    {
        return Http::withToken($token)->acceptJson()->timeout(30);
    }

    private function errorMessage(Response $res): string
    {
        $body = $res->json();
        $message = is_array($body) ? ($body['message'] ?? null) : null;
        if (is_string($message) && $message !== '') {
            return $message;
        }
        if (is_array($body) && !empty($body)) {
            return (string) json_encode($body);
        }
        return 'Shiprocket request failed (HTTP ' . $res->status() . ').';
    }

    private function ids(array|int|string $ids): array
    {
        return is_array($ids) ? array_values($ids) : [$ids];
    }

    /** Credentials may be stored encrypted; tolerate legacy plaintext. */
    private function decrypt(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }
}
