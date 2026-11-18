<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_page_builder_documents', function (Blueprint $table) {
            $table->boolean('show_header_footer')->default(true)->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('landing_page_builder_documents', function (Blueprint $table) {
            $table->dropColumn('show_header_footer');
        });
    }
};
