<?php

namespace App\Console\Commands;

use App\Services\Search\ProductSearchIndexer;
use Illuminate\Console\Command;

class RebuildProductSearchIndexCommand extends Command
{
    protected $signature = 'search:rebuild-products {--chunk=200}';

    protected $description = 'Rebuild the denormalized products.search_text index used by product search';

    public function handle(ProductSearchIndexer $indexer): int
    {
        $chunk = max(1, (int) $this->option('chunk'));

        $this->info('Rebuilding product search index...');

        $total = $indexer->rebuildAll(
            fn (int $count) => $this->output->write('.'),
            $chunk
        );

        $this->newLine();
        $this->info("Indexed {$total} products.");

        return self::SUCCESS;
    }
}
