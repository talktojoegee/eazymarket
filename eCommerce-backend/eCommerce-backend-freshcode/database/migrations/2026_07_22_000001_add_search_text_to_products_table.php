<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'search_text')) {
                $table->mediumText('search_text')->nullable()->after('tags');
            }
        });

        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->fullText('title', 'products_title_fulltext');
            $table->fullText('search_text', 'products_search_text_fulltext');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('products', function (Blueprint $table) {
                $table->dropFullText('products_title_fulltext');
                $table->dropFullText('products_search_text_fulltext');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('search_text');
        });
    }
};
