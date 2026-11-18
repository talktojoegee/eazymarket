<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('countries') && !Schema::hasColumn('countries', 'seller_fields_initialized')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->boolean('seller_fields_initialized')->default(false);
            });
        }

        if (!Schema::hasTable('dynamic_seller_field_country')) {
            Schema::create('dynamic_seller_field_country', function (Blueprint $table) {
                $table->foreignId('dynamic_seller_field_id')->constrained('dynamic_seller_fields')->cascadeOnDelete();
                $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
                $table->primary(['dynamic_seller_field_id', 'country_id'], 'dsfc_field_country_primary');
                $table->index('country_id', 'dsfc_country_idx');
            });
        }

        if (Schema::hasTable('market_country') && Schema::hasTable('dynamic_seller_fields')) {
            $countryLinkQuery = DB::table('market_country')
                ->join('countries', 'countries.iso2', '=', 'market_country.country_iso2')
                ->select('market_country.market_id', 'countries.id as country_id');
            $countryLinks = $countryLinkQuery->get();

            foreach ($countryLinks as $countryLink) {
                $fieldIds = DB::table('dynamic_seller_fields')
                    ->where('market_id', $countryLink->market_id)
                    ->pluck('id');

                foreach ($fieldIds as $fieldId) {
                    DB::table('dynamic_seller_field_country')->insertOrIgnore([
                        'dynamic_seller_field_id' => $fieldId,
                        'country_id' => $countryLink->country_id,
                    ]);
                }

                if ($fieldIds->isNotEmpty()) {
                    DB::table('countries')->where('id', $countryLink->country_id)->update(['seller_fields_initialized' => true]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_seller_field_country');

        if (Schema::hasColumn('countries', 'seller_fields_initialized')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->dropColumn('seller_fields_initialized');
            });
        }
    }
};
