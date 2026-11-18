<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\CategoryStatusEnum;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\DatatableExportRegistry;
use App\Services\CategoryService;
use App\Support\HomeCache;
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

class CategoryController extends Controller
{
    use AuthorizesRequests, ChecksPermissions, PanelAware;

    protected bool $editPermission = false;

    protected bool $deletePermission = false;

    protected bool $createPermission = false;

    public function __construct()
    {
        if ($this->getPanel() === 'admin') {
            $this->editPermission = $this->hasPermission(AdminPermissionEnum::CATEGORY_EDIT());
            $this->deletePermission = $this->hasPermission(AdminPermissionEnum::CATEGORY_DELETE());
            $this->createPermission = $this->hasPermission(AdminPermissionEnum::CATEGORY_CREATE());
        }
    }

    /**
     * Display a listing of the resource.
     */
    public function index(DatatableExportRegistry $exports): View
    {
        $this->authorize('viewAny', Category::class);

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title')],
            ['data' => 'image', 'name' => 'image', 'title' => __('labels.image')],
            ['data' => 'parent', 'name' => 'parent', 'title' => __('labels.parent')],
            ['data' => 'commission', 'name' => 'commission', 'title' => __('labels.commission')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'requires_approval', 'name' => 'requires_approval', 'title' => __('labels.requires_approval')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $editPermission = $this->editPermission;
        $createPermission = $this->createPermission;
        $exportOptions = $exports->frontendOptions(auth()->user(), 'categories', $this->getPanel(), [
            'selectionSupport' => false,
            'supportedScopes' => ['all_filtered', 'all_records'],
            'searchSelector' => '#cat-tree-search',
            'visibleColumns' => ['id', 'title', 'parent', 'commission', 'status', 'requires_approval', 'products_count', 'created_at'],
            'sort' => 'title',
            'direction' => 'asc',
            'length' => 50,
        ]);

        return view($this->panelView('categories.index'), compact('columns', 'editPermission', 'createPermission', 'exportOptions'));
    }

    private function flushHomeCaches(): void
    {
        HomeCache::flushLayout();
    }

    /**
     * Update sort order of parent categories.
     */
    public function updateSort(Request $request): JsonResponse
    {
        try {
            if (! $this->editPermission) {
                throw new AuthorizationException(__('labels.permission_denied'));
            }

            $request->validate([
                'categories' => 'required|array',
                'categories.*' => 'required|integer|exists:categories,id',
            ]);

            DB::beginTransaction();
            foreach ($request->categories as $index => $categoryId) {
                Category::where('id', $categoryId)->whereNull('parent_id')->update([
                    'sort_order' => $index + 1,
                ]);
            }
            DB::commit();

            $this->flushHomeCaches();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.sort_order_updated_successfully'),
                data: []
            );
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed'),
                data: $e->errors()
            );
        } catch (AuthorizationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: []
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: []
            );
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request, CategoryService $categoryService): JsonResponse
    {
        try {
            $this->authorize('create', Category::class);
            $validated = $request->validated();

            $category = $categoryService->createCategory($validated, [
                'image' => $request->file('image'),
                'banner' => $request->file('banner'),
            ]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.category_created_successfully'),
                data: $category,
                status: 201
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed'),
                data: $e->errors(),
                status: 422
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id): JsonResponse
    {
        $this->authorize('viewAny', Category::class);
        try {
            $category = Category::with('parent')->findOrFail($id);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.category_retrieved_successfully'),
                data: $category
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.category_not_found')
            );
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, $id, CategoryService $categoryService): JsonResponse
    {
        try {
            $category = Category::findOrFail($id);
            $this->authorize('update', $category);
            $validated = $request->validated();

            $category = $categoryService->updateCategory(
                $category,
                $validated,
                [
                    'image' => $request->file('image'),
                    'banner' => $request->file('banner'),
                ],
                [
                    'image' => $request->boolean('remove_image'),
                    'banner' => $request->boolean('remove_banner'),
                ],
            );

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.category_updated_successfully'),
                data: $category
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.category_not_found',
                data: [],
                status: 404
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_failed'),
                data: $e->errors(),
                status: 422
            );
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();
            $category = Category::findOrFail($id);
            $this->authorize('delete', $category);
            if ($category->products()->exists()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.category_cannot_be_deleted_with_products'),
                );
            }
            if ($category->children()->whereHas('products')->exists()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.category_cannot_be_deleted_with_products'),
                );
            }

            $category->delete();
            $category->clearMediaCollection('image');
            $category->clearMediaCollection('banner');
            $category->children()->update(['parent_id' => null]);
            DB::commit();

            $this->flushHomeCaches();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.category_deleted_successfully'),
            );
        } catch (ModelNotFoundException) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.category_not_found'),
                status: 404
            );
        } catch (AuthorizationException) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.permission_denied'),
                data: [],
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.operation_failed'),
                data: ['error' => $e->getMessage()],
            );
        }
    }

    public function getCategories(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $draw = $request->get('draw');
        $start = $request->get('start');
        $length = $request->get('length');
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';

        $columns = ['id', 'title', null, null, 'commission', 'status', 'requires_approval', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? null ?: 'id';

        $query = Category::with('parent');

        $totalRecords = Category::count();
        $filteredRecords = $totalRecords;

        if (! empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('title', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('parent', function ($q) use ($searchValue) {
                        $q->where('title', 'like', "%{$searchValue}%");
                    });
            });
            $filteredRecords = $query->count();
        }

        $data = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'image' => view('partials.image', ['image' => (! empty($category->image) ? $category->image : asset('assets/images/category-placeholder.png')), 'title' => $category->title])->render(),
                    'status' => view('partials.status', ['status' => $category->status ?? ''])->render(),
                    'requires_approval' => '<span class="badge text-uppercase '.($category->requires_approval == 1 ? 'bg-info-lt' : 'bg-warning-lt').'">'.($category->requires_approval == 1 ? __('labels.required') : __('labels.not_required')).'</span>',
                    'created_at' => $category->created_at->format('Y-m-d'),
                    'parent' => $category->parent ? $category->parent->title : 'N/A',
                    'commission' => (max($category->commission, 0)).'%',
                    'action' => view('partials.actions', [
                        'modelName' => 'category',
                        'id' => $category->id,
                        'title' => $category->title,
                        'mode' => 'model_view',
                        'editPermission' => $this->editPermission,
                        'deletePermission' => $this->deletePermission,
                    ])->render(),
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

    public function tree(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);

        $perPage = 50;
        $page = max(1, (int) $request->get('page', 1));
        $search = trim((string) $request->get('search', ''));

        if ($search !== '') {
            $base = Category::query()
                ->where('title', 'like', "%{$search}%")
                ->withCount('products')
                ->with(['parent', 'media'])
                ->orderBy('title');

            $total = (clone $base)->count();
            $categories = $base->skip(($page - 1) * $perPage)->take($perPage)->get();

            $html = $categories->map(fn ($category) => view('partials.category-tree-row', [
                'category' => $category,
                'depth' => 0,
                'expandable' => false,
                'pathPrefix' => $this->categoryPathPrefix($category),
                'editPermission' => $this->editPermission,
                'deletePermission' => $this->deletePermission,
            ])->render())->implode('');

            return response()->json([
                'html' => $html,
                'has_more' => ($page * $perPage) < $total,
                'next_page' => $page + 1,
            ]);
        }

        $parentId = $request->get('parent_id');
        $parentId = ($parentId === null || $parentId === '') ? null : (int) $parentId;
        $depth = max(0, (int) $request->get('depth', 0));

        $base = Category::query()
            ->when(
                $parentId === null,
                fn ($q) => $q->whereNull('parent_id'),
                fn ($q) => $q->where('parent_id', $parentId)
            )
            ->withCount(['children', 'products'])
            ->with('media')
            ->ordered()
            ->orderBy('id');

        $total = (clone $base)->count();
        $categories = $base->skip(($page - 1) * $perPage)->take($perPage)->get();

        $html = $categories->map(fn ($category) => view('partials.category-tree-row', [
            'category' => $category,
            'depth' => $depth,
            'expandable' => ($category->children_count ?? 0) > 0,
            'pathPrefix' => null,
            'editPermission' => $this->editPermission,
            'deletePermission' => $this->deletePermission,
        ])->render())->implode('');

        return response()->json([
            'html' => $html,
            'has_more' => ($page * $perPage) < $total,
            'next_page' => $page + 1,
        ]);
    }

    private function categoryPathPrefix(Category $category): ?string
    {
        $parts = [];
        $current = $category->parent;

        while ($current) {
            array_unshift($parts, $current->title);
            $current = $current->parent;
        }

        return empty($parts) ? null : implode(' / ', $parts);
    }

    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Category::class);
        $query = $request->input('search');
        $exceptId = $request->input('exceptId');
        $findId = $request->input('find_id');
        $type = $request->input('type');

        if ($findId) {
            $categories = Category::where('id', $findId)
                ->select('id', 'title', 'requires_approval')
                ->with('media')
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->get();
        } elseif (! empty($type) && $type == 'root') {
            $categories = Category::where('title', 'LIKE', '%'.$query.'%')
                ->select('id', 'title', 'requires_approval')
                ->with('media')
                ->where('parent_id', null)
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->get();
        } else {
            $categories = Category::where('title', 'like', "%{$query}%")
                ->select('id', 'title', 'requires_approval')
                ->with('media')
                ->when($exceptId, function ($q) use ($exceptId) {
                    $q->where('id', '!=', $exceptId);
                })
                ->where('status', CategoryStatusEnum::ACTIVE())
                ->get();
        }

        $results = $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'value' => $category->id,
                'text' => $category->title,
                'image' => $category->image,
                'requires_approval' => (bool) $category->requires_approval,
            ];
        });

        return response()->json($results);
    }

}
