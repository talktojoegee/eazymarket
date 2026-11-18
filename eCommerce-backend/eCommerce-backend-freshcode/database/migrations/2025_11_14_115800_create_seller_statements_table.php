<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('return_id')->nullable();
            $table->enum('entry_type', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 10)->nullable();
            $table->string('reference_type', 191)->nullable();
            $table->string('reference_id', 191)->nullable();
            $table->string('description', 191)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->enum('settlement_status', ['pending', 'settled'])->default('pending');
            $table->timestamp('settled_at')->nullable();
            $table->string('settlement_reference', 191)->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'posted_at'], 'seller_statements_seller_id_posted_at_index');
            $table->index('order_id', 'seller_statements_order_id_index');
            $table->index('order_item_id', 'seller_statements_order_item_id_index');
            $table->index('return_id', 'seller_statements_return_id_index');
            $table->index('entry_type', 'seller_statements_entry_type_index');
            $table->index(['seller_id', 'settlement_status'], 'seller_statements_seller_id_settlement_status_index');
            $table->index(['settlement_status', 'posted_at'], 'seller_statements_settlement_status_posted_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_statements');
    }
};
