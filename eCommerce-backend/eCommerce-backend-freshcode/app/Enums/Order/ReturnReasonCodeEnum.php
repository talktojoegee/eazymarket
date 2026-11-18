<?php

namespace App\Enums\Order;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * Predefined return reasons (Shopify-style). Each reason classifies the return
 * as seller-fault or customer-fault, which drives who pays return shipping
 * (see ReturnReasonTypeEnum) and whether a restocking fee is appropriate.
 *
 * @method static DAMAGED()
 * @method static DEFECTIVE()
 * @method static WRONG_ITEM()
 * @method static NOT_AS_DESCRIBED()
 * @method static ARRIVED_LATE()
 * @method static NO_LONGER_NEEDED()
 * @method static SIZE_FIT()
 * @method static OTHER()
 */
enum ReturnReasonCodeEnum: string
{
    use InvokableCases, Values, Names;

    case DAMAGED = 'damaged';
    case DEFECTIVE = 'defective';
    case WRONG_ITEM = 'wrong_item';
    case NOT_AS_DESCRIBED = 'not_as_described';
    case ARRIVED_LATE = 'arrived_late';
    case NO_LONGER_NEEDED = 'no_longer_needed';
    case SIZE_FIT = 'size_fit';
    case OTHER = 'other';

    /** Seller-fault vs customer-fault classification for this reason. */
    public function faultType(): ReturnReasonTypeEnum
    {
        return match ($this) {
            self::DAMAGED,
            self::DEFECTIVE,
            self::WRONG_ITEM,
            self::NOT_AS_DESCRIBED,
            self::ARRIVED_LATE => ReturnReasonTypeEnum::SELLER_FAULT,
            default => ReturnReasonTypeEnum::CUSTOMER_FAULT,
        };
    }

    /**
     * "Critical" reasons that require proof — the customer must attach at least
     * one photo AND write an explanation (damaged / defective / wrong item /
     * not as described). Stops unsubstantiated seller-fault claims.
     */
    public function requiresEvidence(): bool
    {
        return match ($this) {
            self::DAMAGED,
            self::DEFECTIVE,
            self::WRONG_ITEM,
            self::NOT_AS_DESCRIBED => true,
            default => false,
        };
    }

    /** Human label for UI dropdowns. */
    public function label(): string
    {
        return match ($this) {
            self::DAMAGED => 'Item arrived damaged',
            self::DEFECTIVE => 'Item is defective or not working',
            self::WRONG_ITEM => 'Wrong item was sent',
            self::NOT_AS_DESCRIBED => 'Not as described',
            self::ARRIVED_LATE => 'Arrived too late',
            self::NO_LONGER_NEEDED => 'No longer needed / changed mind',
            self::SIZE_FIT => 'Size or fit issue',
            self::OTHER => 'Other',
        };
    }

    /** [value => label] map for building select inputs. */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }
        return $out;
    }
}
