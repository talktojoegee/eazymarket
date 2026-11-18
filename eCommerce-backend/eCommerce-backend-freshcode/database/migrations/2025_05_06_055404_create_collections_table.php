<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->string('title', 191);
            $table->string('slug', 191);
            $table->enum('type', ['manual', 'smart']);
            $table->enum('visibility', ['published', 'draft']);
            $table->text('description');
            $table->json('metadata');
            $table->timestamps();
            $table->unique('uuid', 'collections_uuid_unique');
            $table->unique('slug', 'collections_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
