<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_locations', function (Blueprint $table) {
            $table->string('shiprocket_sync_hash', 64)->nullable()->after('shiprocket_registered_at');
            $table->enum('shiprocket_sync_status', ['pending', 'registered', 'failed'])
                ->nullable()
                ->after('shiprocket_sync_hash');
            $table->text('shiprocket_sync_error')->nullable()->after('shiprocket_sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('store_locations', function (Blueprint $table) {
            $table->dropColumn(['shiprocket_sync_hash', 'shiprocket_sync_status', 'shiprocket_sync_error']);
        });
    }
};
