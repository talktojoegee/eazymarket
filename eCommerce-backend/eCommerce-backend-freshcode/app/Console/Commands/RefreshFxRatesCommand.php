<?php

namespace App\Console\Commands;

use App\Services\Fx\FxRateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Refresh foreign-exchange rates for every active market.
 *
 * Run on a daily schedule (see routes/console.php) ~30 minutes after ECB
 * publishes (16:00 CET), so 17:00 UTC is the safe default.
 *
 *   php artisan fx:refresh-rates
 */
class RefreshFxRatesCommand extends Command
{
    protected $signature = 'fx:refresh-rates';

    protected $description = 'Pull latest FX rates from the configured provider and snapshot per-market rows.';

    public function handle(FxRateService $fx): int
    {
        try {
            $summary = $fx->refresh();
        } catch (\Throwable $e) {
            $this->error("FX refresh failed: {$e->getMessage()}");
            Log::error('fx:refresh-rates failed', ['exception' => $e]);
            return self::FAILURE;
        }

        $missing = $summary['missing'] === [] ? '—' : implode(', ', $summary['missing']);

        $this->info(sprintf(
            'FX refresh (%s, base=%s): %d updated, %d unchanged, missing=%s',
            $summary['provider'],
            $summary['base'],
            $summary['updated'],
            $summary['skipped'],
            $missing,
        ));

        Log::info('fx:refresh-rates completed', $summary);

        if ($summary['missing'] !== []) {
            $this->warn('Some currencies could not be refreshed: ' . implode(', ', $summary['missing']));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
