<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the legacy featured-section + banner tables. These features were
     * replaced by the home page builder; their create migrations were removed,
     * so this migration only affects databases provisioned before the removal.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('banner_market');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('category_featured_section');
        Schema::dropIfExists('featured_section_market');
        Schema::dropIfExists('featured_section_product');
        Schema::dropIfExists('featured_sections');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Legacy featured-section + banner tables are intentionally not restored.
    }
};
