<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->boolean('is_default')->default('0');
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['store_id', 'slug'], 'shipping_profiles_store_id_slug_unique');
            $table->index(['store_id', 'is_default'], 'shipping_profiles_store_id_is_default_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_profiles');
    }
};
