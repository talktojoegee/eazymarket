<?php

namespace App\Services\Shipping\Contracts;

use App\Models\OrderItemReturn;
use App\Models\SellerOrder;
use App\Models\Shipment;

/**
 * Strategy for one fulfillment method / shipping provider.
 *
 * ShipmentService owns the provider-agnostic parts of fulfilment (selecting the
 * shippable lines, idempotency, the rollup, the seller settlement, and guarding
 * transitions). Everything method-specific lives behind this interface so a new
 * method is added by writing an implementation + registering it in
 * {@see \App\Services\Shipping\ShipmentProviderResolver} — with no change to the
 * engine, the controllers, or the rollup.
 *
 * Today only ManualProvider (and the ported LocalPickupProvider) are wired.
 * LocalDelivery and Shiprocket implement this later; the carrier hooks
 * (mapWebhookStatus / requestReturnPickup) exist now so that path is additive.
 */
interface ShipmentProvider
{
    /**
     * Provider/method code — matches the FulfillmentMethodEnum value and is
     * written to shipments.provider_code.
     */
    public function code(): string;

    /**
     * The per-method shipment state machine: `from => [allowed to, ...]`.
     * Terminal statuses are absent (no outgoing transitions). Drives both the
     * transition guard and the next-action buttons in the fulfilment UI.
     *
     * @return array<string, array<int, string>>
     */
    public function allowedTransitions(): array;

    /**
     * Provider-specific attributes for a NEW shipment, merged on top of the
     * generic column set ShipmentService builds. MUST include a 'status' key
     * (the initial ShipmentStatusEnum value).
     *
     * Manual records the seller-entered carrier/tracking and picks the initial
     * status from whether tracking was supplied. A carrier provider would call
     * its API here (AWB / label / pickup) and return those fields.
     *
     * @param array<string, mixed> $data  caller-supplied fields (carrier_name, tracking_number, tracking_url, weight_kg, ...)
     * @return array<string, mixed>
     */
    public function buildShipmentAttributes(SellerOrder $sellerOrder, array $data): array;

    public function bookShipment(Shipment $shipment): Shipment;

    public function cancelRemote(Shipment $shipment): void;

    public function updateRemoteLines(Shipment $shipment, array $lines): void;

    /**
     * Map an inbound carrier webhook payload onto a ShipmentStatusEnum value,
     * or null when it can't / shouldn't drive a transition. Manual and local
     * methods have no webhooks → always null.
     *
     * @param array<string, mixed> $payload
     */
    public function mapWebhookStatus(array $payload): ?string;

    /**
     * Arrange a reverse (return) pickup if the provider supports it, returning
     * the created return Shipment (is_return = true, linked via parent/return
     * shipment). Manual and local methods are handled off-platform → null.
     */
    public function requestReturnPickup(OrderItemReturn $return): ?Shipment;
}
