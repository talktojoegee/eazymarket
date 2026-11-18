<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('command_run_logs', function (Blueprint $table) {
            $table->id();
            $table->string('command', 191);
            $table->string('status', 20)->default('running');
            $table->string('triggered_by', 20)->default('schedule');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('output')->nullable();
            $table->timestamps();
            $table->index(['command', 'started_at'], 'command_run_logs_command_started_at_index');
            $table->index('status', 'command_run_logs_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('command_run_logs');
    }
};
