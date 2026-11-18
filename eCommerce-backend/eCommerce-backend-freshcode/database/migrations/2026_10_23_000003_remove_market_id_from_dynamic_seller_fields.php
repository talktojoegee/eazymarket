<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('dynamic_seller_fields', 'market_id')) {
            Schema::table('dynamic_seller_fields', function (Blueprint $table) {
                $table->dropConstrainedForeignId('market_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('dynamic_seller_fields', 'market_id')) {
            Schema::table('dynamic_seller_fields', function (Blueprint $table) {
                $table->foreignId('market_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        if (!Schema::hasTable('dynamic_seller_field_country') || !Schema::hasTable('market_country')) {
            return;
        }

        DB::table('dynamic_seller_field_country')
            ->join('countries', 'countries.id', '=', 'dynamic_seller_field_country.country_id')
            ->join('market_country', 'market_country.country_iso2', '=', 'countries.iso2')
            ->select('dynamic_seller_field_country.dynamic_seller_field_id', 'market_country.market_id')
            ->orderBy('dynamic_seller_field_country.dynamic_seller_field_id')
            ->get()
            ->unique('dynamic_seller_field_id')
            ->each(function ($row) {
                DB::table('dynamic_seller_fields')
                    ->where('id', $row->dynamic_seller_field_id)
                    ->update(['market_id' => $row->market_id]);
            });
    }
};
