<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            $table->foreignId('source_shipment_id')->nullable()->after('return_shipment_id')
                ->constrained('shipments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_item_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_shipment_id');
        });
    }
};
