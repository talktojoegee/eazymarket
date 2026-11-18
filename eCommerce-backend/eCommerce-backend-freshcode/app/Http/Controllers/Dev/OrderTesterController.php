<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Market;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderPaymentTransaction;
use App\Models\StoreLocationInventory;
use App\Models\User;
use App\Enums\PaymentStatusEnum;
use App\Services\MarketService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderTesterController extends Controller
{
    private function guard(): void
    {
        if (!app()->environment('local')) {
            abort(404);
        }
    }

    public function index()
    {
        $this->guard();

        return view('dev.order-tester');
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $this->guard();

        $user = User::find($request->input('user_id'));
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 422);
        }

        Auth::login($user);

        $market = Market::where('code', 'IN')->first() ?? app(MarketService::class)->default();
        $request->attributes->set('market', $market);

        $addressId = $request->input('address_id');
        if (!$addressId) {
            $addressId = Address::where('user_id', $user->id)->value('id');
        }

        $data = [
            'payment_type' => $request->input('payment_type'),
            'address_id' => $addressId,
            'use_wallet' => $request->boolean('use_wallet') ? '1' : '0',
            'promo_code' => $request->input('promo_code'),
            'redirect_url' => $request->input('redirect_url'),
            'idempotency_key' => $request->input('idempotency_key'),
        ];

        $result = app(OrderService::class)->createOrder($user, $data);

        $order = $result['success'] ? $result['data'] : null;

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
            'stripe_pk' => $this->stripePublishableKey(),
            'order' => $order ? $this->orderSnapshot($order) : ($result['data'] ?? null),
        ]);
    }

    private function stripePublishableKey(): ?string
    {
        return app(\App\Services\SettingService::class)
            ->getSettingByVariable(\App\Enums\SettingTypeEnum::PAYMENT())
            ->value['stripePublishableKey'] ?? null;
    }

    public function simulateCapture(int $orderId): JsonResponse
    {
        $this->guard();

        OrderPaymentTransaction::where('order_id', $orderId)
            ->update(['payment_status' => PaymentStatusEnum::COMPLETED(), 'message' => 'Captured via dev tester']);

        Order::capturePayment($orderId);
        OrderItem::capturePayment($orderId);

        return response()->json(['success' => true, 'order' => $this->orderSnapshot(Order::find($orderId))]);
    }

    public function retryPayment(int $orderId): JsonResponse
    {
        $this->guard();

        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $user = User::find($order->user_id);
        Auth::login($user);

        $result = app(OrderService::class)->retryOrderPayment($user, $order->slug);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
            'stripe_pk' => $this->stripePublishableKey(),
            'order' => $this->orderSnapshot($result['success'] ? $result['data'] : Order::find($orderId)),
        ]);
    }

    public function cancelPayment(int $orderId): JsonResponse
    {
        $this->guard();

        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        $user = User::find($order->user_id);
        Auth::login($user);

        $result = app(OrderService::class)->cancelOrderPayment($user, $order->slug);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'] ?? null,
            'order' => $this->orderSnapshot($result['success'] ? $result['data'] : Order::find($orderId)),
        ]);
    }

    public function simulateFail(int $orderId): JsonResponse
    {
        $this->guard();

        OrderPaymentTransaction::where('order_id', $orderId)
            ->update(['payment_status' => PaymentStatusEnum::FAILED(), 'message' => 'Failed via dev tester']);

        Order::paymentFailed($orderId);
        OrderItem::paymentFailed($orderId);

        return response()->json(['success' => true, 'order' => $this->orderSnapshot(Order::find($orderId))]);
    }

    public function snapshot(int $orderId): JsonResponse
    {
        $this->guard();

        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        return response()->json(['success' => true, 'order' => $this->orderSnapshot($order)]);
    }

    private function orderSnapshot(Order $order): array
    {
        $order->loadMissing(['items.sellerOrderItem.sellerOrder']);

        $items = $order->items->map(function (OrderItem $item) {
            $locationId = $item->sellerOrderItem?->sellerOrder?->store_location_id;
            $inventory = $locationId
                ? StoreLocationInventory::where('store_location_id', $locationId)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first()
                : null;

            return [
                'order_item_id' => $item->id,
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'item_status' => $item->status,
                'location_id' => $locationId,
                'stock' => $inventory?->stock,
                'reserved' => $inventory?->reserved,
                'available' => $inventory ? max(0, (int) $inventory->stock - (int) $inventory->reserved) : null,
            ];
        })->values();

        $transaction = OrderPaymentTransaction::where('order_id', $order->id)->latest('id')->first();

        return [
            'id' => $order->id,
            'slug' => $order->slug,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'status' => $order->status,
            'stock_status' => $order->stock_status,
            'total_payable' => $order->total_payable,
            'currency_code' => $order->currency_code,
            'payment_response' => $order->payment_response ?? null,
            'transaction' => $transaction ? [
                'id' => $transaction->id,
                'transaction_id' => $transaction->transaction_id,
                'payment_status' => $transaction->payment_status,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
            ] : null,
            'items' => $items,
        ];
    }
}
