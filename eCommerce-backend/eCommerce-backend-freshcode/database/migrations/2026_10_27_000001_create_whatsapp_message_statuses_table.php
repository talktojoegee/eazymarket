<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 191)->unique();
            $table->string('recipient_id', 32)->nullable();
            $table->string('module', 64)->nullable();
            $table->string('template_name', 191)->nullable();
            $table->string('status', 32)->index();
            $table->timestamp('status_at')->nullable()->index();
            $table->string('error_code', 32)->nullable();
            $table->text('error_details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_statuses');
    }
};
