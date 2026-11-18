<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('seller_id');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('request_note')->nullable();
            $table->text('admin_remark')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->timestamps();
            $table->index('user_id', 'seller_withdrawal_requests_user_id_index');
            $table->index('seller_id', 'seller_withdrawal_requests_seller_id_index');
            $table->index('status', 'seller_withdrawal_requests_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_withdrawal_requests');
    }
};
