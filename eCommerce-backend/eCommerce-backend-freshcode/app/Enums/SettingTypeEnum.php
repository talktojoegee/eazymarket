<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static PAYMENT()
 * @method static AUTHENTICATION()
 * @method static NOTIFICATION()
 * @method static WEB()
 * @method static APP()
 * @method static DELIVERY_BOY()
 * @method static SELLER()
 * @method static HOME_GENERAL_SETTINGS()
 * @method static SUBSCRIPTION()
 * @method static SYSTEM()
 * @method static STORAGE()
 * @method static EMAIL()
 * @method static SYSTEM_UPDATE_SETTINGS()
 * @method static ADVERTISEMENT()
 * @method static POS_SETTINGS()
 * @method static SHIPPING()
 * @method static WATCH_AND_BUY()
 * @method static BROADCAST_DRIVER()
 * @method static WHATSAPP()
 */
enum SettingTypeEnum: string
{
    use InvokableCases, Names, Values;
    case SYSTEM = 'system';
    case STORAGE = 'storage';
    case EMAIL = 'email';
    case PAYMENT = 'payment';
    case AUTHENTICATION = 'authentication';
    case NOTIFICATION = 'notification';
    case WEB = 'web';
    case APP = 'app';
    case DELIVERY_BOY = 'delivery_boy';
    case SELLER = 'seller';
    case HOME_GENERAL_SETTINGS = 'home_general_settings';
    case SUBSCRIPTION = 'subscription';
    case SYSTEM_UPDATE_SETTINGS = 'system_update_settings';
    case ADVERTISEMENT = 'advertisement';
    case POS_SETTINGS = 'pos_settings';
    case SHIPPING = 'shipping';
    case WATCH_AND_BUY = 'watch_and_buy';
    case BROADCAST_DRIVER = 'broadcast_driver';
    case WHATSAPP = 'whatsapp';
}
