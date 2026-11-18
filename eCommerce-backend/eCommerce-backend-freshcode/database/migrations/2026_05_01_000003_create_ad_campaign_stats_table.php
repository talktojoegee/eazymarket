<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaign_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->date('stat_date');
            $table->unsignedBigInteger('clicks')->default('0');
            $table->unsignedBigInteger('impressions')->default('0');
            $table->decimal('spent', 12, 2)->default('0.00');
            $table->timestamps();
            $table->unique(['campaign_id', 'stat_date'], 'ad_campaign_stats_campaign_id_stat_date_unique');
            $table->index('stat_date', 'ad_campaign_stats_stat_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaign_stats');
    }
};
