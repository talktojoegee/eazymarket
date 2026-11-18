<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\BrandStatusEnum;
use App\Enums\CategoryStatusEnum;
use App\Enums\HomePage\HomePagePlatformEnum;
use App\Enums\HomePage\HomeSectionTypeEnum;
use App\Enums\HomePageScopeEnum;
use App\Enums\Product\ProductStatusEnum;
use App\Enums\Product\ProductVarificationStatusEnum;
use App\Enums\SettingTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use App\Http\Requests\HomeBuilder\SaveHomeLayoutRequest;
use App\Http\Resources\HomeBuilder\HomeBuilderLayoutResource;
use App\Http\Resources\HomeBuilder\HomeBuilderPaginatedLayoutResource;
use App\Http\Resources\HomeNavbarItemResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\HomeLayout;
use App\Models\HomeNavbarItem;
use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Models\Market;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CurrencyService;
use App\Services\HomeBuilderOptions;
use App\Services\HomeBuilderService;
use App\Services\HomeBuilderTemplates;
use App\Services\SettingService;
use App\Support\HomeCache;
use App\Support\PricePresenter;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HomeBuilderController extends Controller
{
    use AuthorizesRequests, ChecksPermissions;

    /** Section media collections the admin can upload, keyed by client name. */
    private const array SECTION_MEDIA = [
        'background' => SpatieMediaCollectionName::HOME_SECTION_BACKGROUND,
        'hero_media' => SpatieMediaCollectionName::HOME_SECTION_HERO_MEDIA,
        'card_background' => SpatieMediaCollectionName::HOME_SECTION_CARD_BACKGROUND,
    ];

    public function __construct(
        private readonly HomeBuilderService $service,
        private readonly HomeBuilderOptions $options,
        private readonly HomeBuilderTemplates $templates,
    ) {}

    public function index(SettingService $settingService): View
    {
        $this->authorize('viewAny', HomeLayout::class);

        $webSettings = data_get(
            $settingService->getSettingByVariable(SettingTypeEnum::WEB())?->resolve(),
            'value',
            []
        );
        $homeGeneralSettings = data_get(
            $settingService->getSettingByVariable(SettingTypeEnum::HOME_GENERAL_SETTINGS())?->resolve(),
            'value',
            []
        );
        $categories = Category::query()
            ->whereNull('parent_id')
            ->where('status', CategoryStatusEnum::ACTIVE())
            ->with('media')
            ->orderBy('sort_order')
            ->limit(16)
            ->get(['id', 'title']);
        $homeNavbarItems = HomeNavbarItem::query()
            ->with('media')
            ->where('is_active', true)
            ->ordered()
            ->get();
        $productQuery = Product::query()
            ->where('status', ProductStatusEnum::ACTIVE())
            ->where('verification_status', ProductVarificationStatusEnum::APPROVED())
            ->select(['id', 'title', 'short_description', 'tags', 'category_id', 'brand_id', 'badge_id', 'image_fit', 'featured', 'is_inclusive_tax']);
        $productSources = [
            'newly_added' => (clone $productQuery)->latest('id')->limit(12)->get(),
            'top_rated' => (clone $productQuery)->applySorting('avg_rated')->limit(12)->get(),
            'best_seller' => (clone $productQuery)->applySorting('best_seller')->limit(12)->get(),
            'featured' => (clone $productQuery)->applySorting('featured')->limit(12)->get(),
            'recommended' => (clone $productQuery)->orderByDesc('featured')->latest('id')->limit(12)->get(),
        ];
        $previewProducts = new EloquentCollection(
            collect($productSources)->flatten(1)->unique('id')->values()->all(),
        );
        $previewProducts->load(['media', 'variants.locationInventories', 'taxClasses.taxRates', 'brand', 'category', 'reviews', 'badge']);
        $previewProducts->each(function (Product $product): void {
            $product->variants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $product));
        });
        $previewProductsById = $previewProducts->keyBy('id');
        $productSources = collect($productSources)
            ->map(fn ($products) => $products->map(fn (Product $product) => $previewProductsById->get($product->id)))
            ->all();
        $brands = Brand::query()
            ->where('status', BrandStatusEnum::ACTIVE())
            ->with('media')
            ->latest('id')
            ->limit(16)
            ->get(['id', 'title']);

        return view('admin.home-builder.index', [
            'scopeTypes' => HomePageScopeEnum::values(),
            'appOptions' => $this->options->uiOptions(HomePagePlatformEnum::APP()),
            'webOptions' => $this->options->uiOptions(HomePagePlatformEnum::WEB()),
            'appTemplates' => $this->templates->sectionTemplates(HomePagePlatformEnum::APP()),
            'webTemplates' => $this->templates->sectionTemplates(HomePagePlatformEnum::WEB()),
            'appThemes' => $this->templates->layoutThemes(HomePagePlatformEnum::APP()),
            'webThemes' => $this->templates->layoutThemes(HomePagePlatformEnum::WEB()),
            'homeNavbarItems' => HomeNavbarItemResource::collection($homeNavbarItems)->resolve(request()),
            'labels' => $this->builderLabels(),
            'canEdit' => $this->hasPermission(AdminPermissionEnum::HOME_BUILDER_EDIT()),
            'storefrontSettings' => $webSettings,
            'homeGeneralSettings' => $homeGeneralSettings,
            'previewCatalog' => [
                'products' => $this->productPreviewItems($productSources['newly_added']),
                'product_sources' => collect($productSources)
                    ->map(fn ($products) => $this->productPreviewItems($products))
                    ->all(),
                'categories' => $categories->map(fn (Category $category) => [
                    'purpose' => 'display',
                    'itemable_type' => 'category',
                    'itemable_id' => $category->id,
                    'itemable_label' => $category->title,
                    'itemable_image' => $category->image,
                    'itemable_meta' => [],
                ])->values()->all(),
                'brands' => $brands->map(fn (Brand $brand) => [
                    'purpose' => 'display',
                    'itemable_type' => 'brand',
                    'itemable_id' => $brand->id,
                    'itemable_label' => $brand->title,
                    'itemable_image' => $brand->logo,
                    'itemable_meta' => [],
                ])->values()->all(),
            ],
        ]);
    }

    /** Translated strings consumed by the builder JS (rendered client-side). */
    private function builderLabels(): array
    {
        return [
            'scope' => __('labels.hb_scope'),
            'global' => __('labels.hb_global'),
            'byCategory' => __('labels.hb_by_category'),
            'selectCategory' => __('labels.hb_select_category'),
            'sections' => __('labels.hb_sections'),
            'addSection' => __('labels.hb_add_section'),
            'selectSection' => __('labels.hb_select_section'),
            'save' => __('labels.hb_save'),
            'saved' => __('labels.home_layout_saved_successfully'),
            'advanced' => __('labels.hb_advanced'),
            'markets' => __('labels.hb_markets'),
            'allMarkets' => __('labels.hb_all_markets'),
            'addItem' => __('labels.hb_add_item'),
            'removeConfirm' => __('labels.hb_remove_section_confirm'),
            'saveFirst' => __('labels.hb_save_first_upload'),
            'upload' => __('labels.hb_upload'),
            'tabContent' => __('labels.hb_tab_content'),
            'tabLayout' => __('labels.hb_tab_layout'),
            'tabStyle' => __('labels.hb_tab_style'),
            'tabVisibility' => __('labels.hb_tab_visibility'),
            'sectionEnabled' => __('labels.hb_section_enabled'),
            'noSettings' => __('labels.hb_no_settings'),
            'enabled' => __('labels.hb_enabled'),
            'disabled' => __('labels.hb_disabled'),
        ];
    }

    public function show(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HomeLayout::class);

        $request->validate([
            'platform' => ['nullable', new Enum(HomePagePlatformEnum::class)],
            'scope_type' => ['nullable', new Enum(HomePageScopeEnum::class)],
            'scope_id' => ['nullable', 'integer'],
            'paginate_sections' => ['nullable', 'boolean'],
            'include_manifest' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        if ($request->boolean('paginate_sections')) {
            $result = $this->service->loadPaginatedForScope(
                $request->input('scope_type', HomePageScopeEnum::GLOBAL()),
                $request->filled('scope_id') ? (int) $request->input('scope_id') : null,
                $request->input('platform', HomePagePlatformEnum::APP()),
                (int) $request->input('page', 1),
                (int) $request->input('per_page', 6),
                $request->boolean('include_manifest', true),
            );

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.home_layout_fetched_successfully',
                data: (new HomeBuilderPaginatedLayoutResource(
                    $result['layout'],
                    $result['manifest'],
                    $result['sections'],
                ))->resolve($request),
            );
        }

        $layout = $this->service->loadForScope(
            $request->input('scope_type', HomePageScopeEnum::GLOBAL()),
            $request->filled('scope_id') ? (int) $request->input('scope_id') : null,
            $request->input('platform', HomePagePlatformEnum::APP()),
        );

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: (new HomeBuilderLayoutResource($layout))->resolve($request),
        );
    }

    /**
     * Resolve products for the unsaved builder state. With a market this uses
     * storefront market visibility; without one it still applies the section's
     * selected source, categories, custom products, and limit.
     */
    public function previewCatalog(Request $request): JsonResponse
    {
        $this->authorize('viewAny', HomeLayout::class);

        $validated = $request->validate([
            'market_id' => ['nullable', 'integer', 'exists:markets,id'],
            'sections' => ['nullable', 'array', 'max:100'],
            'sections.*.key' => ['required', 'string', 'max:120'],
            'sections.*.source' => ['required', Rule::in(['newly_added', 'top_rated', 'best_seller', 'featured', 'recommended', 'custom'])],
            'sections.*.limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sections.*.category_ids' => ['nullable', 'array'],
            'sections.*.category_ids.*' => ['integer'],
            'sections.*.product_ids' => ['nullable', 'array'],
            'sections.*.product_ids.*' => ['integer'],
        ]);

        $market = ! empty($validated['market_id'])
            ? Market::query()->findOrFail($validated['market_id'])
            : null;
        $storeIds = Product::getStoreIdsForMarket($market);
        $sectionProducts = [];

        foreach ($validated['sections'] ?? [] as $section) {
            $sectionProducts[$section['key']] = $this->marketPreviewProducts($market, $section, $storeIds);
        }

        $previewProducts = new EloquentCollection(
            collect($sectionProducts)->flatten(1)->unique('id')->values()->all(),
        );
        $previewProducts->load([
            'media',
            'variants' => fn ($query) => $query->whereHas(
                'storeProductVariants',
                fn ($storeQuery) => $storeQuery->whereIn('store_id', $storeIds),
            ),
            'variants.locationInventories',
            'taxClasses.taxRates',
            'brand',
            'category',
            'reviews',
            'badge',
        ]);
        $previewProducts->each(function (Product $product): void {
            $product->variants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $product));
        });
        $previewProductsById = $previewProducts->keyBy('id');
        $resolved = collect($sectionProducts)->map(
            fn ($products) => $this->productPreviewItems(
                $products->map(fn (Product $product) => $previewProductsById->get($product->id)),
                $market,
            ),
        )->all();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: [
                'market' => $market ? [
                    'id' => (int) $market->id,
                    'code' => $market->code,
                    'name' => $market->name,
                ] : null,
                'section_products' => $resolved,
            ],
        );
    }

    public function save(SaveHomeLayoutRequest $request): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        $layout = $this->service->save($request->validated());
        $this->attachMedia($request, $layout);
        HomeCache::flushLayout();
        $layout = $this->service->loadForScope($layout->scope_type, $layout->scope_id, $layout->platform);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_saved_successfully',
            data: (new HomeBuilderLayoutResource($layout))->resolve($request),
        );
    }

    /**
     * Attach images uploaded alongside the save payload. Files are keyed by
     * section index (media[i][collection]) and item index (item_media[i][j]),
     * matching the ordered sections/items the service just persisted.
     */
    private function attachMedia(Request $request, HomeLayout $layout): void
    {
        $sections = $layout->sections->values();

        foreach ($sections as $i => $section) {
            $sectionData = $request->input("sections.$i");
            if (! $sectionData) {
                continue;
            }

            // Section level media
            foreach (self::SECTION_MEDIA as $kind => $collection) {
                $file = $request->file("media.$i.$kind");
                if ($file) {
                    $section->clearMediaCollection($collection->value);
                    $section->addMedia($file)->toMediaCollection($collection->value);
                } else {
                    $field = $kind === 'hero_media' ? 'hero_media' : ($kind === 'background' ? 'background_image' : 'card_background_image');
                    $url = $sectionData[$field] ?? null;
                    if ($url && is_string($url)) {
                        $this->copyMediaFromUrl($url, $section, $collection->value);
                    }
                }
            }

            $this->attachRowBackgrounds($request, $section, $i);

            // Item level media
            $itemsData = $sectionData['items'] ?? [];
            $sectionItems = $section->items->values();
            foreach ($sectionItems as $j => $item) {
                $itemData = $itemsData[$j] ?? null;
                if (! $itemData) {
                    continue;
                }

                // Desktop image
                $file = $request->file("item_media.$i.$j");
                if ($request->boolean("remove_item_media.$i.$j")) {
                    $item->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE());
                } elseif ($file) {
                    $item->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE());
                    $item->addMedia($file)->toMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE());
                } else {
                    $url = $itemData['image'] ?? null;
                    if ($url && is_string($url)) {
                        $this->copyMediaFromUrl($url, $item, SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE());
                    }
                }

                // Mobile image
                $file = $request->file("item_mobile_media.$i.$j");
                if ($file) {
                    $item->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_MOBILE_IMAGE());
                    $item->addMedia($file)->toMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_MOBILE_IMAGE());
                } else {
                    $url = $itemData['mobile_image'] ?? null;
                    if ($url && is_string($url)) {
                        $this->copyMediaFromUrl($url, $item, SpatieMediaCollectionName::HOME_SECTION_ITEM_MOBILE_IMAGE());
                    }
                }
            }
        }
    }

    private function attachRowBackgrounds(Request $request, HomeSection $section, int $sectionIndex): void
    {
        if ($section->type !== HomeSectionTypeEnum::PLAY_IMAGE()) {
            return;
        }

        $collection = SpatieMediaCollectionName::HOME_SECTION_ROW_BACKGROUND();
        $config = $section->config ?? [];
        $rows = array_values((array) ($config['rows'] ?? []));
        $activeKeys = [];

        foreach ($rows as $rowIndex => &$row) {
            $key = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($row['media_key'] ?? ''));
            if ($key === '') {
                $key = 'row_'.$section->id.'_'.$rowIndex;
            }

            $row['media_key'] = $key;
            $activeKeys[] = $key;
            $media = $section->getMedia($collection)->first(
                fn ($item) => $item->getCustomProperty('row_key') === $key
            );
            $file = $request->file("row_media.$sectionIndex.$rowIndex");

            if (($row['background_type'] ?? 'none') !== 'image') {
                $media?->delete();
                $row['background_image'] = null;

                continue;
            }

            if ($file) {
                $media?->delete();
                $media = $section->addMedia($file)
                    ->withCustomProperties(['row_key' => $key])
                    ->toMediaCollection($collection);
            }

            $row['background_image'] = $media?->getFullUrl() ?: ($row['background_image'] ?? null);
        }
        unset($row);

        $section->getMedia($collection)
            ->reject(fn ($media) => in_array($media->getCustomProperty('row_key'), $activeKeys, true))
            ->each->delete();

        $config['rows'] = $rows;
        $section->forceFill(['config' => $config])->save();
    }

    private function copyMediaFromUrl(string $url, $targetModel, string $collectionName): void
    {
        if (preg_match('/\/storage\/(?:media\/)?(\d+)\//', $url, $matches)) {
            $mediaId = (int) $matches[1];
            $media = Media::find($mediaId);
            if ($media && ($media->model_type !== get_class($targetModel) || $media->model_id !== $targetModel->id)) {
                $targetModel->clearMediaCollection($collectionName);
                $targetModel->addMedia($media->getPath())
                    ->preservingOriginal()
                    ->toMediaCollection($collectionName);
            }
        }
    }

    public function uploadSectionMedia(Request $request, HomeSection $section): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        $validated = $request->validate([
            'collection' => ['required', Rule::in(array_keys(self::SECTION_MEDIA))],
            'file' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $collection = self::SECTION_MEDIA[$validated['collection']];
        $section->clearMediaCollection($collection);
        $section->addMediaFromRequest('file')->toMediaCollection($collection);

        HomeCache::flushLayout();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.media_uploaded_successfully',
            data: ['url' => $section->refresh()->getFirstMediaUrl($collection) ?: null],
        );
    }

    public function deleteSectionMedia(Request $request, HomeSection $section): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        $validated = $request->validate([
            'collection' => ['required', Rule::in(array_keys(self::SECTION_MEDIA))],
        ]);

        $section->clearMediaCollection(self::SECTION_MEDIA[$validated['collection']]);

        HomeCache::flushLayout();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.media_deleted_successfully',
        );
    }

    public function uploadItemImage(Request $request, HomeSectionItem $item): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120']]);

        $collection = SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE;
        $item->clearMediaCollection($collection);
        $item->addMediaFromRequest('file')->toMediaCollection($collection);

        HomeCache::flushLayout();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.media_uploaded_successfully',
            data: ['url' => $item->refresh()->image],
        );
    }

    public function deleteItemImage(HomeSectionItem $item): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        $item->clearMediaCollection(SpatieMediaCollectionName::HOME_SECTION_ITEM_IMAGE);

        HomeCache::flushLayout();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.media_deleted_successfully',
        );
    }

    /**
     * Load the global ("All") home appearance from the home_general_settings
     * keys. Returns media as full URLs (via the setting resource), matching what
     * the storefront/API already reads — no schema change.
     */
    public function globalAppearanceShow(SettingService $settingService): JsonResponse
    {
        $this->authorize('viewAny', HomeLayout::class);

        $resource = $settingService->getSettingByVariable(SettingTypeEnum::HOME_GENERAL_SETTINGS());
        $value = $resource ? ($resource->toArray(request())['value'] ?? []) : [];

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: $value,
        );
    }

    /**
     * Save the global ("All") home appearance back to the same
     * home_general_settings keys. Existing media paths are preserved unless a new
     * file is uploaded or an explicit remove flag is sent.
     */
    public function globalAppearanceSave(Request $request, SettingService $settingService): JsonResponse
    {
        $this->authorize('update', HomeLayout::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'searchLabels' => ['nullable', 'array'],
            'searchLabels.*' => ['nullable', 'string'],
            'home_appearance' => ['required', 'array'],
            'home_appearance.app' => ['required', 'array'],
            'home_appearance.desktop' => ['required', 'array'],
            'home_appearance.*.background_type' => ['required', Rule::in(['none', 'gradient', 'image', 'lottie'])],
            'home_appearance.*.gradient_start' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'home_appearance.*.gradient_end' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'home_appearance.*.gradient_angle' => ['nullable', 'integer', 'min:0', 'max:360'],
            'home_appearance.*.font_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'home_appearance.*.active_font_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'home_appearance.*.background_media_mobile' => ['nullable', 'array:media_type,extension,custom_extension,aspect_ratio,aspect_width,aspect_width_unit,aspect_height,aspect_height_unit,alt,object_fit,object_position'],
            'home_appearance.*.background_media_tablet' => ['nullable', 'array:media_type,extension,custom_extension,aspect_ratio,aspect_width,aspect_width_unit,aspect_height,aspect_height_unit,alt,object_fit,object_position'],
            'home_appearance.*.background_media_desktop' => ['nullable', 'array:media_type,extension,custom_extension,aspect_ratio,aspect_width,aspect_width_unit,aspect_height,aspect_height_unit,alt,object_fit,object_position'],
            'home_appearance.*.*.media_type' => ['nullable', Rule::in(['image', 'lottie'])],
            'home_appearance.*.*.extension' => ['nullable', Rule::in(['jpg', 'jpeg', 'png', 'webp', 'json'])],
            'home_appearance.*.*.custom_extension' => ['nullable', 'string', 'max:20', 'regex:/^[a-z0-9]*$/'],
            'home_appearance.*.*.aspect_ratio' => ['nullable', Rule::in(['original', '1:1', '16:9', '4:3', '3:2', '2:3', 'custom'])],
            'home_appearance.*.*.aspect_width' => ['nullable', 'numeric', 'gt:0', 'max:10000'],
            'home_appearance.*.*.aspect_height' => ['nullable', 'numeric', 'gt:0', 'max:10000'],
            'home_appearance.*.*.aspect_width_unit' => ['nullable', Rule::in(['px', '%', 'rem', 'em', 'vw', 'vh'])],
            'home_appearance.*.*.aspect_height_unit' => ['nullable', Rule::in(['px', '%', 'rem', 'em', 'vw', 'vh'])],
            'home_appearance.*.*.alt' => ['nullable', 'string', 'max:255'],
            'home_appearance.*.*.object_fit' => ['nullable', Rule::in(['cover', 'contain', 'fill', 'scale-down', 'none'])],
            'home_appearance.*.*.object_position' => ['nullable', Rule::in(['center', 'top', 'right', 'bottom', 'left'])],
            'app_icon' => ['nullable', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'app_active_icon' => ['nullable', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'app_background_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,json', 'max:5120'],
            'app_tablet_background_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,json', 'max:5120'],
            'desktop_icon' => ['nullable', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'desktop_active_icon' => ['nullable', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'desktop_background_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,json', 'max:5120'],
            'desktop_tablet_background_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,json', 'max:5120'],
            'desktop_mobile_background_image' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,json', 'max:5120'],
            'copy_media' => ['nullable', 'array'],
            'copy_media.*' => ['string', Rule::in([
                'app_icon',
                'app_active_icon',
                'app_background_image',
                'app_tablet_background_image',
                'desktop_icon',
                'desktop_active_icon',
                'desktop_background_image',
                'desktop_tablet_background_image',
                'desktop_mobile_background_image',
            ])],
        ]);

        $existing = $settingService->getSettingValues(SettingTypeEnum::HOME_GENERAL_SETTINGS());

        $appearance = $validated['home_appearance'];
        $appAppearance = $appearance['app'];
        $appBackgroundType = $appAppearance['background_type'];

        $value = [
            'title' => $validated['title'],
            'searchLabels' => array_values(array_filter((array) $request->input('searchLabels', []), fn ($l) => is_string($l) && trim($l) !== '')),
            'backgroundType' => $appBackgroundType === 'gradient' ? 'color' : ($appBackgroundType === 'image' ? 'image' : ''),
            'backgroundColor' => $appAppearance['gradient_start'] ?? '#ffffff',
            'fontColor' => $appAppearance['font_color'],
            'homeAppearance' => $appearance,
            'icon' => $existing['icon'] ?? '',
            'activeIcon' => $existing['activeIcon'] ?? '',
            'backgroundImage' => $existing['backgroundImage'] ?? '',
            'appTabletBackgroundImage' => $existing['appTabletBackgroundImage'] ?? '',
            'desktopIcon' => $existing['desktopIcon'] ?? ($existing['icon'] ?? ''),
            'desktopActiveIcon' => $existing['desktopActiveIcon'] ?? ($existing['activeIcon'] ?? ''),
            'desktopBackgroundImage' => $existing['desktopBackgroundImage'] ?? '',
            'desktopTabletBackgroundImage' => $existing['desktopTabletBackgroundImage'] ?? '',
            'desktopMobileBackgroundImage' => $existing['desktopMobileBackgroundImage'] ?? '',
        ];

        $mediaFields = [
            'app_icon' => 'icon',
            'app_active_icon' => 'activeIcon',
            'app_background_image' => 'backgroundImage',
            'app_tablet_background_image' => 'appTabletBackgroundImage',
            'desktop_icon' => 'desktopIcon',
            'desktop_active_icon' => 'desktopActiveIcon',
            'desktop_background_image' => 'desktopBackgroundImage',
            'desktop_tablet_background_image' => 'desktopTabletBackgroundImage',
            'desktop_mobile_background_image' => 'desktopMobileBackgroundImage',
        ];
        $this->ensureGlobalBackgroundMedia($request, $appearance, $existing, $mediaFields, $validated['copy_media'] ?? []);
        foreach ($mediaFields as $requestField => $valueField) {
            if ($request->hasFile($requestField)) {
                $file = $request->file($requestField);
                $value[$valueField] = $file->storeAs('settings', uniqid().'-'.$file->getClientOriginalName(), 'public');
            } elseif ($request->boolean('remove_'.$requestField)) {
                $value[$valueField] = '';
            }
        }

        foreach ($validated['copy_media'] ?? [] as $target => $source) {
            if ($target === $source || ! isset($mediaFields[$target], $mediaFields[$source])) {
                continue;
            }
            $value[$mediaFields[$target]] = $value[$mediaFields[$source]] ?? '';
        }

        $settingService->saveSetting(SettingTypeEnum::HOME_GENERAL_SETTINGS(), $value);

        $resource = $settingService->getSettingByVariable(SettingTypeEnum::HOME_GENERAL_SETTINGS());

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.updated_successfully'),
            data: $resource ? ($resource->toArray(request())['value'] ?? []) : [],
        );
    }

    private function ensureGlobalBackgroundMedia(
        Request $request,
        array $appearance,
        array $existing,
        array $mediaFields,
        array $copies,
    ): void {
        $errors = [];
        if (in_array($appearance['app']['background_type'], ['image', 'lottie'], true)
            && ! $this->globalMediaWillExist($request, 'app_background_image', $existing, $mediaFields, $copies)) {
            $errors['app_background_image'] = 'The app mobile background is required for an image or Lottie header.';
        }
        if (in_array($appearance['desktop']['background_type'], ['image', 'lottie'], true)
            && ! $this->globalMediaWillExist($request, 'desktop_background_image', $existing, $mediaFields, $copies)) {
            $errors['desktop_background_image'] = 'The web desktop background is required for an image or Lottie header.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function globalMediaWillExist(
        Request $request,
        string $field,
        array $existing,
        array $mediaFields,
        array $copies,
        array $visited = [],
    ): bool {
        if (in_array($field, $visited, true) || ! isset($mediaFields[$field])) {
            return false;
        }
        if ($request->hasFile($field)) {
            return true;
        }
        if (isset($copies[$field])) {
            return $this->globalMediaWillExist(
                $request,
                $copies[$field],
                $existing,
                $mediaFields,
                $copies,
                [...$visited, $field],
            );
        }
        if ($request->boolean('remove_'.$field)) {
            return false;
        }

        return filled($existing[$mediaFields[$field]] ?? null);
    }

    private function marketPreviewProducts(?Market $market, array $section, array $storeIds)
    {
        $source = $section['source'];
        $ids = collect($section['product_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();

        if ($source === 'custom') {
            if (empty($ids)) {
                return collect();
            }

            $query = Product::scopeForMarket(
                $market,
                Product::query()->whereIn('products.id', $ids),
                ['store_ids' => $storeIds],
            );
            $products = $query->setEagerLoads([])->get();
            $order = array_flip($ids);

            return $products->sortBy(fn (Product $product) => $order[$product->id] ?? PHP_INT_MAX)->values();
        }

        $sortMap = [
            'newly_added' => 'relevance',
            'top_rated' => 'avg_rated',
            'best_seller' => 'best_seller',
            'featured' => 'featured',
            'recommended' => 'has_badge',
        ];
        $filter = [
            'sort' => $sortMap[$source] ?? 'relevance',
            'store_ids' => $storeIds,
        ];
        $categoryIds = collect($section['category_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($categoryIds->isNotEmpty()) {
            $filter['categories'] = Category::query()->whereKey($categoryIds)->pluck('slug')->all();
        }

        $query = Product::scopeForMarket($market, Product::query(), $filter)->setEagerLoads([]);
        if ($source === 'newly_added') {
            $query->reorder()->latest('products.id');
        }

        return $query->limit((int) ($section['limit'] ?? 12))->get();
    }

    private function productPreviewMeta(Product $product, ?Market $market = null): array
    {
        $variant = $product->variants->firstWhere('is_default', true) ?? $product->variants->first();
        $price = (float) ($variant?->price ?? 0);
        $specialPrice = (float) ($variant?->special_price ?? 0);
        $hasDiscount = $specialPrice > 0 && $specialPrice < $price;
        $reviews = $product->reviews;

        $presentedPrice = $market ? PricePresenter::forMarket($price, $market) : null;
        $presentedSpecialPrice = $market && $hasDiscount ? PricePresenter::forMarket($specialPrice, $market) : null;

        return [
            'brand' => $product->brand?->title,
            'category' => $product->category?->title,
            'short_description' => trim(preg_replace('/\s+/', ' ', strip_tags((string) $product->short_description))),
            'detail_tags' => collect($product->tags)->filter(fn ($tag) => is_string($tag) && trim($tag) !== '')->take(3)->values()->all(),
            'rating' => $reviews->isNotEmpty() ? round((float) $reviews->avg('rating'), 1) : null,
            'rating_count' => $reviews->count(),
            'is_out_of_stock' => $this->productIsOutOfStock($variant),
            'price' => $price,
            'special_price' => $specialPrice,
            'formatted_price' => $presentedPrice['formatted'] ?? app(CurrencyService::class)->format($price),
            'formatted_special_price' => $hasDiscount ? ($presentedSpecialPrice['formatted'] ?? app(CurrencyService::class)->format($specialPrice)) : null,
            'discount_percent' => $hasDiscount && $price > 0 ? (int) round((($price - $specialPrice) / $price) * 100) : 0,
            'image_fit' => $product->image_fit ?: 'contain',
            'badge' => $product->badge ? [
                'label' => $product->badge->label,
                'bg_color' => $product->badge->bg_color,
                'text_color' => $product->badge->text_color,
                'border_color' => $product->badge->border_color,
            ] : null,
        ];
    }

    private function productPreviewItems(iterable $products, ?Market $market = null): array
    {
        return collect($products)->map(fn (Product $product) => [
            'purpose' => 'display',
            'itemable_type' => 'product',
            'itemable_id' => $product->id,
            'itemable_label' => $product->title,
            'itemable_image' => $product->main_image,
            'itemable_meta' => $this->productPreviewMeta($product, $market),
        ])->values()->all();
    }

    private function productIsOutOfStock(?ProductVariant $variant): bool
    {
        if (! $variant) {
            return true;
        }

        if ($variant->availability === false) {
            return true;
        }

        // Only report out-of-stock when inventory data proves it. Missing or
        // untracked inventory means the item remains available, matching the storefront.
        if (! $variant->relationLoaded('locationInventories')) {
            return false;
        }

        $inventory = $variant->locationInventories
            ->where('is_fulfillment_location', true);

        if ($inventory->isEmpty() || $inventory->contains(fn ($row) => ! $row->tracked)) {
            return false;
        }

        return $inventory->sum(fn ($row) => max(0, (int) $row->stock - (int) $row->reserved)) <= 0;
    }
}
