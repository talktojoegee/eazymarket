<?php

namespace App\Http\Controllers\Api;

use App\Enums\HomePageScopeEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\HomePage\HomeLayoutResource;
use App\Http\Resources\HomePage\HomeSectionPageResource;
use App\Models\HomeLayout;
use App\Models\HomeNavbarItem;
use App\Models\HomeSection;
use App\Services\HomeLayoutResolver;
use App\Support\HomeCache;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

#[Group('Home page')]
class HomeLayoutApiController extends Controller
{
    public function __construct(private readonly HomeLayoutResolver $resolver) {}

    /**
     * Resolved home layout for the current market.
     *
     * Global by default; pass ?navbar_slug=… for a custom navbar homepage.
     * Returns ordered, active, market-visible sections with their content
     * materialised (products, categories, brands, hero/banner items).
     */
    #[QueryParameter('platform', description: 'Storefront platform (app | web)', type: 'string', example: 'app')]
    #[QueryParameter('navbar_slug', description: 'Custom home navbar item slug', type: 'string', example: 'rain')]
    #[QueryParameter('page', description: 'Sections page number', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Sections per page (max 100)', type: 'integer', example: 5)]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'sometimes|in:app,web',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'navbar_slug' => 'sometimes|nullable|string|max:191',
        ]);

        $market = $request->attributes->get('market');
        $platform = (string) $request->input('platform', 'app');
        $perPage = (int) $request->input('per_page', 5);
        $page = (int) $request->input('page', 1);

        $scopeType = HomePageScopeEnum::GLOBAL();
        $scopeId = null;

        $navbarItem = null;
        if ($slug = $request->query('navbar_slug')) {
            $navbarItem = HomeNavbarItem::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->forPlatform($platform)
                ->first();
            if ($navbarItem) {
                $scopeType = HomePageScopeEnum::NAVBAR();
                $scopeId = $navbarItem->id;
            } else {
                return $this->emptyLayoutResponse($request, HomePageScopeEnum::NAVBAR(), null, $page, $perPage, $slug);
            }
        }

        $cacheKey = HomeCache::layoutKey($platform, (string) $scopeType, $scopeId, $market?->id, $page, $perPage);

        $structure = Cache::remember($cacheKey, HomeCache::TTL, function () use ($platform, $scopeType, $scopeId, $market, $perPage, $page) {
            $layout = HomeLayout::query()
                ->where('platform', $platform)
                ->where('scope_type', $scopeType)
                ->when(is_null($scopeId), fn ($q) => $q->whereNull('scope_id'), fn ($q) => $q->where('scope_id', $scopeId))
                ->where('is_active', true)
                ->with([
                    'sections' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                    'sections.media',
                    'sections.items' => fn ($q) => $q->orderBy('sort_order'),
                    'sections.items.itemable',
                    'sections.items.media',
                    'sections.markets:id',
                    'sections.marketOrders',
                ])
                ->first();

            return $layout
                ? $this->resolver->structure($layout, $market, $perPage, $page)
                : ['scope_type' => $scopeType, 'scope_id' => $scopeId, 'current_page' => $page, 'last_page' => 1, 'per_page' => $perPage, 'total' => 0, 'sections' => []];
        });

        $data = (new HomeLayoutResource(
            $this->resolver->hydrate($structure, $market)
        ))->resolve($request);
        if ($navbarItem) {
            $data['navbar_slug'] = $navbarItem->slug;
            $data['navbar_title'] = $navbarItem->title;
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: $data,
        );
    }

    private function emptyLayoutResponse(Request $request, string $scopeType, ?int $scopeId, int $page, int $perPage, string $slug): JsonResponse
    {
        $data = (new HomeLayoutResource([
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
            'current_page' => $page,
            'last_page' => 1,
            'per_page' => $perPage,
            'total' => 0,
            'sections' => [],
        ]))->resolve($request);
        $data['navbar_slug'] = $slug;

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: $data,
        );
    }

    /**
     * Paginated content for a single section ("view all" / load more).
     *
     * Resolves the section's products / categories / brands / items for the
     * current market, paginated by page + per_page.
     */
    #[QueryParameter('page', description: 'Page number', type: 'integer', example: 1)]
    #[QueryParameter('per_page', description: 'Items per page (max 100)', type: 'integer', example: 15)]
    public function section(Request $request, HomeSection $section): JsonResponse
    {
        $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $perPage = (int) $request->input('per_page', 15);
        $page = (int) $request->input('page', 1);

        if (! $section->is_active) {
            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.home_layout_fetched_successfully',
                data: (new HomeSectionPageResource([
                    'current_page' => $page,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0,
                    'data' => [],
                ]))->resolve($request),
            );
        }

        $section->load([
            'items' => fn ($q) => $q->orderBy('sort_order'),
            'items.itemable',
            'items.media',
            'markets:id',
        ]);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.home_layout_fetched_successfully',
            data: (new HomeSectionPageResource(
                $this->resolver->paginateSection($section, $request->attributes->get('market'), $perPage, $page)
            ))->resolve($request),
        );
    }
}
