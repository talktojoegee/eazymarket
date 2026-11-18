<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->unique('name', 'badges_name_unique');
            $table->unique('label', 'badges_label_unique');
        });
    }

    public function down(): void
    {
        Schema::table('badges', function (Blueprint $table) {
            $table->dropUnique('badges_name_unique');
            $table->dropUnique('badges_label_unique');
        });
    }
};
