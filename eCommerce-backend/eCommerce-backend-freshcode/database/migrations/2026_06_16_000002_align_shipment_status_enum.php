<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shipment tracking status → Shopify `shipment_status` vocabulary
 * (App\Enums\Shipping\ShipmentStatusEnum). Zero orders → no rows to remap, so
 * this is a straight ENUM redefinition.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', [
                'label_printed', 'confirmed', 'in_transit', 'out_for_delivery',
                'delivered', 'attempted_delivery', 'failure', 'returned',
                'cancelled', 'rto_initiated', 'rto_delivered', 'lost'
            ])->default('label_printed')->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', [
                'NEW', 'LABEL_PURCHASED', 'READY', 'PICKED_UP',
                'IN_TRANSIT', 'OUT_FOR_DELIVERY', 'DELIVERED',
                'EXCEPTION', 'RTO_INITIATED', 'RTO_DELIVERED',
                'CANCELLED', 'LOST'
            ])->default('NEW')->change();
        });
    }
};
