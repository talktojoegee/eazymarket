<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_buy_profiles', function (Blueprint $table) {
            $table->id();
            $table->enum('profile_type', ['seller', 'platform']);
            $table->foreignId('seller_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('username', 30)->unique();
            $table->string('bio', 160);
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['profile_type', 'status']);
        });

        Schema::create('watch_buy_reels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->foreignId('profile_id')->constrained('watch_buy_profiles')->restrictOnDelete();
            $table->enum('source', ['seller', 'seller_behalf', 'admin_direct']);
            $table->text('caption')->nullable();
            $table->enum('status', ['published', 'unpublished', 'blocked'])->default('published');
            $table->enum('status_before_block', ['published', 'unpublished'])->nullable();
            $table->unsignedInteger('duration_ms');
            $table->unsignedInteger('video_width');
            $table->unsignedInteger('video_height');
            $table->unsignedBigInteger('video_size_bytes');
            $table->unsignedBigInteger('like_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('block_reason')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'published_at']);
            $table->index(['profile_id', 'status', 'published_at']);
        });

        Schema::create('watch_buy_reel_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reel_id')->constrained('watch_buy_reels')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['reel_id', 'product_id', 'store_id', 'product_variant_id'], 'watch_buy_reel_product_unique');
            $table->unique(['reel_id', 'sort_order']);
        });

        Schema::create('watch_buy_reel_likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reel_id')->constrained('watch_buy_reels')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['reel_id', 'user_id']);
        });

        Schema::create('watch_buy_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('target_type', ['profile', 'reel']);
            $table->unsignedBigInteger('target_id');
            $table->enum('action', ['suspend', 'unsuspend', 'block', 'unblock', 'delete']);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['target_type', 'target_id']);
        });

        DB::table('watch_buy_profiles')->insert([
            'profile_type' => 'platform',
            'username' => 'hypercommerce',
            'bio' => 'Official HyperCommerce profile',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('settings')->updateOrInsert(
            ['variable' => 'watch_and_buy'],
            [
                'value' => json_encode([
                    'enabled' => true,
                    'title' => 'Watch & Buy',
                    'maxDurationSeconds' => 60,
                    'maxUploadMb' => 50,
                    'maxProductsPerReel' => 10,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('settings')->where('variable', 'watch_and_buy')->delete();
        Schema::dropIfExists('watch_buy_moderation_logs');
        Schema::dropIfExists('watch_buy_reel_likes');
        Schema::dropIfExists('watch_buy_reel_products');
        Schema::dropIfExists('watch_buy_reels');
        Schema::dropIfExists('watch_buy_profiles');
    }
};
