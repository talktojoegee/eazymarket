<?php

namespace App\Services;

use App\Enums\Seller\SellerSettlementStatusEnum;
use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Models\Order;
use App\Models\SellerStatement;
use Illuminate\Support\Facades\DB;
use App\Support\PricePresenter;
use App\Support\SettlementSettings;

/**
 * Creates the seller settlement entries for a completed (archived) order.
 *
 * Runs once, at the archive transition (see ArchiveCompletedOrders). For each
 * seller order it writes a PENDING SellerStatement using the SAME figure the
 * order-detail earnings breakdown shows:
 *   - platform owes seller  → CREDIT (the seller's net earning)
 *   - seller owes platform  → DEBIT  (legacy COD self-ship only)
 * The admin then settles those entries into the seller wallet through the
 * existing flow.
 *
 * Currency: amounts are stored in BASE currency, because the settle action credits
 * the (base) seller wallet 1:1 from `statement->amount`. The breakdown is in market
 * currency, so we divide by the order's FX rate (for a base-currency order the two
 * are identical). The original market amount + rate are kept in `meta`.
 *
 * Idempotent per seller order via reference_type=order_settlement — re-running is a
 * no-op. Zero amounts are skipped (no statement created).
 */
class OrderSettlementService
{
    public const REFERENCE_TYPE = 'order_settlement';

    public function __construct(
        protected OrderEarningsCalculator $earnings,
        protected SellerStatementService $statements,
    ) {
    }

    /**
     * @return array{created:int, skipped:int}
     */
    public function settleOrder(Order $order): array
    {
        $breakdown = $this->earnings->forOrder($order);
        $rate = (float) ($order->currency_rate ?: 1);
        $baseCode = PricePresenter::baseCode();

        $clearingDays = SettlementSettings::clearingDays();
        $availableAt = now()->addDays($clearingDays);

        $created = 0;
        $skipped = 0;

        foreach ($breakdown['seller_orders'] as $group) {
            $sellerOrderId = (int) ($group['seller_order_id'] ?? 0);
            $sellerId = (int) ($group['seller_id'] ?? 0);
            if ($sellerOrderId <= 0 || $sellerId <= 0) {
                $skipped++;
                continue;
            }

            $already = SellerStatement::where('reference_type', self::REFERENCE_TYPE)
                ->where('reference_id', $sellerOrderId)
                ->exists();
            if ($already) {
                $skipped++;
                continue;
            }

            $marketAmount = (float) ($group['settlement_amount'] ?? 0);
            $baseAmount = $rate > 0 ? round($marketAmount / $rate, 2) : round($marketAmount, 2);

            $marketCommission = (float) ($group['commission_total'] ?? 0);
            $baseMarketplaceFee = $rate > 0 ? round($marketCommission / $rate, 2) : round($marketCommission, 2);

            if ($baseAmount <= 0) {
                $skipped++;
                continue;
            }

            $isCredit = ($group['direction'] ?? null) === OrderEarningsCalculator::DIR_PLATFORM_OWES_SELLER;

            $this->statements->addEntry([
                'seller_id'      => $sellerId,
                'entry_type'     => $isCredit ? SellerSettlementTypeEnum::CREDIT() : SellerSettlementTypeEnum::DEBIT(),
                'amount'         => $baseAmount,
                'currency_code'  => $baseCode,
                'order_id'       => $order->id,
                'available_at'   => $availableAt,
                'reference_type' => self::REFERENCE_TYPE,
                'reference_id'   => $sellerOrderId,
                'description'    => ($isCredit ? 'Seller earning' : 'Amount owed to platform')
                    . " for Order #{$order->id} (seller order #{$sellerOrderId})",
                'meta'           => [
                    'seller_order_id' => $sellerOrderId,
                    'seller_name'     => $group['seller_name'] ?? null,
                    'market_amount'   => $marketAmount,
                    'currency_code'   => $breakdown['currency_code'] ?? null,
                    'currency_rate'   => $rate,
                    'direction'       => $group['direction'] ?? null,
                    'marketplace_fee' => $baseMarketplaceFee,
                ],
            ]);

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Reverse an order's settlement so it can be reopened. Only PENDING entries can be
     * undone (they're simply removed) — if any has already been SETTLED into a wallet,
     * reversal is refused so the admin handles the wallet adjustment deliberately.
     *
     * @return array{success:bool, reversed:int, settled:int}
     */
    public function reverseSettlement(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $statements = SellerStatement::where('reference_type', self::REFERENCE_TYPE)
                ->where('order_id', $order->id)
                ->lockForUpdate()
                ->get();

            $settled = $statements->filter(
                fn ($s) => (string) $s->settlement_status === (string) SellerSettlementStatusEnum::SETTLED()
            );
            if ($settled->isNotEmpty()) {
                return ['success' => false, 'reversed' => 0, 'settled' => $settled->count()];
            }

            $reversed = 0;
            foreach ($statements as $s) {
                $s->delete();
                $reversed++;
            }

            return ['success' => true, 'reversed' => $reversed, 'settled' => 0];
        });
    }
}
