<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dynamic_seller_field_country') && !Schema::hasColumn('dynamic_seller_field_country', 'archived_at')) {
            Schema::table('dynamic_seller_field_country', function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dynamic_seller_field_country', 'archived_at')) {
            Schema::table('dynamic_seller_field_country', function (Blueprint $table) {
                $table->dropColumn('archived_at');
            });
        }
    }
};
