<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_faqs', function (Blueprint $table) {
            $table->text('question')->change();
            $table->text('answer')->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_faqs', function (Blueprint $table) {
            $table->string('question', 191)->change();
            $table->string('answer', 191)->change();
        });
    }
};
