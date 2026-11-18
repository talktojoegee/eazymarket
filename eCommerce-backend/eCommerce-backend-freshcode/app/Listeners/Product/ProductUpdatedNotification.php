<?php

namespace App\Listeners\Product;

use App\Enums\GuardNameEnum;
use App\Events\Product\ProductAfterUpdate;
use App\Models\User;
use App\Notifications\ProductUpdated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProductUpdatedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(ProductAfterUpdate $event): void
    {
        Log::info('Product updated', [
            'product_id' => $event->product->id,
            'product_title' => $event->product->title,
            'seller_id' => $event->product->seller_id,
        ]);

        $adminUsers = User::where('access_panel', GuardNameEnum::ADMIN())->get();

        foreach ($adminUsers as $admin) {
            $admin->notify(new ProductUpdated($event));
        }
    }
}
