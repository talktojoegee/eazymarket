<?php

namespace App\Enums;

use ArchTech\Enums\InvokableCases;
use ArchTech\Enums\Names;
use ArchTech\Enums\Values;

/**
 * @method static REPORT_VIEW()
 * @method static REPORT_CATEGORY_VIEW()
 * @method static REPORT_BRAND_VIEW()
 * @method static REPORT_PRODUCT_VIEW()
 * @method static REPORT_SELLER_VIEW()
 * @method static REPORT_STORE_VIEW()
 * @method static REPORT_EARNING_VIEW()
 * @method static REPORT_TAX_VIEW()
 * @method static DASHBOARD_VIEW()
 * @method static HOME_BUILDER_VIEW()
 * @method static HOME_BUILDER_EDIT()
 * @method static CATEGORY_VIEW()
 * @method static CATEGORY_EDIT()
 * @method static CATEGORY_DELETE()
 * @method static CATEGORY_CREATE()
 * @method static CATEGORY_EXPORT()
 * @method static ZONE_PREVIEW_VIEW()
 * @method static BRAND_VIEW()
 * @method static BRAND_EDIT()
 * @method static BRAND_DELETE()
 * @method static BRAND_CREATE()
 * @method static BRAND_EXPORT()
 * @method static SELLER_EDIT()
 * @method static SELLER_DELETE()
 * @method static SELLER_CREATE()
 * @method static SELLER_VIEW()
 * @method static ROLE_CREATE()
 * @method static ROLE_EDIT()
 * @method static ROLE_DELETE()
 * @method static ROLE_VIEW()
 * @method static ROLE_PERMISSIONS_VIEW()
 * @method static ROLE_PERMISSIONS_EDIT()
 * @method static TAX_CLASS_VIEW()
 * @method static TAX_CLASS_CREATE()
 * @method static TAX_CLASS_EDIT()
 * @method static TAX_CLASS_DELETE()
 * @method static TAX_RATE_VIEW()
 * @method static TAX_RATE_CREATE()
 * @method static TAX_RATE_EDIT()
 * @method static TAX_RATE_DELETE()
 * @method static MARKET_VIEW()
 * @method static MARKET_CREATE()
 * @method static MARKET_EDIT()
 * @method static MARKET_DELETE()
 * @method static CURRENCY_VIEW()
 * @method static CURRENCY_CREATE()
 * @method static CURRENCY_EDIT()
 * @method static CURRENCY_DELETE()
 * @method static SYSTEM_USER_CREATE()
 * @method static SYSTEM_USER_EDIT()
 * @method static SYSTEM_USER_DELETE()
 * @method static SYSTEM_USER_VIEW()
 * @method static FAQ_CREATE()
 * @method static FAQ_EDIT()
 * @method static FAQ_DELETE()
 * @method static SELLER_WITHDRAWAL_VIEW()
 * @method static SELLER_WITHDRAWAL_PROCESS()
 * @method static COMMISSION_VIEW()
 * @method static COMMISSION_SETTLE()
 * @method static ORDER_VIEW()
 * @method static ORDER_EDIT()
 * @method static ORDER_REFUND()
 * @method static ORDER_PAYMENT_ACCEPT()
 * @method static RETURN_VIEW()
 * @method static PRODUCT_VIEW()
 * @method static PRODUCT_CREATE()
 * @method static PRODUCT_EDIT()
 * @method static PRODUCT_DELETE()
 * @method static PRODUCT_STATUS_UPDATE()
 * @method static PRODUCT_FAQS_VIEW()
 * @method static PROMO_CREATE()
 * @method static PROMO_EDIT()
 * @method static PROMO_DELETE()
 * @method static PROMO_VIEW()
 * @method static NOTIFICATION_CREATE()
 * @method static NOTIFICATION_EDIT()
 * @method static NOTIFICATION_DELETE()
 * @method static NOTIFICATION_VIEW()
 * @method static SUPPORT_VIEW()
 * @method static SUPPORT_REPLY()
 * @method static SUPPORT_ASSIGN()
 * @method static SUPPORT_MANAGE()
 * @method static SUBSCRIPTION_PLAN_VIEW()
 * @method static SUBSCRIPTION_PLAN_CREATE()
 * @method static SUBSCRIPTION_PLAN_EDIT()
 * @method static SUBSCRIPTION_PLAN_DELETE()
 * @method static SUBSCRIPTION_SUBSCRIBER_VIEW()
 * @method static STORE_VIEW()
 * @method static STORE_VERIFY()
 * @method static STORE_LOCATION_VIEW()
 * @method static STORE_LOCATION_CREATE()
 * @method static STORE_LOCATION_EDIT()
 * @method static STORE_LOCATION_DELETE()
 *
 * Module-wise setting permissions
 * @method static SETTING_SYSTEM_VIEW()
 * @method static SETTING_SYSTEM_EDIT()
 * @method static SETTING_STORAGE_VIEW()
 * @method static SETTING_STORAGE_EDIT()
 * @method static SETTING_EMAIL_VIEW()
 * @method static SETTING_EMAIL_EDIT()
 * @method static SETTING_PAYMENT_VIEW()
 * @method static SETTING_PAYMENT_EDIT()
 * @method static SETTING_AUTHENTICATION_VIEW()
 * @method static SETTING_AUTHENTICATION_EDIT()
 * @method static SETTING_NOTIFICATION_VIEW()
 * @method static SETTING_NOTIFICATION_EDIT()
 * @method static SETTING_WEB_VIEW()
 * @method static SETTING_WEB_EDIT()
 * @method static SETTING_APP_VIEW()
 * @method static SETTING_APP_EDIT()
 * @method static SETTING_SYSTEM_UPDATE_SETTINGS_VIEW()
 * @method static SETTING_SYSTEM_UPDATE_SETTINGS_EDIT()
 * @method static SETTING_HOME_GENERAL_SETTINGS_VIEW()
 * @method static SETTING_HOME_GENERAL_SETTINGS_EDIT()
 * @method static SETTING_ADVERTISEMENT_VIEW()
 * @method static SETTING_ADVERTISEMENT_EDIT()
 * @method static SETTING_POS_SETTINGS_VIEW()
 * @method static SETTING_POS_SETTINGS_EDIT()
 * @method static SETTING_SHIPPING_VIEW()
 * @method static SETTING_SHIPPING_EDIT()
 * @method static SETTING_BROADCAST_DRIVER_VIEW()
 * @method static SETTING_BROADCAST_DRIVER_EDIT()
 * @method static SETTING_WHATSAPP_VIEW()
 * @method static SETTING_WHATSAPP_EDIT()
 * @method static FAQ_VIEW()
 * @method static AD_CAMPAIGN_VIEW()
 * @method static AD_CAMPAIGN_APPROVE()
 * @method static AD_CAMPAIGN_REJECT()
 * @method static AD_CAMPAIGN_FORCE_STOP()
 * @method static AD_CAMPAIGN_DASHBOARD_VIEW()
 * @method static BADGE_VIEW()
 * @method static BADGE_CREATE()
 * @method static BADGE_EDIT()
 * @method static BADGE_DELETE()
 * @method static ONBOARDING_CREATE()
 * @method static ONBOARDING_EDIT()
 * @method static ONBOARDING_DELETE()
 * @method static SPLASH_SCREEN_VIEW()
 * @method static SPLASH_SCREEN_EDIT()
 * @method static CRON_MONITOR_VIEW()
 * @method static CRON_MONITOR_RUN()
 * @method static POS_DASHBOARD_VIEW()
 */
enum AdminPermissionEnum: string
{
    use InvokableCases, Names, Values;

    case DASHBOARD_VIEW = 'dashboard.view';
    case HOME_BUILDER_VIEW = 'home_builder.view';
    case HOME_BUILDER_EDIT = 'home_builder.edit';
    case LANDING_PAGE_BUILDER_VIEW = 'landing_page_builder.view';
    case LANDING_PAGE_BUILDER_EDIT = 'landing_page_builder.edit';
    case CATEGORY_CREATE = 'category.create';
    case CATEGORY_EDIT = 'category.edit';
    case CATEGORY_DELETE = 'category.delete';
    case CATEGORY_VIEW = 'category.view';
    case CATEGORY_EXPORT = 'category.export';
    case BRAND_CREATE = 'brand.create';
    case BRAND_EXPORT = 'brand.export';
    case BRAND_EDIT = 'brand.edit';
    case BRAND_DELETE = 'brand.delete';
    case BRAND_VIEW = 'brand.view';
    case SELLER_CREATE = 'seller.create';
    case SELLER_EDIT = 'seller.edit';
    case SELLER_DELETE = 'seller.delete';
    case SELLER_VIEW = 'seller.view';
    case SETTING_VIEW_ALL = 'setting.view.all';
    case SETTING_VIEW = 'setting.view';
    case SETTING_EDIT = 'setting.edit';
    case SETTING_SYSTEM_VIEW = 'setting.system.view';
    case SETTING_SYSTEM_EDIT = 'setting.system.edit';
    case SETTING_STORAGE_VIEW = 'setting.storage.view';
    case SETTING_STORAGE_EDIT = 'setting.storage.edit';
    case SETTING_EMAIL_VIEW = 'setting.email.view';
    case SETTING_EMAIL_EDIT = 'setting.email.edit';
    case SETTING_PAYMENT_VIEW = 'setting.payment.view';
    case SETTING_PAYMENT_EDIT = 'setting.payment.edit';
    case SETTING_AUTHENTICATION_VIEW = 'setting.authentication.view';
    case SETTING_AUTHENTICATION_EDIT = 'setting.authentication.edit';
    case SETTING_NOTIFICATION_VIEW = 'setting.notification.view';
    case SETTING_NOTIFICATION_EDIT = 'setting.notification.edit';
    case SETTING_WEB_VIEW = 'setting.web.view';
    case SETTING_WEB_EDIT = 'setting.web.edit';
    case SETTING_APP_VIEW = 'setting.app.view';
    case SETTING_APP_EDIT = 'setting.app.edit';
    case SETTING_SELLER_VIEW = 'setting.seller.view';
    case SETTING_SELLER_EDIT = 'setting.seller.edit';
    case SETTING_SYSTEM_UPDATE_SETTINGS_VIEW = 'setting.system_update_settings.view';
    case SETTING_SYSTEM_UPDATE_SETTINGS_EDIT = 'setting.system_update_settings.edit';
    case SETTING_HOME_GENERAL_SETTINGS_VIEW = 'setting.home_general_settings.view';
    case SETTING_HOME_GENERAL_SETTINGS_EDIT = 'setting.home_general_settings.edit';
    case SETTING_ADVERTISEMENT_VIEW = 'setting.advertisement.view';
    case SETTING_ADVERTISEMENT_EDIT = 'setting.advertisement.edit';
    case SETTING_POS_SETTINGS_VIEW = 'setting.pos_settings.view';
    case SETTING_POS_SETTINGS_EDIT = 'setting.pos_settings.edit';
    case SETTING_SHIPPING_VIEW = 'setting.shipping.view';
    case SETTING_SHIPPING_EDIT = 'setting.shipping.edit';
    case SETTING_BROADCAST_DRIVER_VIEW = 'setting.broadcast_driver.view';
    case SETTING_BROADCAST_DRIVER_EDIT = 'setting.broadcast_driver.edit';
    case SETTING_WATCH_AND_BUY_VIEW = 'setting.watch_and_buy.view';
    case SETTING_WATCH_AND_BUY_EDIT = 'setting.watch_and_buy.edit';
    case SETTING_WHATSAPP_VIEW = 'setting.whatsapp.view';
    case SETTING_WHATSAPP_EDIT = 'setting.whatsapp.edit';
    case SETTING_SELLER_LANDING_VIEW = 'setting.seller_landing.view';
    case SETTING_SELLER_LANDING_EDIT = 'setting.seller_landing.edit';
    case ROLE_CREATE = 'role.create';
    case ROLE_EDIT = 'role.edit';
    case ROLE_DELETE = 'role.delete';
    case ROLE_VIEW = 'role.view';
    case ROLE_PERMISSIONS_VIEW = 'role.permission.view';
    case ROLE_PERMISSIONS_EDIT = 'role.permission.edit';
    case TAX_CLASS_VIEW = 'tax_class.view';
    case TAX_CLASS_CREATE = 'tax_class.create';
    case TAX_CLASS_EDIT = 'tax_class.edit';
    case TAX_CLASS_DELETE = 'tax_class.delete';
    case TAX_RATE_VIEW = 'tax_rate.view';
    case TAX_RATE_CREATE = 'tax_rate.create';
    case TAX_RATE_EDIT = 'tax_rate.edit';
    case TAX_RATE_DELETE = 'tax_rate.delete';
    case MARKET_VIEW = 'market.view';
    case MARKET_CREATE = 'market.create';
    case MARKET_EDIT = 'market.edit';
    case MARKET_DELETE = 'market.delete';
    case LANGUAGE_VIEW = 'language.view';
    case LANGUAGE_CREATE = 'language.create';
    case LANGUAGE_EDIT = 'language.edit';
    case LANGUAGE_DELETE = 'language.delete';
    case CURRENCY_VIEW = 'currency.view';
    case CURRENCY_CREATE = 'currency.create';
    case CURRENCY_EDIT = 'currency.edit';
    case CURRENCY_DELETE = 'currency.delete';
    case SYSTEM_USER_CREATE = 'system_user.create';
    case SYSTEM_USER_EDIT = 'system_user.edit';
    case SYSTEM_USER_DELETE = 'system_user.delete';
    case SYSTEM_USER_VIEW = 'system_user.view';
    case FAQ_CREATE = 'faq.create';
    case FAQ_EDIT = 'faq.edit';
    case FAQ_DELETE = 'faq.delete';
    case FAQ_VIEW = 'faq.view';
    case CUSTOMER_WALLET_VIEW = 'customer_wallet.view';
    case CUSTOMER_WALLET_PROCESS = 'customer_wallet.process';
    case SELLER_WITHDRAWAL_VIEW = 'seller_withdrawal.view';
    case SELLER_WITHDRAWAL_PROCESS = 'seller_withdrawal.process';
    case COMMISSION_VIEW = 'commission.view';
    case COMMISSION_SETTLE = 'commission.settle';
    case ORDER_VIEW = 'orders.view';
    case ORDER_EDIT = 'orders.edit';
    case ORDER_REFUND = 'orders.refund';
    case ORDER_PAYMENT_ACCEPT = 'orders.payment_accept';
    case ORDER_EXPORT = 'orders.export';
    case RETURN_VIEW = 'return.view';
    case RETURN_EXPORT = 'return.export';
    case PRODUCT_VIEW = 'product.view';
    case PRODUCT_CREATE = 'product.create';
    case PRODUCT_EDIT = 'product.edit';
    case PRODUCT_DELETE = 'product.delete';
    case PRODUCT_STATUS_UPDATE = 'product.status_update';
    case PRODUCT_EXPORT = 'product.export';
    case WATCH_BUY_PROFILE_VIEW = 'watch_buy.profile.view';
    case WATCH_BUY_PROFILE_EDIT = 'watch_buy.profile.edit';
    case WATCH_BUY_PROFILE_SUSPEND = 'watch_buy.profile.suspend';
    case WATCH_BUY_REEL_VIEW = 'watch_buy.reel.view';
    case WATCH_BUY_REEL_CREATE = 'watch_buy.reel.create';
    case WATCH_BUY_REEL_EDIT = 'watch_buy.reel.edit';
    case WATCH_BUY_REEL_DELETE = 'watch_buy.reel.delete';
    case WATCH_BUY_REEL_BLOCK = 'watch_buy.reel.block';
    case WATCH_BUY_STATUS_VIEW = 'watch_buy.status.view';
    case WATCH_BUY_STATUS_CREATE = 'watch_buy.status.create';
    case WATCH_BUY_STATUS_DELETE = 'watch_buy.status.delete';
    case WATCH_BUY_BLOG_CATEGORY_VIEW = 'watch_buy.blog_category.view';
    case WATCH_BUY_BLOG_CATEGORY_CREATE = 'watch_buy.blog_category.create';
    case WATCH_BUY_BLOG_CATEGORY_EDIT = 'watch_buy.blog_category.edit';
    case WATCH_BUY_BLOG_CATEGORY_DELETE = 'watch_buy.blog_category.delete';
    case WATCH_BUY_BLOG_VIEW = 'watch_buy.blog.view';
    case WATCH_BUY_BLOG_CREATE = 'watch_buy.blog.create';
    case WATCH_BUY_BLOG_EDIT = 'watch_buy.blog.edit';
    case WATCH_BUY_BLOG_DELETE = 'watch_buy.blog.delete';
    case PRODUCT_FAQS_VIEW = 'product_faqs.view';
    case PROMO_CREATE = 'promo.create';
    case PROMO_EDIT = 'promo.edit';
    case PROMO_DELETE = 'promo.delete';
    case PROMO_VIEW = 'promo.view';
    case NOTIFICATION_CREATE = 'notification.create';
    case NOTIFICATION_EDIT = 'notification.edit';
    case NOTIFICATION_DELETE = 'notification.delete';
    case NOTIFICATION_VIEW = 'notification.view';
    case SUPPORT_VIEW = 'support.view';
    case SUPPORT_REPLY = 'support.reply';
    case SUPPORT_ASSIGN = 'support.assign';
    case SUPPORT_MANAGE = 'support.manage';
    case STORE_VIEW = 'store.view';
    case STORE_VERIFY = 'store.verify';
    case STORE_LOCATION_VIEW = 'store_location.view';
    case STORE_LOCATION_CREATE = 'store_location.create';
    case STORE_LOCATION_EDIT = 'store_location.edit';
    case STORE_LOCATION_DELETE = 'store_location.delete';
    case CUSTOMER_VIEW = 'customer.view';
    case CUSTOMER_EXPORT = 'customer.export';
    case SELLER_EXPORT = 'seller.export';
    case COMMISSION_EXPORT = 'commission.export';
    case SELLER_WITHDRAWAL_EXPORT = 'seller_withdrawal.export';
    //    case AD_CAMPAIGN_VIEW = 'ad_campaign.view';
    //    case AD_CAMPAIGN_APPROVE = 'ad_campaign.approve';
    //    case AD_CAMPAIGN_REJECT = 'ad_campaign.reject';
    //    case AD_CAMPAIGN_FORCE_STOP = 'ad_campaign.force_stop';
    //    case AD_CAMPAIGN_DASHBOARD_VIEW = 'ad_campaign.dashboard_view';
    case BADGE_VIEW = 'badge.view';
    case BADGE_CREATE = 'badge.create';
    case BADGE_EDIT = 'badge.edit';
    case BADGE_DELETE = 'badge.delete';
    case ONBOARDING_CREATE = 'onboarding.create';
    case ONBOARDING_EDIT = 'onboarding.edit';
    case ONBOARDING_DELETE = 'onboarding.delete';
    case SPLASH_SCREEN_VIEW = 'splash_screen.view';
    case SPLASH_SCREEN_EDIT = 'splash_screen.edit';
    case CRON_MONITOR_VIEW = 'cron_monitor.view';
    case CRON_MONITOR_RUN = 'cron_monitor.run';
    case REPORT_VIEW = 'report.view';
    case REPORT_CATEGORY_VIEW = 'report.category.view';
    case REPORT_BRAND_VIEW = 'report.brand.view';
    case REPORT_PRODUCT_VIEW = 'report.product.view';
    case REPORT_SELLER_VIEW = 'report.seller.view';
    case REPORT_STORE_VIEW = 'report.store.view';
    case REPORT_EARNING_VIEW = 'report.earning.view';
    case REPORT_TAX_VIEW = 'report.tax.view';

    public static function groupedPermissions(): array
    {
        $permissions = [
            'reports' => [
                'name' => 'Reports & Analytics',
                'permissions' => [
                    self::REPORT_VIEW(),
                    self::REPORT_CATEGORY_VIEW(),
                    self::REPORT_BRAND_VIEW(),
                    self::REPORT_PRODUCT_VIEW(),
                    self::REPORT_SELLER_VIEW(),
                    self::REPORT_STORE_VIEW(),
                    self::REPORT_EARNING_VIEW(),
                    self::REPORT_TAX_VIEW(),
                ],
            ],
            'dashboard' => [
                'name' => 'Dashboard',
                'permissions' => [
                    self::DASHBOARD_VIEW(),
                ],
            ],
            'home_builder' => [
                'name' => 'Home Page Builder',
                'permissions' => [
                    self::HOME_BUILDER_VIEW(),
                    self::HOME_BUILDER_EDIT(),
                ],
            ],
            'landing_page_builder' => [
                'name' => 'Landing Page Builder',
                'permissions' => [
                    self::LANDING_PAGE_BUILDER_VIEW(),
                    self::LANDING_PAGE_BUILDER_EDIT(),
                ],
            ],
            'orders' => [
                'name' => 'Orders',
                'permissions' => [
                    self::ORDER_VIEW(),
                    self::ORDER_EDIT(),
                    self::ORDER_REFUND(),
                    self::ORDER_PAYMENT_ACCEPT(),
                    self::ORDER_EXPORT(),
                ],
            ],
            'return' => [
                'name' => 'Return Orders',
                'permissions' => [
                    self::RETURN_VIEW(),
                    self::RETURN_EXPORT(),
                ],
            ],
            'category' => [
                'name' => 'Category',
                'permissions' => [
                    self::CATEGORY_VIEW(),
                    self::CATEGORY_CREATE(),
                    self::CATEGORY_EDIT(),
                    self::CATEGORY_DELETE(),
                    self::CATEGORY_EXPORT(),
                ],
            ],
            'brand' => [
                'name' => 'Brand',
                'permissions' => [
                    self::BRAND_VIEW(),
                    self::BRAND_CREATE(),
                    self::BRAND_EDIT(),
                    self::BRAND_DELETE(),
                    self::BRAND_EXPORT(),
                ],
            ],
            'seller' => [
                'name' => 'Seller',
                'permissions' => [
                    self::SELLER_VIEW(),
                    self::SELLER_CREATE(),
                    self::SELLER_EDIT(),
                    self::SELLER_DELETE(),
                    self::SELLER_EXPORT(),
                ],
            ],
            'commission' => [
                'name' => 'Seller Settlement',
                'permissions' => [
                    self::COMMISSION_VIEW(),
                    self::COMMISSION_SETTLE(),
                    self::COMMISSION_EXPORT(),
                ],
            ],
            'seller_withdrawal' => [
                'name' => 'Seller Withdrawals',
                'permissions' => [
                    self::SELLER_WITHDRAWAL_VIEW(),
                    self::SELLER_WITHDRAWAL_PROCESS(),
                    self::SELLER_WITHDRAWAL_EXPORT(),
                ],
            ],
            'store' => [
                'name' => 'Store',
                'permissions' => [
                    self::STORE_VIEW(),
                    self::STORE_VERIFY(),
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
            'customers' => [
                'name' => 'Customers',
                'permissions' => [
                    self::CUSTOMER_VIEW(),
                    self::CUSTOMER_EXPORT(),
                ],
            ],
            'customer_wallet' => [
                'name' => 'Customer Wallet',
                'permissions' => [
                    self::CUSTOMER_WALLET_VIEW(),
                    self::CUSTOMER_WALLET_PROCESS(),
                ],
            ],
            'setting' => [
                'name' => 'Settings',
                'permissions' => [
                    self::SETTING_VIEW_ALL(),
                    self::SETTING_VIEW(),
                    self::SETTING_EDIT(),
                ],
            ],
            'products' => [
                'name' => 'Products',
                'permissions' => [
                    self::PRODUCT_VIEW(),
                    self::PRODUCT_CREATE(),
                    self::PRODUCT_EDIT(),
                    self::PRODUCT_DELETE(),
                    self::PRODUCT_STATUS_UPDATE(),
                    self::PRODUCT_EXPORT(),
                ],
            ],
            'products_faqs' => [
                'name' => 'Products FAQs',
                'permissions' => [
                    self::PRODUCT_FAQS_VIEW(),
                ],
            ],
            'tax_class' => [
                'name' => 'Tax Classes',
                'permissions' => [
                    self::TAX_CLASS_VIEW(),
                    self::TAX_CLASS_CREATE(),
                    self::TAX_CLASS_EDIT(),
                    self::TAX_CLASS_DELETE(),
                ],
            ],
            'tax_rate' => [
                'name' => 'Tax Rates',
                'permissions' => [
                    self::TAX_RATE_VIEW(),
                    self::TAX_RATE_CREATE(),
                    self::TAX_RATE_EDIT(),
                    self::TAX_RATE_DELETE(),
                ],
            ],
            'market' => [
                'name' => 'Markets',
                'permissions' => [
                    self::MARKET_VIEW(),
                    self::MARKET_CREATE(),
                    self::MARKET_EDIT(),
                    self::MARKET_DELETE(),
                ],
            ],
            'language' => [
                'name' => 'Languages',
                'permissions' => [
                    self::LANGUAGE_VIEW(),
                    self::LANGUAGE_CREATE(),
                    self::LANGUAGE_EDIT(),
                    self::LANGUAGE_DELETE(),
                ],
            ],
            'currency' => [
                'name' => 'Currencies',
                'permissions' => [
                    self::CURRENCY_VIEW(),
                    self::CURRENCY_CREATE(),
                    self::CURRENCY_EDIT(),
                    self::CURRENCY_DELETE(),
                ],
            ],
            'promo' => [
                'name' => 'Promo',
                'permissions' => [
                    self::PROMO_VIEW(),
                    self::PROMO_CREATE(),
                    self::PROMO_EDIT(),
                    self::PROMO_DELETE(),
                ],
            ],
            'faq' => [
                'name' => 'FAQ',
                'permissions' => [
                    self::FAQ_VIEW(),
                    self::FAQ_CREATE(),
                    self::FAQ_EDIT(),
                    self::FAQ_DELETE(),
                ],
            ],
            'notification' => [
                'name' => 'Notification',
                'permissions' => [
                    self::NOTIFICATION_VIEW(),
                    self::NOTIFICATION_EDIT(),
                    self::NOTIFICATION_DELETE(),
                ],
            ],
            'support' => [
                'name' => 'Customer Support',
                'permissions' => [
                    self::SUPPORT_VIEW(),
                    self::SUPPORT_REPLY(),
                    self::SUPPORT_ASSIGN(),
                    self::SUPPORT_MANAGE(),
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
                    self::SYSTEM_USER_VIEW(),
                    self::SYSTEM_USER_CREATE(),
                    self::SYSTEM_USER_EDIT(),
                    self::SYSTEM_USER_DELETE(),
                ],
            ],
            'setting_system' => [
                'name' => 'System Settings',
                'permissions' => [
                    self::SETTING_SYSTEM_VIEW(),
                    self::SETTING_SYSTEM_EDIT(),
                ],
            ],
            'setting_storage' => [
                'name' => 'Storage Settings',
                'permissions' => [
                    self::SETTING_STORAGE_VIEW(),
                    self::SETTING_STORAGE_EDIT(),
                ],
            ],
            'setting_email' => [
                'name' => 'Email Settings',
                'permissions' => [
                    self::SETTING_EMAIL_VIEW(),
                    self::SETTING_EMAIL_EDIT(),
                ],
            ],
            'setting_broadcast_driver' => [
                'name' => 'Broadcast Driver Settings',
                'permissions' => [
                    self::SETTING_BROADCAST_DRIVER_VIEW(),
                    self::SETTING_BROADCAST_DRIVER_EDIT(),
                ],
            ],
            'setting_whatsapp' => [
                'name' => 'WhatsApp Settings',
                'permissions' => [
                    self::SETTING_WHATSAPP_VIEW(),
                    self::SETTING_WHATSAPP_EDIT(),
                ],
            ],
            'setting_payment' => [
                'name' => 'Payment Settings',
                'permissions' => [
                    self::SETTING_PAYMENT_VIEW(),
                    self::SETTING_PAYMENT_EDIT(),
                ],
            ],
            'setting_authentication' => [
                'name' => 'Authentication Settings',
                'permissions' => [
                    self::SETTING_AUTHENTICATION_VIEW(),
                    self::SETTING_AUTHENTICATION_EDIT(),
                ],
            ],
            'setting_notification' => [
                'name' => 'Notification Settings',
                'permissions' => [
                    self::SETTING_NOTIFICATION_VIEW(),
                    self::SETTING_NOTIFICATION_EDIT(),
                ],
            ],
            'setting_web' => [
                'name' => 'Web Settings',
                'permissions' => [
                    self::SETTING_WEB_VIEW(),
                    self::SETTING_WEB_EDIT(),
                ],
            ],
            'setting_seller_landing' => [
                'name' => 'Seller Landing Page',
                'permissions' => [
                    self::SETTING_SELLER_LANDING_VIEW(),
                    self::SETTING_SELLER_LANDING_EDIT(),
                ],
            ],
            'setting_app' => [
                'name' => 'App Settings',
                'permissions' => [
                    self::SETTING_APP_VIEW(),
                    self::SETTING_APP_EDIT(),
                ],
            ],
            'setting_seller' => [
                'name' => 'Seller Settings',
                'permissions' => [
                    self::SETTING_SELLER_VIEW(),
                    self::SETTING_SELLER_EDIT(),
                ],
            ],
            'setting_system_update' => [
                'name' => 'System Update Settings',
                'permissions' => [
                    self::SETTING_SYSTEM_UPDATE_SETTINGS_VIEW(),
                    self::SETTING_SYSTEM_UPDATE_SETTINGS_EDIT(),
                ],
            ],
            'setting_home_general' => [
                'name' => 'Home General Settings',
                'permissions' => [
                    self::SETTING_HOME_GENERAL_SETTINGS_VIEW(),
                    self::SETTING_HOME_GENERAL_SETTINGS_EDIT(),
                ],
            ],
            'setting_shipping' => [
                'name' => 'Shipping Settings',
                'permissions' => [
                    self::SETTING_SHIPPING_VIEW(),
                    self::SETTING_SHIPPING_EDIT(),
                ],
            ],
            'setting_watch_and_buy' => [
                'name' => __('labels.watch_buy_settings'),
                'permissions' => [
                    self::SETTING_WATCH_AND_BUY_VIEW(),
                    self::SETTING_WATCH_AND_BUY_EDIT(),
                ],
            ],
            'watch_buy_profile' => [
                'name' => __('labels.watch_buy_profiles'),
                'permissions' => [
                    self::WATCH_BUY_PROFILE_VIEW(),
                    self::WATCH_BUY_PROFILE_EDIT(),
                    self::WATCH_BUY_PROFILE_SUSPEND(),
                ],
            ],
            'watch_buy_reel' => [
                'name' => __('labels.watch_buy_reels'),
                'permissions' => [
                    self::WATCH_BUY_REEL_VIEW(),
                    self::WATCH_BUY_REEL_CREATE(),
                    self::WATCH_BUY_REEL_EDIT(),
                    self::WATCH_BUY_REEL_DELETE(),
                    self::WATCH_BUY_REEL_BLOCK(),
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
            'watch_buy_blog_category' => [
                'name' => __('labels.blog_categories'),
                'permissions' => [
                    self::WATCH_BUY_BLOG_CATEGORY_VIEW(),
                    self::WATCH_BUY_BLOG_CATEGORY_CREATE(),
                    self::WATCH_BUY_BLOG_CATEGORY_EDIT(),
                    self::WATCH_BUY_BLOG_CATEGORY_DELETE(),
                ],
            ],
            'watch_buy_blog' => [
                'name' => __('labels.blogs'),
                'permissions' => [
                    self::WATCH_BUY_BLOG_VIEW(),
                    self::WATCH_BUY_BLOG_CREATE(),
                    self::WATCH_BUY_BLOG_EDIT(),
                    self::WATCH_BUY_BLOG_DELETE(),
                ],
            ],
            'badge' => [
                'name' => 'Badges',
                'permissions' => [
                    self::BADGE_VIEW(),
                    self::BADGE_CREATE(),
                    self::BADGE_EDIT(),
                    self::BADGE_DELETE(),
                ],
            ],
            'onboarding' => [
                'name' => 'App Onboarding',
                'permissions' => [
                    self::ONBOARDING_CREATE(),
                    self::ONBOARDING_EDIT(),
                    self::ONBOARDING_DELETE(),
                ],
            ],
            'splash_screen' => [
                'name' => 'Splash Screen Builder',
                'permissions' => [
                    self::SPLASH_SCREEN_VIEW(),
                    self::SPLASH_SCREEN_EDIT(),
                ],
            ],
            'cron_monitor' => [
                'name' => 'Cron Monitor',
                'permissions' => [
                    self::CRON_MONITOR_VIEW(),
                    self::CRON_MONITOR_RUN(),
                ],
            ],
        ];

        return $permissions;
    }
}
