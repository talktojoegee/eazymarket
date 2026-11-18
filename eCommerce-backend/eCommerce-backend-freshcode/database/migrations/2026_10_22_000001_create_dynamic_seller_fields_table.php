<?php

use App\Models\Seller;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BANK_COLUMNS = [
        'bank_name',
        'bank_branch_code',
        'account_holder_name',
        'account_number',
        'routing_number',
        'bank_account_type',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('dynamic_seller_fields')) {
            Schema::create('dynamic_seller_fields', function (Blueprint $table) {
                $table->id();
                $table->foreignId('market_id')->constrained()->cascadeOnDelete();
                $table->string('section', 40);
                $table->string('key', 80)->unique();
                $table->string('label', 120);
                $table->string('type', 20)->default('text');
                $table->string('placeholder', 160)->nullable();
                $table->string('default_value', 1000)->nullable();
                $table->json('options')->nullable();
                $table->boolean('required')->default(false);
                $table->boolean('system')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (Schema::hasColumn('dynamic_seller_fields', 'legacy_key')) {
            Schema::table('dynamic_seller_fields', function (Blueprint $table) {
                $table->dropColumn('legacy_key');
            });
        }

        if (!Schema::hasColumn('sellers', 'dynamic_fields')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->json('dynamic_fields')->nullable();
            });
        }

        if (!Schema::hasColumn('markets', 'seller_fields_initialized')) {
            Schema::table('markets', function (Blueprint $table) {
                $table->boolean('seller_fields_initialized')->default(false);
            });
        }

        $this->moveLegacySellerData();
        $this->dropLegacyBankColumns();
    }

    public function down(): void
    {
        $this->restoreLegacyBankColumns();

        if (Schema::hasColumn('sellers', 'dynamic_fields')) {
            Schema::table('sellers', function (Blueprint $table) {
                $table->dropColumn('dynamic_fields');
            });
        }

        if (Schema::hasColumn('markets', 'seller_fields_initialized')) {
            Schema::table('markets', function (Blueprint $table) {
                $table->dropColumn('seller_fields_initialized');
            });
        }

        Schema::dropIfExists('dynamic_seller_fields');
    }

    private function moveLegacySellerData(): void
    {
        $markets = DB::table('markets')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $legacyColumns = array_values(array_filter(self::BANK_COLUMNS, fn ($column) => Schema::hasColumn('sellers', $column)));
        $select = array_values(array_unique(array_merge(['id', 'dynamic_fields'], $legacyColumns)));
        Seller::withTrashed()
            ->select($select)
            ->orderBy('id')
            ->chunkById(100, function ($sellers) use ($markets, $legacyColumns) {
                foreach ($sellers as $seller) {
                    $values = $seller->dynamic_fields ?? [];
                    $changed = false;

                    foreach (self::BANK_COLUMNS as $key) {
                        if (!in_array($key, $legacyColumns, true)) {
                            continue;
                        }

                        $value = $seller->getAttribute($key);
                        if ($value === null || $value === '') {
                            continue;
                        }

                        $this->storeLegacyValue($values, $key, $value, $markets);
                        $changed = true;
                    }

                    if ($changed) {
                        DB::table('sellers')->where('id', $seller->id)->update([
                            'dynamic_fields' => json_encode($values, JSON_THROW_ON_ERROR),
                        ]);
                    }
                }
            });
    }

    private function storeLegacyValue(array &$values, string $key, mixed $value, array $markets): void
    {
        $fallbackKey = '__legacy_'.$key;
        if (!array_key_exists($fallbackKey, $values)) {
            $values[$fallbackKey] = $value;
        }

        foreach ($markets as $marketId) {
            $marketKey = 'm'.$marketId.'_'.$key;
            if (!array_key_exists($marketKey, $values)) {
                $values[$marketKey] = $value;
            }
        }
    }

    private function dropLegacyBankColumns(): void
    {
        $columns = array_values(array_filter(self::BANK_COLUMNS, fn ($column) => Schema::hasColumn('sellers', $column)));
        if ($columns === []) {
            return;
        }

        Schema::table('sellers', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    private function restoreLegacyBankColumns(): void
    {
        if (!Schema::hasTable('sellers')) {
            return;
        }

        $missingColumns = array_values(array_filter(self::BANK_COLUMNS, fn ($column) => !Schema::hasColumn('sellers', $column)));
        if ($missingColumns !== []) {
            Schema::table('sellers', function (Blueprint $table) use ($missingColumns) {
                foreach ($missingColumns as $column) {
                    $table->string($column, 250)->nullable();
                }
            });
        }

        if (!Schema::hasColumn('sellers', 'dynamic_fields')) {
            return;
        }

        $defaultMarketId = DB::table('markets')->where('is_default', true)->value('id');
        $marketIds = array_values(array_unique(array_filter(array_merge(
            $defaultMarketId ? [(int) $defaultMarketId] : [],
            DB::table('markets')->pluck('id')->map(fn ($id) => (int) $id)->all(),
        ))));

        DB::table('sellers')
            ->select(['id', 'dynamic_fields'])
            ->orderBy('id')
            ->chunkById(100, function ($sellers) use ($marketIds) {
                foreach ($sellers as $seller) {
                    $values = is_array($seller->dynamic_fields)
                        ? $seller->dynamic_fields
                        : (json_decode((string) $seller->dynamic_fields, true) ?: []);
                    $restored = [];

                    foreach (self::BANK_COLUMNS as $key) {
                        foreach ($marketIds as $marketId) {
                            $marketKey = 'm'.$marketId.'_'.$key;
                            if (array_key_exists($marketKey, $values)) {
                                $restored[$key] = $values[$marketKey];
                                break;
                            }
                        }

                        $restored[$key] ??= $values['__legacy_'.$key] ?? null;
                    }

                    DB::table('sellers')->where('id', $seller->id)->update($restored);
                }
            });
    }
};
