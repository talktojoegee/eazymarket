<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_classes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('market_id');
            $table->string('title', 191);
            $table->softDeletes();
            $table->timestamps();
            $table->index('market_id', 'tax_classes_market_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_classes');
    }
};
