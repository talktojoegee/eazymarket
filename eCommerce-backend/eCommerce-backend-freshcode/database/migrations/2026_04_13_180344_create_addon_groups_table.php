<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_groups', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('title', 191);
            $table->string('slug', 191);
            $table->enum('selection_type', ['single', 'multiple'])->default('single');
            $table->boolean('is_required')->default('0');
            $table->integer('sort_order')->default('0');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique('uuid', 'addon_groups_uuid_unique');
            $table->unique('slug', 'addon_groups_slug_unique');
            $table->index('status', 'addon_groups_status_index');
            $table->index('seller_id', 'addon_groups_seller_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_groups');
    }
};
