<?php

namespace App\Services\Shipping\Providers;

use App\Enums\GuardNameEnum;
use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Models\OrderItemReturn;
use App\Models\SellerOrder;
use App\Models\ServiceabilityCache;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\ShiprocketWalletLow;
use App\Services\Fx\FxRateService;
use App\Services\Shipping\Contracts\ShipmentProvider;
use App\Services\Shipping\Exceptions\ShiprocketException;
use App\Services\Shipping\ShipmentMoney;
use App\Services\Shipping\ShiprocketClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ShiprocketProvider implements ShipmentProvider
{
    private ?string $baseCurrencyCode = null;

    public function __construct(private readonly ShiprocketClient $client)
    {
    }

    public function code(): string
    {
        return (string) FulfillmentMethodEnum::SHIPROCKET();
    }

    public function allowedTransitions(): array
    {
        $S = fn ($c) => (string) $c;

        return [
            $S(ShipmentStatusEnum::PENDING()) => [
                $S(ShipmentStatusEnum::CANCELLED()),
            ],
            $S(ShipmentStatusEnum::LABEL_PRINTED()) => [
                $S(ShipmentStatusEnum::CONFIRMED()),
                $S(ShipmentStatusEnum::IN_TRANSIT()),
                $S(ShipmentStatusEnum::CANCELLED()),
            ],
            $S(ShipmentStatusEnum::CONFIRMED()) => [
                $S(ShipmentStatusEnum::IN_TRANSIT()),
                $S(ShipmentStatusEnum::OUT_FOR_DELIVERY()),
                $S(ShipmentStatusEnum::CANCELLED()),
                $S(ShipmentStatusEnum::RTO_INITIATED()),
            ],
            $S(ShipmentStatusEnum::IN_TRANSIT()) => [
                $S(ShipmentStatusEnum::OUT_FOR_DELIVERY()),
                $S(ShipmentStatusEnum::DELIVERED()),
                $S(ShipmentStatusEnum::ATTEMPTED_DELIVERY()),
                $S(ShipmentStatusEnum::FAILURE()),
                $S(ShipmentStatusEnum::RTO_INITIATED()),
                $S(ShipmentStatusEnum::LOST()),
            ],
            $S(ShipmentStatusEnum::OUT_FOR_DELIVERY()) => [
                $S(ShipmentStatusEnum::DELIVERED()),
                $S(ShipmentStatusEnum::ATTEMPTED_DELIVERY()),
                $S(ShipmentStatusEnum::FAILURE()),
                $S(ShipmentStatusEnum::RTO_INITIATED()),
            ],
            $S(ShipmentStatusEnum::ATTEMPTED_DELIVERY()) => [
                $S(ShipmentStatusEnum::OUT_FOR_DELIVERY()),
                $S(ShipmentStatusEnum::IN_TRANSIT()),
                $S(ShipmentStatusEnum::DELIVERED()),
                $S(ShipmentStatusEnum::RTO_INITIATED()),
                $S(ShipmentStatusEnum::FAILURE()),
            ],
            $S(ShipmentStatusEnum::FAILURE()) => [
                $S(ShipmentStatusEnum::IN_TRANSIT()),
                $S(ShipmentStatusEnum::RTO_INITIATED()),
                $S(ShipmentStatusEnum::CANCELLED()),
            ],
            $S(ShipmentStatusEnum::RTO_INITIATED()) => [
                $S(ShipmentStatusEnum::RTO_DELIVERED()),
                $S(ShipmentStatusEnum::LOST()),
            ],
            $S(ShipmentStatusEnum::DELIVERED()) => [
                $S(ShipmentStatusEnum::RETURNED()),
            ],
        ];
    }

    public function buildShipmentAttributes(SellerOrder $sellerOrder, array $data): array
    {
        $location = $sellerOrder->storeLocation;
        if (!$location || !$location->isShiprocketRegistered()) {
            throw new ShiprocketException(__('labels.shiprocket_location_not_registered'));
        }

        $this->assertBookableAddress($sellerOrder);

        $cfg = $this->client->config();

        return [
            'length_cm'  => (int) round((float) ($data['length_cm'] ?? $cfg['shiprocketDefaultLength'] ?? 10)),
            'breadth_cm' => (int) round((float) ($data['breadth_cm'] ?? $cfg['shiprocketDefaultBreadth'] ?? 10)),
            'height_cm'  => (int) round((float) ($data['height_cm'] ?? $cfg['shiprocketDefaultHeight'] ?? 10)),
            'weight_kg'  => (float) ($data['weight_kg'] ?? $cfg['shiprocketDefaultWeight'] ?? 0.5),
            'status'     => (string) ShipmentStatusEnum::PENDING(),
            'metadata'   => ['pickup_location' => $location->shiprocket_pickup_location_id],
        ];
    }

    public function bookShipment(Shipment $shipment): Shipment
    {
        if ($shipment->is_return) {
            return $this->bookReturnShipment($shipment);
        }

        $sellerOrder = $shipment->sellerOrder;
        $meta = (array) ($shipment->metadata ?? []);

        try {
            $this->ensureRemoteOrder($shipment, $sellerOrder, $meta);
            $this->ensureAwb($shipment, $meta);
        } catch (\Throwable $e) {
            $meta['booking_error'] = $e->getMessage();
            $this->persistBooking($shipment, $meta);
            $this->alertLowWallet();
            throw $e;
        }

        unset($meta['booking_error']);
        if ((string) $shipment->status === (string) ShipmentStatusEnum::PENDING()) {
            $shipment->status = (string) ShipmentStatusEnum::CONFIRMED();
            $shipment->last_event_at = now();
        }

        $warnings = [];
        foreach (['ensurePickup', 'ensureManifest', 'ensureLabel', 'ensureInvoice'] as $step) {
            try {
                $this->{$step}($shipment, $meta);
            } catch (\Throwable $e) {
                $warnings[] = strtolower(str_replace('ensure', '', $step)) . ': ' . $e->getMessage();
            }
        }
        if (!empty($warnings)) {
            $meta['booking_warning'] = implode(' | ', $warnings);
        } else {
            unset($meta['booking_warning']);
        }

        $this->persistBooking($shipment, $meta);
        $this->alertLowWallet();

        return $shipment;
    }

    private function alertLowWallet(): void
    {
        $threshold = (float) ($this->client->config()['shiprocketWalletAlert'] ?? 0);
        if ($threshold <= 0) {
            return;
        }

        if (Cache::get('shiprocket_wallet_alert_sent') || Cache::get('shiprocket_wallet_checked')) {
            return;
        }
        Cache::put('shiprocket_wallet_checked', true, now()->addMinutes(30));

        $balance = $this->client->walletBalance();
        if ($balance === null || $balance >= $threshold) {
            return;
        }

        Cache::put('shiprocket_wallet_alert_sent', true, now()->addHours(6));

        $admins = User::where('access_panel', GuardNameEnum::ADMIN)->get();
        foreach ($admins as $admin) {
            try {
                $admin->notify(new ShiprocketWalletLow($balance, $threshold));
            } catch (\Throwable $e) {
                Log::warning('Shiprocket wallet alert failed', ['error' => $e->getMessage()]);
            }
        }
    }

    public function cancelRemote(Shipment $shipment): void
    {
        $meta = (array) ($shipment->metadata ?? []);
        $srOrderId = $meta['shiprocket_order_id'] ?? null;
        if (!$srOrderId) {
            return;
        }

        if ($shipment->awb_number) {
            try {
                $this->client->cancelShipment($shipment->awb_number);
            } catch (\Throwable) {
            }
        }

        try {
            $this->client->cancelOrder($srOrderId);
        } catch (ShiprocketException $e) {
            if ($this->remoteStatusIsCancelled($srOrderId)) {
                return;
            }
            throw new ShiprocketException(__('labels.shiprocket_cancel_failed') . ' ' . $e->getMessage(), $e->payload);
        }
    }

    public function updateRemoteLines(Shipment $shipment, array $lines): void
    {
        if ($shipment->awb_number) {
            throw new ShiprocketException(__('labels.shiprocket_cannot_edit_after_awb'));
        }

        $meta = (array) ($shipment->metadata ?? []);
        if (empty($meta['shiprocket_order_id'])) {
            return;
        }

        $sellerOrder = $shipment->sellerOrder;
        $reference = $meta['shiprocket_reference'] ?? ($sellerOrder->id . '-' . $shipment->id);

        $this->client->updateOrderAdhoc($this->orderPayload($shipment, $sellerOrder, $reference, $lines));
    }

    public function remoteOrderSnapshot(Shipment $shipment): ?array
    {
        $meta = (array) ($shipment->metadata ?? []);
        $srOrderId = $meta['shiprocket_order_id'] ?? null;
        if (!$srOrderId) {
            return null;
        }

        try {
            $res = $this->client->orderShow($srOrderId);
        } catch (\Throwable) {
            return null;
        }

        $data = (array) ($res['data'] ?? []);
        $shipments = (array) ($data['shipments'] ?? []);
        $first = isset($shipments[0]) ? (array) $shipments[0] : $shipments;

        $awb = $data['awb_data']['awb'] ?? $first['awb'] ?? $first['awb_code'] ?? null;
        $courierId = $first['courier_id'] ?? $first['courier_company_id'] ?? $data['awb_data']['courier_company_id'] ?? null;

        $pickupScheduled = $first['pickup_scheduled_date']
            ?? $data['pickup_scheduled_date']
            ?? $data['shipments']['pickup_scheduled_date']
            ?? null;

        return [
            'status'           => strtoupper((string) ($data['status'] ?? '')),
            'awb'              => is_string($awb) && $awb !== '' ? $awb : null,
            'courier_name'     => $first['courier'] ?? $first['courier_name'] ?? $data['awb_data']['courier_name'] ?? null,
            'courier_id'       => is_numeric($courierId) ? (int) $courierId : null,
            'pickup_scheduled' => is_string($pickupScheduled) && $pickupScheduled !== '' ? $pickupScheduled : null,
        ];
    }

    public function remoteTrackedStatus(Shipment $shipment): ?string
    {
        if ($shipment->awb_number) {
            try {
                $res = $this->client->track((string) $shipment->awb_number);
                $td = $res['tracking_data'] ?? $res;
                $dead = isset($td['track_status']) && is_numeric($td['track_status']) && !(int) $td['track_status'];
                if (!$dead) {
                    $current = $td['shipment_track'][0]['current_status'] ?? $td['current_status'] ?? null;
                    if ($current) {
                        return $this->mapWebhookStatus(['current_status' => $current]);
                    }
                }
            } catch (\Throwable) {
            }
        }

        $snapshot = $this->remoteOrderSnapshot($shipment);
        if ($snapshot && str_contains($snapshot['status'], 'CANCEL')) {
            return (string) ShipmentStatusEnum::CANCELLED();
        }

        return null;
    }

    public function syncRemoteAssignment(Shipment $shipment): bool
    {
        $snapshot = $this->remoteOrderSnapshot($shipment);
        if (!$snapshot) {
            return false;
        }

        $meta = (array) ($shipment->metadata ?? []);
        $changed = false;
        $awbChanged = $snapshot['awb'] && $snapshot['awb'] !== (string) $shipment->awb_number;

        if ($awbChanged) {
            unset($meta['pickup_requested'], $meta['manifest_generated'], $meta['booking_warning'], $meta['booking_error']);

            $shipment->awb_number = $snapshot['awb'];
            $shipment->tracking_number = $snapshot['awb'];
            $shipment->tracking_url = 'https://shiprocket.co/tracking/' . $snapshot['awb'];
            if (!empty($snapshot['courier_name'])) {
                $shipment->courier_name = $snapshot['courier_name'];
            }
            if (!empty($snapshot['courier_id'])) {
                $shipment->courier_id = $snapshot['courier_id'];
                $rate = $this->courierRate($meta, ['courier_company_id' => $snapshot['courier_id']]);
                if ($rate !== null) {
                    $shipment->provider_shipping_cost = $rate;
                }
            }
            $shipment->label_url = null;
            $shipment->manifest_url = null;

            if ((string) $shipment->status === (string) ShipmentStatusEnum::CANCELLED()) {
                $shipment->status = (string) ShipmentStatusEnum::CONFIRMED();
                $shipment->delivered_at = null;
            }
            $changed = true;
        }

        if (!empty($snapshot['pickup_scheduled'])) {
            try {
                $scheduled = Carbon::parse($snapshot['pickup_scheduled']);
                if (!$shipment->pickup_scheduled_at || !$shipment->pickup_scheduled_at->equalTo($scheduled)) {
                    $shipment->pickup_scheduled_at = $scheduled;
                    $meta['pickup_scheduled_date'] = $snapshot['pickup_scheduled'];
                    $changed = true;
                }
            } catch (\Throwable) {
            }
        }

        if (!$changed) {
            return false;
        }

        $shipment->last_event_at = now();
        $this->persistBooking($shipment, $meta);

        if ($awbChanged) {
            $shipment->recordStatusEvent(null, __('labels.shiprocket_reassigned_note') . ' ' . ($snapshot['courier_name'] ?: $snapshot['awb']));

            try {
                $this->bookShipment($shipment);
            } catch (\Throwable) {
            }
        }

        return true;
    }

    public function mapWebhookStatus(array $payload): ?string
    {
        $raw = $payload['current_status'] ?? $payload['shipment_status'] ?? $payload['status'] ?? null;
        if ($raw === null) {
            return null;
        }

        $key = strtolower(str_replace([' ', '-'], '_', (string) $raw));

        return match ($key) {
            'pickup_scheduled', 'pickup_generated', 'manifest_generated', 'pickup_queued' => (string) ShipmentStatusEnum::CONFIRMED(),
            'in_transit', 'shipped', 'picked_up', 'pickup_completed', 'reached_at_destination' => (string) ShipmentStatusEnum::IN_TRANSIT(),
            'out_for_delivery' => (string) ShipmentStatusEnum::OUT_FOR_DELIVERY(),
            'delivered' => (string) ShipmentStatusEnum::DELIVERED(),
            'undelivered', 'delivery_attempted', 'ndr' => (string) ShipmentStatusEnum::ATTEMPTED_DELIVERY(),
            'cancelled', 'canceled' => (string) ShipmentStatusEnum::CANCELLED(),
            'rto', 'rto_initiated', 'rto_in_transit', 'rto_acknowledged' => (string) ShipmentStatusEnum::RTO_INITIATED(),
            'rto_delivered' => (string) ShipmentStatusEnum::RTO_DELIVERED(),
            'lost', 'damaged' => (string) ShipmentStatusEnum::LOST(),
            'return_delivered', 'returned' => (string) ShipmentStatusEnum::RETURNED(),
            default => null,
        };
    }

    public function ndrAction(Shipment $shipment, string $action, string $comments): void
    {
        if (!$shipment->awb_number) {
            throw new ShiprocketException(__('labels.shiprocket_awb_missing'));
        }

        $this->client->ndrAction((string) $shipment->awb_number, $action, $comments);
    }

    public function requestReturnPickup(OrderItemReturn $return): ?Shipment
    {
        if (!$this->client->isEnabled() || $return->quantity < 1) {
            return null;
        }

        $parent = Shipment::query()
            ->where('provider_code', $this->code())
            ->where('is_return', false)
            ->where('status', '!=', (string) ShipmentStatusEnum::CANCELLED())
            ->whereHas('items', fn ($q) => $q->where('order_item_id', $return->order_item_id))
            ->orderByDesc('id')
            ->first();

        if (!$parent) {
            return null;
        }

        $location = $parent->storeLocation;
        if (!$location || !$location->isShiprocketRegistered() || !$location->hasCompleteShiprocketAddress()) {
            return null;
        }

        $cfg = $this->client->config();

        $shipment = \Illuminate\Support\Facades\DB::transaction(function () use ($parent, $return, $cfg) {
            $shipment = Shipment::create([
                'uuid'               => (string) \Illuminate\Support\Str::uuid(),
                'seller_order_id'    => $parent->seller_order_id,
                'store_location_id'  => $parent->store_location_id,
                'provider_code'      => $this->code(),
                'shipping_zone_id'   => $parent->shipping_zone_id,
                'is_return'          => true,
                'parent_shipment_id' => $parent->id,
                'weight_kg'          => (float) ($cfg['shiprocketDefaultWeight'] ?? 0.5),
                'length_cm'          => (int) ($cfg['shiprocketDefaultLength'] ?? 10),
                'breadth_cm'         => (int) ($cfg['shiprocketDefaultBreadth'] ?? 10),
                'height_cm'          => (int) ($cfg['shiprocketDefaultHeight'] ?? 10),
                'shipping_cost'      => 0,
                'shipping_currency'  => $return->order->currency_code ?? 'USD',
                'payment_method'     => 'prepaid',
                'status'             => (string) ShipmentStatusEnum::PENDING(),
                'metadata'           => ['order_item_return_id' => $return->id],
                'last_event_at'      => now(),
            ]);

            $shipment->items()->create([
                'order_item_id' => $return->order_item_id,
                'quantity'      => (int) $return->quantity,
            ]);

            $return->forceFill(['return_shipment_id' => $shipment->id])->save();

            return $shipment;
        });

        try {
            $this->bookReturnShipment($shipment);
        } catch (\Throwable $e) {
            Log::warning('Shiprocket return pickup booking failed', [
                'return_id'   => $return->id,
                'shipment_id' => $shipment->id,
                'error'       => $e->getMessage(),
            ]);
        }

        if ($shipment->awb_number) {
            $return->forceFill([
                'return_carrier'         => $shipment->courier_name,
                'return_tracking_number' => $shipment->awb_number,
                'return_tracking_url'    => $shipment->tracking_url,
            ])->save();
        }

        return $shipment;
    }

    private function bookReturnShipment(Shipment $shipment): Shipment
    {
        $meta = (array) ($shipment->metadata ?? []);

        try {
            $this->ensureRemoteReturnOrder($shipment, $meta);
            $this->ensureAwb($shipment, $meta, true);
        } catch (\Throwable $e) {
            $meta['booking_error'] = $e->getMessage();
            $this->persistBooking($shipment, $meta);
            throw $e;
        }

        unset($meta['booking_error']);
        if ((string) $shipment->status === (string) ShipmentStatusEnum::PENDING()) {
            $shipment->status = (string) ShipmentStatusEnum::CONFIRMED();
            $shipment->last_event_at = now();
        }
        $this->persistBooking($shipment, $meta);

        return $shipment;
    }

    private function ensureRemoteReturnOrder(Shipment $shipment, array &$meta): void
    {
        if (!empty($meta['shiprocket_order_id']) && !empty($meta['shiprocket_shipment_id'])) {
            return;
        }

        $sellerOrder = $shipment->sellerOrder;
        $order = $sellerOrder->order;
        $location = $shipment->storeLocation;
        $store = $location?->store;
        $orderCurrency = $order->currency_code ?? null;
        $cfg = $this->client->config();

        $lines = $shipment->items()->get()
            ->mapWithKeys(fn ($si) => [(int) $si->order_item_id => (int) $si->quantity])
            ->all();
        $money = ShipmentMoney::linesFor($sellerOrder, $lines);

        $items = array_map(fn ($line) => array_filter([
            'name'          => $line['title'],
            'sku'           => $line['sku'],
            'units'         => $line['units'],
            'selling_price' => $this->toInr($line['unit_gross'], $orderCurrency),
            'hsn'           => $line['hsn'] ?? null,
        ], fn ($v) => $v !== null), $money['lines']);

        $subTotal = round(array_sum(array_map(
            fn ($item) => (float) $item['selling_price'] * (int) $item['units'],
            $items
        )), 2);

        $reference = $sellerOrder->id . '-' . $shipment->id;

        $payload = [
            'order_id'               => $reference,
            'order_date'             => now()->format('Y-m-d H:i'),
            'pickup_customer_name'   => $order->shipping_name ?? $order->billing_name ?? '',
            'pickup_last_name'       => '',
            'pickup_address'         => $order->shipping_address_1 ?? $order->billing_address_1 ?? '',
            'pickup_address_2'       => $order->shipping_address_2 ?? $order->billing_address_2 ?? '',
            'pickup_city'            => $order->shipping_city ?? $order->billing_city ?? '',
            'pickup_state'           => $order->shipping_state ?? $order->billing_state ?? '',
            'pickup_country'         => $order->shipping_country ?? $order->billing_country ?? '',
            'pickup_pincode'         => $order->shipping_zip ?? $order->billing_zip ?? '',
            'pickup_email'           => $order->email ?? '',
            'pickup_phone'           => $order->shipping_phone ?? $order->billing_phone ?? '',
            'shipping_customer_name' => $store->name ?? $location->name ?? '',
            'shipping_last_name'     => '',
            'shipping_address'       => $location->address_1 ?? '',
            'shipping_address_2'     => $location->address_2 ?? '',
            'shipping_city'          => $location->city ?? '',
            'shipping_state'         => $location->state ?? '',
            'shipping_country'       => $location->country_name ?? 'India',
            'shipping_pincode'       => $location->pin_code ?? '',
            'shipping_email'         => $store->contact_email ?? '',
            'shipping_phone'         => $store->contact_number ?? '',
            'order_items'            => $items,
            'payment_method'         => 'Prepaid',
            'sub_total'              => $subTotal,
            'length'                 => (float) ($shipment->length_cm ?: ($cfg['shiprocketDefaultLength'] ?? 10)),
            'breadth'                => (float) ($shipment->breadth_cm ?: ($cfg['shiprocketDefaultBreadth'] ?? 10)),
            'height'                 => (float) ($shipment->height_cm ?: ($cfg['shiprocketDefaultHeight'] ?? 10)),
            'weight'                 => (float) ($shipment->weight_kg ?: ($cfg['shiprocketDefaultWeight'] ?? 0.5)),
        ];

        $created = $this->client->createReturn($payload);

        $srShipmentId = $created['shipment_id'] ?? null;
        if (!$srShipmentId) {
            throw new ShiprocketException(__('labels.shiprocket_return_create_failed'), $created);
        }

        $meta['shiprocket_order_id'] = $created['order_id'] ?? null;
        $meta['shiprocket_shipment_id'] = $srShipmentId;
        $meta['shiprocket_reference'] = $reference;

        $this->persistBooking($shipment, $meta, ['create_return' => $created]);
    }

    private function ensureRemoteOrder(Shipment $shipment, SellerOrder $sellerOrder, array &$meta): void
    {
        if (!empty($meta['shiprocket_order_id']) && !empty($meta['shiprocket_shipment_id'])) {
            return;
        }

        $this->assertBookableAddress($sellerOrder);

        $reference = $sellerOrder->id . '-' . $shipment->id;
        $created = $this->client->createOrderAdhoc($this->orderPayload($shipment, $sellerOrder, $reference));

        $srShipmentId = $created['shipment_id'] ?? null;
        if (!$srShipmentId) {
            throw new ShiprocketException(__('labels.shiprocket_order_create_failed'), $created);
        }

        $meta['shiprocket_order_id'] = $created['order_id'] ?? null;
        $meta['shiprocket_shipment_id'] = $srShipmentId;
        $meta['shiprocket_reference'] = $reference;

        $this->persistBooking($shipment, $meta, ['create' => $created]);
    }

    private function ensureAwb(Shipment $shipment, array &$meta, bool $isReturn = false): void
    {
        if (!empty($shipment->awb_number)) {
            return;
        }

        $awb = $this->client->assignAwb($meta['shiprocket_shipment_id'], $isReturn);
        $awbData = (array) ($awb['response']['data'] ?? []);
        $awbNumber = $awbData['awb_code'] ?? null;
        $assignStatus = $awb['awb_assign_status'] ?? null;

        if (!$awbNumber || ($assignStatus !== null && !(int) $assignStatus)) {
            $reason = $awbData['awb_assign_error']
                ?? $awb['awb_assign_error']
                ?? $awb['message']
                ?? null;
            throw new ShiprocketException(is_string($reason) && $reason !== '' ? $reason : __('labels.shiprocket_awb_failed'), $awb);
        }

        $shipment->awb_number = $awbNumber;
        $shipment->courier_id = $awbData['courier_company_id'] ?? null;
        $shipment->courier_name = $awbData['courier_name'] ?? null;
        $shipment->tracking_number = $awbNumber;
        $shipment->tracking_url = 'https://shiprocket.co/tracking/' . $awbNumber;
        $shipment->provider_shipping_cost = $this->courierRate($meta, $awbData);

        $this->persistBooking($shipment, $meta, ['awb' => $awb]);
    }

    private function ensurePickup(Shipment $shipment, array &$meta): void
    {
        if (!empty($meta['pickup_requested'])) {
            return;
        }

        try {
            $res = $this->client->generatePickup($meta['shiprocket_shipment_id']);
        } catch (ShiprocketException $e) {
            if (stripos($e->getMessage(), 'already') !== false) {
                $meta['pickup_requested'] = true;
                return;
            }
            throw $e;
        }

        if (isset($res['pickup_status']) && is_numeric($res['pickup_status']) && !(int) $res['pickup_status']) {
            throw new ShiprocketException(is_string($res['response'] ?? null) ? $res['response'] : __('labels.shiprocket_pickup_failed'), $res);
        }

        $meta['pickup_requested'] = true;
        $scheduled = $res['response']['pickup_scheduled_date'] ?? null;
        $meta['pickup_scheduled_date'] = $scheduled;
        $meta['pickup_token_number'] = $res['response']['pickup_token_number'] ?? null;

        if ($scheduled) {
            try {
                $shipment->pickup_scheduled_at = Carbon::parse($scheduled);
            } catch (\Throwable) {
            }
        }
    }

    private function ensureManifest(Shipment $shipment, array &$meta): void
    {
        if (!empty($meta['manifest_generated']) || $shipment->manifest_url) {
            return;
        }

        try {
            $res = $this->client->generateManifest($meta['shiprocket_shipment_id']);
        } catch (ShiprocketException $e) {
            if (!empty($e->payload['already_manifested_shipment_ids'])) {
                $meta['manifest_generated'] = true;
                return;
            }
            throw $e;
        }

        $url = $res['manifest_url'] ?? null;
        if (is_string($url) && $url !== '') {
            $shipment->manifest_url = $url;
        }
        $meta['manifest_generated'] = true;
    }

    private function ensureLabel(Shipment $shipment, array &$meta): void
    {
        if (!empty($shipment->label_url)) {
            return;
        }

        $res = $this->client->generateLabel($meta['shiprocket_shipment_id']);
        if (isset($res['label_created']) && !(int) $res['label_created']) {
            throw new ShiprocketException(is_string($res['response'] ?? null) ? $res['response'] : __('labels.shiprocket_label_failed'), $res);
        }

        $url = $res['label_url'] ?? null;
        if (!is_string($url) || $url === '') {
            throw new ShiprocketException(__('labels.shiprocket_label_failed'), $res);
        }
        $shipment->label_url = $url;
    }

    private function ensureInvoice(Shipment $shipment, array &$meta): void
    {
        if (!empty($shipment->invoice_url) || empty($meta['shiprocket_order_id'])) {
            return;
        }

        $res = $this->client->printInvoice($meta['shiprocket_order_id']);
        $url = $res['invoice_url'] ?? null;
        if (is_string($url) && $url !== '') {
            $shipment->invoice_url = $url;
        }
    }

    private function persistBooking(Shipment $shipment, array $meta, array $raw = []): void
    {
        $shipment->metadata = $meta;
        if (!empty($raw)) {
            $shipment->raw_response = array_merge((array) ($shipment->raw_response ?? []), $raw);
        }
        $shipment->save();
    }

    private function courierRate(array $meta, array $awbData): ?float
    {
        $courierId = $awbData['courier_company_id'] ?? null;
        $srOrderId = $meta['shiprocket_order_id'] ?? null;
        if (!$courierId || !$srOrderId) {
            return null;
        }

        try {
            $res = $this->client->serviceability(['order_id' => $srOrderId]);
        } catch (\Throwable) {
            return null;
        }

        foreach ($res['data']['available_courier_companies'] ?? [] as $courier) {
            if ((int) ($courier['courier_company_id'] ?? 0) !== (int) $courierId) {
                continue;
            }
            $rate = (float) ($courier['rate'] ?? $courier['freight_charge'] ?? 0);
            return $rate > 0 ? round($this->toBaseCurrency($rate), 2) : null;
        }

        return null;
    }

    private function remoteStatusIsCancelled(int|string $srOrderId): bool
    {
        try {
            $res = $this->client->orderShow($srOrderId);
        } catch (\Throwable) {
            return false;
        }

        return str_contains(strtoupper((string) ($res['data']['status'] ?? '')), 'CANCEL');
    }

    private function assertBookableAddress(SellerOrder $sellerOrder): void
    {
        $order = $sellerOrder->order;

        $required = [
            'name'    => $order->shipping_name ?? $order->billing_name ?? null,
            'address' => $order->shipping_address_1 ?? $order->billing_address_1 ?? null,
            'city'    => $order->shipping_city ?? $order->billing_city ?? null,
            'state'   => $order->shipping_state ?? $order->billing_state ?? null,
            'country' => $order->shipping_country ?? $order->billing_country ?? null,
            'pincode' => $order->shipping_zip ?? $order->billing_zip ?? null,
            'email'   => $order->email ?? null,
            'phone'   => $order->shipping_phone ?? $order->billing_phone ?? null,
        ];

        $missing = array_keys(array_filter($required, fn ($v) => blank($v)));
        if (!empty($missing)) {
            throw new ShiprocketException(__('labels.shiprocket_missing_address_fields', ['fields' => implode(', ', $missing)]));
        }
    }

    private function orderPayload(Shipment $shipment, SellerOrder $sellerOrder, string $reference, ?array $linesOverride = null): array
    {
        $order = $sellerOrder->order;
        $isCod = (string) $shipment->payment_method === 'cod';
        $cfg = $this->client->config();
        $orderCurrency = $order->currency_code ?? null;

        $lines = $linesOverride ?? $shipment->items()->get()
            ->mapWithKeys(fn ($si) => [(int) $si->order_item_id => (int) $si->quantity])
            ->all();

        $money = ShipmentMoney::linesFor($sellerOrder, $lines);

        $items = array_map(fn ($line) => array_filter([
            'name'          => $line['title'],
            'sku'           => $line['sku'],
            'units'         => $line['units'],
            'selling_price' => $this->toInr($line['unit_gross'], $orderCurrency),
            'hsn'           => $line['hsn'] ?? null,
        ], fn ($v) => $v !== null), $money['lines']);
        $subTotal = round(array_sum(array_map(
            fn ($item) => (float) $item['selling_price'] * (int) $item['units'],
            $items
        )), 2);

        $meta = (array) ($shipment->metadata ?? []);
        $pickupLocation = $meta['pickup_location']
            ?? $shipment->storeLocation?->shiprocket_pickup_location_id
            ?? $sellerOrder->storeLocation?->shiprocket_pickup_location_id;

        $payload = [
            'order_id'              => $reference,
            'order_date'            => optional($sellerOrder->created_at)->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i'),
            'pickup_location'       => $pickupLocation,
            'billing_customer_name' => $order->shipping_name ?? $order->billing_name ?? '',
            'billing_last_name'     => '',
            'billing_address'       => $order->shipping_address_1 ?? $order->billing_address_1 ?? '',
            'billing_address_2'     => $order->shipping_address_2 ?? $order->billing_address_2 ?? '',
            'billing_city'          => $order->shipping_city ?? $order->billing_city ?? '',
            'billing_pincode'       => $order->shipping_zip ?? $order->billing_zip ?? '',
            'billing_state'         => $order->shipping_state ?? $order->billing_state ?? '',
            'billing_country'       => $order->shipping_country ?? $order->billing_country ?? '',
            'billing_email'         => $order->email ?? '',
            'billing_phone'         => $order->shipping_phone ?? $order->billing_phone ?? '',
            'shipping_is_billing'   => true,
            'order_items'           => $items,
            'payment_method'        => $isCod ? 'COD' : 'Prepaid',
            'sub_total'             => $subTotal,
            'total_discount'        => $money['discount'] > 0 ? $this->toInr($money['discount'], $orderCurrency) : 0,
            'shipping_charges'      => (float) $shipment->shipping_cost > 0 ? $this->toInr((float) $shipment->shipping_cost, $orderCurrency) : 0,
            'length'                => (float) ($shipment->length_cm ?: ($cfg['shiprocketDefaultLength'] ?? 10)),
            'breadth'               => (float) ($shipment->breadth_cm ?: ($cfg['shiprocketDefaultBreadth'] ?? 10)),
            'height'                => (float) ($shipment->height_cm ?: ($cfg['shiprocketDefaultHeight'] ?? 10)),
            'weight'                => (float) ($shipment->weight_kg ?: ($cfg['shiprocketDefaultWeight'] ?? 0.5)),
        ];

        $channelId = $cfg['shiprocketChannelId'] ?? null;
        if (!blank($channelId)) {
            $payload['channel_id'] = $channelId;
        }

        return $payload;
    }

    public function checkoutRate(string $pickupPin, string $deliveryPin, float $weightKg, float $cartTotal, bool $cod = false): ?array
    {
        if (!$this->client->isEnabled() || blank($pickupPin) || blank($deliveryPin)) {
            return null;
        }

        $cfg = $this->client->config();
        $weight = $weightKg > 0 ? $weightKg : (float) ($cfg['shiprocketDefaultWeight'] ?? 0.5);

        $quote = $this->serviceabilityQuote($pickupPin, $deliveryPin, $weight, $cod);
        if (!$quote || !$quote['serviceable']) {
            return null;
        }

        $rate = match ($cfg['shiprocketRateMode'] ?? 'carrier_calculated') {
            'free'  => 0.0,
            'flat'  => (float) ($cfg['shiprocketFlatRate'] ?? 0),
            default => $this->toBaseCurrency((float) $quote['rate']),
        };

        $freeAbove = (float) ($cfg['shiprocketFreeAbove'] ?? 0);
        if ($freeAbove > 0 && $cartTotal >= $freeAbove) {
            $rate = 0.0;
        }

        return [
            'rate'     => $rate,
            'eta_min'  => $quote['eta_days'],
            'eta_max'  => $quote['eta_days'],
            'eta_unit' => 'days',
            'courier'  => $quote['courier'],
        ];
    }

    private function toInr(float $amount, ?string $fromCurrency): float
    {
        $from = strtoupper((string) ($fromCurrency ?: ''));
        if ($from === '' || $from === 'INR') {
            return round($amount, 2);
        }

        $converted = app(FxRateService::class)->convertCurrency($amount, $from, 'INR');

        return $converted !== null ? round($converted, 2) : round($amount, 2);
    }

    private function toBaseCurrency(float $inr): float
    {
        $this->baseCurrencyCode ??= strtoupper((string) (\App\Models\Currency::query()->where('is_base', true)->value('code') ?: 'INR'));
        if ($this->baseCurrencyCode === 'INR') {
            return $inr;
        }

        $converted = app(FxRateService::class)->convertCurrency($inr, 'INR', $this->baseCurrencyCode);

        return $converted !== null ? $converted : $inr;
    }

    private function serviceabilityQuote(string $pickupPin, string $deliveryPin, float $weight, bool $cod): ?array
    {
        $weight = round($weight, 3);
        $payment = $cod ? 'cod' : 'prepaid';
        $key = [
            'provider_code'  => $this->code(),
            'pickup_pin'     => $pickupPin,
            'delivery_pin'   => $deliveryPin,
            'weight_kg'      => $weight,
            'payment_method' => $payment,
        ];

        $cached = ServiceabilityCache::where($key)->first();
        if ($cached && $cached->expires_at->isFuture()) {
            return [
                'serviceable' => (bool) $cached->is_serviceable,
                'rate'        => (float) $cached->cheapest_rate,
                'eta_days'    => $cached->fastest_eta_days !== null ? (int) $cached->fastest_eta_days : null,
                'courier'     => $cached->response_payload['courier'] ?? null,
            ];
        }

        try {
            $res = $this->client->serviceability([
                'pickup_postcode'   => $pickupPin,
                'delivery_postcode' => $deliveryPin,
                'weight'            => $weight,
                'cod'               => $cod ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            return null;
        }

        $cheapest = null;
        foreach ($res['data']['available_courier_companies'] ?? [] as $courier) {
            $r = (float) ($courier['rate'] ?? 0);
            if ($r <= 0) {
                continue;
            }
            if ($cheapest === null || $r < $cheapest['rate']) {
                $etd = $courier['estimated_delivery_days'] ?? $courier['etd'] ?? null;
                $cheapest = [
                    'rate'     => $r,
                    'eta_days' => is_numeric($etd) ? (int) $etd : null,
                    'courier'  => $courier['courier_name'] ?? null,
                ];
            }
        }

        $serviceable = $cheapest !== null;

        ServiceabilityCache::updateOrCreate($key, [
            'response_payload' => ['courier' => $cheapest['courier'] ?? null],
            'is_serviceable'   => $serviceable,
            'cheapest_rate'    => $cheapest['rate'] ?? null,
            'fastest_eta_days' => $cheapest['eta_days'] ?? null,
            'expires_at'       => now()->addHours(12),
        ]);

        return [
            'serviceable' => $serviceable,
            'rate'        => $cheapest['rate'] ?? 0.0,
            'eta_days'    => $cheapest['eta_days'] ?? null,
            'courier'     => $cheapest['courier'] ?? null,
        ];
    }
}
