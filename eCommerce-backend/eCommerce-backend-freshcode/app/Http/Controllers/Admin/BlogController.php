<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\StoreBlogRequest;
use App\Http\Requests\Blog\UpdateBlogRequest;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Product;
use App\Models\WatchBuyProfile;
use App\Services\BlogService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    use ChecksPermissions;

    public function index(): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_BLOG_VIEW());

        return view('admin.watch_buy.blogs.index', [
            'columns' => [
                ['data' => 'image', 'name' => 'image', 'title' => __('labels.image'), 'orderable' => false, 'searchable' => false],
                ['data' => 'title', 'name' => 'title', 'title' => __('labels.blog_title')],
                ['data' => 'categories', 'name' => 'categories', 'title' => __('labels.blog_categories'), 'orderable' => false],
                ['data' => 'author', 'name' => 'author', 'title' => __('labels.author'), 'orderable' => false],
                ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
                ['data' => 'published_at', 'name' => 'published_at', 'title' => __('labels.published_at')],
                ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
            ],
            'createPermission' => $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_CREATE()),
            'editPermission' => $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_EDIT()),
            'deletePermission' => $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_DELETE()),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_BLOG_VIEW());
        $draw = (int) $request->input('draw', 0);
        $start = max((int) $request->input('start', 0), 0);
        $length = min(max((int) $request->input('length', 10), 1), 100);
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $orderIndex = (int) data_get($request->input('order'), '0.column', 5);
        $direction = data_get($request->input('order'), '0.dir') === 'asc' ? 'asc' : 'desc';
        $orderName = data_get($request->input('columns'), $orderIndex.'.name', 'published_at');
        $orderColumn = in_array($orderName, ['title', 'status', 'published_at'], true) ? $orderName : 'published_at';
        $query = Blog::query()->with(['categories', 'authorProfile', 'media']);
        $total = (clone $query)->count();

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('categories', fn ($category) => $category->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('authorProfile', fn ($profile) => $profile->where('username', 'like', "%{$search}%"));
            });
        }

        $filtered = $search === '' ? $total : (clone $query)->count();
        $editPermission = $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_EDIT());
        $deletePermission = $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_DELETE());
        $rows = $query->orderBy($orderColumn, $direction)
            ->orderBy('id')
            ->skip($start)
            ->take($length)
            ->get()
            ->map(fn (Blog $blog) => [
                'image' => $blog->image !== ''
                    ? '<img src="'.e($blog->image).'" alt="'.e($blog->title).'" class="avatar avatar-md rounded object-fit-cover">'
                    : '<span class="avatar avatar-md rounded"><i class="ti ti-photo"></i></span>',
                'title' => '<div><strong>'.e($blog->title).'</strong><div class="text-secondary">'.e($blog->slug).'</div></div>',
                'categories' => e($blog->categories->pluck('name')->join(', ')),
                'author' => e('@'.$blog->authorProfile?->username),
                'status' => '<span class="badge '.($blog->status === 'published' ? 'bg-success-lt' : 'bg-secondary-lt').'">'.e(__('labels.blog_status_'.$blog->status)).'</span>',
                'published_at' => $blog->published_at?->format('Y-m-d H:i') ?? '—',
                'action' => view('admin.watch_buy.blogs._actions', [
                    'blog' => $blog,
                    'editPermission' => $editPermission,
                    'deletePermission' => $deletePermission,
                ])->render(),
            ]);

        return response()->json(['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $rows]);
    }

    public function create(): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_BLOG_CREATE());

        return $this->form(new Blog);
    }

    public function store(StoreBlogRequest $request, BlogService $blogs): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_BLOG_CREATE());
        $blog = $blogs->save(new Blog, $request->validated(), $request->file('image'));
        $redirectUrl = route('admin.watch-and-buy.blogs.index');

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.blog_created', ['redirect_url' => $redirectUrl, 'id' => $blog->id], 201)
            : redirect($redirectUrl)->with('success', __('labels.blog_created'));
    }

    public function edit(Blog $blog): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_BLOG_EDIT());

        return $this->form($blog->load(['categories', 'products', 'media']));
    }

    public function update(UpdateBlogRequest $request, Blog $blog, BlogService $blogs): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_BLOG_EDIT());
        $blog = $blogs->save($blog, $request->validated(), $request->file('image'));
        $redirectUrl = route('admin.watch-and-buy.blogs.index');

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.blog_updated', ['redirect_url' => $redirectUrl])
            : redirect($redirectUrl)->with('success', __('labels.blog_updated'));
    }

    public function destroy(Request $request, Blog $blog, BlogService $blogs): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_BLOG_DELETE());
        $blogs->delete($blog);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.blog_deleted')
            : back()->with('success', __('labels.blog_deleted'));
    }

    public function productOptions(Request $request): JsonResponse
    {
        abort_unless(
            $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_VIEW())
            || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_CREATE())
            || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_BLOG_EDIT()),
            403,
            trans('labels.permission_denied')
        );
        $findIn = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('find_in', [])))));
        $search = trim((string) $request->input('search', ''));
        $products = Product::query()
            ->with('seller.user')
            ->when($findIn !== [], fn ($query) => $query->whereKey($findIn))
            ->when($findIn === [] && $search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->orderBy('title')
            ->limit($findIn === [] ? 15 : 50)
            ->get();

        return response()->json($products->map(fn (Product $product) => [
            'value' => (string) $product->id,
            'text' => $product->title,
            'subtitle' => $product->seller?->user?->name,
        ])->values());
    }

    private function form(Blog $blog): View
    {
        $selectedCategoryIds = old('category_ids', $blog->categories->pluck('id')->all());
        $selectedProductIds = old('product_ids', $blog->products->pluck('id')->all());
        $selectedAuthorId = (int) old('author_profile_id', $blog->author_profile_id);

        return view('admin.watch_buy.blogs.form', [
            'blog' => $blog,
            'categories' => BlogCategory::query()
                ->where('is_active', true)
                ->when($selectedCategoryIds !== [], fn ($query) => $query->orWhereIn('id', $selectedCategoryIds))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'profiles' => WatchBuyProfile::query()
                ->with('seller.user')
                ->where('status', 'active')
                ->when($selectedAuthorId > 0, fn ($query) => $query->orWhere('id', $selectedAuthorId))
                ->orderBy('username')
                ->get(),
            'selectedCategoryIds' => array_map('intval', (array) $selectedCategoryIds),
            'selectedProductIds' => array_map('intval', (array) $selectedProductIds),
            'selectedProducts' => Product::query()->whereKey($selectedProductIds)->orderBy('title')->get(['id', 'title']),
            'selectedTags' => old('tags', $blog->tags ?? []),
        ]);
    }

    private function allow(string $permission): void
    {
        abort_unless($this->hasPermission($permission), 403, trans('labels.permission_denied'));
    }
}
