<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_updates', function (Blueprint $table) {
            $table->id();
            $table->string('version', 191);
            $table->string('package_name', 191);
            $table->string('checksum', 191)->nullable();
            $table->enum('status', ['pending', 'applied', 'failed'])->default('pending');
            $table->string('step', 50)->nullable();
            $table->unsignedTinyInteger('progress')->default('0');
            $table->timestamp('heartbeat_at')->nullable();
            $table->string('min_supported_version', 191)->nullable();
            $table->unsignedBigInteger('applied_by')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->text('notes')->nullable();
            $table->longText('log')->nullable();
            $table->timestamps();
            $table->index('version', 'system_updates_version_index');
            $table->index('heartbeat_at', 'system_updates_heartbeat_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_updates');
    }
};
