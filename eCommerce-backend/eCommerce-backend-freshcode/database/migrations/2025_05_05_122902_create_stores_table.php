<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('name', 191);
            $table->string('slug', 300);
            $table->string('contact_email', 50);
            $table->string('contact_number', 20);
            $table->text('description')->nullable();
            $table->string('tax_name', 250);
            $table->string('tax_number', 250);
            $table->string('currency_code', 191)->nullable();
            $table->string('promotional_text', 1024)->nullable();
            $table->text('about_us')->nullable();
            $table->text('return_replacement_policy')->nullable();
            $table->text('refund_policy')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->json('metadata')->nullable();
            $table->json('receipt_template')->nullable();
            $table->decimal('default_weight_kg', 8, 3)->default('0.000');
            $table->unsignedInteger('default_length_cm')->default('0');
            $table->unsignedInteger('default_breadth_cm')->default('0');
            $table->unsignedInteger('default_height_cm')->default('0');
            $table->string('pos_upi_vpa', 100)->nullable();
            $table->string('pos_upi_payee_name', 100)->nullable();
            $table->json('pos_payment_config')->nullable();
            $table->enum('verification_status', ['approved', 'not_approved']);
            $table->enum('visibility_status', ['visible', 'draft'])->default('draft');
            $table->boolean('is_recommended')->default('0');
            $table->softDeletes();
            $table->timestamps();
            $table->unique('slug', 'stores_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
