<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('budget', 12, 2);
            $table->decimal('spent', 12, 2)->default('0.00');
            $table->decimal('cpc_rate_snapshot', 10, 4);
            $table->json('placements')->nullable();
            $table->string('status', 30)->default('draft');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('force_stopped_by')->nullable();
            $table->text('force_stop_reason')->nullable();
            $table->timestamp('force_stopped_at')->nullable();
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['seller_id', 'status'], 'ad_campaigns_seller_id_status_index');
            $table->index(['product_id', 'status'], 'ad_campaigns_product_id_status_index');
            $table->index('status', 'ad_campaigns_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
