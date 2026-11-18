<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('delivery_boy_id')->nullable();
            $table->text('reason')->nullable();
            $table->enum('reason_type', ['SELLER_FAULT', 'CUSTOMER_FAULT'])->nullable();
            $table->string('reason_code', 64)->nullable();
            $table->enum('shipping_cost_payer', ['SELLER', 'PLATFORM', 'CUSTOMER'])->nullable();
            $table->decimal('reimbursement_amount', 12, 2)->nullable();
            $table->unsignedBigInteger('return_shipment_id')->nullable();
            $table->enum('admin_adjudication_status', ['none', 'pending', 'resolved'])->default('none');
            $table->text('admin_notes')->nullable();
            $table->decimal('refund_amount', 10, 2)->default('0.00');
            $table->text('seller_comment')->nullable();
            $table->enum('pickup_status', ['pending', 'assigned', 'picked_up', 'delivered_to_seller', 'cancelled'])->default('pending');
            $table->enum('return_status', ['cancelled', 'requested', 'seller_approved', 'seller_rejected', 'pickup_assigned', 'picked_up', 'received_by_seller', 'refund_processed', 'completed'])->default('requested');
            $table->timestamp('seller_approved_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('refund_processed_at')->nullable();
            $table->timestamps();
            $table->index(['reason_type', 'shipping_cost_payer'], 'order_item_returns_reason_type_shipping_cost_payer_index');
            $table->index('admin_adjudication_status', 'order_item_returns_admin_adjudication_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_returns');
    }
};
