<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_pages', function (Blueprint $table) {
            $table->string('surface', 30)->default('customer_app')->index();
            $table->renameColumn('description', 'subtitle');
        });

        $customerPages = DB::table('onboarding_pages')->orderBy('id')->get();
        $sellerPages = $customerPages->map(fn (object $page): array => [
            'surface' => 'seller_app',
            'title' => $page->title,
            'subtitle' => $page->subtitle,
            'image_path' => $page->image_path,
            'background_color' => $page->background_color,
            'sort_order' => $page->sort_order,
            'is_active' => $page->is_active,
            'created_at' => $page->created_at,
            'updated_at' => $page->updated_at,
        ])->all();

        if ($sellerPages !== []) {
            DB::table('onboarding_pages')->insert($sellerPages);
        }
    }

    public function down(): void
    {
        DB::table('onboarding_pages')->where('surface', 'seller_app')->delete();

        Schema::table('onboarding_pages', function (Blueprint $table) {
            $table->dropIndex(['surface']);
            $table->dropColumn('surface');
            $table->renameColumn('subtitle', 'description');
        });
    }
};
