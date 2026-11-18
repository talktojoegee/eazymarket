<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('seller_order_id');
            $table->unsignedBigInteger('store_location_id');
            $table->string('provider_code', 32);
            $table->unsignedBigInteger('shipping_zone_id')->nullable();
            $table->string('awb_number', 64)->nullable();
            $table->unsignedInteger('courier_id')->nullable();
            $table->string('courier_name', 80)->nullable();
            $table->string('manual_carrier_name', 80)->nullable();
            $table->string('tracking_number', 80)->nullable();
            $table->string('label_url', 512)->nullable();
            $table->string('invoice_url', 512)->nullable();
            $table->string('manifest_url', 512)->nullable();
            $table->string('tracking_url', 512)->nullable();
            $table->decimal('weight_kg', 8, 3)->default('0.000');
            $table->unsignedInteger('length_cm')->default('0');
            $table->unsignedInteger('breadth_cm')->default('0');
            $table->unsignedInteger('height_cm')->default('0');
            $table->decimal('shipping_cost', 12, 2)->default('0.00');
            $table->enum('shipping_cost_paid_by', ['CUSTOMER', 'SELLER', 'PLATFORM'])->default('CUSTOMER');
            $table->char('shipping_currency', 3)->default('USD');
            $table->enum('payment_method', ['prepaid', 'cod'])->default('prepaid');
            $table->decimal('cod_amount', 12, 2)->nullable();
            $table->boolean('is_return')->default('0');
            $table->unsignedBigInteger('parent_shipment_id')->nullable();
            $table->enum('status', ['NEW', 'LABEL_PURCHASED', 'READY', 'PICKED_UP', 'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED', 'EXCEPTION', 'RTO_INITIATED', 'RTO_DELIVERED', 'CANCELLED', 'LOST'])->default('NEW');
            $table->timestamp('pickup_scheduled_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->json('metadata')->nullable();
            $table->json('raw_response')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique('uuid', 'shipments_uuid_unique');
            $table->index('awb_number', 'shipments_awb_number_index');
            $table->index(['seller_order_id', 'status'], 'shipments_seller_order_id_status_index');
            $table->index(['provider_code', 'status'], 'shipments_provider_code_status_index');
            $table->index('is_return', 'shipments_is_return_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
