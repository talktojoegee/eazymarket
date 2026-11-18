<?php

namespace App\Console\Commands;

use App\Enums\Order\OrderStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Order;
use App\Services\OrderInventoryService;
use App\Services\OrderService;
use Illuminate\Console\Command;

/**
 * Cancel abandoned unpaid online orders past their TTL and release the stock
 * they reserved. Only touches orders still awaiting an online capture — the
 * `stock_status = reserved` filter inherently excludes COD/wallet orders (which
 * commit stock immediately at creation), so they are never expired.
 */
class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending {--minutes=30}';

    protected $description = 'Cancel abandoned unpaid online orders past their TTL and release reserved stock';

    public function handle(OrderService $orderService): int
    {
        $minutesOption = $this->option('minutes');

        if (!is_numeric($minutesOption) || (int) $minutesOption < 1) {
            $this->error('The --minutes option must be a positive number.');

            return self::FAILURE;
        }

        $cutoff = now()->subMinutes((int) $minutesOption);

        $count = 0;

        Order::where('status', OrderStatusEnum::OPEN())
            ->where('payment_status', PaymentStatusEnum::PENDING())
            ->where('stock_status', OrderInventoryService::STATUS_RESERVED)
            ->where('created_at', '<', $cutoff)
            ->chunkById(200, function ($orders) use ($orderService, &$count) {
                foreach ($orders as $order) {
                    $orderService->cancelReservedOrder($order);
                    $count++;
                }
            });

        $this->info("Expired {$count} pending order(s).");

        return self::SUCCESS;
    }
}
