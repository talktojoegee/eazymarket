<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_id');
            $table->char('uuid', 36);
            $table->unsignedBigInteger('user_id');
            $table->enum('created_by', ['customer', 'seller', 'admin'])->default('customer');
            $table->string('slug', 100);
            $table->string('email', 191);
            $table->string('walkin_customer_name', 191)->nullable();
            $table->string('walkin_customer_mobile', 32)->nullable();
            $table->string('ip_address', 45);
            $table->string('currency_code', 3)->default('USD');
            $table->decimal('currency_rate', 10, 6);
            $table->string('payment_method', 191);
            $table->string('payment_status', 191);
            $table->decimal('wallet_balance', 12, 2);
            $table->string('promo_code', 50)->nullable();
            $table->decimal('promo_discount', 10, 2)->default('0.00');
            $table->decimal('pos_savings', 12, 2)->default('0.00');
            $table->decimal('pos_split_cash', 12, 2)->default('0.00');
            $table->string('gift_card', 50)->nullable();
            $table->decimal('gift_card_discount', 10, 2)->default('0.00');
            $table->decimal('delivery_charge', 10, 2)->default('0.00');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_payable', 12, 2);
            $table->decimal('final_total', 12, 2);
            $table->decimal('shipping_total', 12, 2)->default('0.00');
            $table->decimal('tax_total', 12, 2)->default('0.00');
            $table->decimal('total_shipping_clawback', 12, 2)->default('0.00');
            $table->decimal('total_promo_clawback', 12, 2)->default('0.00');
            $table->decimal('total_pending_charge_created', 12, 2)->default('0.00');
            $table->enum('cancellation_state', ['NONE', 'PARTIAL', 'FULL'])->default('NONE');
            $table->timestamp('last_cancellation_at')->nullable();
            $table->json('tax_breakdown')->nullable();
            $table->string('status', 191)->default('pending');
            $table->boolean('is_flagged')->default('0');
            $table->json('escalation_reasons')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->string('billing_name', 191);
            $table->text('billing_address_1');
            $table->text('billing_address_2')->nullable();
            $table->string('billing_zip', 20);
            $table->string('billing_phone', 20);
            $table->enum('billing_address_type', ['home', 'office', 'other']);
            $table->string('billing_city', 191);
            $table->string('billing_state', 191);
            $table->string('billing_country', 191);
            $table->string('billing_country_code', 3);
            $table->string('shipping_name', 191);
            $table->text('shipping_address_1');
            $table->text('shipping_address_2')->nullable();
            $table->string('shipping_zip', 20);
            $table->string('shipping_phone', 20);
            $table->enum('shipping_address_type', ['home', 'office', 'other']);
            $table->string('shipping_city', 191);
            $table->string('shipping_state', 191);
            $table->string('shipping_country', 191);
            $table->string('shipping_country_code', 3);
            $table->text('order_note')->nullable();
            $table->timestamps();
            $table->json('order_summary')->nullable();
            $table->unique('uuid', 'orders_uuid_unique');
            $table->unique('slug', 'orders_slug_unique');
            $table->index('created_by', 'orders_created_by_index');
            $table->index('is_flagged', 'orders_is_flagged_index');
            $table->index('market_id', 'orders_market_id_idx');
            $table->index(['cancellation_state', 'last_cancellation_at'], 'orders_cancel_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
