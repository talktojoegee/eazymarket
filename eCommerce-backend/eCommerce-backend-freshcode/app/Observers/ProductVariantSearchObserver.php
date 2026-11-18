<?php

namespace App\Observers;

use App\Models\ProductVariant;
use App\Services\Search\ProductSearchIndexer;
use Illuminate\Support\Facades\Log;

class ProductVariantSearchObserver
{
    public function __construct(private readonly ProductSearchIndexer $indexer) {}

    public function saved(ProductVariant $variant): void
    {
        if (! $variant->wasChanged(['title', 'sku', 'barcode']) && ! $variant->wasRecentlyCreated) {
            return;
        }

        $this->reindex($variant);
    }

    public function deleted(ProductVariant $variant): void
    {
        $this->reindex($variant);
    }

    private function reindex(ProductVariant $variant): void
    {
        try {
            if ($variant->product_id) {
                $this->indexer->queue((int) $variant->product_id);
            }
        } catch (\Throwable $e) {
            Log::warning('Product search reindex failed for variant '.$variant->id.': '.$e->getMessage());
        }
    }
}
