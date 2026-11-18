<?php

namespace App\Console\Commands;

use App\Models\SharedList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpiredSharedList extends Command
{
    protected $signature = 'expiredsharedlist';

    protected $description = 'Delete shared cart links that have expired after seven days';

    public function handle(): int
    {
        $deleted = SharedList::query()
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now())
            ->delete();

        $this->info("Deleted {$deleted} expired shared cart link(s).");
        Log::info('Deleted expired shared cart links.', [
            'deleted' => $deleted,
        ]);

        return self::SUCCESS;
    }
}
