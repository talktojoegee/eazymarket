<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->string('serviceability_mode', 10)->default('none')->after('is_active');
        });

        Schema::create('shipping_zone_serviceability', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shipping_zone_id');
            $table->char('country_iso2', 2);
            $table->string('match_type', 20);
            $table->string('value', 120);
            $table->string('value_to', 120)->nullable();
            $table->timestamps();
            $table->index(['shipping_zone_id', 'country_iso2'], 'szsvc_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zone_serviceability');

        Schema::table('shipping_zones', function (Blueprint $table) {
            $table->dropColumn('serviceability_mode');
        });
    }
};
