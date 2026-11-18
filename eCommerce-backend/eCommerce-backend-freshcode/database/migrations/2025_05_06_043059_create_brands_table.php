<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->enum('scope_type', ['global', 'category'])->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('slug', 191);
            $table->string('title', 191);
            $table->string('description', 191);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique('uuid', 'brands_uuid_unique');
            $table->unique('slug', 'brands_slug_unique');
            $table->index(['scope_type', 'scope_id'], 'brands_scope_type_scope_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
