<?php

return [

    'admin' => [
        // ── OVERVIEW ──
        '_section_overview' => [
            'type' => 'section',
            'title' => 'labels.menu_section_overview',
        ],
        'dashboard' => [
            'icon' => 'ti-home',
            'route' => 'admin.dashboard',
            'title' => 'labels.dashboard',
            'active' => 'dashboard',
            'permission' => 'dashboard.view',
        ],
        'orders' => [
            'icon' => 'ti-package',
            'route' => 'admin.orders.index',
            'title' => 'labels.orders',
            'active' => 'orders',
            'permission' => 'orders.view',
        ],
        'seller_landing' => [
            'icon' => 'ti-building-store',
            'route' => 'admin.seller-landing.index',
            'title' => 'labels.seller_landing_page',
            'active' => 'seller-landing',
            'permission' => 'setting.seller_landing.view',
        ],
        'returns' => [
            'icon' => 'ti-arrow-back-up',
            'route' => 'admin.orders.returns.index',
            'title' => 'labels.returns',
            'active' => 'returns',
            'permission' => 'orders.view',
        ],
        'support' => [
            'icon' => 'ti-message-circle-question',
            'title' => 'labels.customer_support',
            'active' => 'support',
            'menu_gradient' => 'success',
            'route' => [
                'inbox' => [
                    'sub_active' => 'inbox',
                    'sub_route' => 'admin.support.index',
                    'sub_title' => 'labels.inbox',
                    'permission' => 'support.view',
                ],
                'dashboard' => [
                    'sub_active' => 'dashboard',
                    'sub_route' => 'admin.support.dashboard',
                    'sub_title' => 'labels.dashboard',
                    'permission' => 'support.view',
                ],
            ],
        ],

        '_section_reports' => [
            'type' => 'section',
            'title' => 'labels.reports',
        ],
        'reports' => [
            'icon' => 'ti-chart-bar',
            'title' => 'labels.reports',
            'active' => 'reports',
            'route' => [
                'categories' => [
                    'sub_active' => 'category_report',
                    'sub_route' => 'admin.reports.categories',
                    'sub_title' => 'labels.category_report',
                    'permission' => 'report.view',
                ],
                'brands' => [
                    'sub_active' => 'brand_report',
                    'sub_route' => 'admin.reports.brands',
                    'sub_title' => 'labels.brand_report',
                    'permission' => 'report.view',
                ],
                'products' => [
                    'sub_active' => 'product_report',
                    'sub_route' => 'admin.reports.products',
                    'sub_title' => 'labels.product_report',
                    'permission' => 'report.view',
                ],
                'sellers' => [
                    'sub_active' => 'seller_report',
                    'sub_route' => 'admin.reports.sellers',
                    'sub_title' => 'labels.seller_report',
                    'permission' => 'report.view',
                ],
                'stores' => [
                    'sub_active' => 'store_report',
                    'sub_route' => 'admin.reports.stores',
                    'sub_title' => 'labels.store_report',
                    'permission' => 'report.view',
                ],
                'earnings' => [
                    'sub_active' => 'earning_report',
                    'sub_route' => 'admin.reports.earnings',
                    'sub_title' => 'labels.earning_report',
                    'permission' => 'report.view',
                ],
                'taxes' => [
                    'sub_active' => 'tax_report',
                    'sub_route' => 'admin.reports.taxes',
                    'sub_title' => 'labels.tax_report',
                    'permission' => 'report.view',
                ],
            ],
        ],

        '_section_catalog' => [
            'type' => 'section',
            'title' => 'labels.menu_section_catalog',
        ],
        'categories' => [
            'icon' => 'ti-category-2',
            'title' => 'labels.categories',
            'active' => 'categories',
            'route' => [
                'categories' => [
                    'sub_active' => 'categories',
                    'sub_route' => 'admin.categories.index',
                    'sub_title' => 'labels.categories',
                    'permission' => 'category.view',
                ],
                'bulk_upload' => [
                    'sub_active' => 'bulk_upload',
                    'sub_route' => 'admin.categories.bulk-upload.page',
                    'sub_title' => 'labels.bulk_upload',
                    'permission' => 'category.create',
                ],
            ],
        ],
        'products' => [
            'icon' => 'ti-cube-spark',
            'title' => 'labels.products',
            'active' => 'products',
            'route' => [
                'products' => [
                    'sub_active' => 'products',
                    'sub_route' => 'admin.products.index',
                    'sub_title' => 'labels.products',
                    'permission' => 'product.view',
                ],
                'bulk_upload' => [
                    'sub_active' => 'bulk_upload',
                    'sub_route' => 'admin.products.bulk-upload.page',
                    'sub_title' => 'labels.bulk_upload',
                    'permission' => 'product.create',
                ],
                'pending_approval_products' => [
                    'sub_active' => 'pending_approval_products',
                    'sub_route' => 'admin.products.index',
                    'route_param' => ['verification_status' => 'pending_verification'],
                    'sub_title' => 'labels.pending_approval_products',
                    'permission' => 'product.view',
                ],
                'attributes' => [
                    'sub_active' => 'attributes',
                    'sub_route' => 'admin.attributes.index',
                    'sub_title' => 'labels.attributes',
                    'permission' => 'product.view',
                ],
                'badges' => [
                    'sub_active' => 'badges',
                    'sub_route' => 'admin.badges.index',
                    'sub_title' => 'labels.badges',
                    'permission' => 'badge.view',
                ],
                'product_faqs' => [
                    'sub_active' => 'product_faqs',
                    'sub_route' => 'admin.product_faqs.index',
                    'sub_title' => 'labels.product_faqs',
                    'permission' => 'product_faqs.view',
                ],
            ],
        ],
        'brands' => [
            'icon' => 'ti-sparkles',
            'route' => 'admin.brands.index',
            'title' => 'labels.brands',
            'active' => 'brands',
            'permission' => 'brand.view',
        ],
        'tax_rates' => [
            'icon' => 'ti-square-rounded-percentage',
            'route' => 'admin.tax-rates.index',
            'title' => 'labels.tax_rates',
            'active' => 'tax_rates',
            'permission' => 'tax_class.view',
        ],

        '_section_builders' => [
            'type' => 'section',
            'title' => 'labels.menu_section_builders',
        ],
        'home_builder' => [
            'icon' => 'ti-layout-dashboard',
            'route' => 'admin.home-builder.index',
            'title' => 'labels.home_builder',
            'active' => 'home-builder',
            'permission' => 'home_builder.view',
        ],
        'onboarding' => [
            'icon' => 'ti-rocket',
            'route' => 'admin.onboarding.index',
            'title' => 'labels.onboarding_builder',
            'active' => 'onboarding',
        ],
        'landing_page_builder' => [
            'icon' => 'ti-browser',
            'route' => 'admin.landing-page-builder.index',
            'title' => 'labels.landing_page_builder',
            'active' => 'landing-page-builder',
            'permission' => 'landing_page_builder.view',
        ],
        'splash_screen' => [
            'icon' => 'ti-device-mobile-cog',
            'route' => 'admin.splash-screen.index',
            'title' => 'labels.splash_screen_builder',
            'active' => 'splash-screen',
        ],
        'invoice_builder' => [
            'icon' => 'ti-file-invoice',
            'route' => 'admin.orders.invoice-builder',
            'title' => 'labels.invoice_builder',
            'active' => 'invoice-builder',
            'permission' => 'orders.edit',
        ],

        // ── PEOPLE ──
        '_section_people' => [
            'type' => 'section',
            'title' => 'labels.menu_section_people',
        ],
        'customers' => [
            'icon' => 'ti-users',
            'title' => 'labels.customers',
            'active' => 'customers',
            'route' => [
                'customers' => [
                    'sub_active' => 'customers',
                    'sub_route' => 'admin.customers.index',
                    'sub_title' => 'labels.customers',
                    'permission' => 'customer.view',
                ],
                'transactions' => [
                    'sub_active' => 'transactions',
                    'sub_route' => 'admin.wallet.transactions',
                    'sub_title' => 'labels.wallet_transactions',
                    'permission' => 'orders.view',
                ],
                'deposits' => [
                    'sub_active' => 'deposits',
                    'sub_route' => 'admin.wallet.deposits',
                    'sub_title' => 'labels.pending_wallet_deposits',
                    'permission' => 'orders.view',
                ],
                'referrals' => [
                    'sub_active' => 'referrals',
                    'sub_route' => 'admin.referrals.index',
                    'sub_title' => 'labels.refer_and_earn',
                    'permission' => 'orders.view',
                ],
                'referral_earnings' => [
                    'sub_active' => 'earnings',
                    'sub_route' => 'admin.referrals.earnings',
                    'sub_title' => 'labels.referral_earnings',
                    'permission' => 'orders.view',
                ],
            ],
        ],
        'seller_management' => [
            'icon' => 'ti-users-group',
            'title' => 'labels.seller_management',
            'active' => 'sellers',
            'route' => [
                'sellers' => [
                    'sub_active' => 'sellers',
                    'sub_route' => 'admin.sellers.index',
                    'sub_title' => 'labels.sellers',
                    'permission' => 'seller.view',
                ],
                'add_sellers' => [
                    'sub_active' => 'add_sellers',
                    'sub_route' => 'admin.sellers.create',
                    'sub_title' => 'labels.add_sellers',
                    'permission' => 'seller.create',
                ],
                'seller_form_fields' => [
                    'sub_active' => 'seller_form_fields',
                    'sub_route' => 'admin.sellers.dynamic-fields.index',
                    'sub_title' => 'labels.seller_form_fields',
                    'permission' => 'seller.view',
                ],
                'earning_settlement' => [
                    'sub_active' => 'seller_earning_settlement',
                    'sub_route' => 'admin.commissions.index',
                    'sub_title' => 'labels.earning_settlement',
                    'permission' => 'commission.view',
                ],
                'seller_withdrawals' => [
                    'sub_active' => 'seller_withdrawals',
                    'sub_route' => 'admin.seller-withdrawals.index',
                    'sub_title' => 'labels.seller_withdrawals',
                    'permission' => 'seller_withdrawal.view',
                ],
                'seller_withdrawal_history' => [
                    'sub_active' => 'seller_withdrawal_history',
                    'sub_route' => 'admin.seller-withdrawals.history',
                    'sub_title' => 'labels.seller_withdrawal_history',
                    'permission' => 'seller_withdrawal.view',
                ],
            ],
        ],
        'stores' => [
            'icon' => 'ti-building-warehouse',
            'route' => 'admin.sellers.store.index',
            'title' => 'labels.stores',
            'active' => 'stores',
            'permission' => 'store.view',
        ],
        'store_locations' => [
            'icon' => 'ti-map-pin',
            'route' => 'admin.store-locations.index',
            'title' => 'labels.store_locations',
            'active' => 'store_locations',
            'permission' => 'store.view',
        ],

        // ── MARKETING ──
        '_section_marketing' => [
            'type' => 'section',
            'title' => 'labels.menu_section_marketing',
        ],
        'social_content' => [
            'icon' => 'ti-news',
            'title' => 'labels.social_and_content',
            'active' => 'social_content',
            'route' => [
                'blog_categories' => [
                    'sub_active' => 'blog_categories',
                    'sub_route' => 'admin.watch-and-buy.blog-categories.index',
                    'sub_title' => 'labels.blog_categories',
                    'permission' => 'watch_buy.blog_category.view',
                ],
                'blogs' => [
                    'sub_active' => 'blogs',
                    'sub_route' => 'admin.watch-and-buy.blogs.index',
                    'sub_title' => 'labels.blogs',
                    'permission' => 'watch_buy.blog.view',
                ],
                'statuses' => [
                    'sub_active' => 'statuses',
                    'sub_route' => 'admin.watch-and-buy.statuses.index',
                    'sub_title' => 'labels.watch_buy_statuses',
                    'permission' => 'watch_buy.status.view',
                ],
                'reels' => [
                    'sub_active' => 'reels',
                    'sub_route' => 'admin.watch-and-buy.reels.index',
                    'sub_title' => 'labels.watch_buy_reels',
                    'permission' => 'watch_buy.reel.view',
                ],
                'profiles' => [
                    'sub_active' => 'profiles',
                    'sub_route' => 'admin.watch-and-buy.profiles.index',
                    'sub_title' => 'labels.watch_buy_profiles',
                    'permission' => 'watch_buy.profile.view',
                ],
                'official_profile' => [
                    'sub_active' => 'official_profile',
                    'sub_route' => 'admin.watch-and-buy.official-profile.edit',
                    'sub_title' => 'labels.watch_buy_official_profile',
                    'permission' => 'watch_buy.profile.edit',
                ],
            ],
        ],
        'promos' => [
            'icon' => 'ti-ticket',
            'route' => 'admin.promos.index',
            'title' => 'labels.promos',
            'active' => 'promos',
            'permission' => 'promo.view',
        ],
        'ad_campaigns' => [
            'icon' => 'ti-rocket',
            'title' => 'labels.ad_campaigns',
            'active' => 'ad_campaigns',
            'permission' => 'ad_campaign.view',
            'route' => [
                'dashboard' => [
                    'sub_active' => 'ad_campaigns_dashboard',
                    'sub_route' => 'admin.ads.campaigns.dashboard',
                    'sub_title' => 'labels.dashboard',
                    'permission' => 'ad_campaign.dashboard_view',
                ],
                'index' => [
                    'sub_active' => 'ad_campaigns_index',
                    'sub_route' => 'admin.ads.campaigns.index',
                    'sub_title' => 'labels.ad_campaigns',
                    'permission' => 'ad_campaign.view',
                ],
            ],
        ],

        // ── FINANCE ──
        //        '_section_finance' => [
        //            'type' => 'section',
        //            'title' => 'labels.menu_section_finance',
        //        ],
        //        'subscriptions' => [
        //            'icon' => 'ti-user-dollar',
        //            'title' => 'labels.subscriptions',
        //            'active' => 'subscriptions',
        //            'route' => [
        //                'plans' => [
        //                    'sub_active' => 'plans',
        //                    'sub_route' => 'admin.subscription-plans.index',
        //                    'sub_title' => 'labels.plans',
        //                    'permission' => 'subscription_plan.view',
        //                ],
        //                'subscribers' => [
        //                    'sub_active' => 'subscribers',
        //                    'sub_route' => 'admin.subscription-plans.subscribers',
        //                    'sub_title' => 'labels.subscribers',
        //                    'permission' => 'subscription_subscriber.view',
        //                ],
        //            ],
        //        ],

        // ── COMMUNICATION ──
        '_section_communication' => [
            'type' => 'section',
            'title' => 'labels.menu_section_communication',
        ],
        'app_notifications' => [
            'icon' => 'ti-bell-plus',
            'route' => 'admin.app-notifications.index',
            'title' => 'labels.app_notifications',
            'active' => 'app_notifications',
            'permission' => 'notification.view',
        ],
        'notifications' => [
            'icon' => 'ti-bell-ringing',
            'route' => 'admin.notifications.index',
            'title' => 'labels.notifications',
            'active' => 'notifications',
            'permission' => 'notification.view',
        ],
        'faqs' => [
            'icon' => 'ti-help-circle',
            'route' => 'admin.faqs.index',
            'title' => 'labels.faqs',
            'active' => 'faqs',
            'permission' => 'faq.view',
        ],
        'markets' => [
            'icon' => 'ti-world',
            'title' => 'labels.markets',
            'active' => 'markets',
            'route' => [
                'index' => [
                    'sub_active' => 'markets',
                    'sub_route' => 'admin.markets.index',
                    'sub_title' => 'labels.markets',
                    'permission' => 'market.view',
                ],
            ],
        ],
        'languages' => [
            'icon' => 'ti-language',
            'title' => 'labels.languages_management',
            'active' => 'languages',
            'route' => [
                'index' => [
                    'sub_active' => 'language_overview',
                    'sub_route' => 'admin.languages.index',
                    'sub_title' => 'labels.overview',
                    'permission' => 'language.view',
                ],
                'panel' => [
                    'sub_active' => 'panel_languages',
                    'sub_route' => 'admin.languages.panel',
                    'sub_title' => 'labels.panel_languages',
                    'permission' => 'language.view',
                ],
                'website' => [
                    'sub_active' => 'website_languages',
                    'sub_route' => 'admin.languages.clients.show',
                    'route_param' => ['surface' => 'web'],
                    'sub_title' => 'labels.website_languages',
                    'permission' => 'language.view',
                ],
                'customer_app' => [
                    'sub_active' => 'customer_app_languages',
                    'sub_route' => 'admin.languages.clients.show',
                    'route_param' => ['surface' => 'customer_app'],
                    'sub_title' => 'labels.customer_app_languages',
                    'permission' => 'language.view',
                ],
                'seller_app' => [
                    'sub_active' => 'seller_app_languages',
                    'sub_route' => 'admin.languages.clients.show',
                    'route_param' => ['surface' => 'seller_app'],
                    'sub_title' => 'labels.seller_app_languages',
                    'permission' => 'language.view',
                ],
            ],
        ],

        // ── SYSTEM ──
        '_section_system' => [
            'type' => 'section',
            'title' => 'labels.menu_section_system',
        ],
        'roles_permissions' => [
            'icon' => 'ti-users-group',
            'title' => 'labels.roles_permissions',
            'active' => 'roles_permissions',
            'route' => [
                'roles' => [
                    'sub_active' => 'roles',
                    'sub_route' => 'admin.roles.index',
                    'sub_title' => 'labels.roles',
                    'permission' => 'role.view',
                ],
                'system_users' => [
                    'sub_active' => 'system_users',
                    'sub_route' => 'admin.system-users.index',
                    'sub_title' => 'labels.system_users',
                    'permission' => 'system_user.view',
                ],
            ],
        ],
        'settings' => [
            'icon' => 'ti-settings',
            'title' => 'labels.settings',
            'active' => 'settings',
            'route' => [
                'system' => [
                    'sub_active' => 'system',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'system'],
                    'sub_title' => 'labels.menu_system',
                    'permission' => 'setting.system.view',
                ],
                'web' => [
                    'sub_active' => 'web',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'web'],
                    'sub_title' => 'labels.menu_web',
                    'permission' => 'setting.web.view',
                ],
                'app' => [
                    'sub_active' => 'app',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'app'],
                    'sub_title' => 'labels.menu_app',
                    'permission' => 'setting.app.view',
                ],
                'watch_and_buy' => [
                    'sub_active' => 'watch_and_buy',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'watch_and_buy'],
                    'sub_title' => 'labels.watch_buy_settings',
                    'permission' => 'setting.watch_and_buy.view',
                ],
                'system_update_settings' => [
                    'sub_active' => 'system_update_settings',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'system_update_settings'],
                    'sub_title' => 'labels.system_update_settings',
                    'permission' => 'setting.system_update_settings.view',
                ],
                //                'storage' => [
                //                    'sub_active' => 'storage',
                //                    'sub_route' => 'admin.settings.show',
                //                    'route_param' => ['setting' => 'storage'],
                //                    'sub_title' => 'labels.menu_storage',
                //                    'permission' => 'setting.storage.view',
                //                ],
                'authentication' => [
                    'sub_active' => 'authentication',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'authentication'],
                    'sub_title' => 'labels.menu_authentication',
                    'permission' => 'setting.authentication.view',
                ],
                'email' => [
                    'sub_active' => 'email',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'email'],
                    'sub_title' => 'labels.email',
                    'permission' => 'setting.email.view',
                ],
                'broadcast_driver' => [
                    'sub_active' => 'broadcast_driver',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'broadcast_driver'],
                    'sub_title' => 'labels.broadcast_driver',
                    'permission' => 'setting.broadcast_driver.view',
                ],
                'whatsapp' => [
                    'sub_active' => 'whatsapp',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'whatsapp'],
                    'sub_title' => 'labels.whatsapp_settings',
                    'permission' => 'setting.whatsapp.view',
                ],
                'payment' => [
                    'sub_active' => 'payment',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'payment'],
                    'sub_title' => 'labels.menu_payment',
                    'permission' => 'setting.payment.view',
                ],
                'notification' => [
                    'sub_active' => 'notification',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'notification'],
                    'sub_title' => 'labels.menu_notification',
                    'permission' => 'setting.notification.view',
                ],
                'seller' => [
                    'sub_active' => 'seller',
                    'sub_route' => 'admin.settings.show',
                    'route_param' => ['setting' => 'seller'],
                    'sub_title' => 'labels.seller',
                    'permission' => 'setting.seller.view',
                ],
                'shipping' => [
                    'sub_active' => 'shipping',
                    'sub_route' => 'admin.settings.shipping.index',
                    'sub_title' => 'labels.shipping_settings',
                    'permission' => 'setting.shipping.view',
                ],
            ],
        ],
        'cron_monitor' => [
            'icon' => 'ti-clock-cog',
            'route' => 'admin.cron-monitor.index',
            'title' => 'labels.cron_monitor',
            'active' => 'cron_monitor',
            'permission' => 'cron_monitor.view',
        ],
        'system_updates' => [
            'icon' => 'ti-package',
            'route' => 'admin.system-updates.index',
            'title' => 'labels.system_updates',
            'active' => 'system_updates',
            'permission' => 'setting.system.view',
        ],
        'logout' => [
            'icon' => 'ti-logout-2',
            'route' => 'admin.logout',
            'title' => 'labels.logout',
        ],
    ],

    'delivery-partner' => [
        'dashboard' => [
            'icon' => 'ti-home',
            'route' => 'delivery-partner.dashboard',
            'title' => 'labels.delivery_partner_dashboard',
        ],
    ],

    'seller' => [
        // ── OVERVIEW ──
        '_section_overview' => [
            'type' => 'section',
            'title' => 'labels.menu_section_overview',
        ],
        'dashboard' => [
            'icon' => 'ti-home',
            'route' => 'seller.dashboard',
            'title' => 'labels.seller_dashboard',
            'active' => 'dashboard',
            'permission' => 'dashboard.view',
        ],
        'orders' => [
            'icon' => 'ti-package',
            'route' => 'seller.orders.index',
            'title' => 'labels.seller_orders',
            'active' => 'orders',
            'permission' => 'order.view',
        ],
        'return_orders' => [
            'icon' => 'ti-truck-return',
            'title' => 'labels.return_orders',
            'active' => 'return_orders',
            'route' => [
                'return_requests' => [
                    'sub_active' => 'return_requests',
                    'sub_route' => 'seller.returns.index',
                    'sub_title' => 'labels.return_requests',
                    'permission' => 'return.view',
                ],
            ],
        ],

        '_section_reports' => [
            'type' => 'section',
            'title' => 'labels.reports',
        ],
        'reports' => [
            'icon' => 'ti-chart-bar',
            'title' => 'labels.reports',
            'active' => 'reports',
            'route' => [
                'sales' => [
                    'sub_active' => 'sales_report',
                    'sub_route' => 'seller.reports.sales',
                    'sub_title' => 'labels.sales_report',
                    'permission' => 'report.sales.view',
                ],
                'products' => [
                    'sub_active' => 'product_report',
                    'sub_route' => 'seller.reports.products',
                    'sub_title' => 'labels.product_report',
                    'permission' => 'report.product.view',
                ],
                'settlements' => [
                    'sub_active' => 'settlement_report',
                    'sub_route' => 'seller.reports.settlements',
                    'sub_title' => 'labels.settlement_report',
                    'permission' => 'report.settlement.view',
                ],
                'returns' => [
                    'sub_active' => 'return_report',
                    'sub_route' => 'seller.reports.returns',
                    'sub_title' => 'labels.return_report',
                    'permission' => 'report.return.view',
                ],
                'stores' => [
                    'sub_active' => 'store_report',
                    'sub_route' => 'seller.reports.stores',
                    'sub_title' => 'labels.store_report',
                    'permission' => 'report.store.view',
                ],
                'taxes' => [
                    'sub_active' => 'tax_report',
                    'sub_route' => 'seller.reports.taxes',
                    'sub_title' => 'labels.tax_report',
                    'permission' => 'report.tax.view',
                ],
            ],
        ],

        '_section_catalog' => [
            'type' => 'section',
            'title' => 'labels.menu_section_catalog',
        ],
        'categories' => [
            'icon' => 'ti-category-2',
            'route' => 'seller.categories.index',
            'title' => 'labels.seller_categories',
            'active' => 'categories',
            'permission' => 'category.view',
        ],
        'products' => [
            'icon' => 'ti-cube-spark',
            'title' => 'labels.manage_products',
            'active' => 'products',
            'route' => [
                'products' => [
                    'sub_active' => 'products',
                    'sub_route' => 'seller.products.index',
                    'sub_title' => 'labels.seller_products',
                    'permission' => 'product.view',

                ],
                'add_products' => [
                    'sub_active' => 'add_products',
                    'sub_route' => 'seller.products.create',
                    'sub_title' => 'labels.add_products',
                    'permission' => 'product.create',

                ],
                'bulk_upload' => [
                    'sub_active' => 'bulk_upload',
                    'sub_route' => 'seller.products.bulk-upload.page',
                    'sub_title' => 'labels.bulk_upload',
                    'permission' => 'product.create',
                ],
                'product_faqs' => [
                    'sub_active' => 'product_faqs',
                    'sub_route' => 'seller.product_faqs.index',
                    'sub_title' => 'labels.seller_product_faqs',
                    'permission' => 'product_faq.view',
                ],
                'addon_groups' => [
                    'sub_active' => 'addon_groups',
                    'sub_route' => 'seller.addon-groups.index',
                    'sub_title' => 'labels.addon_groups',
                    'permission' => 'addon_group.view',
                ],
                'product_addons' => [
                    'sub_active' => 'product_addons',
                    'sub_route' => 'seller.product-addons.index',
                    'sub_title' => 'labels.product_addons',
                    'permission' => 'product_addon.view',
                ],
                'store_addon_items' => [
                    'sub_active' => 'store_addon_items',
                    'sub_route' => 'seller.store-addon-items.index',
                    'sub_title' => 'labels.store_addon_items',
                    'permission' => 'store_addon_item.view',
                ],
            ],
        ],
        'watch_and_buy' => [
            'icon' => 'ti-player-play',
            'title' => 'labels.watch_buy',
            'active' => 'watch_and_buy',
            'route' => [
                'statuses' => [
                    'sub_active' => 'statuses',
                    'sub_route' => 'seller.watch-and-buy.statuses.index',
                    'sub_title' => 'labels.watch_buy_statuses',
                    'permission' => 'watch_buy.status.view',
                ],
                'reels' => [
                    'sub_active' => 'reels',
                    'sub_route' => 'seller.watch-and-buy.reels.index',
                    'sub_title' => 'labels.watch_buy_reels',
                    'permission' => 'watch_buy.reel.view',
                ],
                'profile' => [
                    'sub_active' => 'profile',
                    'sub_route' => 'seller.watch-and-buy.profile.edit',
                    'sub_title' => 'labels.watch_buy_profile',
                    'permission' => 'watch_buy.profile.view',
                ],
            ],
        ],
        'brands' => [
            'icon' => 'ti-sparkles',
            'route' => 'seller.brands.index',
            'title' => 'labels.seller_brands',
            'active' => 'brands',
            'permission' => 'brand.view',
        ],
        'attributes' => [
            'icon' => 'ti-tag-starred',
            'route' => 'seller.attributes.index',
            'title' => 'labels.attributes',
            'active' => 'attributes',
            'permission' => 'attribute.view',
        ],
        'tax_rates' => [
            'icon' => 'ti-square-rounded-percentage',
            'route' => 'seller.tax-rates.index',
            'title' => 'labels.seller_tax_rates',
            'active' => 'tax_rates',
            'permission' => 'tax_rate.view',
        ],

        // ── STORE ──
        '_section_store' => [
            'type' => 'section',
            'title' => 'labels.menu_section_store',
        ],
        'stores' => [
            'icon' => 'ti-building-warehouse',
            'title' => 'labels.seller_stores',
            'active' => 'stores',
            'route' => 'seller.stores.index',
            'permission' => 'store.view',
        ],
        'locations' => [
            'icon' => 'ti-map-pin',
            'title' => 'labels.locations',
            'active' => 'locations',
            'route' => 'seller.locations.index',
            'permission' => 'store_location.view',
        ],
        'shipping' => [
            'icon' => 'ti-truck-delivery',
            'title' => 'labels.shipping_and_delivery',
            'active' => 'shipping',
            'route' => 'seller.shipping.index',
            'permission' => 'shipping_profile.view',
        ],

        // ── FINANCE ──
        '_section_finance' => [
            'type' => 'section',
            'title' => 'labels.menu_section_finance',
        ],
        'earnings' => [
            'icon' => 'ti-currency-dollar',
            'route' => 'seller.commissions.index',
            'title' => 'labels.earnings',
            'active' => 'earnings',
            'permission' => 'earning.view',
        ],
        'wallet' => [
            'icon' => 'ti-wallet',
            'title' => 'labels.wallet',
            'active' => 'wallet',
            'route' => [
                'balance' => [
                    'sub_active' => 'wallet_balance',
                    'sub_route' => 'seller.wallet.index',
                    'sub_title' => 'labels.wallet_balance',
                    'permission' => 'wallet.view',

                ],
                'withdrawals' => [
                    'sub_active' => 'withdrawals',
                    'sub_route' => 'seller.withdrawals.index',
                    'sub_title' => 'labels.withdrawals',
                    'permission' => 'withdrawal.view',
                ],
                'withdrawal_history' => [
                    'sub_active' => 'withdrawal_history',
                    'sub_route' => 'seller.withdrawals.history',
                    'sub_title' => 'labels.withdrawal_history',
                    'permission' => 'withdrawal.view',
                ],
            ],
        ],
        'subscriptions' => [
            'icon' => 'ti-user-dollar',
            'title' => 'labels.subscriptions',
            'active' => 'subscriptions',
            'route' => [
                'plans' => [
                    'sub_active' => 'plans',
                    'sub_route' => 'seller.subscription-plans.index',
                    'sub_title' => 'labels.plans',
                    'permission' => 'subscription.view',
                ],
                'current' => [
                    'sub_active' => 'current',
                    'sub_route' => 'seller.subscription-plans.current',
                    'sub_title' => 'labels.current_subscription',
                    'permission' => 'subscription.view',
                ],
                'history' => [
                    'sub_active' => 'history',
                    'sub_route' => 'seller.subscription-plans.history',
                    'sub_title' => 'labels.subscription_history',
                    'permission' => 'subscription.view',
                ],
            ],
        ],

        // ── MARKETING ──
        //        '_section_marketing' => [
        //            'type' => 'section',
        //            'title' => 'labels.menu_section_marketing',
        //        ],
        //        'ad_campaigns' => [
        //            'icon' => 'ti-rocket',
        //            'title' => 'labels.ad_campaigns',
        //            'active' => 'ad_campaigns',
        //            'route' => [
        //                'dashboard' => [
        //                    'sub_active' => 'ad_campaigns_dashboard',
        //                    'sub_route' => 'seller.ads.campaigns.dashboard',
        //                    'sub_title' => 'labels.dashboard',
        //                    'permission' => 'ad_campaign.dashboard_view',
        //                ],
        //                'index' => [
        //                    'sub_active' => 'ad_campaigns_index',
        //                    'sub_route' => 'seller.ads.campaigns.index',
        //                    'sub_title' => 'labels.ad_campaigns',
        //                    'permission' => 'ad_campaign.view',
        //                ],
        //                'create' => [
        //                    'sub_active' => 'ad_campaigns_create',
        //                    'sub_route' => 'seller.ads.campaigns.create',
        //                    'sub_title' => 'labels.create_ad_campaign',
        //                    'permission' => 'ad_campaign.create',
        //                ],
        //            ],
        //        ],
        //        'ad_wallet' => [
        //            'icon' => 'ti-speakerphone',
        //            'title' => 'labels.ad_wallet',
        //            'active' => 'ad_wallet',
        //            'route' => [
        //                'balance' => [
        //                    'sub_active' => 'ad_wallet_balance',
        //                    'sub_route' => 'seller.ads.wallet.index',
        //                    'sub_title' => 'labels.ad_wallet_balance',
        //                    'permission' => 'ad_wallet.view',
        //                ],
        //                'transactions' => [
        //                    'sub_active' => 'ad_wallet_transactions',
        //                    'sub_route' => 'seller.ads.wallet.transactions',
        //                    'sub_title' => 'labels.transaction_history',
        //                    'permission' => 'ad_wallet.view',
        //                ],
        //            ],
        //        ],

        // ── COMMUNICATION ──
        '_section_communication' => [
            'type' => 'section',
            'title' => 'labels.menu_section_communication',
        ],
        'notifications' => [
            'icon' => 'ti-bell-ringing',
            'route' => 'seller.notifications.index',
            'title' => 'labels.seller_notifications',
            'active' => 'notifications',
            'permission' => 'notification.view',
        ],

        // ── SYSTEM ──
        '_section_system' => [
            'type' => 'section',
            'title' => 'labels.menu_section_system',
        ],
        'roles_permissions' => [
            'icon' => 'ti-users-group',
            'title' => 'labels.seller_roles_permissions',
            'active' => 'roles_permissions',
            'route' => [
                'roles' => [
                    'sub_active' => 'roles',
                    'sub_route' => 'seller.roles.index',
                    'sub_title' => 'labels.seller_roles',
                    'permission' => 'role.view',

                ],
                'system_users' => [
                    'sub_active' => 'system_users',
                    'sub_route' => 'seller.system-users.index',
                    'sub_title' => 'labels.seller_system_users',
                    'permission' => 'system_user.view',

                ],
            ],
        ],
        'logout' => [
            'icon' => 'ti-logout-2',
            'route' => 'seller.logout',
            'title' => 'labels.seller_logout',
        ],
    ],
];
