<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Fulfillment rollup status — a separate axis from payment status and from the
 * granular per-package ShipmentStatusEnum (Shopify's three-axis model).
 *
 * Used on `orders`, `seller_orders`, and `order_items`. It is DERIVED upward
 * from shipments, never the source of truth:
 *
 *   shipment.status (ShipmentStatusEnum)  ← set directly (seller / carrier)
 *        ↓ rollup
 *   order_item.fulfillment_status
 *        ↓ rollup
 *   seller_order.fulfillment_status
 *        ↓ rollup
 *   order.fulfillment_status
 *
 * Derivation (see FulfillmentRollupService, Phase 2):
 *   FULFILLED            – all child units terminal-success (DELIVERED / PICKED_UP)
 *   PARTIALLY_FULFILLED  – some, but not all, child units fulfilled
 *   UNFULFILLED          – none fulfilled (default at order creation)
 *   IN_PROGRESS          – seller manually flagged fulfillment as underway
 *   ON_HOLD              – seller paused fulfillment
 *
 * IN_PROGRESS / ON_HOLD / UNFULFILLED are also the three values a seller can set
 * BY HAND from the fulfillment card (see manuallySettable()). They may only be
 * set while the seller order has no live shipment; once a shipment exists the
 * status is derived from shipments again (FulfillmentRollupService).
 *
 * @method static UNFULFILLED()
 * @method static PARTIALLY_FULFILLED()
 * @method static IN_PROGRESS()
 * @method static ON_HOLD()
 * @method static FULFILLED()
 */
enum FulfillmentStatusEnum: string
{
    use InvokableCases, Names, Values;

    case UNFULFILLED         = 'unfulfilled';
    case PARTIALLY_FULFILLED = 'partially_fulfilled';
    case FULFILLED           = 'fulfilled';
    case IN_PROGRESS         = 'in_progress';
    case ON_HOLD             = 'on_hold';

    /** Statuses that still have outstanding (unfulfilled) units. */
    public static function open(): array
    {
        return [
            self::UNFULFILLED(),
            self::PARTIALLY_FULFILLED(),
            self::IN_PROGRESS(),
            self::ON_HOLD(),
        ];
    }

    /**
     * The values a seller may set by hand from the fulfillment card (Shopify's
     * "Mark as in progress / on hold / unfulfilled"). Allowed only before any
     * shipment exists — see FulfillmentRollupService::setManualStatus().
     */
    public static function manuallySettable(): array
    {
        return [
            self::UNFULFILLED(),
            self::IN_PROGRESS(),
            self::ON_HOLD(),
        ];
    }

    /**
     * Pre-shipment states a product's "cancellable until" policy may be set to.
     * Cancellation is a pre-parcel action — once a shipment exists the customer
     * uses the return flow instead — so only the no-parcel-yet states qualify.
     */
    public static function cancellableTillOptions(): array
    {
        return [
            self::UNFULFILLED(),
            self::IN_PROGRESS(),
            self::ON_HOLD(),
        ];
    }

    /**
     * Given a product's `cancelable_till` threshold, the set of CURRENT fulfillment
     * states (as strings) in which a customer may still cancel:
     *   - unfulfilled            => strict: only before the seller starts.
     *   - in_progress / on_hold  => any pre-shipment state (no parcel created yet).
     * A null/legacy value defaults to the strict UNFULFILLED-only window.
     */
    public static function cancellableWhileIn(?string $till): array
    {
        return match ((string) $till) {
            (string) self::IN_PROGRESS(), (string) self::ON_HOLD() => [
                (string) self::UNFULFILLED(),
                (string) self::IN_PROGRESS(),
                (string) self::ON_HOLD(),
            ],
            default => [(string) self::UNFULFILLED()],
        };
    }
}
