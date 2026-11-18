<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->char('id', 36);
            $table->string('type', 191);
            $table->enum('role_type', ['admin', 'seller', 'customer', 'rider'])->nullable();
            $table->string('notifiable_type', 191);
            $table->unsignedBigInteger('notifiable_id');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->primary('id');
            $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
            $table->index('role_type', 'notifications_role_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
