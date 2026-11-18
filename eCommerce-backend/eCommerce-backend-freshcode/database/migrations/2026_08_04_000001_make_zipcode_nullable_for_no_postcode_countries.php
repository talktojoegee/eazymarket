<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Countries in config('geo.no_postcode_countries') (UAE, Hong Kong, …) save
     * addresses without a zipcode, so the column has to accept null.
     */
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('zipcode', 20)->nullable()->change();
        });

        // Orders copy the address zip verbatim, so they inherit the same rule.
        Schema::table('orders', function (Blueprint $table) {
            $table->string('billing_zip', 20)->nullable()->change();
            $table->string('shipping_zip', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('addresses')->whereNull('zipcode')->update(['zipcode' => '']);
        DB::table('orders')->whereNull('billing_zip')->update(['billing_zip' => '']);
        DB::table('orders')->whereNull('shipping_zip')->update(['shipping_zip' => '']);

        Schema::table('addresses', function (Blueprint $table) {
            $table->string('zipcode', 20)->nullable(false)->default('')->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('billing_zip', 20)->nullable(false)->default('')->change();
            $table->string('shipping_zip', 20)->nullable(false)->default('')->change();
        });
    }
};
