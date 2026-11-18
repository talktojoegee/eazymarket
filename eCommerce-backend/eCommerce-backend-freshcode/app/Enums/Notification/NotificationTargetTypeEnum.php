<?php

namespace App\Enums\Notification;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static PRODUCT()
 * @method static BRAND()
 * @method static CATEGORY()
 * @method static STORE()
 */
enum NotificationTargetTypeEnum: string
{
    use InvokableCases, Values, Names;

    case PRODUCT = 'product';
    case BRAND = 'brand';
    case CATEGORY = 'category';
    case STORE = 'store';
}
