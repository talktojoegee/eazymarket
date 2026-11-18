<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\BrandStatusEnum;
use App\Enums\HomePageScopeEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Seller;
use App\Services\DatatableExportRegistry;
use App\Services\BrandService;
use App\Services\Visibility\BrandVisibilityInspector;
use App\Traits\ChecksPermissions;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BrandController extends Controller
{
    use ChecksPermissions, PanelAware, AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    protected bool $editPermission = false;
    protected bool $deletePermission = false;
    protected bool $createPermission = false;
    public $sellerId;

    public function __construct()
    {
        $user = auth()->user();
        $seller = $user?->seller();
        $this->sellerId = $seller ? $seller->id : 0;

        if ($this->getPanel() === 'admin') {
            $this->editPermission = $this->hasPermission(AdminPermissionEnum::BRAND_EDIT());
            $this->deletePermission = $this->hasPermission(AdminPermissionEnum::BRAND_DELETE());
            $this->createPermission = $this->hasPermission(AdminPermissionEnum::BRAND_CREATE());
        }
    }

    public function index(DatatableExportRegistry $exports): View
    {
        $this->authorize('viewAny', Brand::class);
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title')],
            ['data' => 'scope_type', 'name' => 'scope_type', 'title' => __('labels.scope_type'), 'orderable' => false],
            ['data' => 'image', 'name' => 'image', 'title' => __('labels.image'), 'orderable' => false, 'searchable' => false],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'products_count', 'name' => 'products_count', 'title' => __('labels.products'), 'searchable' => false],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];
        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $createPermission = $this->createPermission;
        $panel = $this->getPanel();
        $exportOptions = $exports->frontendOptions(auth()->user(), 'brands', $panel, [
            'selectionSupport' => false,
            'supportedScopes' => ['all_filtered', 'all_records'],
            'searchSelector' => '#brand-group-search',
            'visibleColumns' => ['id', 'title', 'scope', 'status', 'products_count', 'created_at'],
            'sort' => 'title',
            'direction' => 'asc',
            'length' => 50,
        ]);

        return view($this->panelView('brands.index'), compact('columns', 'editPermission', 'deletePermission', 'createPermission', 'exportOptions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBrandRequest $request, BrandService $brandService): JsonResponse
    {
        try {
            $this->authorize('create', Brand::class);
            $validated = $request->validated();
            $brand = $brandService->createBrand($validated, $request->file('logo'));
            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.brand_created_successfully', data: $brand, status: 201);
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.validation_failed', data: $e->errors());
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: []);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $this->authorize('viewAny', Brand::class);
        try {
            $brand = Brand::find($id);
            return ApiResponseType::sendJsonResponse(success: true, message: 'labels.brand_retrieved_successfully', data: new BrandResource($brand));
        } catch (ModelNotFoundException $e) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.brand_not_found', data: $e);
        }
    }

    /**
     * Return the visibility inspection for the brand — what gates it passes
     * and which zones it's reachable in. Used by the edit-modal panel that
     * answers "why isn't my brand showing?".
     */
    public function visibility($id, BrandVisibilityInspector $inspector, \App\Services\ZonePreviewService $zoneSvc): JsonResponse
    {
        $this->authorize('viewAny', Brand::class);
        try {
            $brand = Brand::find($id);
            if (! $brand) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.brand_not_found',
                    data: [],
                );
            }
            $this->authorize('viewAny', $brand);

            $zoneScope = null;
            if ($this->getPanel() === 'seller' && $this->sellerId) {
                $zoneScope = $zoneSvc->sellerZoneIds($this->sellerId);
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.fetched_successfully',
                data: $inspector->inspect($brand, $zoneScope),
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('BrandController@visibility error: ' . $e->getMessage(), ['exception' => $e]);
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.something_went_wrong',
                data: [],
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBrandRequest $request, $id, BrandService $brandService): JsonResponse
    {
        try {
            $brand = Brand::findOrFail($id);
            $this->authorize('update', $brand);
            $validated = $request->validated();
            $brand = $brandService->updateBrand($brand, $validated, $request->file('logo'));
            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.brand_updated_successfully',
                data: $brand
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.validation_failed',
                data: $e->errors()
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.brand_not_found',
                data: []
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: []
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     * @throws AuthorizationException
     */
    public function destroy($id): JsonResponse
    {
        $brand = Brand::find($id);
        if (!$brand) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.brand_not_found',
                data: [],
                status: 404
            );
        }

        $this->authorize('delete', $brand);

        $brand->delete();
        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.brand_deleted_successfully',
            data: []
        );
    }

    public function getBrands(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Brand::class);
        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = strtolower((string) ($request->get('order')[0]['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $orderColumnName = $request->get('columns')[$orderColumnIndex]['name'] ?? 'id';
        $sortable = ['id', 'title', 'status', 'created_at', 'products_count'];
        $orderColumn = in_array($orderColumnName, $sortable, true) ? $orderColumnName : 'id';

        $query = Brand::query()
            ->with(['scopeCategory:id,title', 'media'])
            ->withCount('products');

        $totalRecords = Brand::count();
        $filteredRecords = $totalRecords;
        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('title', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('scopeCategory', function ($c) use ($searchValue) {
                        $c->where('title', 'like', "%{$searchValue}%");
                    });
            });
            $filteredRecords = $query->count();
        }


        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($brand) {
                $scopeDisplay = $brand->scope_type;
                if ($brand->scope_type === HomePageScopeEnum::CATEGORY() && $brand->scopeCategory) {
                    $scopeDisplay .= ' (' . $brand->scopeCategory->title . ')';
                }
                return [
                    'id' => $brand->id,
                    'title' => e($brand->title),
                    'scope_type' => view('partials.status', [
                        'status' => $scopeDisplay,
                    ])->render(),
                    'image' => view('partials.image', ['image' => (!empty($brand->logo) ? $brand->logo : asset('assets/images/brand-placeholder.png')), 'title' => $brand->title])->render(),
                    'status' => view('partials.status', ['status' => $brand->status ?? ""])->render(),
                    'products_count' => '<span class="badge bg-blue-lt">' . ($brand->products_count ?? 0) . '</span>',
                    'created_at' => $brand->created_at->format('Y-m-d'),
                    'action' => view('partials.actions', ['modelName' => 'brand', 'id' => $brand->id, 'title' => $brand->title, 'mode' => 'model_view', 'editPermission' => $this->editPermission, 'deletePermission' => $this->deletePermission])->render(),
                ];
            })
            ->toArray();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function groups(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Brand::class);

        $perPage = 50;
        $page = max(1, (int) $request->get('page', 1));
        $search = trim((string) $request->get('search', ''));
        $listingFilters = [
            'search' => $search,
        ];

        if ($search !== '') {
            $base = Brand::query()
                ->with(['scopeCategory:id,title', 'media'])
                ->withCount('products')
                ->applyListingFilters($listingFilters)
                ->orderBy('title');

            $total = (clone $base)->count();
            $brands = $base->skip(($page - 1) * $perPage)->take($perPage)->get();

            $html = $brands->map(fn ($brand) => view('partials.brand-row', [
                'brand' => $brand,
                'scopeLabel' => $brand->scope_type === HomePageScopeEnum::CATEGORY()
                    ? ($brand->scopeCategory->title ?? __('labels.category'))
                    : __('labels.global'),
                'editPermission' => $this->editPermission,
                'deletePermission' => $this->deletePermission,
            ])->render())->implode('');

            return response()->json([
                'html' => $html,
                'has_more' => ($page * $perPage) < $total,
                'next_page' => $page + 1,
            ]);
        }

        if ($request->get('type') === 'brands') {
            $base = Brand::query()
                ->with('media')
                ->withCount('products')
                ->applyListingFilters($listingFilters);

            if ($request->get('group') === 'global') {
                $base->where('scope_type', HomePageScopeEnum::GLOBAL());
            } else {
                $base->where('scope_type', HomePageScopeEnum::CATEGORY())
                    ->where('scope_id', (int) $request->get('scope_id'));
            }

            $base->orderBy('title');

            $total = (clone $base)->count();
            $brands = $base->skip(($page - 1) * $perPage)->take($perPage)->get();

            $html = $brands->map(fn ($brand) => view('partials.brand-row', [
                'brand' => $brand,
                'scopeLabel' => null,
                'editPermission' => $this->editPermission,
                'deletePermission' => $this->deletePermission,
            ])->render())->implode('');

            return response()->json([
                'html' => $html,
                'has_more' => ($page * $perPage) < $total,
                'next_page' => $page + 1,
            ]);
        }

        $html = '';

        if ($page === 1) {
            $globalCount = Brand::query()
                ->applyListingFilters($listingFilters)
                ->where('scope_type', HomePageScopeEnum::GLOBAL())
                ->count();
            if ($globalCount > 0) {
                $html .= view('partials.brand-group-header', [
                    'isGlobal' => true,
                    'scopeId' => null,
                    'title' => __('labels.global'),
                    'count' => $globalCount,
                ])->render();
            }
        }

        $catGroups = Brand::query()
            ->applyListingFilters($listingFilters)
            ->join('categories', 'categories.id', '=', 'brands.scope_id')
            ->where('brands.scope_type', HomePageScopeEnum::CATEGORY())
            ->groupBy('brands.scope_id', 'categories.title')
            ->orderBy('categories.title')
            ->select('brands.scope_id as scope_id', 'categories.title as category_title', DB::raw('COUNT(*) as brand_count'))
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $scopeCategories = Category::query()
            ->with('media')
            ->whereIn('id', $catGroups->pluck('scope_id'))
            ->get()
            ->keyBy('id');

        foreach ($catGroups as $group) {
            $scopeCategory = $scopeCategories->get($group->scope_id);
            $html .= view('partials.brand-group-header', [
                'isGlobal' => false,
                'scopeId' => $group->scope_id,
                'title' => $group->category_title,
                'count' => $group->brand_count,
                'categoryImage' => $scopeCategory?->image,
            ])->render();
        }

        $groupTotal = Brand::query()
            ->applyListingFilters($listingFilters)
            ->where('scope_type', HomePageScopeEnum::CATEGORY())
            ->whereNotNull('scope_id')
            ->distinct()
            ->count('scope_id');

        return response()->json([
            'html' => $html,
            'has_more' => ($page * $perPage) < $groupTotal,
            'next_page' => $page + 1,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Brand::class);
        $query = $request->input('q');
        $exceptId = $request->input('exceptId');
        $findId = $request->input('find_id');

        if ($findId) {
            $brands = Brand::where('id', $findId)
                ->where('status', BrandStatusEnum::ACTIVE())
                ->select('id', 'title')
                ->with('media')
                ->get();
        } else {
            $brands = Brand::where('title', 'LIKE', '%' . $query . '%')
                ->where('status', BrandStatusEnum::ACTIVE())
                ->select('id', 'title')
                ->with('media')
                ->when($exceptId, function ($q) use ($exceptId) {
                    $q->where('id', '!=', $exceptId);
                })
                ->get();
        }
        $results = $brands->map(function ($brand) {
            return [
                'id' => $brand->id,
                'value' => $brand->id,
                'text' => $brand->title,
                'logo' => $brand->logo,
            ];
        });
        return response()->json($results);
    }

}
