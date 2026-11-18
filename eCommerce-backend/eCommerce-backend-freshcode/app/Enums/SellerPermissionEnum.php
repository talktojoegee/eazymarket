<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static REPORT_VIEW()
 * @method static REPORT_SALES_VIEW()
 * @method static REPORT_PRODUCT_VIEW()
 * @method static REPORT_SETTLEMENT_VIEW()
 * @method static REPORT_RETURN_VIEW()
 * @method static REPORT_STORE_VIEW()
 * @method static REPORT_TAX_VIEW()
 * @method static CATEGORY_VIEW()
 * @method static CATEGORY_EXPORT()
 * @method static ROLE_PERMISSIONS_VIEW()
 * @method static ROLE_PERMISSIONS_EDIT()
 * @method static ROLE_VIEW()
 * @method static ROLE_CREATE()
 * @method static ROLE_EDIT()
 * @method static ROLE_DELETE()
 * @method static SYSTEM_USER_CREATE()
 * @method static SYSTEM_USER_EDIT()
 * @method static SYSTEM_USER_DELETE()
 * @method static SYSTEM_USER_VIEW()
 * @method static STORE_VIEW()
 * @method static STORE_CREATE()
 * @method static STORE_EDIT()
 * @method static STORE_DELETE()
 * @method static STORE_LOCATION_VIEW()
 * @method static STORE_LOCATION_CREATE()
 * @method static STORE_LOCATION_EDIT()
 * @method static STORE_LOCATION_DELETE()
 * @method static SHIPPING_PROFILE_VIEW()
 * @method static SHIPPING_PROFILE_CREATE()
 * @method static SHIPPING_PROFILE_EDIT()
 * @method static SHIPPING_PROFILE_DELETE()
 * @method static SHIPPING_PACKAGE_VIEW()
 * @method static SHIPPING_PACKAGE_CREATE()
 * @method static SHIPPING_PACKAGE_EDIT()
 * @method static SHIPPING_PACKAGE_DELETE()
 * @method static ATTRIBUTE_VIEW()
 * @method static ATTRIBUTE_CREATE()
 * @method static ATTRIBUTE_EDIT()
 * @method static ATTRIBUTE_DELETE()
 * @method static PRODUCT_CONDITION_CREATE()
 * @method static PRODUCT_CONDITION_EDIT()
 * @method static PRODUCT_CONDITION_DELETE()
 * @method static PRODUCT_VIEW()
 * @method static PRODUCT_CREATE()
 * @method static PRODUCT_EDIT()
 * @method static PRODUCT_DELETE()
 * @method static PRODUCT_FAQ_VIEW()
 * @method static PRODUCT_FAQ_CREATE()
 * @method static PRODUCT_FAQ_EDIT()
 * @method static PRODUCT_FAQ_DELETE()
 * @method static ORDER_VIEW()
 * @method static ORDER_EDIT()
 * @method static ORDER_UPDATE_STATUS()
 * @method static EARNING_VIEW()
 * @method static NOTIFICATION_CREATE()
 * @method static NOTIFICATION_VIEW()
 * @method static NOTIFICATION_EDIT()
 * @method static NOTIFICATION_DELETE()
 * @method static TAX_RATE_VIEW()
 * @method static WALLET_VIEW()
 * @method static WITHDRAWAL_VIEW()
 * @method static WITHDRAWAL_REQUEST()
 * @method static RETURN_VIEW()
 * @method static RETURN_DECIDE()
 * @method static BRAND_VIEW()
 * @method static BRAND_EXPORT()
 * @method static SUBSCRIPTION_VIEW()
 * @method static SUBSCRIPTION_BUY()
 * @method static DASHBOARD_VIEW()
 * @method static SELLER_DELETE()
 * @method static ADDON_GROUP_VIEW()
 * @method static ADDON_GROUP_CREATE()
 * @method static ADDON_GROUP_EDIT()
 * @method static ADDON_GROUP_DELETE()
 * @method static PRODUCT_ADDON_VIEW()
 * @method static PRODUCT_ADDON_CREATE()
 * @method static PRODUCT_ADDON_EDIT()
 * @method static PRODUCT_ADDON_DELETE()
 * @method static STORE_ADDON_ITEM_VIEW()
 * @method static STORE_ADDON_ITEM_CREATE()
 * @method static STORE_ADDON_ITEM_EDIT()
 * @method static STORE_ADDON_ITEM_DELETE()
 * @method static AD_WALLET_VIEW()
 * @method static AD_WALLET_TOPUP()
 * @method static AD_CAMPAIGN_VIEW()
 * @method static AD_CAMPAIGN_CREATE()
 * @method static AD_CAMPAIGN_PAUSE()
 * @method static AD_CAMPAIGN_DASHBOARD_VIEW()
 * @method static POS_VIEW()
 * @method static POS_CREATE_ORDER()
 * @method static POS_REFUND()
 * @method static POS_PARK_SALE()
 * @method static POS_PAYMENT_SESSION()
 */
enum SellerPermissionEnum: string
{
    use InvokableCases, Values, Names;


    case DASHBOARD_VIEW = 'dashboard.view';
    case ROLE_VIEW = 'role.view';
    case ROLE_CREATE = 'role.create';
    case ROLE_EDIT = 'role.edit';
    case ROLE_DELETE = 'role.delete';
    case ROLE_PERMISSIONS_VIEW = 'role.permission.view';
    case ROLE_PERMISSIONS_EDIT = 'role.permission.edit';
    case SYSTEM_USER_CREATE = 'system_user.create';
    case SYSTEM_USER_EDIT = 'system_user.edit';
    case SYSTEM_USER_DELETE = 'system_user.delete';
    case SYSTEM_USER_VIEW = 'system_user.view';
    case STORE_VIEW = 'store.view';
    case STORE_CREATE = 'store.create';
    case STORE_EDIT = 'store.edit';
    case STORE_DELETE = 'store.delete';
    case STORE_LOCATION_VIEW   = 'store_location.view';
    case STORE_LOCATION_CREATE = 'store_location.create';
    case STORE_LOCATION_EDIT   = 'store_location.edit';
    case STORE_LOCATION_DELETE = 'store_location.delete';
    case SHIPPING_PROFILE_VIEW   = 'shipping_profile.view';
    case SHIPPING_PROFILE_CREATE = 'shipping_profile.create';
    case SHIPPING_PROFILE_EDIT   = 'shipping_profile.edit';
    case SHIPPING_PROFILE_DELETE = 'shipping_profile.delete';
    case SHIPPING_PACKAGE_VIEW   = 'shipping_package.view';
    case SHIPPING_PACKAGE_CREATE = 'shipping_package.create';
    case SHIPPING_PACKAGE_EDIT   = 'shipping_package.edit';
    case SHIPPING_PACKAGE_DELETE = 'shipping_package.delete';
    case ATTRIBUTE_VIEW = 'attribute.view';
    case ATTRIBUTE_CREATE = 'attribute.create';
    case ATTRIBUTE_EDIT = 'attribute.edit';
    case ATTRIBUTE_DELETE = 'attribute.delete';
    case PRODUCT_CONDITION_CREATE = 'product_condition.create';
    case PRODUCT_CONDITION_EDIT = 'product_condition.edit';
    case PRODUCT_CONDITION_DELETE = 'product_condition.delete';
    case PRODUCT_VIEW = 'product.view';
    case PRODUCT_CREATE = 'product.create';
    case PRODUCT_EDIT = 'product.edit';
    case PRODUCT_DELETE = 'product.delete';
    case PRODUCT_EXPORT = 'product.export';
    case WATCH_BUY_PROFILE_VIEW = 'watch_buy.profile.view';
    case WATCH_BUY_PROFILE_EDIT = 'watch_buy.profile.edit';
    case WATCH_BUY_REEL_VIEW = 'watch_buy.reel.view';
    case WATCH_BUY_REEL_CREATE = 'watch_buy.reel.create';
    case WATCH_BUY_REEL_EDIT = 'watch_buy.reel.edit';
    case WATCH_BUY_REEL_DELETE = 'watch_buy.reel.delete';
    case WATCH_BUY_REEL_PUBLISH = 'watch_buy.reel.publish';
    case WATCH_BUY_STATUS_VIEW = 'watch_buy.status.view';
    case WATCH_BUY_STATUS_CREATE = 'watch_buy.status.create';
    case WATCH_BUY_STATUS_DELETE = 'watch_buy.status.delete';
    case PRODUCT_FAQ_VIEW = 'product_faq.view';
    case PRODUCT_FAQ_CREATE = 'product_faq.create';
    case PRODUCT_FAQ_EDIT = 'product_faq.edit';
    case PRODUCT_FAQ_DELETE = 'product_faq.delete';
    case ORDER_VIEW = 'order.view';
    case ORDER_EDIT = 'order.edit';
    case ORDER_UPDATE_STATUS = 'order.update_status';
    case ORDER_EXPORT = 'order.export';
    case EARNING_VIEW = 'earning.view';
    case EARNING_EXPORT = 'earning.export';
    case NOTIFICATION_CREATE = 'notification.create';
    case NOTIFICATION_VIEW = 'notification.view';
    case NOTIFICATION_EDIT = 'notification.edit';
    case NOTIFICATION_DELETE = 'notification.delete';
    case TAX_RATE_VIEW = 'tax_rate.view';
    case WALLET_VIEW = 'wallet.view';
    case WITHDRAWAL_VIEW = 'withdrawal.view';
    case WITHDRAWAL_REQUEST = 'withdrawal.request';
    case WITHDRAWAL_EXPORT = 'withdrawal.export';
    case RETURN_VIEW = 'return.view';
    case RETURN_DECIDE = 'return.decide';
    case RETURN_EXPORT = 'return.export';
    case CATEGORY_VIEW = 'category.view';
    case CATEGORY_EXPORT = 'category.export';
    case BRAND_VIEW = 'brand.view';
    case BRAND_EXPORT = 'brand.export';
    case SUBSCRIPTION_VIEW = 'subscription.view';
    case SUBSCRIPTION_BUY = 'subscription.buy';
    case SELLER_DELETE = 'seller.delete';
    case ADDON_GROUP_VIEW = 'addon_group.view';
    case ADDON_GROUP_CREATE = 'addon_group.create';
    case ADDON_GROUP_EDIT = 'addon_group.edit';
    case ADDON_GROUP_DELETE = 'addon_group.delete';
    case PRODUCT_ADDON_VIEW = 'product_addon.view';
    case PRODUCT_ADDON_CREATE = 'product_addon.create';
    case PRODUCT_ADDON_EDIT = 'product_addon.edit';
    case PRODUCT_ADDON_DELETE = 'product_addon.delete';
    case STORE_ADDON_ITEM_VIEW = 'store_addon_item.view';
    case STORE_ADDON_ITEM_CREATE = 'store_addon_item.create';
    case STORE_ADDON_ITEM_EDIT = 'store_addon_item.edit';
    case STORE_ADDON_ITEM_DELETE = 'store_addon_item.delete';
    case AD_WALLET_VIEW = 'ad_wallet.view';
    case AD_WALLET_TOPUP = 'ad_wallet.topup';
    case AD_CAMPAIGN_VIEW           = 'ad_campaign.view';
    case AD_CAMPAIGN_CREATE         = 'ad_campaign.create';
    case AD_CAMPAIGN_PAUSE          = 'ad_campaign.pause';
    case AD_CAMPAIGN_DASHBOARD_VIEW = 'ad_campaign.dashboard_view';
    case POS_VIEW            = 'pos.view';
    case POS_CREATE_ORDER    = 'pos.create_order';
    case POS_REFUND          = 'pos.refund';
    case POS_PARK_SALE       = 'pos.park_sale';
    case POS_PAYMENT_SESSION = 'pos.payment_session';
    case REPORT_VIEW = 'report.view';
    case REPORT_SALES_VIEW = 'report.sales.view';
    case REPORT_PRODUCT_VIEW = 'report.product.view';
    case REPORT_SETTLEMENT_VIEW = 'report.settlement.view';
    case REPORT_RETURN_VIEW = 'report.return.view';
    case REPORT_STORE_VIEW = 'report.store.view';
    case REPORT_TAX_VIEW = 'report.tax.view';

    public static function groupedPermissions(): array
    {
        $permissions = [
            'reports' => [
                'name' => 'Reports & Analytics',
                'permissions' => [
                    self::REPORT_VIEW(),
                    self::REPORT_SALES_VIEW(),
                    self::REPORT_PRODUCT_VIEW(),
                    self::REPORT_SETTLEMENT_VIEW(),
                    self::REPORT_RETURN_VIEW(),
                    self::REPORT_STORE_VIEW(),
                    self::REPORT_TAX_VIEW(),
                ],
            ],
            'dashboard' => [
                'name' => 'Dashboard',
                'permissions' => [
                    self::DASHBOARD_VIEW(),
                ],
            ],
            'watch_buy_profile' => [
                'name' => __('labels.watch_buy_profile'),
                'permissions' => [
                    self::WATCH_BUY_PROFILE_VIEW(),
                    self::WATCH_BUY_PROFILE_EDIT(),
                ],
            ],
            'watch_buy_reel' => [
                'name' => __('labels.watch_buy_reels'),
                'permissions' => [
                    self::WATCH_BUY_REEL_VIEW(),
                    self::WATCH_BUY_REEL_CREATE(),
                    self::WATCH_BUY_REEL_EDIT(),
                    self::WATCH_BUY_REEL_DELETE(),
                    self::WATCH_BUY_REEL_PUBLISH(),
                ],
            ],
            'watch_buy_status' => [
                'name' => __('labels.watch_buy_statuses'),
                'permissions' => [
                    self::WATCH_BUY_STATUS_VIEW(),
                    self::WATCH_BUY_STATUS_CREATE(),
                    self::WATCH_BUY_STATUS_DELETE(),
                ],
            ],
            'wallet' => [
                'name' => 'Wallet',
                'permissions' => [
                    self::WALLET_VIEW(),
                ],
            ],
            'withdrawal' => [
                'name' => 'Withdrawal',
                'permissions' => [
                    self::WITHDRAWAL_VIEW(),
                    self::WITHDRAWAL_REQUEST(),
                    self::WITHDRAWAL_EXPORT(),
                ],
            ],
            'earning' => [
                'name' => 'Settlements',
                'permissions' => [
                    self::EARNING_VIEW(),
                    self::EARNING_EXPORT(),
                ],
            ],
            'order' => [
                'name' => 'Order',
                'permissions' => [
                    self::ORDER_VIEW(),
                    self::ORDER_EDIT(),
                    self::ORDER_UPDATE_STATUS(),
                    self::ORDER_EXPORT(),
                ],
            ],
            'return' => [
                'name' => 'Return Orders',
                'permissions' => [
                    self::RETURN_VIEW(),
                    self::RETURN_DECIDE(),
                    self::RETURN_EXPORT(),
                ],
            ],
            'category' => [
                'name' => 'Category',
                'permissions' => [
                    self::CATEGORY_VIEW(),
                    self::CATEGORY_EXPORT(),
                ],
            ],
            'brand' => [
                'name' => 'Brand',
                'permissions' => [
                    self::BRAND_VIEW(),
                    self::BRAND_EXPORT(),
                ],
            ],
            'seller' => [
                'name' => 'Seller Account',
                'permissions' => [
                    self::SELLER_DELETE(),
                ],
            ],
            'attribute' => [
                'name' => 'Attribute',
                'permissions' => [
                    self::ATTRIBUTE_VIEW(),
                    self::ATTRIBUTE_CREATE(),
                    self::ATTRIBUTE_EDIT(),
                    self::ATTRIBUTE_DELETE(),
                ],
            ],
            'product' => [
                'name' => 'Product',
                'permissions' => [
                    self::PRODUCT_VIEW(),
                    self::PRODUCT_CREATE(),
                    self::PRODUCT_EDIT(),
                    self::PRODUCT_DELETE(),
                    self::PRODUCT_EXPORT(),
                ],
            ],
            'addon_group' => [
                'name' => 'Addon Group',
                'permissions' => [
                    self::ADDON_GROUP_VIEW(),
                    self::ADDON_GROUP_CREATE(),
                    self::ADDON_GROUP_EDIT(),
                    self::ADDON_GROUP_DELETE(),
                ],
            ],
            'product_addon' => [
                'name' => 'Product Addon Attachment',
                'permissions' => [
                    self::PRODUCT_ADDON_VIEW(),
                    self::PRODUCT_ADDON_CREATE(),
                    self::PRODUCT_ADDON_EDIT(),
                    self::PRODUCT_ADDON_DELETE(),
                ],
            ],
            'store_addon_item' => [
                'name' => 'Store Addon Inventory',
                'permissions' => [
                    self::STORE_ADDON_ITEM_VIEW(),
                    self::STORE_ADDON_ITEM_CREATE(),
                    self::STORE_ADDON_ITEM_EDIT(),
                    self::STORE_ADDON_ITEM_DELETE(),
                ],
            ],
            'product_faq' => [
                'name' => 'Product FAQ',
                'permissions' => [
                    self::PRODUCT_FAQ_VIEW(),
                    self::PRODUCT_FAQ_CREATE(),
                    self::PRODUCT_FAQ_EDIT(),
                    self::PRODUCT_FAQ_DELETE(),
                ],
            ],
            'tax_rate' => [
                'name' => 'Tax Rate',
                'permissions' => [
                    self::TAX_RATE_VIEW(),
                ],
            ],
            'store' => [
                'name' => 'Store',
                'permissions' => [
                    self::STORE_VIEW(),
                    self::STORE_CREATE(),
                    self::STORE_EDIT(),
                    self::STORE_DELETE(),
                ],
            ],
            'store_location' => [
                'name' => 'Store Locations',
                'permissions' => [
                    self::STORE_LOCATION_VIEW(),
                    self::STORE_LOCATION_CREATE(),
                    self::STORE_LOCATION_EDIT(),
                    self::STORE_LOCATION_DELETE(),
                ],
            ],
            'shipping_profile' => [
                'name' => 'Shipping & Delivery',
                'permissions' => [
                    self::SHIPPING_PROFILE_VIEW(),
                    self::SHIPPING_PROFILE_CREATE(),
                    self::SHIPPING_PROFILE_EDIT(),
                    self::SHIPPING_PROFILE_DELETE(),
                ],
            ],
            'shipping_package' => [
                'name' => 'Shipping Packages',
                'permissions' => [
                    self::SHIPPING_PACKAGE_VIEW(),
                    self::SHIPPING_PACKAGE_CREATE(),
                    self::SHIPPING_PACKAGE_EDIT(),
                    self::SHIPPING_PACKAGE_DELETE(),
                ],
            ],
            'notification' => [
                'name' => 'Notification',
                'permissions' => [
                    self::NOTIFICATION_VIEW(),
                    self::NOTIFICATION_CREATE(),
                    self::NOTIFICATION_EDIT(),
                    self::NOTIFICATION_DELETE(),
                ],
            ],
            'role' => [
                'name' => 'Role',
                'permissions' => [
                    self::ROLE_VIEW(),
                    self::ROLE_CREATE(),
                    self::ROLE_EDIT(),
                    self::ROLE_DELETE(),
                ],
            ],
            'permission' => [
                'name' => 'Permission',
                'permissions' => [
                    self::ROLE_PERMISSIONS_VIEW(),
                    self::ROLE_PERMISSIONS_EDIT(),
                ],
            ],
            'system_user' => [
                'name' => 'System User',
                'permissions' => [
                    self::SYSTEM_USER_CREATE(),
                    self::SYSTEM_USER_EDIT(),
                    self::SYSTEM_USER_DELETE(),
                    self::SYSTEM_USER_VIEW(),
                ],
            ],
            'ad_wallet' => [
                'name' => 'Ad Wallet',
                'permissions' => [
                    self::AD_WALLET_VIEW(),
                    self::AD_WALLET_TOPUP(),
                ],
            ],
            'ad_campaign' => [
                'name' => 'Ad Campaigns',
                'permissions' => [
                    self::AD_CAMPAIGN_VIEW(),
                    self::AD_CAMPAIGN_CREATE(),
                    self::AD_CAMPAIGN_PAUSE(),
                    self::AD_CAMPAIGN_DASHBOARD_VIEW(),
                ],
            ],
            'pos' => [
                'name' => 'Point of Sale',
                'permissions' => [
                    self::POS_VIEW(),
                    self::POS_CREATE_ORDER(),
                    self::POS_REFUND(),
                    self::POS_PARK_SALE(),
                    self::POS_PAYMENT_SESSION(),
                ],
            ],

        ];

        return $permissions;
    }
}
