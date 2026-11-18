<?php

namespace App\Services\Shipping\Providers;

use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Models\OrderItemReturn;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Services\Shipping\Contracts\ShipmentProvider;

/**
 * Local pickup: the customer collects from the store location. No carrier, no
 * tracking. Prepared in store (LABEL_PRINTED) → ready (CONFIRMED) → handed over
 * (DELIVERED).
 *
 * Ported as-is from the previous inline ShipmentService logic to avoid any
 * behaviour change. The full seller-handled local pickup/delivery experience
 * (KM range, pickup instructions) is a later phase.
 */
class LocalPickupProvider implements ShipmentProvider
{
    public function code(): string
    {
        return (string) FulfillmentMethodEnum::LOCAL_PICKUP();
    }

    public function allowedTransitions(): array
    {
        $S = fn ($c) => (string) $c;

        return [
            $S(ShipmentStatusEnum::LABEL_PRINTED()) => [
                $S(ShipmentStatusEnum::CONFIRMED()),
                $S(ShipmentStatusEnum::DELIVERED()),
                $S(ShipmentStatusEnum::CANCELLED()),
            ],
            $S(ShipmentStatusEnum::CONFIRMED()) => [
                $S(ShipmentStatusEnum::DELIVERED()),
                $S(ShipmentStatusEnum::CANCELLED()),
            ],
            $S(ShipmentStatusEnum::DELIVERED()) => [
                $S(ShipmentStatusEnum::CANCELLED()),
            ],
        ];
    }

    public function buildShipmentAttributes(SellerOrder $sellerOrder, array $data): array
    {
        $initial = !empty($data['tracking_number'])
            ? ShipmentStatusEnum::CONFIRMED()
            : ShipmentStatusEnum::LABEL_PRINTED();

        return [
            'manual_carrier_name' => $data['carrier_name'] ?? null,
            'tracking_number'     => $data['tracking_number'] ?? null,
            'tracking_url'        => $data['tracking_url'] ?? null,
            'status'              => (string) $initial,
        ];
    }

    public function bookShipment(Shipment $shipment): Shipment
    {
        return $shipment;
    }

    public function cancelRemote(Shipment $shipment): void
    {
    }

    public function updateRemoteLines(Shipment $shipment, array $lines): void
    {
    }

    public function mapWebhookStatus(array $payload): ?string
    {
        return null;
    }

    public function requestReturnPickup(OrderItemReturn $return): ?Shipment
    {
        return null;
    }
}
