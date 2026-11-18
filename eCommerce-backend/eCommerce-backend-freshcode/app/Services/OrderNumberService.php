<?php

namespace App\Services;

use App\Models\Order;

class OrderNumberService
{
    public function assign(Order $order): void
    {
        if (!$order->wasRecentlyCreated || filled($order->order_number)) {
            return;
        }

        $settings = app(SettingService::class)->getSettingValues('system');
        $prefix = (string) ($settings['orderNumberPrefix'] ?? 'ORD-');
        $order->order_number = $prefix.$order->getKey();
        $order->saveQuietly();
    }
}
