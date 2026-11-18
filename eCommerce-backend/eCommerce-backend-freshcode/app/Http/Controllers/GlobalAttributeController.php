<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\Attribute\AttributeTypesEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Requests\Attribute\SaveAttributeRequest;
use App\Models\GlobalProductAttribute;
use App\Models\ProductVariantAttribute;
use App\Models\Seller;
use App\Services\AttributeService;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class GlobalAttributeController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware;

    public float $sellerId;

    protected bool $editPermission = false;

    protected bool $deletePermission = false;

    protected bool $createPermission = false;

    protected AttributeService $attributeService;

    public function __construct(AttributeService $attributeService)
    {
        $this->attributeService = $attributeService;
        $user = auth()->user();
        $seller = $user?->seller();
        $this->sellerId = $seller ? $seller->id : 0;
        if ($this->getPanel() === 'seller') {
            $user = auth()->user();
            $this->editPermission = $this->hasPermission(SellerPermissionEnum::ATTRIBUTE_EDIT()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->deletePermission = $this->hasPermission(SellerPermissionEnum::ATTRIBUTE_DELETE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
            $this->createPermission = $this->hasPermission(SellerPermissionEnum::ATTRIBUTE_CREATE()) || $user->hasRole(DefaultSystemRolesEnum::SELLER());
        } elseif ($this->getPanel() === 'admin') {
            $this->editPermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_EDIT());
            $this->deletePermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_DELETE());
            $this->createPermission = $this->hasPermission(AdminPermissionEnum::PRODUCT_CREATE());
        }
    }

    public function index(): View
    {
        $this->authorize('viewAny', GlobalProductAttribute::class);
        $columns = collect([
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            $this->getPanel() === 'admin'
                ? ['data' => 'seller', 'name' => 'seller', 'title' => __('labels.seller'), 'orderable' => false]
                : null,
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.attribute_name')],
            ['data' => 'swatche_type', 'name' => 'swatche_type', 'title' => __('labels.display_style')],
            ['data' => 'values', 'name' => 'values_count', 'title' => __('labels.attribute_values')],
            ['data' => 'products_count', 'name' => 'products_count', 'title' => __('labels.products_using_attribute')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ])->filter()->values()->all();
        $valuesColumns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'global_attribute_id', 'name' => 'global_attribute_id', 'title' => __('labels.attribute_id')],
            ['data' => 'attribute_title', 'name' => 'attribute_title', 'title' => __('labels.attribute_name'), 'orderable' => false],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.value')],
            ['data' => 'swatche_value', 'name' => 'swatche_value', 'title' => __('labels.swatche_value')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];
        $editPermission = $this->editPermission;
        $createPermission = $this->createPermission;

        return view($this->panelView('global_product_attributes.index'), compact('columns', 'valuesColumns', 'editPermission', 'createPermission'));
    }

    public function create(): View
    {
        $this->authorize('create', GlobalProductAttribute::class);
        $selectedSeller = null;
        if ($this->getPanel() === 'admin' && request()->filled('seller_id')) {
            $selectedSeller = Seller::query()->with('user')->find((int) request()->query('seller_id'));
        }

        return view($this->panelView('global_product_attributes.form'), [
            'attribute' => null,
            'attributeTypes' => AttributeTypesEnum::values(),
            'selectedSeller' => $selectedSeller,
        ]);
    }

    public function edit($id): View
    {
        $attribute = GlobalProductAttribute::query()
            ->with('seller.user')
            ->with(['values' => fn ($query) => $query
                ->with('attribute')
                ->withCount('productVariantAttributeValue')
                ->orderBy('id')])
            ->findOrFail($id);
        $this->authorize('update', $attribute);

        $attribute->setAttribute('products_count', $this->productsCountForAttribute($attribute));

        return view($this->panelView('global_product_attributes.form'), [
            'attribute' => $attribute,
            'attributeTypes' => AttributeTypesEnum::values(),
            'selectedSeller' => $attribute->seller,
        ]);
    }

    public function store(SaveAttributeRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $this->authorize('create', GlobalProductAttribute::class);

            $sellerId = $this->writeSellerId($validated);
            if ($sellerId <= 0) {
                return ApiResponseType::sendJsonResponse(false, __('labels.seller_not_found'), null);
            }

            $attribute = $this->attributeService->saveAttributeWithValues($validated, $sellerId);
            $attribute->setAttribute('products_count', $this->productsCountForAttribute($attribute));

            return ApiResponseType::sendJsonResponse(
                true,
                __('labels.attribute_created_successfully'),
                $attribute
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.no_permission_create_attributes'),
                [],
                403
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.failed_to_save_attribute'),
                ['error' => $e->getMessage()]
            );
        }
    }

    public function update(SaveAttributeRequest $request, $id): JsonResponse
    {
        try {
            $attribute = GlobalProductAttribute::findOrFail($id);
            $this->authorize('update', $attribute);

            $attribute = $this->attributeService->saveAttributeWithValues(
                $request->validated(),
                (int) $attribute->seller_id,
                $attribute
            );
            $attribute->setAttribute('products_count', $this->productsCountForAttribute($attribute));

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.attribute_updated_successfully'),
                data: $attribute
            );

        } catch (ValidationException $e) {
            throw $e;
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(success: false, message: __('labels.attribute_not_found'));
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.no_permission_update_attribute'),
                data: [],
                status: 403
            );
        } catch (\Throwable $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.failed_to_update_attribute'),
                data: ['error' => $e->getMessage()]
            );
        }
    }

    public function destroy($id): JsonResponse
    {
        try {
            $attribute = GlobalProductAttribute::find($id);

            if (! $attribute) {
                return ApiResponseType::sendJsonResponse(false, __('labels.attribute_not_found'), null);
            }
            $this->authorize('delete', $attribute);

            if ($attribute->productVariantAttribute()->exists()) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    __('labels.attribute_used_in_variants'),
                    null
                );
            }
            DB::beginTransaction();
            $attribute->values()->get()->each->delete();
            $attribute->delete();
            DB::commit();

            return ApiResponseType::sendJsonResponse(true, __('labels.attribute_and_values_deleted'), null);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.no_permission_delete_attribute'),
                []
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(false, __('labels.failed_to_delete_attribute_and_values'), ['error' => $e->getMessage()]);
        }
    }

    /**
     * Get attributes for datatable
     */
    public function getAttributes(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', GlobalProductAttribute::class);

            $draw = $request->get('draw');
            $start = $request->get('start');
            $length = $request->get('length');
            $searchValue = $request->get('search')['value'] ?? '';

            $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
            $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';
            $swatcheType = $request->get('swatche_type');

            $columns = $this->getPanel() === 'admin'
                ? ['id', 'title', 'title', 'swatche_type', 'values_count', 'products_count']
                : ['id', 'title', 'swatche_type', 'values_count', 'products_count'];
            $orderColumn = $columns[$orderColumnIndex] ?? 'title';

            $sellerId = $this->getPanel() === 'admin'
                ? (int) $request->input('seller_id', 0)
                : (int) (auth()->user()->seller()?->id ?? 0);
            if ($this->getPanel() === 'seller' && $sellerId <= 0) {
                return ApiResponseType::sendJsonResponse(success: false, message: __('labels.seller_not_found'), data: []);
            }
            $query = GlobalProductAttribute::query()
                ->when($sellerId > 0, fn ($query) => $query->where('seller_id', $sellerId))
                ->with('seller.user')
                ->withCount('values')
                ->with(['values' => fn ($valueQuery) => $valueQuery
                    ->with('attribute')
                    ->orderBy('id')
                    ->limit(5)])
                ->addSelect([
                    'products_count' => ProductVariantAttribute::query()
                        ->selectRaw('COUNT(DISTINCT product_variant_attributes.product_id)')
                        ->join('products', 'products.id', '=', 'product_variant_attributes.product_id')
                        ->join('product_variants', function ($join) {
                            $join->on('product_variants.id', '=', 'product_variant_attributes.product_variant_id')
                                ->on('product_variants.product_id', '=', 'product_variant_attributes.product_id');
                        })
                        ->whereColumn('product_variant_attributes.global_attribute_id', 'global_product_attributes.id')
                        ->whereColumn('products.seller_id', 'global_product_attributes.seller_id')
                        ->whereNull('products.deleted_at')
                        ->whereNull('product_variants.deleted_at'),
                ]);
            $totalRecords = $query->count();
            if (! empty($searchValue)) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('title', 'like', "%{$searchValue}%")
                        ->orWhere('swatche_type', 'like', "%{$searchValue}%")
                        ->orWhereHas('seller.user', fn ($sellerQuery) => $sellerQuery
                            ->where('name', 'like', "%{$searchValue}%")
                            ->orWhere('email', 'like', "%{$searchValue}%"))
                        ->orWhereHas('values', fn ($valueQuery) => $valueQuery
                            ->where('title', 'like', "%{$searchValue}%"));
                });
            }
            if (! empty($swatcheType)) {
                $query->where('swatche_type', $swatcheType);
            }
            $filteredRecords = $query->count();

            try {

                $data = $query
                    ->orderBy($orderColumn, $orderDirection)
                    ->skip($start)
                    ->take($length)
                    ->get()
                    ->map(function ($attribute) {
                        $row = [
                            'id' => '<span class="badge bg-secondary-lt user-select-all">#'.(int) $attribute->id.'</span>',
                            'title' => e($attribute->title),
                            'swatche_type' => view('seller.global_product_attributes.partials.display-style', ['attribute' => $attribute])->render(),
                            'values' => view('seller.global_product_attributes.partials.value-preview', ['attribute' => $attribute])->render(),
                            'products_count' => '<span class="badge bg-blue-lt">'.(int) $attribute->products_count.'</span>',
                            'action' => view('partials.actions', [
                                'modelName' => 'attribute-create-update',
                                'id' => $attribute->id,
                                'title' => $attribute->title,
                                'mode' => 'page',
                                'route' => route($this->getPanel().'.attributes.edit', $attribute->id),
                                'editPermission' => $this->editPermission,
                                'deletePermission' => $this->deletePermission,
                            ])->render(),
                        ];
                        if ($this->getPanel() === 'admin') {
                            $row = ['seller' => e($attribute->seller?->user?->name ?? '—')] + $row;
                        }

                        return $row;
                    })
                    ->toArray();
            } catch (\Exception $e) {
                $totalRecords = 0;
                $filteredRecords = 0;
                $data = [];
            }

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        } catch (\Exception $e) {
            return response()->json([
                'draw' => intval($request->get('draw')),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Search attributes for dropdown
     */
    public function search(Request $request): JsonResponse
    {
        if ($this->getPanel() === 'admin') {
            $this->authorize('viewAny', GlobalProductAttribute::class);
        }

        $query = $request->input('search');
        $exceptId = $request->input('exceptId');
        $findId = $request->input('find_id');

        $sellerId = $this->getPanel() === 'admin'
            ? (int) $request->input('seller_id', 0)
            : (int) $this->sellerId;

        if ($findId) {
            $attributes = GlobalProductAttribute::where('id', $findId)
                ->select('id', 'title')
                ->when($sellerId > 0, fn ($builder) => $builder->where('seller_id', $sellerId))
                ->get();
        } else {
            $attributes = GlobalProductAttribute::where('title', 'LIKE', '%'.$query.'%')
                ->select('id', 'title', 'swatche_type')
                ->when($exceptId, function ($q) use ($exceptId) {
                    $q->where('id', '!=', $exceptId);
                })
                ->when($sellerId > 0, fn ($builder) => $builder->where('seller_id', $sellerId))
                ->get();
        }
        $results = $attributes->map(function ($attribute) {
            return [
                'id' => $attribute->id,
                'value' => $attribute->id,
                'text' => $attribute->title,
                'swatche_type' => $attribute->swatche_type,
            ];
        });

        return response()->json($results);
    }

    private function productsCountForAttribute(GlobalProductAttribute $attribute): int
    {
        return ProductVariantAttribute::query()
            ->join('products', 'products.id', '=', 'product_variant_attributes.product_id')
            ->join('product_variants', function ($join) {
                $join->on('product_variants.id', '=', 'product_variant_attributes.product_variant_id')
                    ->on('product_variants.product_id', '=', 'product_variant_attributes.product_id');
            })
            ->where('product_variant_attributes.global_attribute_id', $attribute->id)
            ->where('products.seller_id', $attribute->seller_id)
            ->whereNull('products.deleted_at')
            ->whereNull('product_variants.deleted_at')
            ->distinct('product_variant_attributes.product_id')
            ->count('product_variant_attributes.product_id');
    }

    private function writeSellerId(array $validated): int
    {
        if ($this->getPanel() === 'admin') {
            return (int) ($validated['seller_id'] ?? 0);
        }

        return (int) (auth()->user()?->seller()?->id ?? 0);
    }
}
