<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', [
                'pending', 'label_printed', 'confirmed', 'in_transit',
                'out_for_delivery', 'delivered', 'attempted_delivery',
                'failure', 'returned', 'cancelled', 'rto_initiated',
                'rto_delivered', 'lost'
            ])->default('label_printed')->change();
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->enum('status', [
                'label_printed', 'confirmed', 'in_transit', 'out_for_delivery',
                'delivered', 'attempted_delivery', 'failure', 'returned',
                'cancelled', 'rto_initiated', 'rto_delivered', 'lost'
            ])->default('label_printed')->change();
        });
    }
};
