<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_page_builder_documents', function (Blueprint $table) {
            $table->id();
            $table->longText('html')->nullable();
            $table->longText('css')->nullable();
            $table->longText('project_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_page_builder_documents');
    }
};
