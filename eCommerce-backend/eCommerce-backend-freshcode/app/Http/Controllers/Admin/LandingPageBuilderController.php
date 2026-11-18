<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\SettingTypeEnum;
use App\Http\Requests\Admin\SaveLandingPageBuilderRequest;
use App\Http\Requests\Admin\UploadLandingPageBuilderAssetsRequest;
use App\Models\LandingPageBuilderDocument;
use App\Services\LandingPageBuilderAssetService;
use App\Services\SettingService;
use App\Types\Api\ApiResponseType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LandingPageBuilderController extends Controller
{
    use AuthorizesRequests;

    public function index(LandingPageBuilderAssetService $assetService): View
    {
        $this->authorize('viewAny', LandingPageBuilderDocument::class);
        $assetService->purgeExpiredPendingAssets();

        return view('admin.landing-page-builder.index', [
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'title' => __('labels.id'), 'label' => __('labels.id')],
                ['data' => 'title', 'name' => 'title', 'title' => __('labels.title'), 'label' => __('labels.title')],
                ['data' => 'slug', 'name' => 'slug', 'title' => __('labels.slug'), 'label' => __('labels.slug')],
                ['data' => 'status', 'name' => 'is_active', 'title' => __('labels.status'), 'label' => __('labels.status')],
                ['data' => 'updated_at', 'name' => 'updated_at', 'title' => __('labels.updated_at'), 'label' => __('labels.updated_at')],
                ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'label' => __('labels.action'), 'orderable' => false, 'searchable' => false],
            ],
            'canEdit' => auth()->user()->can('updateAny', LandingPageBuilderDocument::class),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LandingPageBuilderDocument::class);
        $landingPageBaseUrl = $this->landingPageBaseUrl();
        $draw = (int) $request->input('draw');
        $start = max(0, (int) $request->input('start', 0));
        $length = min(100, max(1, (int) $request->input('length', 10)));
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $query = LandingPageBuilderDocument::query();
        $recordsTotal = (clone $query)->count();

        if ($search !== '') {
            $query->where(fn ($query) => $query
                ->where('title', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%"));
        }

        $recordsFiltered = (clone $query)->count();
        $orderIndex = (int) data_get($request->input('order'), '0.column', 0);
        $orderColumn = data_get($request->input('columns'), "{$orderIndex}.data", 'id');
        $orderDirection = data_get($request->input('order'), '0.dir') === 'asc' ? 'asc' : 'desc';
        $sortableColumns = ['id', 'title', 'slug', 'is_active', 'updated_at'];
        if ($orderColumn === 'status') {
            $orderColumn = 'is_active';
        }
        if (! in_array($orderColumn, $sortableColumns, true)) {
            $orderColumn = 'id';
        }

        $canEdit = auth()->user()->can('updateAny', LandingPageBuilderDocument::class);
        $rows = $query->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(fn (LandingPageBuilderDocument $document): array => [
                'id' => $document->id,
                'title' => e($document->title),
                'slug' => e($document->slug),
                'status' => $document->is_active
                    ? '<span class="badge bg-success-lt text-success fw-semibold">'.e(__('labels.active')).'</span>'
                    : '<span class="badge bg-secondary-lt text-secondary fw-semibold">'.e(__('labels.inactive')).'</span>',
                'updated_at' => e($document->updated_at?->format('Y-m-d H:i')),
                'action' => view('partials.actions', [
                    'modelName' => 'landing-page',
                    'id' => $document->id,
                    'title' => $document->title,
                    'mode' => 'route',
                    'route' => route('admin.landing-page-builder.edit', $document),
                    'editPermission' => $canEdit,
                    'deletePermission' => $canEdit,
                    'buttonVariant' => 'icon',
                    'deleteUrl' => route('admin.landing-page-builder.destroy', $document),
                    'shareUrl' => $landingPageBaseUrl.'/go/'.rawurlencode($document->slug),
                ])->render(),
            ])->all();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', LandingPageBuilderDocument::class);

        return $this->builderView(new LandingPageBuilderDocument(), __('labels.create_landing_page'));
    }

    public function store(SaveLandingPageBuilderRequest $request, LandingPageBuilderAssetService $assetService): JsonResponse|RedirectResponse
    {
        $this->authorize('create', LandingPageBuilderDocument::class);
        $document = new LandingPageBuilderDocument();
        $this->fillDocument($document, $request->validated());
        $document->save();
        $assetService->attachEditorAssets($document, $request->validated()['page_content']['asset_token'], $request->user());
        $assetService->deleteMarkedAssets($document, $request->validated()['page_content']);

        if (! $request->expectsJson()) {
            return redirect()->route('admin.landing-page-builder.index')
                ->with('success', __('labels.landing_page_builder_saved'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.landing_page_builder_saved', [
            'redirect_url' => route('admin.landing-page-builder.edit', $document),
        ], 201);
    }

    public function edit(LandingPageBuilderDocument $document): View
    {
        $this->authorize('viewAny', LandingPageBuilderDocument::class);

        return $this->builderView($document, __('labels.edit_landing_page'));
    }

    public function update(SaveLandingPageBuilderRequest $request, LandingPageBuilderDocument $document, LandingPageBuilderAssetService $assetService): JsonResponse|RedirectResponse
    {
        $this->authorize('update', $document);
        $this->fillDocument($document, $request->validated());
        $document->save();
        $assetService->attachEditorAssets($document, $request->validated()['page_content']['asset_token'], $request->user());
        $assetService->deleteMarkedAssets($document, $request->validated()['page_content']);

        if (! $request->expectsJson()) {
            return redirect()->route('admin.landing-page-builder.edit', $document)
                ->with('success', __('labels.landing_page_builder_saved'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.landing_page_builder_saved');
    }

    public function destroy(Request $request, LandingPageBuilderDocument $document): JsonResponse|RedirectResponse
    {
        $this->authorize('delete', $document);
        $document->delete();

        if (! $request->expectsJson()) {
            return redirect()->route('admin.landing-page-builder.index')
                ->with('success', __('labels.landing_page_deleted'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.landing_page_deleted');
    }

    public function uploadAssets(UploadLandingPageBuilderAssetsRequest $request, LandingPageBuilderAssetService $assetService): JsonResponse
    {
        $document = null;
        if ($request->filled('document_id')) {
            $document = LandingPageBuilderDocument::query()->findOrFail($request->integer('document_id'));
            $this->authorize('update', $document);
        }

        $assetService->purgeExpiredPendingAssets();
        $assets = $assetService->upload(
            $request->file('files', []),
            $request->string('asset_token')->toString(),
            $request->user(),
        );

        return ApiResponseType::sendJsonResponse(true, 'labels.landing_page_assets_uploaded', $assets, 201);
    }

    public function showPublic(string $slug): View
    {
        $document = LandingPageBuilderDocument::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();

        return view('landing-pages.show', compact('document'));
    }

    private function fillDocument(LandingPageBuilderDocument $document, array $validated): void
    {
        $content = $validated['page_content'];
        $document->fill([
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'show_header_footer' => $validated['show_header_footer'],
            'is_active' => $validated['is_active'],
            'html' => $content['html'],
            'css' => $content['css'],
            'project_data' => $content['project'] ?? null,
        ]);
    }

    private function builderView(LandingPageBuilderDocument $document, string $pageTitle): View
    {
        $canEdit = auth()->user()->can('updateAny', LandingPageBuilderDocument::class);

        return view('admin.landing-page-builder.editor', [
            'document' => $document,
            'pageTitle' => $pageTitle,
            'canEdit' => $canEdit,
            'landingPageBaseUrl' => $this->landingPageBaseUrl().'/go/',
            'starterHtml' => <<<'HTML'
<section class="landing-hero">
    <div class="landing-hero__content">
        <p class="landing-hero__eyebrow">A better way to shop</p>
        <h1>Make your storefront yours</h1>
        <p>Build a landing page that introduces your store and helps customers find what they need.</p>
        <a class="landing-hero__button" href="#shop">Explore the collection</a>
    </div>
</section>
HTML,
            'starterCss' => '.landing-hero{min-height:520px;display:flex;align-items:center;padding:64px 8%;background:#f4f6fa;color:#182433}.landing-hero__content{max-width:680px}.landing-hero__eyebrow{color:#667382;font-size:14px;letter-spacing:.08em;text-transform:uppercase}.landing-hero h1{margin:12px 0;font-size:clamp(36px,6vw,64px);line-height:1.08}.landing-hero__button{display:inline-block;margin-top:16px;padding:12px 20px;border-radius:6px;background:#f5c400;color:#182433;text-decoration:none;font-weight:700}',
        ]);
    }

    private function landingPageBaseUrl(): string
    {
        $settings = app(SettingService::class)->getSettingValues(SettingTypeEnum::WEB());

        return rtrim(trim((string) ($settings['customerWebUrl'] ?? '')) ?: url('/'), '/');
    }
}
