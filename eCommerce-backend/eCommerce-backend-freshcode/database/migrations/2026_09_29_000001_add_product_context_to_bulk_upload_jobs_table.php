<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_upload_jobs', function (Blueprint $table) {
            $table->string('panel', 20)->default('admin')->after('uploadable_type');
            $table->foreignId('seller_id')->nullable()->after('user_id')->constrained('sellers')->nullOnDelete();
            $table->unsignedInteger('total_records')->default(0)->after('total_rows');
            $table->unsignedInteger('processed_records')->default(0)->after('processed_rows');
            $table->index(['uploadable_type', 'seller_id', 'status'], 'bulk_upload_scope_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_upload_jobs', function (Blueprint $table) {
            $table->dropIndex('bulk_upload_scope_status_index');
            $table->dropConstrainedForeignId('seller_id');
            $table->dropColumn(['panel', 'total_records', 'processed_records']);
        });
    }
};
