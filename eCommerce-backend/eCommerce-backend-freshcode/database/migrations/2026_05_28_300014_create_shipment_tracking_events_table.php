<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipment_id');
            $table->string('provider_code', 32);
            $table->string('carrier_status_code', 64);
            $table->string('carrier_status_label', 191)->nullable();
            $table->string('mapped_status', 32);
            $table->string('location', 191)->nullable();
            $table->timestamp('scanned_at');
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->unique(['shipment_id', 'carrier_status_code', 'scanned_at'], 'shipment_tracking_dedup_unique');
            $table->index(['shipment_id', 'scanned_at'], 'shipment_tracking_events_shipment_id_scanned_at_index');
            $table->index('mapped_status', 'shipment_tracking_events_mapped_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_tracking_events');
    }
};
