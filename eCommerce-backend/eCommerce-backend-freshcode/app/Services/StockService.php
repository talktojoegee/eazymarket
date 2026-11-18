<?php

namespace App\Services;

use App\Enums\StockInventoryTypeEnum;
use App\Models\StoreInventoryLog;
use App\Models\StoreLocation;
use App\Models\StoreLocationInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * Update stock quantity at a specific store location for a variant.
     *
     * The pre-refactor signature was (storeId, variantId, qty, reason) and it
     * wrote to store_product_variants.stock. Stock is now per-location, so we
     * accept an optional $locationId; if not provided we fall back to the
     * store's default location (the same target the data migration used when
     * back-filling).
     *
     * @param int      $storeId           The store ID (used to resolve default location)
     * @param int      $productVariantId  The product variant ID
     * @param int      $quantity          Positive to add, negative to remove
     * @param string   $reason            The reason for the stock update
     * @param int|null $locationId        Optional — defaults to the store's default location
     */
    public function updateStock(int $productVariantId, int $quantity, string $reason, ?int $locationId = null): array
    {
        try {
            if (! $locationId) {
                return [
                    'success' => false,
                    'message' => 'A store location is required to update stock',
                    'data' => []
                ];
            }

            $storeId = (int) StoreLocation::where('id', $locationId)->value('store_id');

            $row = StoreLocationInventory::where('store_location_id', $locationId)
                ->where('product_variant_id', $productVariantId)
                ->first();

            if ($row) {
                $query = StoreLocationInventory::where('store_location_id', $locationId)
                    ->where('product_variant_id', $productVariantId);

                if ($quantity < 0) {
                    $query->where('stock', '>=', abs($quantity));
                }

                $applied = $query->update(['stock' => DB::raw('stock + '.(int) $quantity)]);

                if ($applied === 0) {
                    return [
                        'success' => false,
                        'message' => 'Insufficient stock at this location',
                        'data' => []
                    ];
                }

                $newStock = (int) StoreLocationInventory::where('store_location_id', $locationId)
                    ->where('product_variant_id', $productVariantId)
                    ->value('stock');
            } else {
                if ($quantity < 0) {
                    return [
                        'success' => false,
                        'message' => 'Insufficient stock at this location',
                        'data' => []
                    ];
                }

                $newStock = $quantity;

                StoreLocationInventory::create([
                    'store_location_id' => $locationId,
                    'product_variant_id' => $productVariantId,
                    'is_fulfillment_location' => false,
                    'tracked' => true,
                    'stock' => $newStock,
                    'reserved' => 0,
                    'low_stock_threshold' => 0,
                ]);
            }

            $changeType = $quantity > 0
                ? StockInventoryTypeEnum::ADD()
                : StockInventoryTypeEnum::REMOVE();

            $this->logInventoryChange(
                $storeId,
                $productVariantId,
                $changeType,
                abs($quantity),
                $reason
            );

            Log::info('Stock updated', [
                'store_location_id' => $locationId,
                'product_variant_id' => $productVariantId,
                'quantity_change' => $quantity,
                'new_stock_level' => $newStock,
                'reason' => $reason
            ]);

            return [
                'success' => true,
                'message' => 'Stock updated successfully',
                'data' => [
                    'new_stock' => $newStock
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error updating stock', [
                'store_location_id' => $locationId,
                'product_variant_id' => $productVariantId,
                'quantity' => $quantity,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error updating stock: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Add stock to a store product variant
     *
     * @param int $storeId The store ID
     * @param int $productVariantId The product variant ID
     * @param int $quantity The quantity to add
     * @param string $reason The reason for adding stock
     * @return array Result containing success status, message, and data
     */
    public function addStock(int $productVariantId, int $quantity, string $reason, ?int $locationId = null): array
    {
        return $this->updateStock($productVariantId, abs($quantity), $reason, $locationId);
    }

    /**
     * Remove stock from a store product variant
     *
     * @param int $storeId The store ID
     * @param int $productVariantId The product variant ID
     * @param int $quantity The quantity to remove
     * @param string $reason The reason for removing stock
     * @return array Result containing success status, message, and data
     */
    public function removeStock(int $productVariantId, int $quantity, string $reason, ?int $locationId = null): array
    {
        return $this->updateStock($productVariantId, -abs($quantity), $reason, $locationId);
    }

    public function reserveStock(int $productVariantId, int $quantity, string $reason, ?int $locationId = null): array
    {
        return $this->adjustInventory($productVariantId, 0, abs($quantity), $reason, $locationId, false);
    }

    public function releaseReservation(int $productVariantId, int $quantity, string $reason, ?int $locationId = null): array
    {
        return $this->adjustInventory($productVariantId, 0, -abs($quantity), $reason, $locationId, false);
    }

    public function commitReservation(int $productVariantId, int $quantity, string $reason, ?int $locationId = null): array
    {
        return $this->adjustInventory($productVariantId, -abs($quantity), -abs($quantity), $reason, $locationId, true);
    }

    private function adjustInventory(int $productVariantId, int $stockDelta, int $reservedDelta, string $reason, ?int $locationId, bool $logPhysical): array
    {
        try {
            if (! $locationId) {
                return [
                    'success' => false,
                    'message' => 'A store location is required to update stock',
                    'data' => []
                ];
            }

            $storeId = (int) StoreLocation::where('id', $locationId)->value('store_id');

            $row = StoreLocationInventory::where('store_location_id', $locationId)
                ->where('product_variant_id', $productVariantId)
                ->first();

            if (! $row) {
                if ($stockDelta < 0 || $reservedDelta > 0) {
                    return [
                        'success' => false,
                        'message' => 'Insufficient stock at this location',
                        'data' => []
                    ];
                }

                $row = StoreLocationInventory::create([
                    'store_location_id' => $locationId,
                    'product_variant_id' => $productVariantId,
                    'is_fulfillment_location' => false,
                    'tracked' => true,
                    'stock' => max(0, $stockDelta),
                    'reserved' => 0,
                    'low_stock_threshold' => 0,
                ]);

                $newStock = (int) $row->stock;
                $newReserved = (int) $row->reserved;
            } else {
                $query = StoreLocationInventory::where('store_location_id', $locationId)
                    ->where('product_variant_id', $productVariantId);

                if ($reservedDelta > 0) {
                    $query->whereRaw('stock - reserved >= ?', [$reservedDelta]);
                }

                if ($stockDelta < 0) {
                    $query->where('stock', '>=', abs($stockDelta));
                }

                if ($reservedDelta < 0) {
                    $query->where('reserved', '>=', abs($reservedDelta));
                }

                $applied = $query->update([
                    'stock' => DB::raw('stock + '.(int) $stockDelta),
                    'reserved' => DB::raw('reserved + '.(int) $reservedDelta),
                ]);

                if ($applied === 0) {
                    return [
                        'success' => false,
                        'message' => 'Insufficient stock at this location',
                        'data' => []
                    ];
                }

                $fresh = StoreLocationInventory::where('store_location_id', $locationId)
                    ->where('product_variant_id', $productVariantId)
                    ->first(['stock', 'reserved']);

                $newStock = (int) ($fresh->stock ?? 0);
                $newReserved = (int) ($fresh->reserved ?? 0);
            }

            if ($logPhysical && $stockDelta !== 0) {
                $this->logInventoryChange(
                    $storeId,
                    $productVariantId,
                    $stockDelta > 0 ? StockInventoryTypeEnum::ADD() : StockInventoryTypeEnum::REMOVE(),
                    abs($stockDelta),
                    $reason
                );
            }

            return [
                'success' => true,
                'message' => 'Stock updated successfully',
                'data' => ['new_stock' => $newStock, 'new_reserved' => $newReserved]
            ];
        } catch (\Exception $e) {
            Log::error('Error adjusting inventory', [
                'store_location_id' => $locationId,
                'product_variant_id' => $productVariantId,
                'stock_delta' => $stockDelta,
                'reserved_delta' => $reservedDelta,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error adjusting inventory: ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Log an inventory change
     *
     * @param int $storeId The store ID
     * @param int $productVariantId The product variant ID
     * @param string $changeType The type of change (add, remove, adjust)
     * @param int $quantity The quantity changed
     * @param string $reason The reason for the change
     * @return void
     */
    private function logInventoryChange(
        int $storeId,
        int $productVariantId,
        string $changeType,
        int $quantity,
        string $reason
    ): void {
        StoreInventoryLog::create([
            'store_id' => $storeId,
            'product_variant_id' => $productVariantId,
            'change_type' => $changeType,
            'quantity' => $quantity,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
