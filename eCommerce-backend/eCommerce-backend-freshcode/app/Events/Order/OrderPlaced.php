<?php

namespace App\Events\Order;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderPlaced implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public Order $order;
    public object $orderItem;
    /**
     * Create a new event instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->orderItem = $order->items;
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('orders.admin')];
        $sellerIds = $this->order->sellerOrders()->distinct()->pluck('seller_id');

        foreach ($sellerIds as $sellerId) {
            $channels[] = new PrivateChannel('orders.seller.'.$sellerId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => (int) $this->order->id,
            'order_slug' => $this->order->slug,
            'placed_at' => now()->toIso8601String(),
        ];
    }

    public function broadcastWhen(): bool
    {
        $broadcastSettings = app(\App\Services\BroadcastSettingsService::class);

        try {
            if (! $broadcastSettings->isConfigured()) {
                return false;
            }

            $broadcastSettings->apply();

            return true;
        } catch (\Throwable $exception) {
            Log::warning('New order popup broadcast setup failed', [
                'order_id' => $this->order->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
