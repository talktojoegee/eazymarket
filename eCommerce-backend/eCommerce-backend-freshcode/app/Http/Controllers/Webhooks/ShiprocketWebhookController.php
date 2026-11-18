<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Services\SettingService;
use App\Services\ShipmentService;
use App\Services\Shipping\Providers\ShiprocketProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Public endpoint that receives Shiprocket tracking webhooks: validate the
 * shared secret, resolve the shipment by AWB, map the carrier status onto
 * ShipmentStatusEnum and apply it (idempotent, terminal-guarded). Always
 * 200-acks so Shiprocket doesn't retry-storm on an unknown/ignored event.
 */
class ShiprocketWebhookController extends Controller
{
    public const string LAST_RECEIVED_CACHE_KEY = 'shiprocket_webhook_last_received_at';

    public function handle(
        Request $request,
        SettingService $settings,
        ShipmentService $shipmentService,
        ShiprocketProvider $provider,
    ): JsonResponse {
        $expected = $settings->shiprocketConfig()['shiprocketWebhookSecret'] ?? null;

        if (empty($expected)) {
            Log::error('Shiprocket webhook rejected: webhook secret not configured');

            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!hash_equals((string) $expected, (string) $request->header('x-api-key'))) {
            Log::warning('Shiprocket webhook rejected: bad x-api-key');
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        Cache::put(self::LAST_RECEIVED_CACHE_KEY, now()->toDateTimeString(), now()->addDays(30));

        $payload = $request->all();
        Log::info('Shiprocket webhook received', ['payload' => $payload]);

        $awb = $payload['awb']
            ?? $payload['awb_code']
            ?? $payload['data']['awb']
            ?? $payload['data']['awb_code']
            ?? null;
        $status = $provider->mapWebhookStatus($payload);

        if ($status) {
            $shipment = $this->resolveShipment($payload, $awb);

            if ($shipment) {
                try {
                    if ($awb && !empty($shipment->awb_number) && $awb !== (string) $shipment->awb_number) {
                        $shipmentService->refreshCarrierAssignment($shipment);
                        $shipment->refresh();
                    }
                    $shipmentService->applyCarrierStatus($shipment, $status, __('labels.shiprocket_webhook_note'));
                } catch (\Throwable $e) {
                    Log::error('Shiprocket webhook apply failed', ['awb' => $awb, 'error' => $e->getMessage()]);
                }
            }
        }

        return response()->json(['success' => true]);
    }

    private function resolveShipment(array $payload, ?string $awb): ?Shipment
    {
        $base = fn () => Shipment::query()
            ->where('provider_code', (string) FulfillmentMethodEnum::SHIPROCKET());

        if ($awb) {
            $shipment = $base()->where('awb_number', $awb)->first();
            if ($shipment) {
                return $shipment;
            }
        }

        $reference = $payload['order_id'] ?? null;
        if (is_string($reference) && $reference !== '') {
            $shipment = $base()->where('metadata->shiprocket_reference', $reference)->first();
            if ($shipment) {
                return $shipment;
            }
        }

        $srOrderId = $payload['sr_order_id'] ?? null;
        if ($srOrderId !== null && $srOrderId !== '') {
            return $base()
                ->where(function ($q) use ($srOrderId) {
                    $q->where('metadata->shiprocket_order_id', (int) $srOrderId)
                        ->orWhere('metadata->shiprocket_order_id', (string) $srOrderId);
                })
                ->first();
        }

        return null;
    }
}
