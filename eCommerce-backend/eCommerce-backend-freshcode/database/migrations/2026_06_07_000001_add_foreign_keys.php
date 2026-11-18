<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_campaign_stats', function (Blueprint $table) {
            $table->foreign('campaign_id', 'ad_campaign_stats_campaign_id_foreign')->references('id')->on('ad_campaigns')->cascadeOnDelete();
        });

        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->foreign('approved_by', 'ad_campaigns_approved_by_foreign')->references('id')->on('users')->nullOnDelete();
            $table->foreign('force_stopped_by', 'ad_campaigns_force_stopped_by_foreign')->references('id')->on('users')->nullOnDelete();
            $table->foreign('product_id', 'ad_campaigns_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('seller_id', 'ad_campaigns_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
            $table->foreign('wallet_transaction_id', 'ad_campaigns_wallet_transaction_id_foreign')->references('id')->on('wallet_transactions')->nullOnDelete();
        });

        Schema::table('addon_groups', function (Blueprint $table) {
            $table->foreign('seller_id', 'addon_groups_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
        });

        Schema::table('addon_items', function (Blueprint $table) {
            $table->foreign('addon_group_id', 'addon_items_addon_group_id_foreign')->references('id')->on('addon_groups')->cascadeOnDelete();
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->foreign('user_id', 'addresses_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('app_notification_market_map', function (Blueprint $table) {
            $table->foreign('market_id', 'app_notification_market_map_market_id_foreign')->references('id')->on('markets')->cascadeOnDelete();
            $table->foreign('notification_id', 'app_notification_market_map_notification_id_foreign')->references('id')->on('app_notifications')->cascadeOnDelete();
        });

        Schema::table('app_notification_user_map', function (Blueprint $table) {
            $table->foreign('notification_id', 'app_notification_user_map_notification_id_foreign')->references('id')->on('app_notifications')->cascadeOnDelete();
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->foreign('created_by', 'app_notifications_created_by_foreign')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->foreign('scope_id', 'brands_scope_id_foreign')->references('id')->on('categories')->cascadeOnDelete();
        });

        Schema::table('cart_item_addons', function (Blueprint $table) {
            $table->foreign('addon_group_id', 'cart_item_addons_addon_group_id_foreign')->references('id')->on('addon_groups')->cascadeOnDelete();
            $table->foreign('addon_item_id', 'cart_item_addons_addon_item_id_foreign')->references('id')->on('addon_items')->cascadeOnDelete();
            $table->foreign('cart_item_id', 'cart_item_addons_cart_item_id_foreign')->references('id')->on('cart_items')->cascadeOnDelete();
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreign('cart_id', 'cart_items_cart_id_foreign')->references('id')->on('carts')->cascadeOnDelete();
            $table->foreign('product_id', 'cart_items_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id', 'cart_items_product_variant_id_foreign')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('store_id', 'cart_items_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->foreign('billing_address_id', 'carts_billing_address_id_foreign')->references('id')->on('addresses')->nullOnDelete();
            $table->foreign('market_id', 'carts_market_id_foreign')->references('id')->on('markets')->nullOnDelete();
            $table->foreign('shipping_address_id', 'carts_shipping_address_id_foreign')->references('id')->on('addresses')->nullOnDelete();
            $table->foreign('user_id', 'carts_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id', 'categories_parent_id_foreign')->references('id')->on('categories')->nullOnDelete();
        });

        Schema::table('category_product', function (Blueprint $table) {
            $table->foreign('category_id', 'category_product_category_id_foreign')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('product_id', 'category_product_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('category_product_conditions', function (Blueprint $table) {
            $table->foreign('category_id', 'category_product_conditions_category_id_foreign')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('product_condition_id', 'category_product_conditions_product_condition_id_foreign')->references('id')->on('product_conditions')->cascadeOnDelete();
        });

        Schema::table('category_search_labels', function (Blueprint $table) {
            $table->foreign('category_id', 'category_search_labels_category_id_foreign')->references('id')->on('categories')->cascadeOnDelete();
        });

        Schema::table('custom_product_section_field', function (Blueprint $table) {
            $table->foreign('custom_product_field_id', 'custom_product_section_field_custom_product_field_id_foreign')->references('id')->on('custom_product_fields')->cascadeOnDelete();
            $table->foreign('custom_product_section_id', 'custom_product_section_field_custom_product_section_id_foreign')->references('id')->on('custom_product_sections')->cascadeOnDelete();
        });

        Schema::table('custom_product_sections', function (Blueprint $table) {
            $table->foreign('product_id', 'custom_product_sections_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('customer_pending_charges', function (Blueprint $table) {
            $table->foreign('applied_to_order_id', 'customer_pending_charges_applied_to_order_id_foreign')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('seller_order_id', 'customer_pending_charges_seller_order_id_foreign')->references('id')->on('seller_orders')->nullOnDelete();
            $table->foreign('user_id', 'customer_pending_charges_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('delivery_boy_assignments', function (Blueprint $table) {
            $table->foreign('delivery_boy_id', 'delivery_boy_assignments_delivery_boy_id_foreign')->references('id')->on('delivery_boys')->cascadeOnDelete();
            $table->foreign('order_id', 'delivery_boy_assignments_order_id_foreign')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('order_item_id', 'delivery_boy_assignments_order_item_id_foreign')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('return_id', 'delivery_boy_assignments_return_id_foreign')->references('id')->on('order_item_returns')->cascadeOnDelete();
            $table->foreign('transaction_id', 'delivery_boy_assignments_transaction_id_foreign')->references('id')->on('wallet_transactions')->nullOnDelete();
        });

        Schema::table('delivery_boys', function (Blueprint $table) {
            $table->foreign('blocked_by_admin_id', 'delivery_boys_blocked_by_admin_id_foreign')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('gift_cards', function (Blueprint $table) {
            $table->foreign('seller_id', 'gift_cards_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
        });

        Schema::table('global_product_attribute_values', function (Blueprint $table) {
            $table->foreign('global_attribute_id', 'global_product_attribute_values_global_attribute_id_foreign')->references('id')->on('global_product_attributes');
        });

        Schema::table('global_product_attributes', function (Blueprint $table) {
            $table->foreign('seller_id', 'global_product_attributes_seller_id_foreign')->references('id')->on('sellers');
        });

        Schema::table('market_country', function (Blueprint $table) {
            $table->foreign('market_id', 'market_country_market_id_foreign')->references('id')->on('markets')->cascadeOnDelete();
        });

        Schema::table('market_currency_rates', function (Blueprint $table) {
            $table->foreign('market_id', 'market_currency_rates_market_id_foreign')->references('id')->on('markets')->cascadeOnDelete();
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->foreign('permission_id', 'model_has_permissions_permission_id_foreign')->references('id')->on('permissions')->cascadeOnDelete();
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->foreign('role_id', 'model_has_roles_role_id_foreign')->references('id')->on('roles')->cascadeOnDelete();
        });

        Schema::table('order_audit_logs', function (Blueprint $table) {
            $table->foreign('admin_id', 'order_audit_logs_admin_id_foreign')->references('id')->on('users')->nullOnDelete();
            $table->foreign('order_id', 'order_audit_logs_order_id_foreign')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('order_item_id', 'order_audit_logs_order_item_id_foreign')->references('id')->on('order_items')->nullOnDelete();
        });

        Schema::table('order_item_addons', function (Blueprint $table) {
            $table->foreign('addon_group_id', 'order_item_addons_addon_group_id_foreign')->references('id')->on('addon_groups')->cascadeOnDelete();
            $table->foreign('addon_item_id', 'order_item_addons_addon_item_id_foreign')->references('id')->on('addon_items')->cascadeOnDelete();
            $table->foreign('order_item_id', 'order_item_addons_order_item_id_foreign')->references('id')->on('order_items')->cascadeOnDelete();
        });

        Schema::table('order_item_returns', function (Blueprint $table) {
            $table->foreign('delivery_boy_id', 'order_item_returns_delivery_boy_id_foreign')->references('id')->on('delivery_boys')->nullOnDelete();
            $table->foreign('order_id', 'order_item_returns_order_id_foreign')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('order_item_id', 'order_item_returns_order_item_id_foreign')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('return_shipment_id', 'order_item_returns_return_shipment_id_foreign')->references('id')->on('shipments')->nullOnDelete();
            $table->foreign('seller_id', 'order_item_returns_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
            $table->foreign('store_id', 'order_item_returns_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('user_id', 'order_item_returns_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('order_id', 'order_items_order_id_foreign')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('product_id', 'order_items_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id', 'order_items_product_variant_id_foreign')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('store_id', 'order_items_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('order_payment_transactions', function (Blueprint $table) {
            $table->foreign('order_id', 'order_payment_transactions_order_id_foreign')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('user_id', 'order_payment_transactions_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('order_promo_line', function (Blueprint $table) {
            $table->foreign('order_id', 'order_promo_line_order_id_foreign')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('promo_id', 'order_promo_line_promo_id_foreign')->references('id')->on('promo')->cascadeOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('market_id', 'orders_market_id_foreign')->references('id')->on('markets');
            $table->foreign('user_id', 'orders_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('pos_parked_sales', function (Blueprint $table) {
            $table->foreign('seller_id', 'pos_parked_sales_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
            $table->foreign('store_id', 'pos_parked_sales_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('pos_refund_lines', function (Blueprint $table) {
            $table->foreign('order_item_id', 'pos_refund_lines_order_item_id_foreign')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('pos_refund_id', 'pos_refund_lines_pos_refund_id_foreign')->references('id')->on('pos_refunds')->cascadeOnDelete();
        });

        Schema::table('pos_refunds', function (Blueprint $table) {
            $table->foreign('order_id', 'pos_refunds_order_id_foreign')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('refunded_by_user_id', 'pos_refunds_refunded_by_user_id_foreign')->references('id')->on('users')->nullOnDelete();
            $table->foreign('store_id', 'pos_refunds_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('product_conditions', function (Blueprint $table) {
            $table->foreign('category_id', 'product_conditions_category_id_foreign')->references('id')->on('categories')->cascadeOnDelete();
        });

        Schema::table('product_faqs', function (Blueprint $table) {
            $table->foreign('product_id', 'product_faqs_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('product_taxes', function (Blueprint $table) {
            $table->foreign('product_id', 'product_taxes_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('tax_class_id', 'product_taxes_tax_class_id_foreign')->references('id')->on('tax_classes')->cascadeOnDelete();
        });

        Schema::table('product_variant_attributes', function (Blueprint $table) {
            $table->foreign('global_attribute_id', 'product_variant_attributes_global_attribute_id_foreign')->references('id')->on('global_product_attributes');
            $table->foreign('global_attribute_value_id', 'product_variant_attributes_global_attribute_value_id_foreign')->references('id')->on('global_product_attribute_values');
            $table->foreign('product_id', 'product_variant_attributes_product_id_foreign')->references('id')->on('products');
            $table->foreign('product_variant_id', 'product_variant_attributes_product_variant_id_foreign')->references('id')->on('product_variants');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreign('product_id', 'product_variants_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('badge_id', 'products_badge_id_foreign')->references('id')->on('badges')->nullOnDelete();
            $table->foreign('brand_id', 'products_brand_id_foreign')->references('id')->on('brands')->nullOnDelete();
            $table->foreign('category_id', 'products_category_id_foreign')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('product_condition_id', 'products_product_condition_id_foreign')->references('id')->on('product_conditions')->cascadeOnDelete();
            $table->foreign('seller_id', 'products_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
        });

        Schema::table('referral_earnings', function (Blueprint $table) {
            $table->foreign('beneficiary_id', 'referral_earnings_beneficiary_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('order_id', 'referral_earnings_order_id_foreign')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('referral_id', 'referral_earnings_referral_id_foreign')->references('id')->on('referrals')->cascadeOnDelete();
            $table->foreign('wallet_transaction_id', 'referral_earnings_wallet_transaction_id_foreign')->references('id')->on('wallet_transactions')->nullOnDelete();
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->foreign('referred_id', 'referrals_referred_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('referrer_id', 'referrals_referrer_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->foreign('product_id', 'reviews_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('user_id', 'reviews_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->foreign('permission_id', 'role_has_permissions_permission_id_foreign')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('role_id', 'role_has_permissions_role_id_foreign')->references('id')->on('roles')->cascadeOnDelete();
        });

        Schema::table('seller_feedback', function (Blueprint $table) {
            $table->foreign('order_id', 'seller_feedback_order_id_foreign')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('order_item_id', 'seller_feedback_order_item_id_foreign')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('seller_id', 'seller_feedback_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
            $table->foreign('store_id', 'seller_feedback_store_id_foreign')->references('id')->on('stores')->nullOnDelete();
            $table->foreign('user_id', 'seller_feedback_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('seller_order_items', function (Blueprint $table) {
            $table->foreign('order_item_id', 'seller_order_items_order_item_id_foreign')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('product_id', 'seller_order_items_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id', 'seller_order_items_product_variant_id_foreign')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('seller_order_id', 'seller_order_items_seller_order_id_foreign')->references('id')->on('seller_orders')->cascadeOnDelete();
        });

        Schema::table('seller_order_promo_allocations', function (Blueprint $table) {
            $table->foreign('promo_id', 'seller_order_promo_allocations_promo_id_foreign')->references('id')->on('promo')->cascadeOnDelete();
            $table->foreign('seller_order_id', 'seller_order_promo_allocations_seller_order_id_foreign')->references('id')->on('seller_orders')->cascadeOnDelete();
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->foreign('market_id', 'seller_orders_market_id_foreign')->references('id')->on('markets');
            $table->foreign('order_id', 'seller_orders_order_id_foreign')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreign('seller_id', 'seller_orders_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
            $table->foreign('shipping_zone_id', 'seller_orders_shipping_zone_id_foreign')->references('id')->on('shipping_zones')->nullOnDelete();
            $table->foreign('store_location_id', 'seller_orders_store_location_id_foreign')->references('id')->on('store_locations');
        });

        Schema::table('seller_shipping_credentials', function (Blueprint $table) {
            $table->foreign('seller_id', 'seller_shipping_credentials_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
        });

        Schema::table('seller_statements', function (Blueprint $table) {
            $table->foreign('order_id', 'seller_statements_order_id_foreign')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('order_item_id', 'seller_statements_order_item_id_foreign')->references('id')->on('order_items')->nullOnDelete();
            $table->foreign('return_id', 'seller_statements_return_id_foreign')->references('id')->on('order_item_returns')->nullOnDelete();
            $table->foreign('seller_id', 'seller_statements_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
        });

        Schema::table('seller_subscription_snapshots', function (Blueprint $table) {
            $table->foreign('seller_subscription_id', 'seller_subscription_snapshots_seller_subscription_id_foreign')->references('id')->on('seller_subscriptions')->cascadeOnDelete();
        });

        Schema::table('seller_subscription_usages', function (Blueprint $table) {
            $table->foreign('seller_id', 'seller_subscription_usages_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
        });

        Schema::table('seller_subscriptions', function (Blueprint $table) {
            $table->foreign('plan_id', 'seller_subscriptions_plan_id_foreign')->references('id')->on('subscription_plans');
            $table->foreign('seller_id', 'seller_subscriptions_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
        });

        Schema::table('seller_user', function (Blueprint $table) {
            $table->foreign('seller_id', 'seller_user_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
            $table->foreign('user_id', 'seller_user_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('seller_withdrawal_requests', function (Blueprint $table) {
            $table->foreign('processed_by', 'seller_withdrawal_requests_processed_by_foreign')->references('id')->on('users')->nullOnDelete();
            $table->foreign('seller_id', 'seller_withdrawal_requests_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
            $table->foreign('user_id', 'seller_withdrawal_requests_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('shipment_items', function (Blueprint $table) {
            $table->foreign('order_item_id', 'shipment_items_order_item_id_foreign')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('shipment_id', 'shipment_items_shipment_id_foreign')->references('id')->on('shipments')->cascadeOnDelete();
        });

        Schema::table('shipment_tracking_events', function (Blueprint $table) {
            $table->foreign('shipment_id', 'shipment_tracking_events_shipment_id_foreign')->references('id')->on('shipments')->cascadeOnDelete();
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->foreign('parent_shipment_id', 'shipments_parent_shipment_id_foreign')->references('id')->on('shipments')->nullOnDelete();
            $table->foreign('seller_order_id', 'shipments_seller_order_id_foreign')->references('id')->on('seller_orders')->cascadeOnDelete();
            $table->foreign('shipping_zone_id', 'shipments_shipping_zone_id_foreign')->references('id')->on('shipping_zones')->nullOnDelete();
            $table->foreign('store_location_id', 'shipments_store_location_id_foreign')->references('id')->on('store_locations');
        });

        Schema::table('shipping_profile_products', function (Blueprint $table) {
            $table->foreign('product_id', 'shipping_profile_products_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('shipping_profile_id', 'shipping_profile_products_shipping_profile_id_foreign')->references('id')->on('shipping_profiles')->cascadeOnDelete();
        });

        Schema::table('shipping_profile_store_location', function (Blueprint $table) {
            $table->foreign('shipping_profile_id', 'shipping_profile_store_location_shipping_profile_id_foreign')->references('id')->on('shipping_profiles')->cascadeOnDelete();
            $table->foreign('store_location_id', 'shipping_profile_store_location_store_location_id_foreign')->references('id')->on('store_locations')->cascadeOnDelete();
        });

        Schema::table('shipping_profiles', function (Blueprint $table) {
            $table->foreign('store_id', 'shipping_profiles_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('shipping_webhook_events', function (Blueprint $table) {
            $table->foreign('shipment_id', 'shipping_webhook_events_shipment_id_foreign')->references('id')->on('shipments')->nullOnDelete();
        });

        Schema::table('shipping_zone_countries', function (Blueprint $table) {
            $table->foreign('shipping_zone_id', 'shipping_zone_countries_shipping_zone_id_foreign')->references('id')->on('shipping_zones')->cascadeOnDelete();
        });

        Schema::table('shipping_zone_rates', function (Blueprint $table) {
            $table->foreign('shipping_zone_id', 'shipping_zone_rates_shipping_zone_id_foreign')->references('id')->on('shipping_zones')->cascadeOnDelete();
        });

        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->foreign('shipping_profile_id', 'shipping_zones_shipping_profile_id_foreign')->references('id')->on('shipping_profiles')->cascadeOnDelete();
        });

        Schema::table('store_addon_items', function (Blueprint $table) {
            $table->foreign('addon_item_id', 'store_addon_items_addon_item_id_foreign')->references('id')->on('addon_items')->cascadeOnDelete();
            $table->foreign('store_id', 'store_addon_items_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('store_inventory_logs', function (Blueprint $table) {
            $table->foreign('product_variant_id', 'store_inventory_logs_product_variant_id_foreign')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('store_id', 'store_inventory_logs_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('store_location_inventory', function (Blueprint $table) {
            $table->foreign('product_variant_id', 'store_location_inventory_product_variant_id_foreign')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('store_location_id', 'store_location_inventory_store_location_id_foreign')->references('id')->on('store_locations')->cascadeOnDelete();
        });

        Schema::table('store_locations', function (Blueprint $table) {
            $table->foreign('store_id', 'store_locations_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('store_market', function (Blueprint $table) {
            $table->foreign('market_id', 'store_market_market_id_foreign')->references('id')->on('markets')->cascadeOnDelete();
            $table->foreign('store_id', 'store_market_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('store_packages', function (Blueprint $table) {
            $table->foreign('store_id', 'store_packages_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('store_product_variant_addons', function (Blueprint $table) {
            $table->foreign('addon_group_id', 'store_product_variant_addons_addon_group_id_foreign')->references('id')->on('addon_groups')->cascadeOnDelete();
            $table->foreign('addon_item_id', 'store_product_variant_addons_addon_item_id_foreign')->references('id')->on('addon_items')->cascadeOnDelete();
            $table->foreign('product_variant_id', 'store_product_variant_addons_product_variant_id_foreign')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('store_id', 'store_product_variant_addons_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('store_product_variants', function (Blueprint $table) {
            $table->foreign('product_variant_id', 'store_product_variants_product_variant_id_foreign')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('store_id', 'store_product_variants_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->foreign('seller_id', 'stores_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
        });

        Schema::table('subscription_plan_limits', function (Blueprint $table) {
            $table->foreign('plan_id', 'subscription_plan_limits_plan_id_foreign')->references('id')->on('subscription_plans')->cascadeOnDelete();
        });

        Schema::table('subscription_transactions', function (Blueprint $table) {
            $table->foreign('plan_id', 'subscription_transactions_plan_id_foreign')->references('id')->on('subscription_plans');
            $table->foreign('seller_id', 'subscription_transactions_seller_id_foreign')->references('id')->on('sellers')->cascadeOnDelete();
            $table->foreign('seller_subscription_id', 'subscription_transactions_seller_subscription_id_foreign')->references('id')->on('seller_subscriptions')->cascadeOnDelete();
        });

        Schema::table('support_ticket_messages', function (Blueprint $table) {
            $table->foreign('ticket_id', 'support_ticket_messages_ticket_id_foreign')->references('id')->on('support_tickets')->cascadeOnDelete();
            $table->foreign('user_id', 'support_ticket_messages_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreign('ticket_type_id', 'support_tickets_ticket_type_id_foreign')->references('id')->on('support_ticket_types')->cascadeOnDelete();
            $table->foreign('user_id', 'support_tickets_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('tax_class_tax_rate', function (Blueprint $table) {
            $table->foreign('tax_class_id', 'tax_class_tax_rate_tax_class_id_foreign')->references('id')->on('tax_classes')->cascadeOnDelete();
            $table->foreign('tax_rate_id', 'tax_class_tax_rate_tax_rate_id_foreign')->references('id')->on('tax_rates')->cascadeOnDelete();
        });

        Schema::table('tax_classes', function (Blueprint $table) {
            $table->foreign('market_id', 'tax_classes_market_id_foreign')->references('id')->on('markets');
        });

        Schema::table('user_fcm_tokens', function (Blueprint $table) {
            $table->foreign('user_id', 'user_fcm_tokens_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('user_market', function (Blueprint $table) {
            $table->foreign('market_id', 'user_market_market_id_foreign')->references('id')->on('markets')->cascadeOnDelete();
            $table->foreign('user_id', 'user_market_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->foreign('order_id', 'wallet_transactions_order_id_foreign')->references('id')->on('orders')->nullOnDelete();
            $table->foreign('store_id', 'wallet_transactions_store_id_foreign')->references('id')->on('stores');
            $table->foreign('user_id', 'wallet_transactions_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('wallet_id', 'wallet_transactions_wallet_id_foreign')->references('id')->on('wallets')->cascadeOnDelete();
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->foreign('user_id', 'wallets_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->foreign('product_id', 'wishlist_items_product_id_foreign')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('product_variant_id', 'wishlist_items_product_variant_id_foreign')->references('id')->on('product_variants')->cascadeOnDelete();
            $table->foreign('store_id', 'wishlist_items_store_id_foreign')->references('id')->on('stores')->cascadeOnDelete();
            $table->foreign('wishlist_id', 'wishlist_items_wishlist_id_foreign')->references('id')->on('wishlists')->cascadeOnDelete();
        });

        Schema::table('wishlists', function (Blueprint $table) {
            $table->foreign('user_id', 'wishlists_user_id_foreign')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ad_campaign_stats', function (Blueprint $table) {
            $table->dropForeign('ad_campaign_stats_campaign_id_foreign');
        });

        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropForeign('ad_campaigns_approved_by_foreign');
            $table->dropForeign('ad_campaigns_force_stopped_by_foreign');
            $table->dropForeign('ad_campaigns_product_id_foreign');
            $table->dropForeign('ad_campaigns_seller_id_foreign');
            $table->dropForeign('ad_campaigns_wallet_transaction_id_foreign');
        });

        Schema::table('addon_groups', function (Blueprint $table) {
            $table->dropForeign('addon_groups_seller_id_foreign');
        });

        Schema::table('addon_items', function (Blueprint $table) {
            $table->dropForeign('addon_items_addon_group_id_foreign');
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropForeign('addresses_user_id_foreign');
        });

        Schema::table('app_notification_market_map', function (Blueprint $table) {
            $table->dropForeign('app_notification_market_map_market_id_foreign');
            $table->dropForeign('app_notification_market_map_notification_id_foreign');
        });

        Schema::table('app_notification_user_map', function (Blueprint $table) {
            $table->dropForeign('app_notification_user_map_notification_id_foreign');
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropForeign('app_notifications_created_by_foreign');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropForeign('brands_scope_id_foreign');
        });

        Schema::table('cart_item_addons', function (Blueprint $table) {
            $table->dropForeign('cart_item_addons_addon_group_id_foreign');
            $table->dropForeign('cart_item_addons_addon_item_id_foreign');
            $table->dropForeign('cart_item_addons_cart_item_id_foreign');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign('cart_items_cart_id_foreign');
            $table->dropForeign('cart_items_product_id_foreign');
            $table->dropForeign('cart_items_product_variant_id_foreign');
            $table->dropForeign('cart_items_store_id_foreign');
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropForeign('carts_billing_address_id_foreign');
            $table->dropForeign('carts_market_id_foreign');
            $table->dropForeign('carts_shipping_address_id_foreign');
            $table->dropForeign('carts_user_id_foreign');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign('categories_parent_id_foreign');
        });

        Schema::table('category_product', function (Blueprint $table) {
            $table->dropForeign('category_product_category_id_foreign');
            $table->dropForeign('category_product_product_id_foreign');
        });

        Schema::table('category_product_conditions', function (Blueprint $table) {
            $table->dropForeign('category_product_conditions_category_id_foreign');
            $table->dropForeign('category_product_conditions_product_condition_id_foreign');
        });

        Schema::table('category_search_labels', function (Blueprint $table) {
            $table->dropForeign('category_search_labels_category_id_foreign');
        });

        Schema::table('custom_product_section_field', function (Blueprint $table) {
            $table->dropForeign('custom_product_section_field_custom_product_field_id_foreign');
            $table->dropForeign('custom_product_section_field_custom_product_section_id_foreign');
        });

        Schema::table('custom_product_sections', function (Blueprint $table) {
            $table->dropForeign('custom_product_sections_product_id_foreign');
        });

        Schema::table('customer_pending_charges', function (Blueprint $table) {
            $table->dropForeign('customer_pending_charges_applied_to_order_id_foreign');
            $table->dropForeign('customer_pending_charges_seller_order_id_foreign');
            $table->dropForeign('customer_pending_charges_user_id_foreign');
        });

        Schema::table('delivery_boy_assignments', function (Blueprint $table) {
            $table->dropForeign('delivery_boy_assignments_delivery_boy_id_foreign');
            $table->dropForeign('delivery_boy_assignments_order_id_foreign');
            $table->dropForeign('delivery_boy_assignments_order_item_id_foreign');
            $table->dropForeign('delivery_boy_assignments_return_id_foreign');
            $table->dropForeign('delivery_boy_assignments_transaction_id_foreign');
        });

        Schema::table('delivery_boys', function (Blueprint $table) {
            $table->dropForeign('delivery_boys_blocked_by_admin_id_foreign');
        });

        Schema::table('gift_cards', function (Blueprint $table) {
            $table->dropForeign('gift_cards_seller_id_foreign');
        });

        Schema::table('global_product_attribute_values', function (Blueprint $table) {
            $table->dropForeign('global_product_attribute_values_global_attribute_id_foreign');
        });

        Schema::table('global_product_attributes', function (Blueprint $table) {
            $table->dropForeign('global_product_attributes_seller_id_foreign');
        });

        Schema::table('market_country', function (Blueprint $table) {
            $table->dropForeign('market_country_market_id_foreign');
        });

        Schema::table('market_currency_rates', function (Blueprint $table) {
            $table->dropForeign('market_currency_rates_market_id_foreign');
        });

        Schema::table('model_has_permissions', function (Blueprint $table) {
            $table->dropForeign('model_has_permissions_permission_id_foreign');
        });

        Schema::table('model_has_roles', function (Blueprint $table) {
            $table->dropForeign('model_has_roles_role_id_foreign');
        });

        Schema::table('order_audit_logs', function (Blueprint $table) {
            $table->dropForeign('order_audit_logs_admin_id_foreign');
            $table->dropForeign('order_audit_logs_order_id_foreign');
            $table->dropForeign('order_audit_logs_order_item_id_foreign');
        });

        Schema::table('order_item_addons', function (Blueprint $table) {
            $table->dropForeign('order_item_addons_addon_group_id_foreign');
            $table->dropForeign('order_item_addons_addon_item_id_foreign');
            $table->dropForeign('order_item_addons_order_item_id_foreign');
        });

        Schema::table('order_item_returns', function (Blueprint $table) {
            $table->dropForeign('order_item_returns_delivery_boy_id_foreign');
            $table->dropForeign('order_item_returns_order_id_foreign');
            $table->dropForeign('order_item_returns_order_item_id_foreign');
            $table->dropForeign('order_item_returns_return_shipment_id_foreign');
            $table->dropForeign('order_item_returns_seller_id_foreign');
            $table->dropForeign('order_item_returns_store_id_foreign');
            $table->dropForeign('order_item_returns_user_id_foreign');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign('order_items_order_id_foreign');
            $table->dropForeign('order_items_product_id_foreign');
            $table->dropForeign('order_items_product_variant_id_foreign');
            $table->dropForeign('order_items_store_id_foreign');
        });

        Schema::table('order_payment_transactions', function (Blueprint $table) {
            $table->dropForeign('order_payment_transactions_order_id_foreign');
            $table->dropForeign('order_payment_transactions_user_id_foreign');
        });

        Schema::table('order_promo_line', function (Blueprint $table) {
            $table->dropForeign('order_promo_line_order_id_foreign');
            $table->dropForeign('order_promo_line_promo_id_foreign');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('orders_market_id_foreign');
            $table->dropForeign('orders_user_id_foreign');
        });

        Schema::table('pos_parked_sales', function (Blueprint $table) {
            $table->dropForeign('pos_parked_sales_seller_id_foreign');
            $table->dropForeign('pos_parked_sales_store_id_foreign');
        });

        Schema::table('pos_refund_lines', function (Blueprint $table) {
            $table->dropForeign('pos_refund_lines_order_item_id_foreign');
            $table->dropForeign('pos_refund_lines_pos_refund_id_foreign');
        });

        Schema::table('pos_refunds', function (Blueprint $table) {
            $table->dropForeign('pos_refunds_order_id_foreign');
            $table->dropForeign('pos_refunds_refunded_by_user_id_foreign');
            $table->dropForeign('pos_refunds_store_id_foreign');
        });

        Schema::table('product_conditions', function (Blueprint $table) {
            $table->dropForeign('product_conditions_category_id_foreign');
        });

        Schema::table('product_faqs', function (Blueprint $table) {
            $table->dropForeign('product_faqs_product_id_foreign');
        });

        Schema::table('product_taxes', function (Blueprint $table) {
            $table->dropForeign('product_taxes_product_id_foreign');
            $table->dropForeign('product_taxes_tax_class_id_foreign');
        });

        Schema::table('product_variant_attributes', function (Blueprint $table) {
            $table->dropForeign('product_variant_attributes_global_attribute_id_foreign');
            $table->dropForeign('product_variant_attributes_global_attribute_value_id_foreign');
            $table->dropForeign('product_variant_attributes_product_id_foreign');
            $table->dropForeign('product_variant_attributes_product_variant_id_foreign');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropForeign('product_variants_product_id_foreign');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign('products_badge_id_foreign');
            $table->dropForeign('products_brand_id_foreign');
            $table->dropForeign('products_category_id_foreign');
            $table->dropForeign('products_product_condition_id_foreign');
            $table->dropForeign('products_seller_id_foreign');
        });

        Schema::table('referral_earnings', function (Blueprint $table) {
            $table->dropForeign('referral_earnings_beneficiary_id_foreign');
            $table->dropForeign('referral_earnings_order_id_foreign');
            $table->dropForeign('referral_earnings_referral_id_foreign');
            $table->dropForeign('referral_earnings_wallet_transaction_id_foreign');
        });

        Schema::table('referrals', function (Blueprint $table) {
            $table->dropForeign('referrals_referred_id_foreign');
            $table->dropForeign('referrals_referrer_id_foreign');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign('reviews_product_id_foreign');
            $table->dropForeign('reviews_user_id_foreign');
        });

        Schema::table('role_has_permissions', function (Blueprint $table) {
            $table->dropForeign('role_has_permissions_permission_id_foreign');
            $table->dropForeign('role_has_permissions_role_id_foreign');
        });

        Schema::table('seller_feedback', function (Blueprint $table) {
            $table->dropForeign('seller_feedback_order_id_foreign');
            $table->dropForeign('seller_feedback_order_item_id_foreign');
            $table->dropForeign('seller_feedback_seller_id_foreign');
            $table->dropForeign('seller_feedback_store_id_foreign');
            $table->dropForeign('seller_feedback_user_id_foreign');
        });

        Schema::table('seller_order_items', function (Blueprint $table) {
            $table->dropForeign('seller_order_items_order_item_id_foreign');
            $table->dropForeign('seller_order_items_product_id_foreign');
            $table->dropForeign('seller_order_items_product_variant_id_foreign');
            $table->dropForeign('seller_order_items_seller_order_id_foreign');
        });

        Schema::table('seller_order_promo_allocations', function (Blueprint $table) {
            $table->dropForeign('seller_order_promo_allocations_promo_id_foreign');
            $table->dropForeign('seller_order_promo_allocations_seller_order_id_foreign');
        });

        Schema::table('seller_orders', function (Blueprint $table) {
            $table->dropForeign('seller_orders_market_id_foreign');
            $table->dropForeign('seller_orders_order_id_foreign');
            $table->dropForeign('seller_orders_seller_id_foreign');
            $table->dropForeign('seller_orders_shipping_zone_id_foreign');
            $table->dropForeign('seller_orders_store_location_id_foreign');
        });

        Schema::table('seller_shipping_credentials', function (Blueprint $table) {
            $table->dropForeign('seller_shipping_credentials_seller_id_foreign');
        });

        Schema::table('seller_statements', function (Blueprint $table) {
            $table->dropForeign('seller_statements_order_id_foreign');
            $table->dropForeign('seller_statements_order_item_id_foreign');
            $table->dropForeign('seller_statements_return_id_foreign');
            $table->dropForeign('seller_statements_seller_id_foreign');
        });

        Schema::table('seller_subscription_snapshots', function (Blueprint $table) {
            $table->dropForeign('seller_subscription_snapshots_seller_subscription_id_foreign');
        });

        Schema::table('seller_subscription_usages', function (Blueprint $table) {
            $table->dropForeign('seller_subscription_usages_seller_id_foreign');
        });

        Schema::table('seller_subscriptions', function (Blueprint $table) {
            $table->dropForeign('seller_subscriptions_plan_id_foreign');
            $table->dropForeign('seller_subscriptions_seller_id_foreign');
        });

        Schema::table('seller_user', function (Blueprint $table) {
            $table->dropForeign('seller_user_seller_id_foreign');
            $table->dropForeign('seller_user_user_id_foreign');
        });

        Schema::table('seller_withdrawal_requests', function (Blueprint $table) {
            $table->dropForeign('seller_withdrawal_requests_processed_by_foreign');
            $table->dropForeign('seller_withdrawal_requests_seller_id_foreign');
            $table->dropForeign('seller_withdrawal_requests_user_id_foreign');
        });

        Schema::table('shipment_items', function (Blueprint $table) {
            $table->dropForeign('shipment_items_order_item_id_foreign');
            $table->dropForeign('shipment_items_shipment_id_foreign');
        });

        Schema::table('shipment_tracking_events', function (Blueprint $table) {
            $table->dropForeign('shipment_tracking_events_shipment_id_foreign');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropForeign('shipments_parent_shipment_id_foreign');
            $table->dropForeign('shipments_seller_order_id_foreign');
            $table->dropForeign('shipments_shipping_zone_id_foreign');
            $table->dropForeign('shipments_store_location_id_foreign');
        });

        Schema::table('shipping_profile_products', function (Blueprint $table) {
            $table->dropForeign('shipping_profile_products_product_id_foreign');
            $table->dropForeign('shipping_profile_products_shipping_profile_id_foreign');
        });

        Schema::table('shipping_profile_store_location', function (Blueprint $table) {
            $table->dropForeign('shipping_profile_store_location_shipping_profile_id_foreign');
            $table->dropForeign('shipping_profile_store_location_store_location_id_foreign');
        });

        Schema::table('shipping_profiles', function (Blueprint $table) {
            $table->dropForeign('shipping_profiles_store_id_foreign');
        });

        Schema::table('shipping_webhook_events', function (Blueprint $table) {
            $table->dropForeign('shipping_webhook_events_shipment_id_foreign');
        });

        Schema::table('shipping_zone_countries', function (Blueprint $table) {
            $table->dropForeign('shipping_zone_countries_shipping_zone_id_foreign');
        });

        Schema::table('shipping_zone_rates', function (Blueprint $table) {
            $table->dropForeign('shipping_zone_rates_shipping_zone_id_foreign');
        });

        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->dropForeign('shipping_zones_shipping_profile_id_foreign');
        });

        Schema::table('store_addon_items', function (Blueprint $table) {
            $table->dropForeign('store_addon_items_addon_item_id_foreign');
            $table->dropForeign('store_addon_items_store_id_foreign');
        });

        Schema::table('store_inventory_logs', function (Blueprint $table) {
            $table->dropForeign('store_inventory_logs_product_variant_id_foreign');
            $table->dropForeign('store_inventory_logs_store_id_foreign');
        });

        Schema::table('store_location_inventory', function (Blueprint $table) {
            $table->dropForeign('store_location_inventory_product_variant_id_foreign');
            $table->dropForeign('store_location_inventory_store_location_id_foreign');
        });

        Schema::table('store_locations', function (Blueprint $table) {
            $table->dropForeign('store_locations_store_id_foreign');
        });

        Schema::table('store_market', function (Blueprint $table) {
            $table->dropForeign('store_market_market_id_foreign');
            $table->dropForeign('store_market_store_id_foreign');
        });

        Schema::table('store_packages', function (Blueprint $table) {
            $table->dropForeign('store_packages_store_id_foreign');
        });

        Schema::table('store_product_variant_addons', function (Blueprint $table) {
            $table->dropForeign('store_product_variant_addons_addon_group_id_foreign');
            $table->dropForeign('store_product_variant_addons_addon_item_id_foreign');
            $table->dropForeign('store_product_variant_addons_product_variant_id_foreign');
            $table->dropForeign('store_product_variant_addons_store_id_foreign');
        });

        Schema::table('store_product_variants', function (Blueprint $table) {
            $table->dropForeign('store_product_variants_product_variant_id_foreign');
            $table->dropForeign('store_product_variants_store_id_foreign');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropForeign('stores_seller_id_foreign');
        });

        Schema::table('subscription_plan_limits', function (Blueprint $table) {
            $table->dropForeign('subscription_plan_limits_plan_id_foreign');
        });

        Schema::table('subscription_transactions', function (Blueprint $table) {
            $table->dropForeign('subscription_transactions_plan_id_foreign');
            $table->dropForeign('subscription_transactions_seller_id_foreign');
            $table->dropForeign('subscription_transactions_seller_subscription_id_foreign');
        });

        Schema::table('support_ticket_messages', function (Blueprint $table) {
            $table->dropForeign('support_ticket_messages_ticket_id_foreign');
            $table->dropForeign('support_ticket_messages_user_id_foreign');
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropForeign('support_tickets_ticket_type_id_foreign');
            $table->dropForeign('support_tickets_user_id_foreign');
        });

        Schema::table('tax_class_tax_rate', function (Blueprint $table) {
            $table->dropForeign('tax_class_tax_rate_tax_class_id_foreign');
            $table->dropForeign('tax_class_tax_rate_tax_rate_id_foreign');
        });

        Schema::table('tax_classes', function (Blueprint $table) {
            $table->dropForeign('tax_classes_market_id_foreign');
        });

        Schema::table('user_fcm_tokens', function (Blueprint $table) {
            $table->dropForeign('user_fcm_tokens_user_id_foreign');
        });

        Schema::table('user_market', function (Blueprint $table) {
            $table->dropForeign('user_market_market_id_foreign');
            $table->dropForeign('user_market_user_id_foreign');
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropForeign('wallet_transactions_order_id_foreign');
            $table->dropForeign('wallet_transactions_store_id_foreign');
            $table->dropForeign('wallet_transactions_user_id_foreign');
            $table->dropForeign('wallet_transactions_wallet_id_foreign');
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropForeign('wallets_user_id_foreign');
        });

        Schema::table('wishlist_items', function (Blueprint $table) {
            $table->dropForeign('wishlist_items_product_id_foreign');
            $table->dropForeign('wishlist_items_product_variant_id_foreign');
            $table->dropForeign('wishlist_items_store_id_foreign');
            $table->dropForeign('wishlist_items_wishlist_id_foreign');
        });

        Schema::table('wishlists', function (Blueprint $table) {
            $table->dropForeign('wishlists_user_id_foreign');
        });
    }
};
