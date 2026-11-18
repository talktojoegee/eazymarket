<?php

namespace App\Events\Order;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShipmentDelivered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $shipmentId)
    {
    }
}
