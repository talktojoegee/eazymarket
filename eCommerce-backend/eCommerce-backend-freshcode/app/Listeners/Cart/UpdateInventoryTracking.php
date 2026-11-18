<?php

namespace App\Listeners\Cart;

use App\Enums\StockInventoryTypeEnum;
use App\Events\Cart\ItemAddedToCart;
use App\Events\Cart\ItemRemovedFromCart;
use App\Models\StoreInventoryLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class UpdateInventoryTracking
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
    }

    /**
     * Handle item added to cart event.
     */
    public function handleItemAdded(ItemAddedToCart $event): void
    {
    }

    /**
     * Handle item removed from cart event.
     */
    public function handleItemRemoved(ItemRemovedFromCart $event): void
    {
    }
}
