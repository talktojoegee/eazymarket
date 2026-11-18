<?php

namespace App\Http\Controllers\Dev;

use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\OrderItemReturn;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Services\SettingService;
use App\Services\ShipmentService;
use App\Services\Shipping\Testing\FakeShiprocketClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiprocketTesterController extends Controller
{
    private function guard(): void
    {
        if (app()->environment('production')) {
            abort(404);
        }
    }

    public function index()
    {
        $this->guard();

        return view('dev.shiprocket-tester');
    }

    public function toggleFake(Request $request): JsonResponse
    {
        $this->guard();

        if ($request->boolean('enable')) {
            touch(FakeShiprocketClient::markerFile());
        } else {
            @unlink(FakeShiprocketClient::markerFile());
        }
        if ($request->boolean('reset')) {
            FakeShiprocketClient::resetState();
        }

        return response()->json(['success' => true, 'fake' => FakeShiprocketClient::enabled()]);
    }

    public function snapshot(int $sellerOrderId): JsonResponse
    {
        $this->guard();

        $sellerOrder = SellerOrder::with(['order', 'shipments.items.orderItem'])->find($sellerOrderId);
        if (!$sellerOrder) {
            return response()->json(['success' => false, 'message' => 'Seller order not found'], 404);
        }

        $orderItemIds = $sellerOrder->items()->pluck('order_item_id')->filter()->all();
        $returns = OrderItemReturn::whereIn('order_item_id', $orderItemIds)->orderByDesc('id')->get();
        $state = FakeShiprocketClient::readState();

        return response()->json([
            'success'      => true,
            'fake'         => FakeShiprocketClient::enabled(),
            'seller_order' => [
                'id'                 => $sellerOrder->id,
                'order_id'           => $sellerOrder->order_id,
                'fulfillment_status' => (string) $sellerOrder->fulfillment_status,
                'order_status'       => (string) $sellerOrder->order?->status,
                'payment_method'     => (string) $sellerOrder->order?->payment_method,
            ],
            'shipments'    => $sellerOrder->shipments->map(fn ($s) => [
                'id'            => $s->id,
                'status'        => (string) $s->status,
                'is_return'     => (bool) $s->is_return,
                'awb'           => $s->awb_number,
                'courier'       => $s->courier_name,
                'pickup_at'     => $s->pickup_scheduled_at?->toDateTimeString(),
                'label_url'     => $s->label_url,
                'cod_amount'    => $s->cod_amount,
                'metadata'      => $s->metadata,
                'items'         => $s->items->map(fn ($si) => ($si->orderItem->title ?? '#' . $si->order_item_id) . ' × ' . $si->quantity)->values()->all(),
            ])->values()->all(),
            'returns'      => $returns->map(fn ($r) => [
                'id'            => $r->id,
                'order_item_id' => $r->order_item_id,
                'status'        => (string) $r->return_status,
                'tracking'      => $r->return_tracking_number,
                'shipment_id'   => $r->return_shipment_id,
            ])->values()->all(),
            'fake_log'     => array_slice($state['log'], 0, 20),
        ]);
    }

    public function createParcel(Request $request, int $sellerOrderId): JsonResponse
    {
        $this->guard();

        $sellerOrder = SellerOrder::find($sellerOrderId);
        if (!$sellerOrder) {
            return response()->json(['success' => false, 'message' => 'Seller order not found'], 404);
        }

        try {
            $shipment = app(ShipmentService::class)->createForSellerOrder($sellerOrder, array_filter([
                'items' => $request->input('items'),
            ]));

            return response()->json([
                'success'  => true,
                'shipment' => ['id' => $shipment->id, 'status' => (string) $shipment->status, 'awb' => $shipment->awb_number, 'error' => $shipment->metadata['booking_error'] ?? null],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function fireEvent(Request $request, int $shipmentId): JsonResponse
    {
        $this->guard();

        $shipment = Shipment::find($shipmentId);
        if (!$shipment || !$shipment->awb_number) {
            return response()->json(['success' => false, 'message' => 'Shipment not found or has no AWB yet'], 422);
        }

        $status = (string) $request->input('status');
        $allowed = ['PICKED UP', 'IN TRANSIT', 'OUT FOR DELIVERY', 'DELIVERED', 'UNDELIVERED', 'CANCELED', 'RTO INITIATED', 'RTO DELIVERED'];
        if (!in_array($status, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Unknown status'], 422);
        }

        if (FakeShiprocketClient::enabled()) {
            FakeShiprocketClient::setRemoteStatus((string) $shipment->awb_number, $status);
        }

        $meta = (array) ($shipment->metadata ?? []);
        $payload = [
            'awb'               => $shipment->awb_number,
            'courier_name'      => $shipment->courier_name,
            'current_status'    => $status,
            'shipment_status'   => $status,
            'current_timestamp' => now()->format('d m Y H:i:s'),
            'order_id'          => $meta['shiprocket_reference'] ?? null,
            'sr_order_id'       => $meta['shiprocket_order_id'] ?? null,
            'etd'               => now()->addDays(2)->toDateTimeString(),
            'scans'             => [['date' => now()->toDateTimeString(), 'activity' => 'Fake scan: ' . $status, 'location' => 'FAKE HUB, IN']],
            'is_return'         => (int) $shipment->is_return,
        ];

        $secret = app(SettingService::class)->shiprocketConfig()['shiprocketWebhookSecret'] ?? '';
        $webhookRequest = Request::create('/api/webhooks/shippingrocket', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => (string) $secret,
        ], json_encode($payload));

        $response = app()->handle($webhookRequest);

        return response()->json([
            'success'          => true,
            'webhook_payload'  => $payload,
            'webhook_response' => json_decode((string) $response->getContent(), true),
            'shipment'         => ['id' => $shipment->id, 'status' => (string) $shipment->fresh()->status],
        ]);
    }

    public function syncShipment(int $shipmentId): JsonResponse
    {
        $this->guard();

        $shipment = Shipment::find($shipmentId);
        if (!$shipment) {
            return response()->json(['success' => false, 'message' => 'Shipment not found'], 404);
        }

        try {
            app(ShipmentService::class)->syncShipmentFromRemote($shipment);

            return response()->json(['success' => true, 'shipment' => ['id' => $shipment->id, 'status' => (string) $shipment->fresh()->status, 'awb' => $shipment->fresh()->awb_number]]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function createReturn(Request $request, int $sellerOrderId): JsonResponse
    {
        $this->guard();

        $sellerOrder = SellerOrder::with(['items.orderItem', 'order'])->find($sellerOrderId);
        if (!$sellerOrder) {
            return response()->json(['success' => false, 'message' => 'Seller order not found'], 404);
        }

        $sellerOrderItem = $request->filled('order_item_id')
            ? $sellerOrder->items->firstWhere('order_item_id', (int) $request->input('order_item_id'))
            : $sellerOrder->items->first();
        if (!$sellerOrderItem) {
            return response()->json(['success' => false, 'message' => 'Order item not found on this seller order'], 422);
        }

        try {
            $return = OrderItemReturn::forceCreate([
                'order_item_id'      => $sellerOrderItem->order_item_id,
                'order_id'           => $sellerOrder->order_id,
                'user_id'            => $sellerOrder->order->user_id,
                'seller_id'          => $sellerOrder->seller_id,
                'store_id'           => $sellerOrderItem->orderItem->store_id ?? null,
                'quantity'           => max(1, (int) $request->input('quantity', 1)),
                'reason'             => 'Tester return',
                'return_status'      => OrderItemReturnStatusEnum::APPROVED(),
                'seller_approved_at' => now(),
            ]);

            $shipment = app(ShipmentService::class)->requestCarrierReturnPickup($return->fresh());

            return response()->json([
                'success' => true,
                'return'  => ['id' => $return->id, 'status' => (string) $return->fresh()->return_status, 'tracking' => $return->fresh()->return_tracking_number],
                'return_shipment' => $shipment ? ['id' => $shipment->id, 'status' => (string) $shipment->status, 'awb' => $shipment->awb_number] : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
