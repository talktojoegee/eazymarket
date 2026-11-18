<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zone_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_zone_id');
            $table->string('name', 120)->nullable();
            $table->string('rate_type', 32);
            $table->decimal('flat_rate', 12, 2)->nullable();
            $table->decimal('free_above', 12, 2)->nullable();
            $table->json('weight_rules')->nullable();
            $table->json('price_rules')->nullable();
            $table->unsignedInteger('eta_min')->nullable();
            $table->unsignedInteger('eta_max')->nullable();
            $table->string('eta_unit', 16)->nullable();
            $table->unsignedInteger('priority')->default('0');
            $table->boolean('is_active')->default('1');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['shipping_zone_id', 'priority'], 'szr_zone_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_rates');
    }
};
