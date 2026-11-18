<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->enum('audience_type', ['customer', 'seller', 'rider']);
            $table->string('title', 191)->nullable();
            $table->text('message')->nullable();
            $table->enum('target_type', ['product', 'featured_section', 'brand', 'category', 'store'])->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('audience_type', 'app_notifications_audience_type_index');
            $table->index('target_type', 'app_notifications_target_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
