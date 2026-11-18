<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_upload_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uploadable_type');
            $table->string('operation', 20)->default('create');
            $table->string('match_field', 60)->nullable();
            $table->boolean('notify_on_finish')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('file_disk', 40)->default('local');
            $table->string('file_path', 500);
            $table->string('failed_rows_path', 500)->nullable();
            $table->string('original_filename');
            $table->json('column_mapping');
            $table->json('default_values')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('status', 20)->default('pending');
            $table->longText('error_log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['uploadable_type', 'status']);
            $table->index(['status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_upload_jobs');
    }
};
