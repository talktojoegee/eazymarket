<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default('0.00');
            $table->enum('duration_type', ['unlimited', 'days'])->default('unlimited');
            $table->integer('duration_days')->nullable();
            $table->boolean('is_free')->default('0');
            $table->boolean('is_default')->default('0');
            $table->boolean('is_recommended')->default('0');
            $table->boolean('status')->default('1');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
