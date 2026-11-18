<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shared_lists', function (Blueprint $table) {
            $table->timestamp('expired_at')->nullable()->index();
        });

        DB::table('shared_lists')
            ->whereNull('expired_at')
            ->orderBy('id')
            ->chunkById(500, function ($sharedLists): void {
                foreach ($sharedLists as $sharedList) {
                    DB::table('shared_lists')
                        ->where('id', $sharedList->id)
                        ->update([
                            'expired_at' => CarbonImmutable::parse($sharedList->created_at)
                                ->addDays(7)
                                ->toDateTimeString(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('shared_lists', function (Blueprint $table) {
            $table->dropIndex(['expired_at']);
            $table->dropColumn('expired_at');
        });
    }
};
