<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static PROFILE_IMAGE()
 * @method static PRODUCT_MAIN_IMAGE()
 * @method static PRODUCT_ADDITIONAL_IMAGE()
 * @method static PRODUCT_VIDEO()
 * @method static ADDRESS_PROOF()
 * @method static VOIDED_CHECK()
 * @method static VARIANT_IMAGE()
 * @method static REVIEW_IMAGES()
 * @method static DRIVER_LICENSE()
 * @method static VEHICLE_REGISTRATION()
 * @method static CATEGORY_BANNER()
 * @method static CATEGORY_IMAGE()
 * @method static BUSINESS_LICENSE()
 * @method static ARTICLES_OF_INCORPORATION()
 * @method static NATIONAL_IDENTITY_CARD()
 * @method static AUTHORIZED_SIGNATURE()
 * @method static STORE_LOGO()
 * @method static STORE_BANNER()
 * @method static ITEM_RETURN_IMAGES()
 * @method static RETURN_LABEL()
 * @method static ORDER_ITEM_ATTACHMENTS()
 * @method static SWATCHE_IMAGE()
 * @method static APP_NOTIFICATION_IMAGE()
 */
enum SpatieMediaCollectionName: string
{
    use InvokableCases, Names, Values;

    case PRODUCT_MAIN_IMAGE = 'main_image';
    case PRODUCT_ADDITIONAL_IMAGE = 'product_additional_image';
    case PRODUCT_VIDEO = 'product_video';
    case ADDRESS_PROOF = 'address_proof';
    case VOIDED_CHECK = 'voided_check';
    case VARIANT_IMAGE = 'variant_image';

    case REVIEW_IMAGES = 'review_images';

    case DRIVER_LICENSE = 'driver_license';
    case VEHICLE_REGISTRATION = 'vehicle_registration';
    case PROFILE_IMAGE = 'profile_image';
    case CATEGORY_BANNER = 'banner';
    case CATEGORY_IMAGE = 'image';
    case BUSINESS_LICENSE = 'business_license';
    case ARTICLES_OF_INCORPORATION = 'articles_of_incorporation';
    case NATIONAL_IDENTITY_CARD = 'national_identity_card';
    case AUTHORIZED_SIGNATURE = 'authorized_signature';
    case STORE_LOGO = 'store_logo';
    case STORE_BANNER = 'store_banner';

    case ITEM_RETURN_IMAGES = 'item_return_images';

    case RETURN_LABEL = 'return_label';

    case ORDER_ITEM_ATTACHMENTS = 'order_item_attachments';

    case SWATCHE_IMAGE = 'swatche_image';

    case APP_NOTIFICATION_IMAGE = 'app_notification_image';

    case WATCH_BUY_PROFILE_PHOTO = 'watch_buy_profile_photo';
    case WATCH_BUY_REEL_VIDEO = 'watch_buy_reel_video';
    case WATCH_BUY_REEL_COVER = 'watch_buy_reel_cover';
    case WATCH_BUY_STATUS_MEDIA = 'watch_buy_status_media';

    case HOME_SECTION_BACKGROUND = 'home_section_background';
    case HOME_SECTION_HERO_MEDIA = 'home_section_hero_media';
    case HOME_SECTION_CARD_BACKGROUND = 'home_section_card_background';
    case HOME_SECTION_ROW_BACKGROUND = 'home_section_row_background';
    case HOME_SECTION_ITEM_IMAGE = 'home_section_item_image';
    case HOME_SECTION_ITEM_MOBILE_IMAGE = 'home_section_item_mobile_image';
    case HOME_NAVBAR_APP_ICON = 'home_navbar_app_icon';
    case HOME_NAVBAR_APP_ACTIVE_ICON = 'home_navbar_app_active_icon';
    case HOME_NAVBAR_APP_BACKGROUND = 'home_navbar_app_background';
    case HOME_NAVBAR_APP_TABLET_BACKGROUND = 'home_navbar_app_tablet_background';
    case HOME_NAVBAR_DESKTOP_ICON = 'home_navbar_desktop_icon';
    case HOME_NAVBAR_DESKTOP_ACTIVE_ICON = 'home_navbar_desktop_active_icon';
    case HOME_NAVBAR_DESKTOP_BACKGROUND = 'home_navbar_desktop_background';
    case HOME_NAVBAR_DESKTOP_TABLET_BACKGROUND = 'home_navbar_desktop_tablet_background';
    case HOME_NAVBAR_DESKTOP_MOBILE_BACKGROUND = 'home_navbar_desktop_mobile_background';
}
