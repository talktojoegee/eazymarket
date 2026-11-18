<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_product_fields', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->string('title', 191);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique('uuid', 'custom_product_fields_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_product_fields');
    }
};
