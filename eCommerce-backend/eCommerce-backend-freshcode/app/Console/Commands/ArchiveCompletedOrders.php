<?php

namespace App\Console\Commands;

use App\Enums\Order\OrderStatusEnum;
use App\Models\Order;
use App\Services\FulfillmentRollupService;
use App\Services\OrderSettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-archive completed orders.
 *
 * An OPEN order is archived once it has been fully resolved (all lines delivered/
 * terminal, no open return) for long enough that it's safe to finalise:
 *
 *   archive when  now >= MAX( latest return_deadline across items,
 *                             resolved_at + orderAutoArchiveDays )
 *
 * The return-window half protects returnable items; the grace half (a system
 * setting) is the buffer for late refund requests on non-returnable items. The
 * grace clock counts from `resolved_at` (set when the order first became fully
 * resolved, reset on reopen) — not creation, not raw updated_at.
 *
 * Archiving has no money effect today. When seller settlement is wired (stage 2),
 * this transition is its trigger — see the marked hook below.
 */
class ArchiveCompletedOrders extends Command
{
    protected $signature = 'orders:auto-archive';

    protected $description = 'Archive fully-resolved orders once their return window + grace period has passed';

    public function handle(FulfillmentRollupService $rollup, OrderSettlementService $settlement): int
    {
        $graceDays = $rollup->graceDays();
        $graceCutoff = now()->subDays($graceDays);

        $open = (string) OrderStatusEnum::OPEN();
        $archived = (string) OrderStatusEnum::ARCHIVED();
        $today = now()->startOfDay();

        $archivedCount = 0;
        $scanned = 0;

        Order::query()
            ->where('status', $open)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '<=', $graceCutoff)
            ->with(['items.returns'])
            ->chunkById(200, function ($orders) use ($rollup, $settlement, $today, $archived, &$archivedCount, &$scanned) {
                foreach ($orders as $order) {
                    $scanned++;
                    try {
                        if (! $rollup->isFullyResolved($order->items)) {
                            continue;
                        }

                        $windowOpen = $order->items->contains(function ($i) use ($today) {
                            return $i->return_eligible
                                && $i->return_deadline
                                && $i->return_deadline->startOfDay()->gte($today);
                        });
                        if ($windowOpen) {
                            continue;
                        }

                        DB::transaction(function () use ($order, $archived, $settlement) {
                            $order->update(['status' => $archived]);
                            $settlement->settleOrder($order);
                        });
                        $archivedCount++;
                    } catch (\Throwable $e) {
                        Log::warning('Auto-archive failed for order', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Auto-archive: scanned {$scanned}, archived {$archivedCount} (grace {$graceDays}d).");

        return self::SUCCESS;
    }
}
