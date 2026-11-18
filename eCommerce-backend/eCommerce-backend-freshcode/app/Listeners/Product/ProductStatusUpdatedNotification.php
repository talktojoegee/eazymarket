<?php

namespace App\Listeners\Product;

use App\Events\Product\ProductStatusAfterUpdate;
use App\Notifications\ProductStatusUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProductStatusUpdatedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(ProductStatusAfterUpdate $event): void
    {
        Log::info('Product status updated', [
            'product_id' => $event->product->id,
            'product_title' => $event->product->title,
            'product_status' => $event->product->status,
            'verification_status' => $event->product->verification_status,
            'seller_id' => $event->product->seller_id,
        ]);

        $seller = $event->product->seller ? $event->product->seller->user : null;

        if ($seller) {
            $seller->notify(new ProductStatusUpdated($event));
        } else {
            Log::warning('Could not send product status notification to seller', [
                'seller_id' => $event->product->seller_id,
            ]);
        }
    }
}
