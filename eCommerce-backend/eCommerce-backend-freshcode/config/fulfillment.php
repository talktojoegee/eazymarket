<?php

/**
 * Fulfillment feature flags (Shopify-aligned status model). The order's state is
 * carried by the independent axes: payment_status, fulfillment_status,
 * cancellation_state, and per-shipment ShipmentStatusEnum. There is no seller
 * acceptance gate — a line is either fulfilled or cancelled.
 */
return [

    /*
     | When TRUE, seller settlement credit is created on shipment success
     | (shipment DELIVERED). The credit uses a per-order-item dedupe key so it is
     | safe to run idempotently. This is the active payer — keep TRUE.
     */
    'settle_on_shipment_success' => env('FULFILLMENT_SETTLE_ON_SHIPMENT', true),

];
