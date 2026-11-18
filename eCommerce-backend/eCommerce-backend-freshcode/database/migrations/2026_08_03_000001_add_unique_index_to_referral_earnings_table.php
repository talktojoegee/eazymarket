<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'referral_earnings_unique_award';

    public function up(): void
    {
        $duplicates = DB::table('referral_earnings')
            ->select('referral_id', 'order_id', 'beneficiary_id', 'beneficiary_type', DB::raw('COUNT(*) as total'))
            ->whereNotNull('order_id')
            ->groupBy('referral_id', 'order_id', 'beneficiary_id', 'beneficiary_type')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add the referral_earnings unique index: '.$duplicates->count().
                ' duplicated award(s) already exist. Each duplicate may have credited a wallet, '.
                'so they must be reconciled by hand before this migration can run. Affected keys: '.
                $duplicates->map(fn ($d) => "referral {$d->referral_id}/order {$d->order_id}/".
                    "beneficiary {$d->beneficiary_id} ({$d->beneficiary_type}) x{$d->total}")->implode('; ')
            );
        }

        Schema::table('referral_earnings', function (Blueprint $table) {
            $table->unique(
                ['referral_id', 'order_id', 'beneficiary_id', 'beneficiary_type'],
                self::INDEX
            );
        });
    }

    public function down(): void
    {
        Schema::table('referral_earnings', function (Blueprint $table) {
            $table->dropUnique(self::INDEX);
        });
    }
};
