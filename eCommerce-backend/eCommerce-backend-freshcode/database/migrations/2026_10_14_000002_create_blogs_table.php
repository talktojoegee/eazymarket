<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->text('short_description')->nullable();
            $table->longText('content');
            $table->string('status', 20)->default('draft');
            $table->boolean('editor_pick')->default(false);
            $table->foreignId('author_profile_id')->constrained('watch_buy_profiles')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
            $table->index(['editor_pick', 'published_at']);
        });

        Schema::create('blog_category_blog', function (Blueprint $table) {
            $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
            $table->foreignId('blog_category_id')->constrained('blog_categories')->restrictOnDelete();
            $table->primary(['blog_id', 'blog_category_id']);
        });

        Schema::create('blog_product', function (Blueprint $table) {
            $table->foreignId('blog_id')->constrained('blogs')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->primary(['blog_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_product');
        Schema::dropIfExists('blog_category_blog');
        Schema::dropIfExists('blogs');
    }
};
