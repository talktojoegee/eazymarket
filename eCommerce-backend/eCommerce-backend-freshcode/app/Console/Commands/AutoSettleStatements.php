<?php

namespace App\Console\Commands;

use App\Enums\Seller\SellerSettlementStatusEnum;
use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Models\SellerStatement;
use App\Services\SettlementProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-settle matured seller earnings into the seller wallet.
 *
 * Replaces the "admin clicks settle on every entry" flow with a payout cycle:
 * once an entry has matured (available_at <= now) it is credited to the seller
 * wallet automatically — no manual action needed.
 *
 * Scope (deliberately conservative):
 *   - CREDIT entries only. DEBITs (seller owes the platform) can push a wallet
 *     negative, so they stay manual for admin review.
 *   - Skips on_hold entries (admin has parked them).
 *   - Skips entries with no available_at (should not happen post-migration).
 *
 * Gated behind the `autoSettleEnabled` system setting (default off) so nothing is
 * paid automatically until the admin opts in. Manual settle keeps working regardless.
 * Runs daily; each entry is settled in its own transaction so one failure never
 * blocks the rest.
 */
class AutoSettleStatements extends Command
{
    protected $signature = 'settlements:auto-settle';

    protected $description = 'Settle matured seller earnings (credits) into the seller wallet when auto-settle is enabled';

    public function handle(SettlementProcessor $processor): int
    {
        $settled = 0;
        $failed = 0;
        $scanned = 0;

        SellerStatement::query()
            ->where('settlement_status', SellerSettlementStatusEnum::PENDING())
            ->where('entry_type', SellerSettlementTypeEnum::CREDIT())
            ->where('on_hold', false)
            ->whereNotNull('available_at')
            ->where('available_at', '<=', now())
            ->with(['orderItem.store.seller'])
            ->orderBy('id')
            ->chunkById(200, function ($statements) use ($processor, &$settled, &$failed, &$scanned) {
                foreach ($statements as $statement) {
                    $scanned++;
                    try {
                        DB::transaction(function () use ($processor, $statement) {
                            $processor->settle($statement, null);
                        });
                        $settled++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::warning('Auto-settle failed for statement', [
                            'statement_id' => $statement->id,
                            'seller_id' => $statement->seller_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Auto-settle: scanned {$scanned}, settled {$settled}, failed {$failed}.");

        return self::SUCCESS;
    }
}
