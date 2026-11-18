<?php

namespace App\Events\Order;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentShipped
{
    use Dispatchable, SerializesModels;

    public function __construct(public int $shipmentId)
    {
    }
}
