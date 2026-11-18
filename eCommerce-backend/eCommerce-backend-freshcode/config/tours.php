<?php

$segment = static function (
    string $id,
    string $route,
    ?string $permission,
    string $title,
    string $overview,
    string $workflow,
    string $finish,
    array $params = [],
): array {
    return [
        'id' => $id,
        'route' => $route,
        'params' => $params,
        'permission' => $permission,
        'steps' => [
            [
                'element' => '[data-tour-menu="{menu}"] > .dropdown-menu .dropdown-item.active, [data-tour-menu="{menu}"] > .nav-link',
                'title' => 'Open '.$title,
                'description' => 'Use this navigation entry to open '.$title.'.',
                'side' => 'right',
                'optional' => true,
            ],
            [
                'element' => '[data-tour-target="panel-content"] .page-title, [data-tour-target="panel-content"] .card-title, [data-tour-target="panel-content"] .card-header',
                'title' => $title,
                'description' => $overview,
                'side' => 'bottom',
            ],
            [
                'element' => '[data-tour-target="panel-content"] .card-header, [data-tour-target="panel-content"] .card-body, [data-tour-target="panel-content"] .table-responsive, [data-tour-target="panel-content"] form, [data-tour-target="panel-content"] .alert',
                'title' => 'Follow the workflow',
                'description' => $workflow,
                'side' => 'right',
            ],
            [
                'element' => '[data-tour-target="panel-content"] button[type="submit"], [data-tour-target="panel-content"] .btn-primary, [data-tour-target="panel-content"] .dataTables_wrapper, [data-tour-target="panel-content"] .table-responsive, [data-tour-target="panel-content"] table',
                'title' => 'Complete and review',
                'description' => $finish,
                'side' => 'top',
                'optional' => true,
            ],
        ],
    ];
};

$guide = static fn (
    string $title,
    string $description,
    string $icon,
    string $menu,
    array $segments,
): array => compact('title', 'description', 'icon', 'menu', 'segments');

return [
    'admin' => [
        [
            'title' => 'Getting Started',
            'guides' => [
                'dashboard' => $guide(
                    'Dashboard',
                    'Read marketplace performance and work in the selected market.',
                    'ti ti-home',
                    'dashboard',
                    [
                        $segment('overview', 'admin.dashboard', 'dashboard.view', 'Admin Dashboard', 'The dashboard summarizes orders, delivered revenue, GMV, fulfilment rate, customers, products, and marketplace growth for the selected period and market.', 'Choose the market from the header first, then use the date controls and summary cards to understand current performance before opening a detailed module.', 'Treat GMV as total demand and revenue as delivered platform earnings. Use the detailed modules to investigate changes rather than editing data from the dashboard.'),
                    ],
                ),
            ],
        ],
        [
            'title' => 'Platform Setup',
            'guides' => [
                'settings' => $guide(
                    'Settings',
                    'Configure the platform in the correct setup order.',
                    'ti ti-settings',
                    'settings',
                    [
                        $segment('system', 'admin.settings.show', 'setting.system.view', 'System Settings', 'Configure marketplace identity, base currency, checkout, cart, wallet, referrals, and maintenance behaviour.', 'Confirm the base currency and default country before sellers enter prices. Review every enabled operational switch and limit in order from top to bottom.', 'Save only after checking currency and checkout values because they affect every market, seller, cart, and order.', ['setting' => 'system']),
                        $segment('web', 'admin.settings.show', 'setting.web.view', 'Web Settings', 'Configure website identity, support contacts, map integration, SEO, social links, download prompts, feature content, policies, PWA metadata, and custom scripts.', 'Start with public website details and support contacts, then review each section in the page menu before changing policy text or custom scripts.', 'Save after verifying the support details, map key, public links, policy content, and any scripts entered on this page.', ['setting' => 'web']),
                        $segment('app', 'admin.settings.show', 'setting.app.view', 'Application Settings', 'Manage the App Store link, Play Store link, deep-link scheme, and map key for the customer and seller applications.', 'Review the Customer App and Seller App sections separately so each application keeps its own store links, scheme, and map key.', 'Save only after checking that every URL and deep-link scheme belongs to the intended application.', ['setting' => 'app']),
                        $segment('system_update_settings', 'admin.settings.show', 'setting.system_update_settings.view', 'System Update Messages', 'Edit the force-update and soft-update messages displayed by the customer app, seller app, and customer web experience.', 'Review the customer app, seller app, and customer web message pairs and clearly distinguish mandatory updates from optional updates.', 'Save the messages after checking their wording on all three customer-facing surfaces.', ['setting' => 'system_update_settings']),
                        $segment('authentication', 'admin.settings.show', 'setting.authentication.view', 'Authentication and SMS', 'Configure sign-in methods, custom SMS OTP delivery, Firebase authentication, and social login providers.', 'Choose the account identifier mode first, then set the SMS gateway, enter its credentials, test delivery, and review Firebase and social login options.', 'Never expose secret credentials. A successful test should be completed before relying on SMS for login or password recovery.', ['setting' => 'authentication']),
                        $segment('email', 'admin.settings.show', 'setting.email.view', 'Email and SMTP', 'Configure the mail server used for password resets, login messages, and optional activity emails.', 'Enter SMTP host, port, encryption, sender, username, and password, then use the test-email action with a controlled recipient.', 'Save only after the test succeeds; password-reset delivery depends on this configuration.', ['setting' => 'email']),
                        $segment('payment', 'admin.settings.show', 'setting.payment.view', 'Payment Gateways', 'Configure Stripe, Razorpay, Paystack, and Flutterwave credentials and test or live mode.', 'Complete one gateway at a time and keep public keys, secret keys, webhook values, and environment mode together.', 'Test before switching to live mode. Customer totals are calculated by the server and must match the gateway capture.', ['setting' => 'payment']),
                        $segment('notification', 'admin.settings.show', 'setting.notification.view', 'Push Notifications', 'Configure Firebase Cloud Messaging, browser push keys, and the global push and email notification channels.', 'Upload or enter the required Firebase service account and VAPID values, then decide which activity channels are enabled.', 'The in-panel notification inbox remains available even when push or activity email is disabled.', ['setting' => 'notification']),
                        $segment('seller', 'admin.settings.show', 'setting.seller.view', 'Seller Policies', 'Maintain the Terms and Conditions and Privacy Policy presented to sellers.', 'Review both policy editors and use their preview links to confirm the public versions before saving changes.', 'Save policy changes only after legal and operational review because the updated text is presented to sellers.', ['setting' => 'seller']),
                        $segment('shipping', 'admin.settings.shipping.index', 'setting.shipping.view', 'Shipping Settings', 'Review the available fulfilment providers and see whether each provider is enabled, disabled, or coming soon.', 'Choose Manual Shipping or Shiprocket and open Configure to manage that provider on its dedicated settings page.', 'Confirm the provider status before opening its configuration. Coming-soon providers cannot be configured yet.'),
                    ],
                ),
                'markets' => $guide(
                    'Markets',
                    'Create regional currency, language, country, fee, and exchange-rate rules.',
                    'ti ti-world',
                    'markets',
                    [
                        $segment('index', 'admin.markets.index', 'market.view', 'Markets and Currencies', 'A market combines a currency and formatting rules with a default language, countries, priority, and optional order fees.', 'Create the default market first, then add additional markets without overlapping countries. Refresh exchange rates after checking the base currency.', 'Platform and COD fees are entered in base currency and converted for shoppers. Keep exactly one default market.'),
                    ],
                ),
            ],
        ],
        [
            'title' => 'Vendor Operations',
            'guides' => [
                'seller_management' => $guide(
                    'Seller Management',
                    'Onboard sellers and manage their earnings and payout requests.',
                    'ti ti-users-group',
                    'seller_management',
                    [
                        $segment('sellers', 'admin.sellers.index', 'seller.view', 'Seller Directory', 'Review seller accounts, their status, contact details, stores, and available management actions.', 'Use search and status filters to find the correct seller before viewing or editing their account.', 'Seller account changes can affect every store owned by that seller, so confirm the identity first.'),
                        $segment('create', 'admin.sellers.create', 'seller.create', 'Add a Seller', 'Create the seller login and initial business identity used to access the seller panel.', 'Enter accurate identity and contact information, assign the intended access, and follow the form in order.', 'After saving, continue to store verification and locations before expecting products to become customer-visible.'),
                        $segment('settlements', 'admin.commissions.index', 'commission.view', 'Seller Earnings Settlement', 'Review delivered-order earnings, deductions, holds, and entries that are ready to move into seller wallets.', 'Filter by seller and status, investigate deductions or holds, then settle one entry or use Settle All only for verified entries.', 'Settlement credits the seller wallet. Never settle an entry until delivery and any exceptional adjustments are confirmed.'),
                        $segment('withdrawals', 'admin.seller-withdrawals.index', 'seller_withdrawal.view', 'Seller Withdrawal Requests', 'Process pending seller requests to withdraw available wallet balance.', 'Open each request, verify the seller and payout details, then approve or reject it with the correct operational evidence.', 'Approval finalizes the payout decision; rejection should include a reason the seller can act on.'),
                        $segment('withdrawal_history', 'admin.seller-withdrawals.history', 'seller_withdrawal.view', 'Seller Withdrawal History', 'Audit previously approved and rejected seller payout requests.', 'Use seller, date, and status filters to trace earlier decisions and payment references.', 'History is for verification and support; new decisions belong in the pending withdrawals page.'),
                    ],
                ),
                'stores' => $guide(
                    'Stores',
                    'Review, verify, or reject seller storefronts.',
                    'ti ti-building-store',
                    'stores',
                    [
                        $segment('index', 'admin.sellers.store.index', 'store.view', 'Store Review', 'Stores remain unavailable to customers until their seller details and storefront information are verified.', 'Filter pending stores, inspect branding and business information, and use the verification action only after review.', 'Approving a store does not bypass product moderation; products must still be approved, active, and visible.'),
                    ],
                ),
                'store_locations' => $guide(
                    'Store Locations',
                    'Review fulfilment locations and Shiprocket pickup readiness.',
                    'ti ti-map-pin',
                    'store_locations',
                    [
                        $segment('index', 'admin.store-locations.index', 'store.view', 'Store Locations', 'Locations are the physical ship-from points where variant inventory is stored and reserved.', 'Find a seller location, verify its address and default status, and review Shiprocket pickup-point readiness where enabled.', 'A Shiprocket pickup address cannot be edited in place after registration; address changes create a new pickup point.'),
                    ],
                ),
            ],
        ],
        [
            'title' => 'Catalog',
            'guides' => [
                'categories' => $guide(
                    'Categories',
                    'Build the catalog hierarchy and import categories safely.',
                    'ti ti-category-2',
                    'categories',
                    [
                        $segment('index', 'admin.categories.index', 'category.view', 'Category Management', 'Categories organize product discovery, filters, and commission inheritance across the catalog tree.', 'Create parents before children, set titles and visibility, and review the resulting hierarchy before assigning products.', 'Changing a parent or status can affect every descendant and the products shoppers can discover.'),
                        $segment('bulk_upload', 'admin.categories.bulk-upload.page', 'category.create', 'Category Bulk Upload', 'Import many categories using the provided template and expected hierarchy fields.', 'Download the current template, preserve its columns, validate parent references, and upload a small sample before a full catalog.', 'Review the import result and correct rejected rows rather than repeatedly uploading the same invalid data.'),
                    ],
                ),
                'brands' => $guide(
                    'Brands',
                    'Maintain reusable product brands and customer-facing identity.',
                    'ti ti-sparkles',
                    'brands',
                    [
                        $segment('index', 'admin.brands.index', 'brand.view', 'Brand Management', 'Brands provide consistent product identity, browsing, and filtering across sellers.', 'Search before creating a brand, use a clean title and image, and keep status active only when the brand is ready for products.', 'Avoid duplicates with spelling variations because they split catalog filtering and reporting.'),
                    ],
                ),
                'tax_rates' => $guide(
                    'Tax Rates',
                    'Create tax classes, rates, and reusable product tax groups.',
                    'ti ti-square-rounded-percentage',
                    'tax_rates',
                    [
                        $segment('index', 'admin.tax-rates.index', 'tax_class.view', 'Taxes', 'Tax classes and rates determine which percentage is applied to products under the configured regional rules.', 'Create the class, add its rate details, and confirm the seller-facing tax option before products use it.', 'Tax changes affect price calculations; verify the rate and jurisdiction before activating it.'),
                    ],
                ),
                'products' => $guide(
                    'Products',
                    'Create, moderate, badge, and support marketplace products.',
                    'ti ti-cube-spark',
                    'products',
                    [
                        $segment('index', 'admin.products.index', 'product.view', 'Product Catalog', 'Review all products, seller ownership, approval, activity, visibility, price, and stock-related status.', 'Use catalog filters to find the product and open its details before changing moderation or visibility.', 'A product is customer-visible only when approved, active, and visible, with sellable inventory.'),
                        $segment('approvals', 'admin.products.index', 'product.view', 'Pending Product Approvals', 'Moderate seller-submitted products waiting for approval or rejection.', 'Review category, media, variants, pricing, policies, shipping information, and seller ownership before deciding.', 'Approve only complete products. Rejections should clearly explain what the seller must correct.', ['verification_status' => 'pending_verification']),
                        $segment('badges', 'admin.badges.index', 'badge.view', 'Product Badges', 'Create reusable labels that help highlight product characteristics in the storefront.', 'Keep badge names and visuals short and clear, then assign them only where they accurately describe a product.', 'Badges are merchandising labels, not a replacement for approval, inventory, or visibility status.'),
                        $segment('faqs', 'admin.product_faqs.index', 'product_faqs.view', 'Product FAQs', 'Review reusable product questions and answers shown to help shoppers before purchase.', 'Search existing FAQs, keep answers accurate and concise, and associate them with the intended products.', 'Do not include private seller or customer information in public answers.'),
                    ],
                ),
            ],
        ],
        [
            'title' => 'Merchandising',
            'guides' => [
                'home_builder' => $guide(
                    'Home Builder',
                    'Compose market-specific customer app and web home pages with live device preview, custom navigation, and flexible design tools.',
                    'ti ti-layout-dashboard',
                    'home_builder',
                    [
                        $segment(
                            'index',
                            'admin.home-builder.index',
                            'home_builder.view',
                            'Home Page Builder',
                            'Build separate customer App (Android/iPhone) and Web (Desktop/Tablet/Mobile) homepages with live device preview, custom navigation tabs, and market-specific section order.',
                            '1) Switch workspace tabs (Header & Navbar, App Layout, Web Layout). 2) Use Scope toolbar for custom views and Market View (🌐 Market View) to customize regional section sequence. 3) Drag sections in the structure panel or click Add Section. 4) Click any component in canvas/panel to open its Content, Layout, Style, and Visibility settings.',
                            'Check the Issues health panel for missing media or required selections. Use Undo (Cmd+Z), Redo (Cmd+Y), and click Save (Cmd+S) to publish App and Web layouts.'
                        ),
                    ],
                ),
                'promos' => $guide(
                    'Promos',
                    'Create controlled coupon discounts with clear eligibility.',
                    'ti ti-ticket',
                    'promos',
                    [
                        $segment('index', 'admin.promos.index', 'promo.view', 'Promo Codes', 'Promos apply configured discounts only when their dates, limits, market, customer, and cart conditions are satisfied.', 'Set the code, discount type and value, schedule, minimums, usage limits, and targeting before activation.', 'Test boundary dates and maximum discounts. A promo must never reduce the payable amount outside its configured rules.'),
                    ],
                ),
                'watch_and_buy' => $guide(
                    'Watch & Buy',
                    'Manage short-form status and reel commerce content.',
                    'ti ti-player-play',
                    'watch_and_buy',
                    [
                        $segment('statuses', 'admin.watch-and-buy.statuses.index', 'watch_buy.status.view', 'Watch & Buy Statuses', 'Review short-lived platform and seller status content and its attached products.', 'Use the status gallery to inspect media, expiry, ownership, and product attachments before moderation or removal.', 'Only publish media that meets customer-facing quality and policy requirements.'),
                        $segment('reels', 'admin.watch-and-buy.reels.index', 'watch_buy.reel.view', 'Watch & Buy Reels', 'Review shoppable reels, their publication state, profile ownership, and attached products or variants.', 'Search and filter reels, preview the media, and use block or publication actions only after checking the complete content.', 'Blocking or deleting content affects storefront discovery immediately; preserve the correct moderation reason.'),
                        $segment('profiles', 'admin.watch-and-buy.profiles.index', 'watch_buy.profile.view', 'Seller Watch & Buy Profiles', 'Review seller creator profiles and their availability for customer discovery.', 'Inspect profile identity, status, and associated content before suspending or restoring access.', 'Profile moderation affects all content owned by that seller profile.'),
                        $segment('official_profile', 'admin.watch-and-buy.official-profile.edit', 'watch_buy.profile.edit', 'Official Watch & Buy Profile', 'Maintain the platform-owned profile used for official reels and statuses.', 'Update the username, biography, and profile image while preserving a recognizable official identity.', 'Save only approved public information because this profile is presented as the marketplace itself.'),
                    ],
                ),
            ],
        ],
        [
            'title' => 'Operations',
            'guides' => [
                'orders' => $guide(
                    'Orders',
                    'Monitor payment, fulfilment, parcels, tracking, cancellation, and refunds.',
                    'ti ti-package',
                    'orders',
                    [
                        $segment('index', 'admin.orders.index', 'orders.view', 'Order Operations', 'The order list combines payment, fulfilment, delivery method, seller, market, and date filters for daily operations.', 'Open an order to review its tabbed details. Fulfil or cancel remaining lines, create manual or Shiprocket parcels, print labels, sync tracking, and use NDR actions only when eligible.', 'Fulfilment is derived from deliveries and is not set by hand. Confirm payment and parcel state before cancellation, refund, or force actions.'),
                    ],
                ),
                'returns' => $guide(
                    'Returns',
                    'Review return requests, decisions, pickups, refunds, and restocking.',
                    'ti ti-arrow-back-up',
                    'returns',
                    [
                        $segment('index', 'admin.orders.returns.index', 'orders.view', 'Order Returns', 'Returns preserve the customer request, evidence, seller decision, pickup method, refund, and stock outcome.', 'Filter pending requests, review photos and reasons, inspect the order line, and approve, decline, cancel, or restock only from the valid state.', 'Shiprocket items can use reverse pickup. Refund and stock restoration must follow the recorded return outcome exactly once.'),
                    ],
                ),
                'customers' => $guide(
                    'Customers',
                    'Support customer accounts, wallet activity, deposits, and referrals.',
                    'ti ti-users',
                    'customers',
                    [
                        $segment('customers', 'admin.customers.index', 'customer.view', 'Customer Accounts', 'Review customer identity, status, contact details, orders, and account-level support information.', 'Search by verified identity details and open the account before changing status or investigating activity.', 'Account status changes can revoke access and affect active sessions; confirm the customer first.'),
                        $segment('transactions', 'admin.wallet.transactions', 'orders.view', 'Customer Wallet Transactions', 'Audit customer wallet credits, debits, refunds, deposits, and order usage.', 'Filter by customer, transaction type, reference, and date to trace the balance history.', 'Wallet balances must be explained by ledger entries; never infer a balance change from the current total alone.'),
                        $segment('deposits', 'admin.wallet.deposits', 'orders.view', 'Pending Wallet Deposits', 'Process customer wallet deposits that require administrator confirmation.', 'Verify the gateway or payment reference and amount before completing or failing the pending deposit.', 'Finalize each deposit once. Repeated completion would over-credit the customer wallet.'),
                        $segment('referrals', 'admin.referrals.index', 'orders.view', 'Customer Referrals', 'Review inviter relationships, reward eligibility, and referral program activity.', 'Search by customer or code and confirm the qualifying relationship before handling support questions.', 'Rewards are earned only under the configured program rules and after the required order conditions are complete.'),
                        $segment('referral_earnings', 'admin.referrals.earnings', 'orders.view', 'Referral Earnings', 'Audit referral reward entries and the orders that produced them.', 'Use status and customer filters to connect each reward to its qualifying event.', 'Do not manually treat a pending reward as available before its return window and eligibility checks finish.'),
                    ],
                ),
            ],
        ],
        [
            'title' => 'Communication',
            'guides' => [
                'app_notifications' => $guide(
                    'App Notifications',
                    'Create targeted customer-facing push campaigns.',
                    'ti ti-bell-plus',
                    'app_notifications',
                    [
                        $segment('index', 'admin.app-notifications.index', 'notification.view', 'App Notifications', 'Create scheduled or immediate application notifications for the intended customer audience.', 'Write a clear title and message, choose the audience and destination carefully, and review scheduling before sending.', 'Push delivery cannot be recalled. Confirm targeting and links before publication.'),
                    ],
                ),
                'notifications' => $guide(
                    'Panel Notifications',
                    'Review operational events delivered to the admin inbox.',
                    'ti ti-bell-ringing',
                    'notifications',
                    [
                        $segment('index', 'admin.notifications.index', 'notification.view', 'Admin Notifications', 'The notification inbox records orders, returns, settlements, withdrawals, product, store, and system events that need attention.', 'Filter unread items, open the linked operational record, and mark notifications read only after review.', 'Notification state does not change the underlying order, payout, or catalog record.'),
                    ],
                ),
                'faqs' => $guide(
                    'FAQs',
                    'Maintain public marketplace help questions and answers.',
                    'ti ti-help-circle',
                    'faqs',
                    [
                        $segment('index', 'admin.faqs.index', 'faq.view', 'Marketplace FAQs', 'FAQs answer common customer questions across the marketplace.', 'Search for an existing answer before adding one, write clear public language, and keep status active only while the information is current.', 'Review FAQs after policy or fulfilment changes so outdated guidance is not shown to customers.'),
                    ],
                ),
            ],
        ],
        [
            'title' => 'Administration',
            'guides' => [
                'roles_permissions' => $guide(
                    'Roles & Permissions',
                    'Give staff the minimum panel access required for their work.',
                    'ti ti-users-group',
                    'roles_permissions',
                    [
                        $segment('roles', 'admin.roles.index', 'role.view', 'Admin Roles', 'Roles group panel permissions for administrator staff without changing the Super Admin account.', 'Create a role around a real job, enable only the required modules and actions, and review it before assignment.', 'Least-privilege access reduces accidental changes to payments, settings, orders, and catalog data.'),
                        $segment('users', 'admin.system-users.index', 'system_user.view', 'Admin Staff Users', 'Create staff accounts and assign the appropriate administrator role.', 'Enter the staff identity, choose the role, and keep access active only while the person requires it.', 'Do not share Super Admin credentials. Disable departing staff promptly.'),
                    ],
                ),
                'cron_monitor' => $guide(
                    'Cron Monitor',
                    'Check scheduled background work and investigate failures.',
                    'ti ti-clock-cog',
                    'cron_monitor',
                    [
                        $segment('index', 'admin.cron-monitor.index', 'cron_monitor.view', 'Cron Monitor', 'Scheduled commands maintain notifications, reservations, tracking, settlements, cleanup, and other background operations.', 'Review last-run time, duration, result, and errors. Run a command manually only when its purpose and idempotency are understood.', 'Repeated failures can interrupt order and shipping workflows; investigate the underlying error rather than repeatedly rerunning it.'),
                    ],
                ),
                'system_updates' => $guide(
                    'System Updates',
                    'Upload, validate, and apply signed Hypercommerce update packages.',
                    'ti ti-package',
                    'system_updates',
                    [
                        $segment('index', 'admin.system-updates.index', 'setting.system.view', 'System Updates', 'System Updates validates an uploaded package and tracks preparation, application, heartbeat, and final result.', 'Back up the installation, read the release notes, upload the correct ZIP, review validation, and apply it during a controlled maintenance window.', 'Never interrupt an active update. Confirm the final status and core panel flows before reopening production traffic.'),
                    ],
                ),
            ],
        ],
    ],
    'seller' => [
        [
            'title' => 'Getting Started',
            'guides' => [
                'dashboard' => $guide(
                    'Dashboard',
                    'Read store performance in the currently selected store.',
                    'ti ti-home',
                    'dashboard',
                    [
                        $segment('overview', 'seller.dashboard', 'dashboard.view', 'Seller Dashboard', 'The dashboard summarizes orders, delivered earnings, GMV, fulfilment rate, customers, and products for the selected store and period.', 'Choose the store from the header first, then use the date controls and cards to understand daily performance.', 'Revenue is delivered net earnings while GMV is total ordered demand. Open the detailed modules before acting on a change.'),
                    ],
                ),
            ],
        ],
        [
            'title' => 'Store Setup',
            'guides' => [
                'stores' => $guide(
                    'Stores',
                    'Create and maintain storefront identity and configuration.',
                    'ti ti-building-warehouse',
                    'stores',
                    [
                        $segment('index', 'seller.stores.index', 'store.view', 'Store Setup', 'A store contains the customer-facing name, description, branding, verification state, and operational configuration.', 'Create or open the store, complete its identity and configuration, and submit accurate information for administrator verification.', 'Customers cannot see an unverified store. Store verification does not automatically approve its products.'),
                    ],
                ),
                'locations' => $guide(
                    'Locations',
                    'Create ship-from locations and maintain location inventory.',
                    'ti ti-map-pin',
                    'locations',
                    [
                        $segment('index', 'seller.locations.index', 'store_location.view', 'Store Locations', 'Locations are physical shops or warehouses that hold variant stock and dispatch online orders.', 'Add the complete address, map position, online-fulfilment status, low-stock thresholds, and choose one default location.', 'Check the address before Shiprocket registration because an existing pickup address cannot be edited there.'),
                    ],
                ),
                'shipping' => $guide(
                    'Shipping',
                    'Configure packages, profiles, zones, rates, and fulfilment method.',
                    'ti ti-truck-delivery',
                    'shipping',
                    [
                        $segment('index', 'seller.shipping.index', 'shipping_profile.view', 'Shipping Setup', 'Shipping connects a product to a profile, zone, customer rate, ship-from location, and package dimensions.', 'Create package presets, create the default profile, add country or state zones and rates, then assign products to the correct profile.', 'Keep weights and dimensions accurate. Shiprocket bookings also require a registered pickup location and the platform shipping integration.'),
                    ],
                ),
            ],
        ],
        [
            'title' => 'Catalog Setup',
            'guides' => [
                'attributes' => $guide(
                    'Attributes',
                    'Create reusable product options and variant values.',
                    'ti ti-tag-starred',
                    'attributes',
                    [
                        $segment('index', 'seller.attributes.index', 'attribute.view', 'Product Attributes', 'Attributes such as Size and Color provide the values used to build product variants.', 'Create the attribute first, add clear reusable values, and avoid duplicates before opening the product form.', 'Changing an attribute used by products can affect how existing variants are understood.'),
                    ],
                ),
                'categories' => $guide(
                    'Categories',
                    'Choose the catalog categories available to your products.',
                    'ti ti-category-2',
                    'categories',
                    [
                        $segment('index', 'seller.categories.index', 'category.view', 'Seller Categories', 'Categories control where customers browse and which product fields or commission rules apply.', 'Review the hierarchy and use the most specific suitable category when creating a product.', 'Contact the administrator when a required category is missing rather than using an unrelated one.'),
                    ],
                ),
                'brands' => $guide(
                    'Brands',
                    'Choose consistent brand identity for catalog products.',
                    'ti ti-sparkles',
                    'brands',
                    [
                        $segment('index', 'seller.brands.index', 'brand.view', 'Seller Brands', 'Brands improve product identity, search, and customer filtering.', 'Search the available brands before requesting or using one and match the product packaging exactly.', 'Do not create spelling variants of an existing brand.'),
                    ],
                ),
                'tax_rates' => $guide(
                    'Tax Rates',
                    'Understand and select the correct product tax treatment.',
                    'ti ti-square-rounded-percentage',
                    'tax_rates',
                    [
                        $segment('index', 'seller.tax-rates.index', 'tax_rate.view', 'Seller Tax Rates', 'Tax rates are configured for reuse when products require a specific tax class or rate.', 'Review the available tax definitions and select the applicable one on product pricing.', 'Confirm the jurisdiction and rate with the marketplace administrator before using it.'),
                    ],
                ),
                'products' => $guide(
                    'Products',
                    'Build products, variants, inventory, add-ons, and FAQs for moderation.',
                    'ti ti-cube-spark',
                    'products',
                    [
                        $segment('index', 'seller.products.index', 'product.view', 'Product Catalog', 'The catalog shows product approval, activity, visibility, price, store availability, and inventory state.', 'Use filters to find products, review rejection reasons, and open the correct product before changing it.', 'Products reach customers only after administrator approval and while active, visible, and in stock.'),
                        $segment('create', 'seller.products.create', 'product.create', 'Add a Product', 'Create the product in the required order: category, information, variants, media, policies, price, tax, shipping, and store inventory.', 'Enter the base-currency price once, add variant weight and dimensions, choose Allow COD correctly, and complete every required policy and fulfilment field.', 'Submit accurate content for moderation. Later edits can return the product to review depending on the changed fields.'),
                        $segment('bulk_upload', 'seller.products.bulk-upload.page', 'product.create', 'Product Bulk Upload', 'Import many products using the current catalog template and URL-based media workflow.', 'Choose the simple or variant template, preserve required columns and identifiers, and validate a small sample before the full catalog.', 'Review every rejected product group and avoid duplicate re-uploads that could create inconsistent catalog entries.'),
                        $segment('faqs', 'seller.product_faqs.index', 'product_faq.view', 'Product FAQs', 'Create common product questions and answers that reduce customer uncertainty before purchase.', 'Keep each answer factual, concise, and linked to the intended products.', 'FAQs are public content and must not contain private customer, staff, or supplier information.'),
                        $segment('addon_groups', 'seller.addon-groups.index', 'addon_group.view', 'Add-on Groups', 'Add-on groups define optional or required choices and how many items customers may select.', 'Create a clear group, choose single or multiple selection, set requirement and limits, then add its items.', 'Selection limits must match the actual choices available or customers may be unable to add the product.'),
                        $segment('product_addons', 'seller.product-addons.index', 'product_addon.view', 'Product Add-ons', 'Attach suitable add-on groups to the product variants where customers should see them.', 'Select the product and variants carefully, then attach only compatible groups.', 'An add-on must be attached to the chosen variant and available in the selected store before checkout accepts it.'),
                        $segment('store_addon_items', 'seller.store-addon-items.index', 'store_addon_item.view', 'Store Add-on Inventory', 'Store add-on items hold store-specific price, cost, stock, and availability for attached add-ons.', 'Choose the store and group, enter base-currency pricing and stock, and use bulk assignment only for stores that share the same values.', 'Keep availability and stock current; checkout validates them again to prevent overselling.'),
                    ],
                ),
            ],
        ],
        [
            'title' => 'Content and Sales',
            'guides' => [
                'watch_and_buy' => $guide(
                    'Watch & Buy',
                    'Create the seller profile, statuses, and shoppable reels.',
                    'ti ti-player-play',
                    'watch_and_buy',
                    [
                        $segment('profile', 'seller.watch-and-buy.profile.edit', 'watch_buy.profile.view', 'Watch & Buy Profile', 'Your seller creator profile owns the statuses and reels customers discover.', 'Complete the username, biography, and profile image before publishing content.', 'Use a recognizable store identity and keep all profile text customer-safe.'),
                        $segment('statuses', 'seller.watch-and-buy.statuses.index', 'watch_buy.status.view', 'Watch & Buy Statuses', 'Statuses are short-lived text, image, or video updates that can feature store products.', 'Create the content, attach eligible products if needed, preview it, and review the expiry before publishing.', 'Use only media you own and remove outdated promotional information promptly.'),
                        $segment('reels', 'seller.watch-and-buy.reels.index', 'watch_buy.reel.view', 'Watch & Buy Reels', 'Reels are persistent shoppable videos connected to your seller profile and eligible products or variants.', 'Upload valid vertical video, add a clear caption and cover, attach products, then publish only after previewing the result.', 'Products without sellable store inventory should not be promoted as immediately available.'),
                    ],
                ),
                'orders' => $guide(
                    'Orders',
                    'Fulfil or cancel lines and manage manual or Shiprocket parcels.',
                    'ti ti-package',
                    'orders',
                    [
                        $segment('index', 'seller.orders.index', 'order.view', 'Seller Orders', 'Orders are scoped to the store selected in the header and combine payment, fulfilment, delivery method, and date filters.', 'Open an order, fulfil or cancel remaining quantities, create the parcel, print its label, and advance or sync tracking until delivery. Use Shiprocket retry, edit, and NDR actions only when their state allows.', 'There is no accept or reject stage. Fulfilment is derived from parcel delivery, and every cancellation requires the correct reason.'),
                    ],
                ),
                'return_orders' => $guide(
                    'Returns',
                    'Decide return requests and complete pickup, refund, and restocking.',
                    'ti ti-truck-return',
                    'return_orders',
                    [
                        $segment('index', 'seller.returns.index', 'return.view', 'Seller Returns', 'Return requests include the customer reason, photos, quantity, order line, and current decision state.', 'Review the evidence and policy, approve or reject with a clear reason, then follow pickup and receipt through to the correct refund and restock outcome.', 'Shiprocket fulfilment can create a reverse pickup. Restore stock only when the returned goods and workflow state support it.'),
                    ],
                ),
            ],
        ],
        [
            'title' => 'Finance',
            'guides' => [
                'earnings' => $guide(
                    'Earnings',
                    'Track delivered-order earnings, deductions, holds, and settlement.',
                    'ti ti-currency-dollar',
                    'earnings',
                    [
                        $segment('index', 'seller.commissions.index', 'earning.view', 'Seller Earnings', 'Delivered orders create earning entries after commission and eligible adjustments, which remain unsettled until the administrator settles them.', 'Filter by order, date, and settlement status to reconcile gross value, commission, deductions, and net earnings.', 'Unsettled earnings are not yet wallet balance. Contact the administrator with the exact order and ledger reference when something differs.'),
                    ],
                ),
                'wallet' => $guide(
                    'Wallet',
                    'Review available balance and request seller payouts.',
                    'ti ti-wallet',
                    'wallet',
                    [
                        $segment('balance', 'seller.wallet.index', 'wallet.view', 'Seller Wallet', 'The seller wallet receives settled earnings and records every credit, debit, and withdrawal effect.', 'Review balance and transaction references before deciding how much is available to withdraw.', 'The wallet is a ledger-backed balance; unsettled earnings do not appear until the administrator settles them.'),
                        $segment('withdrawals', 'seller.withdrawals.index', 'withdrawal.view', 'Request a Withdrawal', 'Create a payout request from available seller wallet balance using the configured payout details.', 'Confirm the available amount and payout information, enter a valid request, and submit it once for administrator review.', 'A pending request is not complete until the administrator approves it. Do not submit duplicates.'),
                        $segment('history', 'seller.withdrawals.history', 'withdrawal.view', 'Withdrawal History', 'Review pending, approved, and rejected payout requests and their references.', 'Use status and date filters to trace a request and read any rejection reason before submitting a corrected request.', 'History is an audit record; changes to payout details apply to future requests.'),
                    ],
                ),
            ],
        ],
        [
            'title' => 'Team and Updates',
            'guides' => [
                'notifications' => $guide(
                    'Notifications',
                    'Review store events that need seller attention.',
                    'ti ti-bell-ringing',
                    'notifications',
                    [
                        $segment('index', 'seller.notifications.index', 'notification.view', 'Seller Notifications', 'The seller inbox records orders, returns, settlements, withdrawals, product moderation, and store events.', 'Open unread items, follow the linked record, and mark them read after the underlying work is reviewed.', 'Marking a notification read does not update the linked order, return, or payout.'),
                    ],
                ),
                'roles_permissions' => $guide(
                    'Roles & Permissions',
                    'Create seller staff accounts with minimum required access.',
                    'ti ti-users-group',
                    'roles_permissions',
                    [
                        $segment('roles', 'seller.roles.index', 'role.view', 'Seller Staff Roles', 'Roles group store permissions for staff who help manage catalog, orders, finance, or content.', 'Create roles around real responsibilities and enable only the stores and actions each job requires.', 'Limit financial and destructive permissions to trusted staff.'),
                        $segment('users', 'seller.system-users.index', 'system_user.view', 'Seller Staff Users', 'Staff users receive individual logins and one of the seller roles you maintain.', 'Create the staff identity, assign the correct role, and disable access as soon as it is no longer needed.', 'Never share the primary seller login with employees.'),
                    ],
                ),
            ],
        ],
    ],
];
