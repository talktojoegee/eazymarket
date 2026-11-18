<?php

namespace App\Enums\Shipping;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * The fulfillment method chosen at checkout for a given seller_order.
 *
 * Mirrors ShippingProviderEnum value-for-value today, but kept as a distinct
 * enum because:
 *   - the consumer here is `seller_orders.fulfillment_method` (per-order choice)
 *   - the consumer of ShippingProviderEnum is `shipments.provider_code` (per-package
 *     carrier), and they could diverge in Phase 2 (e.g. fulfillment_method = SHIPROCKET
 *     but the actual package ships via a fallback provider).
 *
 * @method static SHIPROCKET()
 * @method static MANUAL()
 * @method static LOCAL_DELIVERY()
 * @method static LOCAL_PICKUP()
 */
enum FulfillmentMethodEnum: string
{
    use InvokableCases, Values, Names;

    case SHIPROCKET = 'shiprocket';
    case MANUAL = 'manual';
    case LOCAL_DELIVERY = 'local_delivery';
    case LOCAL_PICKUP = 'local_pickup';

    /** Methods that involve a third-party carrier. */
    public static function carrierBased(): array
    {
        return [self::SHIPROCKET(), self::MANUAL()];
    }

    /** Methods handled by the seller without a third-party carrier. */
    public static function sellerHandled(): array
    {
        return [self::LOCAL_DELIVERY(), self::LOCAL_PICKUP()];
    }

    /**
     * Methods where a courier collects the COD cash and remits it to the platform,
     * so Cash on Delivery is allowed. Seller-collected methods (manual / local
     * delivery / local pickup) would leave the seller holding the cash and owing
     * the platform, so COD is blocked there. Mirrors Amazon (Self-Ship is
     * prepaid-only; COD only on Easy Ship / FBA).
     */
    public static function codCapable(): array
    {
        return [self::SHIPROCKET()];
    }
}
