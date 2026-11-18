<?php

use App\Models\Seller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_IMAGE_COLLECTIONS = [
        'business_license',
        'articles_of_incorporation',
        'national_identity_card',
        'authorized_signature',
        'address_proof',
        'voided_check',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('media') || !Schema::hasTable('sellers') || !Schema::hasColumn('sellers', 'dynamic_fields')) {
            return;
        }

        Seller::withTrashed()
            ->select(['id', 'dynamic_fields'])
            ->orderBy('id')
            ->chunkById(100, function ($sellers) {
                foreach ($sellers as $seller) {
                    $values = $seller->dynamic_fields ?? [];
                    $changed = false;

                    foreach (self::LEGACY_IMAGE_COLLECTIONS as $collection) {
                        $matchingKeys = array_filter(array_keys($values), function ($storedKey) use ($collection) {
                            $normalizedKey = preg_replace('/^__legacy_/', '', $storedKey);
                            $normalizedKey = preg_replace('/^(?:m\d+|[a-z]{2})_/', '', $normalizedKey);

                            return $normalizedKey === $collection;
                        });

                        if ($matchingKeys === [] || !$seller->getFirstMedia($collection)) {
                            continue;
                        }

                        foreach ($matchingKeys as $storedKey) {
                            if (is_string($values[$storedKey]) && filter_var($values[$storedKey], FILTER_VALIDATE_URL)) {
                                unset($values[$storedKey]);
                                $changed = true;
                            }
                        }
                    }

                    if ($changed) {
                        DB::table('sellers')->where('id', $seller->id)->update([
                            'dynamic_fields' => json_encode($values, JSON_THROW_ON_ERROR),
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
    }
};
