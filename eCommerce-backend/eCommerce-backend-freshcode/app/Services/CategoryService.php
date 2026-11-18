<?php

namespace App\Services;

use App\Enums\CategoryStatusEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Models\Category;
use App\Models\Product;
use App\Support\HomeCache;
use App\Traits\HasZoneAvailability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class CategoryService
{
    use HasZoneAvailability;

    private const MEDIA_COLLECTIONS = [
        'image' => 'image',
        'banner' => 'banner',
    ];

    public function createCategory(array $attributes, array $media = []): Category
    {
        $category = Category::create($this->normaliseAttributes($attributes));
        $this->syncMedia($category, $media);
        HomeCache::flushLayout();

        return $category->refresh();
    }

    public function updateCategory(Category $category, array $attributes, array $media = [], array $removeMedia = []): Category
    {
        if ($attributes !== []) {
            $category->update($this->normaliseAttributes($attributes));
        }

        $this->syncMedia($category, $media, $removeMedia);
        HomeCache::flushLayout();

        return $category->refresh();
    }

    public function downloadAndAttachMediaFromUrl(Category $category, string $url, string $field): ?string
    {
        $collection = self::MEDIA_COLLECTIONS[$field] ?? null;
        if ($collection === null) {
            return __('labels.invalid_media_type');
        }

        try {
            SpatieMediaService::uploadFromUrl($category, $url, $collection);

            return null;
        } catch (\Throwable $exception) {
            return $exception->getMessage();
        }
    }

    private function normaliseAttributes(array $attributes): array
    {
        if (array_key_exists('status', $attributes) && empty($attributes['status'])) {
            $attributes['status'] = CategoryStatusEnum::INACTIVE();
        }

        if (array_key_exists('requires_approval', $attributes)) {
            $attributes['requires_approval'] = filter_var($attributes['requires_approval'], FILTER_VALIDATE_BOOLEAN);
        }

        return $attributes;
    }

    private function syncMedia(Category $category, array $media, array $removeMedia = []): void
    {
        foreach (self::MEDIA_COLLECTIONS as $field => $collection) {
            $file = $media[$field] ?? null;

            if ($file instanceof UploadedFile) {
                $existingMedia = $category->getFirstMedia($collection);

                if (! $existingMedia || $existingMedia->file_name !== $file->getClientOriginalName()) {
                    $category->addMedia($file)->toMediaCollection($collection);
                }
            } elseif (($removeMedia[$field] ?? false) === true) {
                $category->clearMediaCollection($collection);
            }
        }
    }

    public static function getCategoriesWithParent()
    {
        return Category::select('id', 'parent_id', 'title', 'requires_approval')->where('status', CategoryStatusEnum::ACTIVE())->get()->map(function ($category) {
            return [
                'id' => (string) $category->id,
                'parent' => $category->parent_id ? (string) $category->parent_id : '#',
                'text' => $category->title.($category->requires_approval ? ' <small class="text-azure">('.__('labels.requires_admin_approval').')</small>' : ''),
                'data' => [
                    'title' => $category->title,
                    'requires_approval' => (bool) $category->requires_approval,
                ],
            ];
        });
    }

    /**
     * Build base category query constrained by active status and availability in given stores.
     *
     * A category is considered available if it has at least one approved and active product
     * that has variants available in any of the specified stores. This also includes products
     * from child categories.
     */
    protected function baseAvailabilityQuery(array $storeIds): Builder
    {
        return Category::query()
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->distinct()
            ->whereHas('products', function ($productQuery) use ($storeIds) {
                $productQuery->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                    ->where('status', ProductStatusEnum::ACTIVE())
                    ->whereHas('variants.storeProductVariants', function ($variantQuery) use ($storeIds) {
                        $variantQuery->whereIn('store_id', $storeIds);
                    });
            });
    }

    /**
     * Canonical zone-aware category query.
     *
     * Returns active categories with a `products_count` attribute reflecting how many
     * active+approved products with variants in the given stores they have. Caller
     * adds parent_id, ordering, and pagination.
     *
     * Mirrors CategoryApiController::applyProductsCount so the customer + admin
     * preview share one source of truth.
     */
    public function availableInStoresQuery(array $storeIds): Builder
    {
        return Category::query()
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->withCount([
                'products as products_count' => function (Builder $q) use ($storeIds) {
                    if (empty($storeIds)) {
                        $q->whereRaw('1 = 0');

                        return;
                    }
                    $q->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                        ->where('status', ProductStatusEnum::ACTIVE())
                        ->whereHas(
                            'variants.storeProductVariants',
                            fn (Builder $sq) => $sq->whereIn('store_id', $storeIds),
                        );
                },
            ]);
    }

    /**
     * For each category in the collection, add the immediate children's product counts.
     *
     * Mirrors CategoryApiController::aggregateImmediateChildrenProducts so a parent
     * category that has zero direct products but stocked subcategories still surfaces.
     */
    public function aggregateImmediateChildrenProducts(Collection $categories, array $storeIds): Collection
    {
        return $categories->map(function (Category $cat) use ($storeIds) {
            $childIds = Category::query()
                ->where('parent_id', $cat->id)
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->pluck('id');
            if ($childIds->isEmpty()) {
                return $cat;
            }

            $additional = empty($storeIds)
                ? 0
                : Product::query()
                    ->whereIn('category_id', $childIds)
                    ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
                    ->where('status', ProductStatusEnum::ACTIVE())
                    ->whereHas('variants.storeProductVariants', fn (Builder $sq) => $sq->whereIn('store_id', $storeIds))
                    ->count();

            $cat->products_count = ($cat->products_count ?? 0) + $additional;

            return $cat;
        });
    }

    /**
     * @param  Collection<int,Category>  $categories
     * @return Collection<int,Category>
     */
    public function attachSubtreeProductCounts(Collection $categories, array $storeIds, bool $useZoneFilter): Collection
    {
        return $categories->map(function (Category $category) use ($storeIds, $useZoneFilter) {
            $subtreeIds = array_merge(
                [$category->id],
                $this->descendantIds([$category->id], [$category->id]),
            );

            $category->products_count = $this->countProductsIn($subtreeIds, $storeIds, $useZoneFilter);
            $category->children_count = Category::query()
                ->where('parent_id', $category->id)
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->count();

            return $category;
        });
    }

    /**
     * @param  int[]  $parentIds
     * @param  int[]  $visited
     * @return int[]
     */
    private function descendantIds(array $parentIds, array $visited = []): array
    {
        if (empty($parentIds)) {
            return [];
        }

        $childIds = Category::query()
            ->whereIn('parent_id', $parentIds)
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->pluck('id')
            ->all();

        $newIds = array_values(array_diff($childIds, $visited));

        if (empty($newIds)) {
            return [];
        }

        return array_merge(
            $newIds,
            $this->descendantIds($newIds, array_merge($visited, $newIds)),
        );
    }

    /**
     * @param  int[]  $categoryIds
     */
    private function countProductsIn(array $categoryIds, array $storeIds, bool $useZoneFilter): int
    {
        $query = Product::query()
            ->whereIn('category_id', $categoryIds)
            ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
            ->where('status', ProductStatusEnum::ACTIVE());

        if ($useZoneFilter) {
            if (empty($storeIds)) {
                return 0;
            }

            $query->whereHas(
                'variants.storeProductVariants',
                fn (Builder $sq) => $sq->whereIn('store_id', $storeIds),
            );
        }

        return $query->count();
    }
}
