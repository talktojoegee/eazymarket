<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type')->default('global'); // HomePageScopeEnum: global | category
            $table->unsignedBigInteger('scope_id')->nullable(); // category id when scope_type = category
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id'], 'home_layouts_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_layouts');
    }
};
