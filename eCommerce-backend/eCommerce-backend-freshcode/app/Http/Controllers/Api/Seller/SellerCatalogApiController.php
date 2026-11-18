<?php

namespace App\Http\Controllers\Api\Seller;

use App\Models\Category;
use App\Models\ProductCondition;
use App\Models\TaxRate;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Paginated lists for the shared catalog taxonomy the seller app needs —
 * categories, tax rates and product conditions. These are platform-wide (not
 * store-scoped), matching what the seller panel's tables show. Create / update /
 * delete reuse the panel controllers so validation and side effects stay
 * identical.
 */
#[Group('Seller Catalog')]
class SellerCatalogApiController
{
    /**
     * Categories, newest first, optionally filtered to one parent level.
     */
    #[QueryParameter('search', description: 'Match against category title', type: 'string')]
    #[QueryParameter('parent_id', description: 'Only children of this category; pass 0 for top level', type: 'int')]
    #[QueryParameter('per_page', description: 'Items per page', type: 'int', default: 20)]
    public function categories(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $paginator = Category::query()
            ->with('parent:id,title')
            ->withCount('products')
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->when($request->filled('parent_id'), function ($q) use ($request) {
                $parentId = (int) $request->input('parent_id');
                $parentId === 0 ? $q->whereNull('parent_id') : $q->where('parent_id', $parentId);
            })
            ->orderBy('title')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($paginator, 'labels.categories_fetched_successfully');
    }

    /**
     * Tax rates available when building a tax class.
     */
    #[QueryParameter('search', description: 'Match against tax rate title', type: 'string')]
    #[QueryParameter('per_page', description: 'Items per page', type: 'int', default: 20)]
    public function taxRates(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $paginator = TaxRate::query()
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderBy('title')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($paginator, 'labels.tax_rates_fetched_successfully');
    }

    /**
     * Product conditions (new, refurbished, used, …) for the product form.
     */
    #[QueryParameter('search', description: 'Match against condition title', type: 'string')]
    #[QueryParameter('per_page', description: 'Items per page', type: 'int', default: 20)]
    public function productConditions(Request $request): JsonResponse
    {
        $search = trim((string) $request->input('search', ''));

        $paginator = ProductCondition::query()
            ->when($search !== '', fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderBy('title')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($paginator, 'labels.product_conditions_fetched_successfully');
    }

    protected function paginated($paginator, string $message): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(true, $message, [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'data' => $paginator->items(),
        ]);
    }
}
