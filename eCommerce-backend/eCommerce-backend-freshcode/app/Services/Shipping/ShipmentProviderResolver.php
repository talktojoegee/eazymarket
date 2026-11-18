<?php

namespace App\Services\Shipping;

use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Services\Shipping\Contracts\ShipmentProvider;
use App\Services\Shipping\Providers\LocalPickupProvider;
use App\Services\Shipping\Providers\ManualProvider;
use App\Services\Shipping\Providers\ShiprocketProvider;

/**
 * Resolves a fulfillment-method string to its {@see ShipmentProvider}.
 *
 * This is the single registration point: adding a provider = construct it here
 * and add a match arm. Methods with no provider (today: local_delivery)
 * resolve to null and are treated as "not supported yet" — the
 * same behaviour as the previous hardcoded whitelist in ShipmentService.
 */
class ShipmentProviderResolver
{
    public function __construct(
        protected ManualProvider $manual,
        protected LocalPickupProvider $localPickup,
        protected ShiprocketProvider $shiprocket,
    ) {
    }

    public function resolve(string $method): ?ShipmentProvider
    {
        return match ($method) {
            (string) FulfillmentMethodEnum::MANUAL()       => $this->manual,
            (string) FulfillmentMethodEnum::LOCAL_PICKUP() => $this->localPickup,
            (string) FulfillmentMethodEnum::SHIPROCKET()   => $this->shiprocket,
            default => null,
        };
    }

    public function resolveOrFail(string $method): ShipmentProvider
    {
        $provider = $this->resolve($method);
        if ($provider === null) {
            throw new \RuntimeException(__('labels.fulfillment_method_not_supported_yet'));
        }

        return $provider;
    }

    public function isSupported(string $method): bool
    {
        return $this->resolve($method) !== null;
    }
}
