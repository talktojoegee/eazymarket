<?php

namespace App\Listeners\Product;

use App\Enums\GuardNameEnum;
use App\Events\Product\ProductAfterCreate;
use App\Models\User;
use App\Notifications\ProductCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProductCreatedNotification implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(ProductAfterCreate $event): void
    {
        Log::info('Product created', [
            'product_id' => $event->product->id,
            'product_title' => $event->product->title,
            'seller_id' => $event->product->seller_id,
        ]);

        $adminUsers = User::where('access_panel', GuardNameEnum::ADMIN())->get();

        foreach ($adminUsers as $admin) {
            $admin->notify(new ProductCreated($event));
        }
    }
}
