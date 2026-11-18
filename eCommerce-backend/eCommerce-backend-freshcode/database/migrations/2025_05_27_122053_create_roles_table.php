<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('name', 191);
            $table->string('guard_name', 191);
            $table->timestamps();
            $table->unique(['team_id', 'guard_name', 'name'], 'roles_team_id_guard_name_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
