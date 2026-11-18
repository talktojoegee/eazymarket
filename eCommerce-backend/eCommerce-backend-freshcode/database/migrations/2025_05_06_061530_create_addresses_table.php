<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('first_name', 80)->nullable();
            $table->string('last_name', 80)->nullable();
            $table->string('company', 120)->nullable();
            $table->string('address_line1', 191);
            $table->string('address_line2', 191)->nullable();
            $table->string('city', 100);
            $table->string('landmark', 100)->nullable();
            $table->string('state', 100);
            $table->string('zipcode', 20);
            $table->string('mobile', 20);
            $table->string('email', 191)->nullable();
            $table->boolean('is_default_billing')->default('0');
            $table->boolean('is_default_shipping')->default('0');
            $table->enum('address_type', ['home', 'office', 'other']);
            $table->string('country', 100);
            $table->string('country_code', 10);
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'is_default_shipping'], 'addresses_user_default_ship_idx');
            $table->index(['user_id', 'is_default_billing'], 'addresses_user_default_bill_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
