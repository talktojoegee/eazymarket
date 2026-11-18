<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider_code', 32);
            $table->string('event_type', 80)->nullable();
            $table->string('signature', 191)->nullable();
            $table->json('headers')->nullable();
            $table->json('payload');
            $table->enum('status', ['received', 'processed', 'failed', 'skipped'])->default('received');
            $table->text('processing_error')->nullable();
            $table->unsignedBigInteger('shipment_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['provider_code', 'status'], 'shipping_webhook_events_provider_code_status_index');
            $table->index(['provider_code', 'created_at'], 'shipping_webhook_events_provider_code_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_webhook_events');
    }
};
