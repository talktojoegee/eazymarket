<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_event_dedup', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->string('event_type', 20);
            $table->string('visitor_key', 64);
            $table->date('event_date');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['campaign_id', 'event_type', 'visitor_key', 'event_date'], 'ad_dedup_unique');
            $table->index('event_date', 'ad_event_dedup_event_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_event_dedup');
    }
};
