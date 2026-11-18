<?php

namespace App\Console\Commands;

use App\Models\ProductVariantAttribute;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupOrphanVariantAttributes extends Command
{
    protected $signature = 'catalog:cleanup-orphan-variant-attributes
                            {--product= : Limit the audit to one product ID}
                            {--apply : Soft-delete orphaned assignments}';

    protected $description = 'Report or soft-delete active attribute assignments attached to deleted product variants';

    public function handle(): int
    {
        $productId = (int) ($this->option('product') ?: 0);
        $inspectedQuery = ProductVariantAttribute::query()
            ->when($productId > 0, fn ($query) => $query->where('product_id', $productId));
        $affectedQuery = DB::table('product_variant_attributes as assignments')
            ->join('product_variants as variants', 'variants.id', '=', 'assignments.product_variant_id')
            ->whereNull('assignments.deleted_at')
            ->whereNotNull('variants.deleted_at')
            ->when($productId > 0, fn ($query) => $query->where('assignments.product_id', $productId));

        $inspected = $inspectedQuery->count();
        $affectedIds = $affectedQuery->pluck('assignments.id');
        $updated = 0;

        if ($this->option('apply') && $affectedIds->isNotEmpty()) {
            DB::transaction(function () use ($affectedIds, &$updated) {
                $affectedIds->chunk(500)->each(function ($ids) use (&$updated) {
                    $updated += ProductVariantAttribute::query()->whereIn('id', $ids)->delete();
                });
            });
        }

        $this->table(['Inspected', 'Affected', 'Updated'], [[
            $inspected,
            $affectedIds->count(),
            $updated,
        ]]);

        if (! $this->option('apply') && $affectedIds->isNotEmpty()) {
            $this->info('Report only. Run again with --apply to soft-delete the affected assignments.');
        }

        return self::SUCCESS;
    }
}
