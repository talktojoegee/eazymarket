<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\SaveOnboardingPageRequest;
use App\Http\Resources\OnboardingPageResource;
use App\Models\OnboardingPage;
use App\Services\OnboardingPageService;
use App\Types\Api\ApiResponseType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class OnboardingPageController extends Controller
{
    use AuthorizesRequests;

    public function index(OnboardingPageService $pages): View
    {
        $this->authorize('viewAny', OnboardingPage::class);

        return view('admin.onboarding.index', [
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
                ['data' => 'preview', 'name' => 'preview', 'title' => __('labels.image'), 'orderable' => false, 'searchable' => false],
                ['data' => 'title', 'name' => 'title', 'title' => __('labels.title')],
                ['data' => 'subtitle', 'name' => 'subtitle', 'title' => __('labels.subtitle')],
                ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
            ],
            'createPermission' => auth()->user()->can('create', OnboardingPage::class),
            'editPermission' => auth()->user()->can('updateAny', OnboardingPage::class),
            'deletePermission' => auth()->user()->can('deleteAny', OnboardingPage::class),
            'onboardingEnabled' => [
                'customer_app' => $pages->isEnabled('customer_app'),
                'seller_app' => $pages->isEnabled('seller_app'),
            ],
            'onboardingSkipEnabled' => [
                'customer_app' => $pages->isSkipEnabled('customer_app'),
                'seller_app' => $pages->isSkipEnabled('seller_app'),
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OnboardingPage::class);
        $draw = (int) $request->input('draw');
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(1, (int) $request->input('length', 10)));
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $data = $request->validate(['surface' => ['required', 'in:customer_app,seller_app']]);
        $query = OnboardingPage::query()->where('surface', $data['surface']);
        $recordsTotal = (clone $query)->count();

        if ($search !== '') {
            $query->where(fn (Builder $query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('subtitle', 'like', "%{$search}%"));
        }

        $recordsFiltered = (clone $query)->count();
        $editPermission = auth()->user()->can('updateAny', OnboardingPage::class);
        $deletePermission = auth()->user()->can('deleteAny', OnboardingPage::class);
        $rows = $query->orderBy('id')
            ->skip($start)->take($length)->get()
            ->map(function (OnboardingPage $page) use ($editPermission, $deletePermission) {
                return [
                    'id' => $page->id,
                    'preview' => $page->image_path
                        ? '<img src="'.e(Storage::disk('public')->url($page->image_path)).'" alt="'.e($page->title).'" class="avatar avatar-md object-fit-cover">'
                        : '<span class="text-secondary">—</span>',
                    'title' => e($page->title),
                    'subtitle' => e($page->subtitle),
                    'action' => view('partials.actions', [
                        'modelName' => 'onboarding-page',
                        'id' => $page->id,
                        'title' => $page->title,
                        'mode' => 'model_view',
                        'editPermission' => $editPermission,
                        'deletePermission' => $deletePermission,
                        'editAttributes' => [
                            'data-subtitle' => $page->subtitle,
                            'data-surface' => $page->surface,
                            'data-image' => $page->image_path ? Storage::disk('public')->url($page->image_path) : '',
                            'data-font-color' => $page->font_color,
                            'data-next-button-background-color' => $page->next_button_background_color,
                            'data-next-button-text-color' => $page->next_button_text_color,
                            'data-background-type' => $page->background_type,
                            'data-background-color' => $page->background_color ?? '',
                            'data-background-image' => $page->background_image_path
                                ? Storage::disk('public')->url($page->background_image_path)
                                : '',
                        ],
                    ])->render(),
                ];
            })->all();

        return response()->json(['draw' => $draw, 'recordsTotal' => $recordsTotal, 'recordsFiltered' => $recordsFiltered, 'data' => $rows]);
    }

    public function preview(Request $request, OnboardingPageService $onboardingPages): JsonResponse
    {
        $this->authorize('viewAny', OnboardingPage::class);
        $data = $request->validate(['surface' => ['required', 'in:customer_app,seller_app']]);
        $pages = OnboardingPage::query()
            ->where('surface', $data['surface'])
            ->orderBy('id')
            ->get();

        $skipEnabled = $onboardingPages->isSkipEnabled($data['surface']);

        return ApiResponseType::sendJsonResponse(
            true,
            'labels.onboarding_pages_fetched',
            array_map(fn (array $page): array => [...$page, 'show_skip' => $skipEnabled], OnboardingPageResource::collection($pages)->resolve($request))
        );
    }

    public function updateSkipEnabled(Request $request, OnboardingPageService $onboardingPages): JsonResponse
    {
        $this->authorize('updateAny', OnboardingPage::class);
        $validated = $request->validate([
            'surface' => ['required', 'in:customer_app,seller_app'],
            'enabled' => ['required', 'boolean'],
        ]);
        $enabled = (bool) $validated['enabled'];
        $surface = $validated['surface'];
        $onboardingPages->setSkipEnabled($surface, $enabled);

        return ApiResponseType::sendJsonResponse(true, 'labels.onboarding_skip_toggle_updated', [
            'surface' => $surface,
            'enabled' => $enabled,
        ]);
    }

    public function updateEnabled(string $surface, Request $request, OnboardingPageService $pages): JsonResponse
    {
        $this->authorize('updateAny', OnboardingPage::class);
        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = $request->boolean('enabled');
        $pages->setEnabled($surface, $enabled);

        return ApiResponseType::sendJsonResponse(true, 'labels.onboarding_toggle_updated', [
            'surface' => $surface,
            'enabled' => $enabled,
        ]);
    }

    public function show(int $id, OnboardingPageService $onboardingPages): JsonResponse
    {
        $page = OnboardingPage::findOrFail($id);
        $this->authorize('view', $page);

        $data = (new OnboardingPageResource($page))->resolve(request());
        $data['show_skip'] = $onboardingPages->isSkipEnabled($page->surface);

        return ApiResponseType::sendJsonResponse(true, 'labels.onboarding_page_fetched', $data);
    }

    public function store(SaveOnboardingPageRequest $request, OnboardingPageService $pages): JsonResponse|RedirectResponse
    {
        $this->authorize('create', OnboardingPage::class);
        $data = $request->safe()->except(['onboarding_image', 'background_image']);
        
        $page = $pages->create($data, $request->file('onboarding_image'), $request->file('background_image'));

        if (! $request->expectsJson()) {
            return redirect()->route('admin.onboarding.index')->with('success', __('labels.onboarding_page_created'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.onboarding_page_created', new OnboardingPageResource($page), 201);
    }

    public function update(SaveOnboardingPageRequest $request, int $id, OnboardingPageService $pages): JsonResponse|RedirectResponse
    {
        $page = OnboardingPage::findOrFail($id);
        $this->authorize('update', $page);
        $data = $request->safe()->except(['onboarding_image', 'background_image']);
        $page = $pages->update($page, $data, $request->file('onboarding_image'), $request->file('background_image'));

        if (! $request->expectsJson()) {
            return redirect()->route('admin.onboarding.index')->with('success', __('labels.onboarding_page_updated'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.onboarding_page_updated', new OnboardingPageResource($page));
    }

    public function destroy(int $id, OnboardingPageService $pages): JsonResponse
    {
        $page = OnboardingPage::findOrFail($id);
        $this->authorize('delete', $page);
        $surface = $page->surface;
        $pages->delete($page);

        return ApiResponseType::sendJsonResponse(true, 'labels.onboarding_page_deleted', [
            'surface' => $surface,
            'enabled' => $pages->isEnabled($surface),
        ]);
    }

}
