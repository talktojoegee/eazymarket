<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('countries', 'seller_bank_fields_initialized')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->boolean('seller_bank_fields_initialized')->default(false);
            });
        }

        if (!Schema::hasColumn('countries', 'seller_business_documents_initialized')) {
            Schema::table('countries', function (Blueprint $table) {
                $table->boolean('seller_business_documents_initialized')->default(false);
            });
        }

        if (!Schema::hasTable('dynamic_seller_field_country') || !Schema::hasTable('dynamic_seller_fields')) {
            return;
        }

        $countryIds = DB::table('countries')->pluck('id');
        foreach ($countryIds as $countryId) {
            $sections = DB::table('dynamic_seller_field_country')
                ->join('dynamic_seller_fields', 'dynamic_seller_fields.id', '=', 'dynamic_seller_field_country.dynamic_seller_field_id')
                ->where('dynamic_seller_field_country.country_id', $countryId)
                ->distinct()
                ->pluck('dynamic_seller_fields.section')
                ->all();

            DB::table('countries')->where('id', $countryId)->update([
                'seller_bank_fields_initialized' => in_array('bank_details', $sections, true),
                'seller_business_documents_initialized' => in_array('business_documents', $sections, true),
            ]);
        }
    }

    public function down(): void
    {
        $columns = array_values(array_filter([
            'seller_bank_fields_initialized',
            'seller_business_documents_initialized',
        ], fn ($column) => Schema::hasColumn('countries', $column)));

        if ($columns !== []) {
            Schema::table('countries', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
