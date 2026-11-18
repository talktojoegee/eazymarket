<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_buy_statuses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('profile_id')->constrained('watch_buy_profiles')->restrictOnDelete();
            $table->enum('source', ['seller', 'seller_behalf', 'admin_direct']);
            $table->enum('content_type', ['text', 'image', 'video']);
            $table->text('text')->nullable();
            $table->string('media_mime', 100)->nullable();
            $table->unsignedBigInteger('media_size_bytes')->nullable();
            $table->unsignedInteger('media_width')->nullable();
            $table->unsignedInteger('media_height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();
            $table->index('expires_at');
            $table->index(['profile_id', 'expires_at']);
        });

        Schema::create('watch_buy_status_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_id')->constrained('watch_buy_statuses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->unique(['status_id', 'product_id', 'store_id', 'product_variant_id'], 'watch_buy_status_product_unique');
            $table->unique(['status_id', 'sort_order']);
        });

        Schema::create('watch_buy_status_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('status_id')->constrained('watch_buy_statuses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('seen_at');
            $table->unique(['status_id', 'user_id']);
        });

        $setting = DB::table('settings')->where('variable', 'watch_and_buy')->first();
        $value = json_decode((string) ($setting?->value ?? '{}'), true) ?: [];
        $value += [
            'maxStatusDurationSeconds' => 15,
            'maxProductsPerStatus' => 5,
        ];

        DB::table('settings')->updateOrInsert(
            ['variable' => 'watch_and_buy'],
            ['value' => json_encode($value), 'created_at' => $setting?->created_at ?? now(), 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_buy_status_views');
        Schema::dropIfExists('watch_buy_status_products');
        Schema::dropIfExists('watch_buy_statuses');

        $setting = DB::table('settings')->where('variable', 'watch_and_buy')->first();
        if ($setting) {
            $value = json_decode((string) $setting->value, true) ?: [];
            unset($value['maxStatusDurationSeconds'], $value['maxProductsPerStatus']);
            DB::table('settings')->where('variable', 'watch_and_buy')->update([
                'value' => json_encode($value),
                'updated_at' => now(),
            ]);
        }
    }
};
