<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('action', 191);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'created_at'], 'order_audit_logs_order_id_created_at_index');
            $table->index('action', 'order_audit_logs_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_audit_logs');
    }
};
