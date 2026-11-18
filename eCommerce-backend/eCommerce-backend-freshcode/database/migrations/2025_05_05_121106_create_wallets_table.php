<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 32)->default('customer');
            $table->decimal('balance', 10, 2);
            $table->decimal('blocked_balance', 15, 2)->default('0.00');
            $table->string('currency_code', 3);
            $table->timestamps();
            $table->unique(['user_id', 'type'], 'wallets_user_id_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
