<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-transition history for a shipment's status. One row is appended every time
 * a shipment's status changes (creation, manual transition, or a future carrier
 * webhook), so the customer timeline can show the exact time a parcel reached
 * each state (shipped / in transit / out for delivery / attempted / delivered)
 * — `shipments.last_event_at` only holds the latest transition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->string('status', 40);
            $table->string('note', 255)->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['shipment_id', 'status']);
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_status_events');
    }
};
