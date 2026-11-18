<?php

namespace App\Services;

use App\Enums\Seller\SellerSettlementStatusEnum;
use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Models\Seller;
use App\Models\SellerStatement;

/**
 * Canonical "move one pending statement into the seller wallet" step.
 *
 * A CREDIT adds to the seller wallet (their earning); a DEBIT deducts (amount the
 * seller owes the platform — legacy COD self-ship / return clawback). On success
 * the statement is marked SETTLED with the acting admin (or null for the auto-settle
 * cron). Throws on any failure so the caller's DB transaction can roll back.
 *
 * Both the admin manual-settle flows and the settlements:auto-settle cron route
 * through this so the money logic lives in exactly one place.
 */
class SettlementProcessor
{
    public function __construct(
        protected WalletService $walletService,
        protected SellerStatementService $statementService,
    ) {
    }

    /**
     * Settle one PENDING statement into the seller wallet.
     *
     * @param int|null $byUserId Admin who triggered it, or null for the auto-settle cron.
     * @throws \Exception on hold, missing seller, or wallet failure.
     */
    public function settle(SellerStatement $statement, ?int $byUserId): void
    {
        $locked = SellerStatement::whereKey($statement->id)->lockForUpdate()->first();

        if (!$locked
            || (string) $locked->settlement_status === (string) SellerSettlementStatusEnum::SETTLED()) {
            throw new \Exception("Statement #{$statement->id} is already settled");
        }

        $statement = $locked;

        if ($statement->on_hold) {
            throw new \Exception("Statement #{$statement->id} is on hold");
        }

        $seller = $statement->orderItem?->store?->seller ?? Seller::find($statement->seller_id);
        if (!$seller) {
            throw new \Exception("Seller not found for statement #{$statement->id}");
        }

        $isDebit = (string) $statement->entry_type === (string) SellerSettlementTypeEnum::DEBIT();
        $amount = (float) $statement->amount;
        $storeId = $statement->orderItem?->store?->id;

        if ($isDebit) {
            $txData = [
                'amount' => $amount,
                'payment_method' => 'commission_debit_settlement',
                'idempotency_key' => 'STMT-DEBIT-' . $statement->id,
                'transaction_reference' => 'STMT-DEBIT-' . $statement->id,
                'description' => $statement->description ?? ('Debit settlement for return/order #' . ($statement->order_id ?? '')),
                'order_id' => $statement->order_id,
                'store_id' => $storeId,
            ];
            $result = WalletService::deductBalance($seller->user_id, $txData, WalletTypeEnum::SELLER);
            $refPrefix = 'STMT-DEBIT-';
        } else {
            $walletResult = $this->walletService->getWallet($seller->user_id, WalletTypeEnum::SELLER);
            if (!($walletResult['success'] ?? false)) {
                throw new \Exception('Failed to get seller wallet: ' . ($walletResult['message'] ?? ''));
            }
            $txData = [
                'amount' => $amount,
                'payment_method' => 'commission_settlement',
                'idempotency_key' => 'STMT-' . $statement->id,
                'transaction_reference' => 'STMT-' . $statement->id,
                'description' => $statement->description ?? ('Commission settlement for order #' . ($statement->order_id ?? '')),
                'order_id' => $statement->order_id,
                'store_id' => $storeId,
            ];
            $result = $this->walletService->addBalance($seller->user_id, $txData, WalletTypeEnum::SELLER);
            $refPrefix = 'STMT-';
        }

        if (!($result['success'] ?? false)) {
            throw new \Exception($result['message'] ?? 'Wallet operation failed');
        }

        $this->statementService->markSettled(
            $statement,
            $result['data']['transaction']['id'] ?? ($refPrefix . $statement->id),
            settledBy: $byUserId
        );
    }
}
