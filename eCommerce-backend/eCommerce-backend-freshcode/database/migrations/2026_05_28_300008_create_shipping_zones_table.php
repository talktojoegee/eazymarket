<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_profile_id');
            $table->string('name', 64);
            $table->enum('method', ['shiprocket', 'manual', 'local_delivery', 'local_pickup']);
            $table->enum('rate_type', ['carrier_quoted', 'flat', 'weight_based', 'price_based']);
            $table->decimal('flat_rate', 12, 2)->nullable();
            $table->decimal('free_above', 12, 2)->nullable();
            $table->json('weight_rules')->nullable();
            $table->json('price_rules')->nullable();
            $table->unsignedInteger('eta_min')->nullable();
            $table->unsignedInteger('eta_max')->nullable();
            $table->enum('eta_unit', ['hours', 'days'])->nullable();
            $table->json('operating_hours')->nullable();
            $table->text('pickup_instructions')->nullable();
            $table->boolean('is_active')->default('1');
            $table->unsignedInteger('priority')->default('0');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['shipping_profile_id', 'is_active'], 'sz_profile_active_idx');
            $table->index(['method', 'is_active'], 'shipping_zones_method_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};
