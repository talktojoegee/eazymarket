<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_page_builder_documents', function (Blueprint $table) {
            $table->string('title', 150)->default('Landing Page')->after('id');
            $table->string('slug', 180)->default('landing-page')->unique()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('landing_page_builder_documents', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'title']);
        });
    }
};
