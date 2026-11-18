<?php

namespace App\Observers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\WatchBuyReelProduct;
use App\Models\WatchBuyStatusProduct;
use Illuminate\Database\Eloquent\Model;

class WatchBuyAttachmentObserver
{
    public function deleted(Model $model): void
    {
        match (true) {
            $model instanceof Product => $this->deleteAttachments('product_id', $model->id),
            $model instanceof Store => $this->deleteAttachments('store_id', $model->id),
            $model instanceof ProductVariant => $this->deleteAttachments('product_variant_id', $model->id),
            default => null,
        };
    }

    private function deleteAttachments(string $column, int $id): void
    {
        WatchBuyReelProduct::where($column, $id)->delete();
        WatchBuyStatusProduct::where($column, $id)->delete();
    }
}
