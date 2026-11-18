<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StorePackage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Store package CRUD + default-promotion invariants.
 *
 * Mirrors StoreLocationService:
 *   - create()              → new row, never default
 *   - update()              → fillable update; is_default not editable inline
 *   - promoteToDefault()    → atomic flip in one transaction
 *   - delete()              → observer rejects defaults
 */
class StorePackageService
{
    public function create(Store $store, array $data): Model
    {
        $payload = $this->normalisePayload($data);
        $payload['is_default'] = false;

        return $store->packages()->create($payload);
    }

    public function update(StorePackage $package, array $data): StorePackage
    {
        $payload = $this->normalisePayload($data);
        unset($payload['is_default']);

        $package->fill($payload)->save();
        return $package->refresh();
    }

    /**
     * Promote a non-default package to default. Demotes whatever is currently
     * default in the same transaction so the invariant holds at every commit
     * point. The observer's "can't unset is_default" guard is bypassed safely
     * here because we set a new default in the same DB call.
     */
    public function promoteToDefault(StorePackage $package): StorePackage
    {
        if ($package->is_default) {
            return $package;
        }

        DB::transaction(function () use ($package) {
            StorePackage::where('store_id', $package->store_id)
                ->where('id', '!=', $package->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            StorePackage::where('id', $package->id)
                ->update(['is_default' => true]);
        });

        $package->refresh();
        $package->save();

        return $package;
    }

    public function delete(StorePackage $package): void
    {
        $package->delete();
    }

    /**
     * Coerce inbound form data into model-fillable shape. Numeric fields
     * default to 0 (not null) because the DB columns are NOT NULL with 0
     * defaults — keeps the AWB pre-fill code from crashing on missing dims.
     */
    protected function normalisePayload(array $data): array
    {
        return [
            'name'            => $data['name'] ?? null,
            'type'            => in_array($data['type'] ?? null, [StorePackage::TYPE_BOX, StorePackage::TYPE_ENVELOPE, StorePackage::TYPE_SOFT_PACK], true)
                ? $data['type']
                : StorePackage::TYPE_BOX,
            'length_cm'       => $this->intOrZero($data['length_cm'] ?? null),
            'breadth_cm'      => $this->intOrZero($data['breadth_cm'] ?? null),
            'height_cm'       => $this->intOrZero($data['height_cm'] ?? null),
            'empty_weight_kg' => $this->decimalOrZero($data['empty_weight_kg'] ?? null),
        ];
    }

    protected function intOrZero($value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    protected function decimalOrZero($value): float
    {
        return is_numeric($value) ? max(0.0, round((float) $value, 3)) : 0.0;
    }
}
