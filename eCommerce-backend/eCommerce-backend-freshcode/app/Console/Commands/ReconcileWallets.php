<?php

namespace App\Console\Commands;

use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;

class ReconcileWallets extends Command
{
    protected $signature = 'wallet:reconcile {--type= : Limit to one wallet type} {--tolerance=0.01 : Drift below this is ignored}';

    protected $description = 'Report wallets whose balance disagrees with the sum of their completed transactions';

    private const CREDIT_TYPES = ['deposit', 'refund', 'referral_bonus', 'delivery_boy_referral_bonus'];

    public function handle(): int
    {
        $tolerance = (float) $this->option('tolerance');

        $query = Wallet::query();
        if ($type = $this->option('type')) {
            $query->where('type', $type);
        }

        $drifted = [];
        $checked = 0;

        $query->chunkById(200, function ($wallets) use (&$drifted, &$checked, $tolerance) {
            foreach ($wallets as $wallet) {
                $checked++;

                $credits = (float) WalletTransaction::where('wallet_id', $wallet->id)
                    ->where('status', WalletTransactionStatusEnum::COMPLETED())
                    ->whereIn('transaction_type', self::CREDIT_TYPES)
                    ->sum('amount');

                $debits = (float) WalletTransaction::where('wallet_id', $wallet->id)
                    ->where('status', WalletTransactionStatusEnum::COMPLETED())
                    ->whereNotIn('transaction_type', self::CREDIT_TYPES)
                    ->sum('amount');

                $expected = round($credits - $debits, 2);
                $actual = round((float) $wallet->balance, 2);
                $delta = round($actual - $expected, 2);

                if (abs($delta) > $tolerance) {
                    $drifted[] = [
                        $wallet->id,
                        $wallet->user_id,
                        $wallet->type instanceof \BackedEnum ? $wallet->type->value : $wallet->type,
                        number_format($expected, 2),
                        number_format($actual, 2),
                        number_format($delta, 2),
                    ];
                }
            }
        });

        $this->info("Checked {$checked} wallet(s).");

        if (empty($drifted)) {
            $this->info('No drift found.');

            return self::SUCCESS;
        }

        $this->warn(count($drifted).' wallet(s) disagree with their ledger:');
        $this->table(['Wallet', 'User', 'Type', 'Ledger', 'Balance', 'Drift'], $drifted);
        $this->warn('Balances were NOT modified. Investigate each row before adjusting.');

        return self::FAILURE;
    }
}
