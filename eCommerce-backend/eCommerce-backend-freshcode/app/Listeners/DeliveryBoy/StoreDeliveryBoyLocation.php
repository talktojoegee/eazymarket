<?php

namespace App\Listeners\DeliveryBoy;

use App\Events\DeliveryBoy\DeliveryBoyStatusUpdatedEvent;
use App\Models\DeliveryBoyLocation;

class StoreDeliveryBoyLocation
{

    /**
     * Create the event listener.
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     */
    public function handle(DeliveryBoyStatusUpdatedEvent $event): void
    {
        $deliveryBoy = $event->deliveryBoy;

        if ($event->latitude !== null && $event->longitude !== null) {
            DeliveryBoyLocation::updateOrCreate(
                ['delivery_boy_id' => $deliveryBoy->id],
                [
                    'latitude' => $event->latitude,
                    'longitude' => $event->longitude,
                    'recorded_at' => now(),
                ]
            );
        }
    }
}
