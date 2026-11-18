<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_page_builder_documents', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('show_header_footer');
        });
    }

    public function down(): void
    {
        Schema::table('landing_page_builder_documents', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
