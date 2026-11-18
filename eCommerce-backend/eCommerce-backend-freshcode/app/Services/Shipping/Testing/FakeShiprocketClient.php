<?php

namespace App\Services\Shipping\Testing;

use App\Services\Shipping\Exceptions\ShiprocketException;
use App\Services\Shipping\ShiprocketClient;

class FakeShiprocketClient extends ShiprocketClient
{
    public static function stateFile(): string
    {
        return storage_path('app/shiprocket-fake-state.json');
    }

    public static function markerFile(): string
    {
        return storage_path('shiprocket-fake');
    }

    public static function enabled(): bool
    {
        return file_exists(self::markerFile());
    }

    public static function resetState(): void
    {
        @unlink(self::stateFile());
    }

    public static function readState(): array
    {
        if (!is_file(self::stateFile())) {
            return ['seq' => 700000, 'orders' => [], 'log' => []];
        }

        $state = json_decode((string) file_get_contents(self::stateFile()), true);

        return is_array($state) ? $state + ['seq' => 700000, 'orders' => [], 'log' => []] : ['seq' => 700000, 'orders' => [], 'log' => []];
    }

    private function writeState(array $state): void
    {
        file_put_contents(self::stateFile(), json_encode($state, JSON_PRETTY_PRINT));
    }

    private function record(array &$state, string $api, array $payload, array $response): array
    {
        array_unshift($state['log'], [
            'at'       => now()->toDateTimeString(),
            'api'      => $api,
            'payload'  => $payload,
            'response' => $response,
        ]);
        $state['log'] = array_slice($state['log'], 0, 60);
        $this->writeState($state);

        return $response;
    }

    private function &findByShipmentId(array &$state, int|string $shipmentId): ?array
    {
        foreach ($state['orders'] as $key => &$order) {
            if ((string) $order['shipment_id'] === (string) $shipmentId) {
                return $order;
            }
        }
        $null = null;

        return $null;
    }

    private function &findByAwb(array &$state, string $awb): ?array
    {
        foreach ($state['orders'] as $key => &$order) {
            if ((string) ($order['awb'] ?? '') === $awb) {
                return $order;
            }
        }
        $null = null;

        return $null;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function authToken(): ?string
    {
        return 'fake-token';
    }

    public function testConnection(?string $email = null, ?string $password = null): array
    {
        return ['success' => true, 'message' => 'Connected to Fake Shiprocket.'];
    }

    public function walletBalance(): ?float
    {
        return 500.0;
    }

    public function serviceability(array $params): array
    {
        $state = self::readState();

        $response = ['status' => 200, 'currency' => 'INR', 'data' => ['available_courier_companies' => [
            ['courier_company_id' => 901, 'courier_name' => 'Fake Xpress', 'rate' => 55.0, 'freight_charge' => 55.0, 'estimated_delivery_days' => '3', 'etd' => now()->addDays(3)->format('M d, Y'), 'rating' => 4.4, 'cod_charges' => 25.0],
            ['courier_company_id' => 902, 'courier_name' => 'Fake Dart Air', 'rate' => 82.0, 'freight_charge' => 82.0, 'estimated_delivery_days' => '2', 'etd' => now()->addDays(2)->format('M d, Y'), 'rating' => 4.7, 'cod_charges' => 30.0],
        ]]];

        return $this->record($state, 'GET /courier/serviceability', $params, $response);
    }

    public function createOrderAdhoc(array $payload): array
    {
        $state = self::readState();
        $reference = (string) ($payload['order_id'] ?? '');

        foreach ($state['orders'] as $order) {
            if ($order['channel_order_id'] === $reference) {
                $error = ['message' => 'Oops! Invalid Data.', 'errors' => ['order_id' => ['The order id has already been taken.']], 'status_code' => 422];
                $this->record($state, 'POST /orders/create/adhoc', $payload, $error);
                throw new ShiprocketException('The order id has already been taken.', $error, 422);
            }
        }

        $orderId = ++$state['seq'];
        $shipmentId = ++$state['seq'];

        $state['orders'][(string) $orderId] = [
            'order_id'              => $orderId,
            'shipment_id'           => $shipmentId,
            'channel_order_id'      => $reference,
            'status'                => 'NEW',
            'awb'                   => null,
            'courier_id'            => null,
            'courier_name'          => null,
            'pickup_scheduled_date' => null,
            'is_return'             => false,
            'payload'               => $payload,
        ];

        $response = ['order_id' => $orderId, 'shipment_id' => $shipmentId, 'status' => 'NEW', 'status_code' => 1, 'onboarding_completed_now' => 0, 'awb_code' => null, 'courier_company_id' => null, 'courier_name' => null];

        return $this->record($state, 'POST /orders/create/adhoc', $payload, $response);
    }

    public function updateOrderAdhoc(array $payload): array
    {
        $state = self::readState();
        $reference = (string) ($payload['order_id'] ?? '');

        foreach ($state['orders'] as $key => $order) {
            if ($order['channel_order_id'] !== $reference) {
                continue;
            }
            if (!empty($order['awb'])) {
                $error = ['message' => 'You can update the order details before assigning the AWB only.', 'status_code' => 400];
                $this->record($state, 'POST /orders/update/adhoc', $payload, $error);
                throw new ShiprocketException($error['message'], $error, 400);
            }
            $state['orders'][$key]['payload'] = array_merge($order['payload'], $payload);
            $response = ['order_id' => $order['order_id'], 'shipment_id' => $order['shipment_id'], 'new_order_status' => 'NEW', 'partially_update' => true, 'not_updated_fields' => ['pickup_location', 'billing_details', 'shipping_details', 'payment_method']];

            return $this->record($state, 'POST /orders/update/adhoc', $payload, $response);
        }

        $error = ['message' => 'Order does not exist!', 'status_code' => 404];
        $this->record($state, 'POST /orders/update/adhoc', $payload, $error);
        throw new ShiprocketException($error['message'], $error, 404);
    }

    public function orderShow(int|string $shiprocketOrderId): array
    {
        $state = self::readState();
        $order = $state['orders'][(string) $shiprocketOrderId] ?? null;

        if (!$order) {
            $error = ['message' => 'Order does not exist!', 'status_code' => 404];
            $this->record($state, 'GET /orders/show/' . $shiprocketOrderId, [], $error);
            throw new ShiprocketException($error['message'], $error, 404);
        }

        $response = ['data' => [
            'id'                    => $order['order_id'],
            'channel_order_id'      => $order['channel_order_id'],
            'status'                => $order['status'],
            'pickup_scheduled_date' => $order['pickup_scheduled_date'],
            'shipments'             => [[
                'id'                    => $order['shipment_id'],
                'awb'                   => $order['awb'],
                'courier'               => $order['courier_name'],
                'courier_id'            => $order['courier_id'],
                'pickup_scheduled_date' => $order['pickup_scheduled_date'],
            ]],
        ]];

        return $this->record($state, 'GET /orders/show/' . $shiprocketOrderId, [], $response);
    }

    public function assignAwb(int|string $shipmentId, bool $isReturn = false): array
    {
        $state = self::readState();
        $order = &$this->findByShipmentId($state, $shipmentId);

        if (!$order || str_contains($order['status'], 'CANCELED')) {
            $error = ['awb_assign_status' => 0, 'response' => ['data' => ['awb_assign_error' => 'Oops! Cannot assign AWB for this shipment.']]];
            $this->record($state, 'POST /courier/assign/awb', ['shipment_id' => $shipmentId], $error);
            throw new ShiprocketException('Oops! Cannot assign AWB for this shipment.', $error, 400);
        }

        $courierId = $isReturn ? 903 : 901;
        $courierName = $isReturn ? 'Fake Reverse Logistics' : 'Fake Xpress';
        $awb = 'FAKE' . str_pad((string) random_int(1, 999999999), 9, '0', STR_PAD_LEFT);
        $pickup = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s');

        $order['awb'] = $awb;
        $order['courier_id'] = $courierId;
        $order['courier_name'] = $courierName;
        $order['pickup_scheduled_date'] = $pickup;
        $order['status'] = 'AWB ASSIGNED';

        $response = ['awb_assign_status' => 1, 'response' => ['data' => [
            'awb_code'              => $awb,
            'courier_company_id'    => $courierId,
            'courier_name'          => $courierName,
            'pickup_scheduled_date' => $pickup,
            'applied_weight'        => 0.5,
            'routing_code'          => 'FK/001/01',
            'shipment_id'           => $order['shipment_id'],
            'order_id'              => $order['order_id'],
        ]]];

        return $this->record($state, 'POST /courier/assign/awb', ['shipment_id' => $shipmentId, 'is_return' => $isReturn ? 1 : 0], $response);
    }

    public function generatePickup(array|int|string $shipmentIds): array
    {
        $state = self::readState();
        $ids = is_array($shipmentIds) ? $shipmentIds : [$shipmentIds];
        $order = &$this->findByShipmentId($state, $ids[0]);
        $pickup = $order['pickup_scheduled_date'] ?? now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s');

        $response = ['pickup_status' => 1, 'response' => ['pickup_scheduled_date' => $pickup, 'pickup_token_number' => 'FKTOKEN' . random_int(1000, 9999)]];

        return $this->record($state, 'POST /courier/generate/pickup', ['shipment_id' => $ids], $response);
    }

    public function generateManifest(array|int|string $shipmentIds): array
    {
        $state = self::readState();
        $response = ['status' => 1, 'manifest_url' => url('/dev/shiprocket-tester/document/manifest.pdf')];

        return $this->record($state, 'POST /manifests/generate', ['shipment_id' => $shipmentIds], $response);
    }

    public function generateLabel(array|int|string $shipmentIds): array
    {
        $state = self::readState();
        $ids = is_array($shipmentIds) ? $shipmentIds : [$shipmentIds];
        $order = &$this->findByShipmentId($state, $ids[0]);
        $label = url('/dev/shiprocket-tester/document/label-' . ($order['awb'] ?? 'none') . '.pdf');
        $response = ['label_created' => 1, 'label_url' => $label, 'response' => 'Label generated'];

        return $this->record($state, 'POST /courier/generate/label', ['shipment_id' => $ids], $response);
    }

    public function printInvoice(array|int|string $orderIds): array
    {
        $state = self::readState();
        $response = ['is_invoice_created' => true, 'invoice_url' => url('/dev/shiprocket-tester/document/invoice.pdf')];

        return $this->record($state, 'POST /orders/print/invoice', ['ids' => (array) $orderIds], $response);
    }

    public function track(string $awb): array
    {
        $state = self::readState();
        $order = &$this->findByAwb($state, $awb);

        if (!$order) {
            $response = ['tracking_data' => ['track_status' => 0, 'error' => 'Aahh! There is no activities found in our DB.']];

            return $this->record($state, 'GET /courier/track/awb/' . $awb, [], $response);
        }

        $response = ['tracking_data' => [
            'track_status'   => 1,
            'shipment_track' => [[
                'awb_code'       => $awb,
                'current_status' => $order['status'],
                'courier_name'   => $order['courier_name'],
            ]],
        ]];

        return $this->record($state, 'GET /courier/track/awb/' . $awb, [], $response);
    }

    public function trackBulk(array $awbs): array
    {
        $response = [];
        foreach ($awbs as $awb) {
            $response[] = [(string) $awb => $this->track((string) $awb)];
        }

        return $response;
    }

    public function cancelShipment(array|string $awbs): array
    {
        $state = self::readState();
        foreach ((array) $awbs as $awb) {
            $order = &$this->findByAwb($state, (string) $awb);
            if ($order) {
                $order['awb'] = null;
                $order['courier_id'] = null;
                $order['courier_name'] = null;
                $order['pickup_scheduled_date'] = null;
                $order['status'] = 'NEW';
            }
            unset($order);
        }

        return $this->record($state, 'POST /orders/cancel/shipment/awbs', ['awbs' => (array) $awbs], ['message' => 'Bulk Shipment cancellation is in progress.']);
    }

    public function cancelOrder(array|int|string $orderIds): array
    {
        $state = self::readState();
        foreach ((array) $orderIds as $id) {
            if (isset($state['orders'][(string) $id])) {
                $state['orders'][(string) $id]['status'] = 'CANCELED';
            }
        }

        return $this->record($state, 'POST /orders/cancel', ['ids' => (array) $orderIds], []);
    }

    public function createReturn(array $payload): array
    {
        $state = self::readState();

        $orderId = ++$state['seq'];
        $shipmentId = ++$state['seq'];

        $state['orders'][(string) $orderId] = [
            'order_id'              => $orderId,
            'shipment_id'           => $shipmentId,
            'channel_order_id'      => (string) ($payload['order_id'] ?? ''),
            'status'                => 'RETURN PENDING',
            'awb'                   => null,
            'courier_id'            => null,
            'courier_name'          => null,
            'pickup_scheduled_date' => null,
            'is_return'             => true,
            'payload'               => $payload,
        ];

        $response = ['order_id' => $orderId, 'shipment_id' => $shipmentId, 'status' => 'RETURN PENDING', 'status_code' => 21, 'company_name' => 'Fake Shiprocket'];

        return $this->record($state, 'POST /orders/create/return', $payload, $response);
    }

    public function ndrAction(string $awb, string $action, string $comments): array
    {
        $state = self::readState();
        $response = ['message' => 'NDR action ' . $action . ' recorded for ' . $awb . '.', 'status' => true];

        return $this->record($state, 'POST /ndr/' . $awb . '/action', ['action' => $action, 'comments' => $comments], $response);
    }

    public function addPickup(array $payload): array
    {
        $state = self::readState();
        $nickname = (string) ($payload['pickup_location'] ?? 'fake-pickup');
        $response = ['success' => true, 'pickup_id' => random_int(10000, 99999), 'address' => ['pickup_code' => $nickname]];

        return $this->record($state, 'POST /settings/company/addpickup', $payload, $response);
    }

    public function pickupLocations(): array
    {
        return ['data' => ['shipping_address' => [['pickup_location' => 'fake-pickup', 'address' => 'Fake street']]]];
    }

    public static function setRemoteStatus(string $awb, string $status): ?array
    {
        $instance = new self(app(\App\Services\SettingService::class));
        $state = self::readState();
        $order = &$instance->findByAwb($state, $awb);
        if (!$order) {
            return null;
        }
        $order['status'] = $status;
        $instance->writeState($state);

        return $order;
    }
}
