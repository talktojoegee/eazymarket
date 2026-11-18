<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Search\ProductSearchIndexer;
use Illuminate\Support\Facades\Log;

class ProductSearchObserver
{
    public function __construct(private readonly ProductSearchIndexer $indexer) {}

    public function saved(Product $product): void
    {
        $this->reindex($product);
    }

    public function restored(Product $product): void
    {
        $this->reindex($product);
    }

    private function reindex(Product $product): void
    {
        try {
            $this->indexer->queue($product->id);
        } catch (\Throwable $e) {
            Log::warning('Product search reindex failed for product '.$product->id.': '.$e->getMessage());
        }
    }
}
