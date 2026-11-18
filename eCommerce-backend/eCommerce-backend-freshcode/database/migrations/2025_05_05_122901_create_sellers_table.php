<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sellers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('address', 191);
            $table->string('city', 100);
            $table->string('landmark', 100);
            $table->string('state', 100);
            $table->string('zipcode', 20);
            $table->string('country', 100);
            $table->string('country_code', 10);
            $table->string('bank_name', 250)->nullable();
            $table->string('bank_branch_code', 250)->nullable();
            $table->string('account_holder_name', 250)->nullable();
            $table->string('account_number', 250)->nullable();
            $table->string('routing_number', 250)->nullable();
            $table->enum('bank_account_type', ['checking', 'savings'])->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->enum('verification_status', ['approved', 'not_approved']);
            $table->json('metadata')->nullable();
            $table->enum('visibility_status', ['visible', 'draft']);
            $table->softDeletes();
            $table->timestamps();
            $table->unsignedInteger('post_accept_cancel_count')->default('0');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sellers');
    }
};
