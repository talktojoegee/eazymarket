<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\BlogCategory\StoreBlogCategoryRequest;
use App\Http\Requests\BlogCategory\UpdateBlogCategoryRequest;
use App\Models\BlogCategory;
use App\Services\BlogCategoryService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogCategoryController extends Controller
{
    use ChecksPermissions;

    public function index(): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_BLOG_CATEGORY_VIEW());

        return view('admin.watch_buy.blog_categories.index', [
            'columns' => [
                ['data' => 'image', 'name' => 'image', 'title' => __('labels.image'), 'orderable' => false, 'searchable' => false],
                ['data' => 'name', 'name' => 'name', 'title' => __('labels.name')],
                ['data' => 'slug', 'name' => 'slug', 'title' => __('labels.slug')],
                ['data' => 'sort_order', 'name' => 'sort_order', 'title' => __('labels.sort_order')],
                ['data' => 'status', 'name' => 'is_active', 'title' => __('labels.status')],
                ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
            ],
            'createPermission' => $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_CATEGORY_CREATE()),
            'editPermission' => $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_CATEGORY_EDIT()),
            'deletePermission' => $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_CATEGORY_DELETE()),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_BLOG_CATEGORY_VIEW());
        $draw = (int) $request->input('draw', 0);
        $start = max((int) $request->input('start', 0), 0);
        $length = min(max((int) $request->input('length', 10), 1), 100);
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $orderIndex = (int) data_get($request->input('order'), '0.column', 3);
        $direction = data_get($request->input('order'), '0.dir') === 'asc' ? 'asc' : 'desc';
        $orderName = data_get($request->input('columns'), $orderIndex.'.name', 'sort_order');
        $orderColumn = in_array($orderName, ['name', 'slug', 'sort_order', 'is_active'], true) ? $orderName : 'sort_order';
        $query = BlogCategory::query()->with('media');
        $total = (clone $query)->count();

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $filtered = $search === '' ? $total : (clone $query)->count();
        $editPermission = $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_CATEGORY_EDIT());
        $deletePermission = $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_CATEGORY_DELETE());
        $rows = $query->orderBy($orderColumn, $direction)
            ->orderBy('id')
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function (BlogCategory $category) use ($editPermission, $deletePermission) {
                $payload = e(json_encode([
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'sort_order' => $category->sort_order,
                    'description' => $category->description,
                    'is_active' => $category->is_active,
                    'image' => $category->image,
                    'metadata' => $category->metadata ?? [],
                ], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT));
                $actions = '';

                if ($editPermission) {
                    $actions .= '<button type="button" class="btn btn-outline-primary btn-icon blog-category-edit" data-category="'.$payload.'" aria-label="'.e(__('labels.edit')).'" title="'.e(__('labels.edit')).'"><i class="ti ti-edit"></i></button>';
                }
                if ($deletePermission) {
                    $actions .= '<button type="button" class="btn btn-outline-danger btn-icon blog-category-delete" data-id="'.$category->id.'" data-name="'.e($category->name).'" aria-label="'.e(__('labels.delete')).'" title="'.e(__('labels.delete')).'"><i class="ti ti-trash"></i></button>';
                }

                return [
                    'image' => $category->image !== ''
                        ? '<img src="'.e($category->image).'" alt="'.e($category->name).'" class="avatar avatar-md rounded object-fit-cover">'
                        : '<span class="avatar avatar-md rounded"><i class="ti ti-photo"></i></span>',
                    'name' => e($category->name),
                    'slug' => e($category->slug),
                    'sort_order' => $category->sort_order,
                    'status' => '<span class="badge '.($category->is_active ? 'bg-success-lt' : 'bg-secondary-lt').'">'.e($category->is_active ? __('labels.active') : __('labels.inactive')).'</span>',
                    'action' => '<div class="d-flex gap-2">'.$actions.'</div>',
                ];
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    public function store(StoreBlogCategoryRequest $request, BlogCategoryService $categories): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_BLOG_CATEGORY_CREATE());
        $data = $request->validated();
        $category = $categories->create($data, $request->file('image'));

        if (! $request->expectsJson()) {
            return redirect()->route('admin.watch-and-buy.blog-categories.index')->with('success', __('labels.blog_category_created'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.blog_category_created', ['id' => $category->id], 201);
    }

    public function update(UpdateBlogCategoryRequest $request, BlogCategory $blogCategory, BlogCategoryService $categories): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_BLOG_CATEGORY_EDIT());
        $category = $categories->update($blogCategory, $request->validated(), $request->file('image'));

        if (! $request->expectsJson()) {
            return redirect()->route('admin.watch-and-buy.blog-categories.index')->with('success', __('labels.blog_category_updated'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.blog_category_updated', ['id' => $category->id]);
    }

    public function destroy(Request $request, BlogCategory $blogCategory, BlogCategoryService $categories): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_BLOG_CATEGORY_DELETE());
        $categories->delete($blogCategory);

        if (! $request->expectsJson()) {
            return redirect()->route('admin.watch-and-buy.blog-categories.index')->with('success', __('labels.blog_category_deleted'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.blog_category_deleted');
    }

    private function allow(string $permission): void
    {
        abort_unless($this->hasPermission($permission), 403, trans('labels.permission_denied'));
    }
}
