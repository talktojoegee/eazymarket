<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_shipping_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('provider_code', 32);
            $table->string('account_label', 80)->nullable();
            $table->text('credentials_encrypted');
            $table->json('config')->nullable();
            $table->boolean('is_active')->default('0');
            $table->timestamp('last_verified_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['seller_id', 'provider_code'], 'seller_shipping_credentials_seller_id_provider_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_shipping_credentials');
    }
};
