<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36);
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('product_condition_id')->nullable();
            $table->string('provider', 191)->nullable();
            $table->unsignedBigInteger('provider_product_id')->nullable();
            $table->string('slug', 500);
            $table->string('title', 191);
            $table->integer('product_identity')->nullable();
            $table->enum('type', ['simple', 'variant', 'digital']);
            $table->string('short_description', 191);
            $table->text('description');
            $table->enum('indicator', ['veg', 'non_veg'])->nullable();
            $table->enum('download_allowed', ['0', '1'])->default('0');
            $table->string('download_link', 191)->nullable();
            $table->integer('minimum_order_quantity')->default('1');
            $table->integer('quantity_step_size')->default('1');
            $table->integer('total_allowed_quantity')->default('1');
            $table->enum('is_inclusive_tax', ['0', '1'])->default('0');
            $table->enum('is_returnable', ['0', '1'])->default('0');
            $table->integer('returnable_days')->nullable();
            $table->enum('is_cancelable', ['0', '1'])->default('0');
            $table->enum('cancelable_till', ['pending', 'awaiting_store_response', 'accepted', 'preparing'])->nullable();
            $table->enum('is_attachment_required', ['0', '1'])->default('0');
            $table->string('attachment_mode', 191)->default('required');
            $table->integer('base_prep_time');
            $table->enum('status', ['active', 'draft'])->default('active');
            $table->enum('verification_status', ['pending_verification', 'rejected', 'approved'])->default('approved');
            $table->string('rejection_reason', 191)->nullable();
            $table->boolean('requires_otp')->default('0');
            $table->enum('featured', ['0', '1'])->default('0');
            $table->unsignedBigInteger('badge_id')->nullable();
            $table->enum('video_type', ['self_hosted', 'youtube', 'vimeo'])->nullable();
            $table->string('video_link', 191)->nullable();
            $table->unsignedBigInteger('cloned_from_id')->nullable();
            $table->text('tags');
            $table->json('custom_fields')->nullable();
            $table->string('warranty_period', 191)->nullable();
            $table->string('guarantee_period', 191)->nullable();
            $table->string('made_in', 191)->nullable();
            $table->char('country_of_origin', 2)->nullable();
            $table->json('metadata')->nullable();
            $table->enum('image_fit', ['cover', 'contain'])->default('contain');
            $table->softDeletes();
            $table->timestamps();
            $table->string('hsn_code', 191)->nullable();
            $table->unique('uuid', 'products_uuid_unique');
            $table->unique('slug', 'products_slug_unique');
            $table->unique('product_identity', 'products_product_identity_unique');
            $table->index('hsn_code', 'products_hsn_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
