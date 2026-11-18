<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE products MODIFY cancelable_till VARCHAR(32) NULL');

        DB::table('products')
            ->whereIn('cancelable_till', ['pending', 'awaiting_store_response', 'accepted', 'preparing'])
            ->update(['cancelable_till' => 'unfulfilled']);

        DB::table('products')
            ->where('cancelable_till', '')
            ->update(['cancelable_till' => null]);
    }

    public function down(): void
    {
        DB::table('products')
            ->whereNotIn('cancelable_till', ['pending', 'awaiting_store_response', 'accepted', 'preparing'])
            ->update(['cancelable_till' => null]);

        DB::statement("ALTER TABLE products MODIFY cancelable_till ENUM('pending','awaiting_store_response','accepted','preparing') NULL");
    }
};
