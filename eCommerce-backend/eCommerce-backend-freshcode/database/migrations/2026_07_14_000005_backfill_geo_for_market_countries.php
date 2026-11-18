<?php

use App\Services\GeoImportService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $geo = app(GeoImportService::class);
        $geo->ensureCountries($geo->marketCountryIso2s());
    }

    public function down(): void
    {
    }
};
