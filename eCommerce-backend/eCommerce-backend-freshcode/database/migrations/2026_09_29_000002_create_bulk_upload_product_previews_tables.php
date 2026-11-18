<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional. A failed first attempt can leave these
        // temporary preview tables behind even though the migration was not logged.
        $this->dropPreviewTables();

        Schema::create('bulk_upload_preview_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seller_id')->nullable()->constrained('sellers')->cascadeOnDelete();
            $table->string('panel', 20);
            $table->string('module', 40)->default('products');
            $table->string('file_disk', 40)->default('local');
            $table->string('file_path', 500);
            $table->string('operation', 20)->default('create');
            $table->string('match_field', 60)->nullable();
            $table->json('column_mapping');
            $table->string('mapping_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('byte_offset')->default(0);
            $table->unsignedInteger('next_row_number')->default(2);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('product_count')->default(0);
            $table->unsignedInteger('variant_count')->default(0);
            $table->unsignedInteger('location_count')->default(0);
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('info_count')->default(0);
            $table->text('failure_reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'token']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('bulk_upload_product_previews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preview_session_id')->constrained('bulk_upload_preview_sessions')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->unsignedInteger('first_row_number');
            $table->unsignedInteger('last_row_number');
            $table->string('product_id')->nullable();
            $table->string('handle')->nullable();
            $table->string('title')->nullable();
            $table->string('type', 30)->nullable();
            $table->string('status', 30)->nullable();
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->string('main_image_url', 2048)->nullable();
            $table->unsignedInteger('variant_count')->default(0);
            $table->unsignedInteger('location_count')->default(0);
            $table->decimal('minimum_price', 14, 2)->nullable();
            $table->decimal('maximum_price', 14, 2)->nullable();
            $table->integer('inventory_total')->nullable();
            $table->unsignedSmallInteger('critical_count')->default(0);
            $table->unsignedSmallInteger('warning_count')->default(0);
            $table->unsignedSmallInteger('info_count')->default(0);
            $table->text('search_text')->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['preview_session_id', 'sequence'], 'bulk_preview_sequence_unique');
            $table->index(['preview_session_id', 'critical_count', 'warning_count'], 'bulk_preview_issue_index');
            $table->index(['preview_session_id', 'title'], 'bulk_preview_title_index');
        });

        Schema::create('bulk_upload_product_preview_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preview_session_id')
                ->constrained('bulk_upload_preview_sessions', indexName: 'bulk_preview_identifier_session_fk')
                ->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('value');

            $table->unique(['preview_session_id', 'type', 'value'], 'bulk_preview_identifier_unique');
        });
    }

    public function down(): void
    {
        $this->dropPreviewTables();
    }

    private function dropPreviewTables(): void
    {
        Schema::dropIfExists('bulk_upload_product_preview_identifiers');
        Schema::dropIfExists('bulk_upload_product_previews');
        Schema::dropIfExists('bulk_upload_preview_sessions');
    }
};
