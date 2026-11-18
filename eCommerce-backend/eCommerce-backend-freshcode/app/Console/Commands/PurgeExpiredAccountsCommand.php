<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Anonymises and releases accounts that have been soft-deleted longer than the
 * configured grace window (config/account.php → deletion_grace_days).
 *
 * The row is kept (so FK-linked orders / wallet / settlement history survive),
 * but its PII is stripped and its unique identifiers (email, mobile,
 * firebase_uid) are released so they can be registered again.
 */
class PurgeExpiredAccountsCommand extends Command
{
    protected $signature = 'accounts:purge-expired';

    protected $description = 'Anonymise and release identifiers for accounts soft-deleted beyond the restore window';

    public function handle(): int
    {
        $cutoff = now()->subDays(User::deletionGraceDays());
        $count = 0;

        User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->where(function ($q) {
                $q->whereNotNull('mobile')
                    ->orWhere('email', 'not like', 'deleted+%@deleted.invalid');
            })
            ->orderBy('id')
            ->chunkById(100, function ($users) use (&$count) {
                foreach ($users as $user) {
                    try {
                        $user->anonymizeAndRelease();
                        $count++;
                    } catch (\Throwable $e) {
                        Log::error('Failed to anonymise expired account '.$user->id.': '.$e->getMessage());
                    }
                }
            });

        $this->info("Anonymised and released {$count} expired account(s).");

        return self::SUCCESS;
    }
}
