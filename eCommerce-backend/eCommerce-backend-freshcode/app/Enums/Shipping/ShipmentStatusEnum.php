<?php

namespace App\Enums\Shipping;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Normalised shipment tracking status (Shopify `shipment_status` vocabulary).
 * Manual today — the seller types courier + tracking and sets the value by hand.
 * Carrier integrations (Shiprocket, …) later map their codes onto these via the
 * provider mapper, including the carrier-only RTO / lost states kept behind that
 * hook.
 *
 * State machine:
 *
 *   label_printed → confirmed → in_transit → out_for_delivery → delivered
 *                                       ↘ attempted_delivery → failure
 *                                       ↘ returned
 *                                       ↘ cancelled
 *   (carrier hook only: rto_initiated → rto_delivered, lost)
 *
 * @method static PENDING()
 * @method static LABEL_PRINTED()
 * @method static CONFIRMED()
 * @method static IN_TRANSIT()
 * @method static OUT_FOR_DELIVERY()
 * @method static DELIVERED()
 * @method static ATTEMPTED_DELIVERY()
 * @method static FAILURE()
 * @method static RETURNED()
 * @method static CANCELLED()
 * @method static RTO_INITIATED()
 * @method static RTO_DELIVERED()
 * @method static LOST()
 */
enum ShipmentStatusEnum: string
{
    use InvokableCases, Values, Names;

    case PENDING            = 'pending';
    case LABEL_PRINTED      = 'label_printed';
    case CONFIRMED          = 'confirmed';
    case IN_TRANSIT         = 'in_transit';
    case OUT_FOR_DELIVERY   = 'out_for_delivery';
    case DELIVERED          = 'delivered';
    case ATTEMPTED_DELIVERY = 'attempted_delivery';
    case FAILURE            = 'failure';
    case RETURNED           = 'returned';
    case CANCELLED          = 'cancelled';
    case RTO_INITIATED      = 'rto_initiated';
    case RTO_DELIVERED      = 'rto_delivered';
    case LOST               = 'lost';

    /** Terminal statuses — no further transitions allowed. */
    public static function terminal(): array
    {
        return [
            self::DELIVERED(),
            self::RETURNED(),
            self::CANCELLED(),
            self::RTO_DELIVERED(),
            self::LOST(),
        ];
    }

    /** Statuses indicating the package is physically in transit with a carrier. */
    public static function inFlight(): array
    {
        return [
            self::CONFIRMED(),
            self::IN_TRANSIT(),
            self::OUT_FOR_DELIVERY(),
            self::ATTEMPTED_DELIVERY(),
        ];
    }

    public static function dispatched(): array
    {
        return [
            self::CONFIRMED(), self::IN_TRANSIT(), self::OUT_FOR_DELIVERY(), self::DELIVERED(),
            self::ATTEMPTED_DELIVERY(), self::FAILURE(), self::RETURNED(),
            self::RTO_INITIATED(), self::RTO_DELIVERED(), self::LOST(),
        ];
    }
}
