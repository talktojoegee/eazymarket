<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\UpdateWatchBuyPublicationRequest;
use App\Http\Requests\WatchBuy\WatchBuyReelRequest;
use App\Http\Resources\WatchBuy\WatchBuyEligibleProductResource;
use App\Http\Resources\WatchBuy\WatchBuyReelResource;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Services\WatchBuyService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Seller Watch & Buy')]
class SellerWatchBuyReelApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly WatchBuyService $watchBuy) {}

    /**
     * Get seller Watch & Buy reels.
     */
    #[QueryParameter('status', description: 'Filter by reel status: published, unpublished, or blocked.', type: 'string', example: 'published')]
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', WatchBuyReel::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:published,unpublished,blocked'],
        ]);
        $sellerId = $request->user()->seller()->id;
        $reels = WatchBuyReel::whereHas('profile', fn ($query) => $query->where('seller_id', $sellerId))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->with('profile')
            ->latest()
            ->paginate(min(50, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reels_retrieved', [
            'items' => WatchBuyReelResource::collection($reels->getCollection()),
            'pagination' => ApiResponseType::responseFromPaginator($reels),
        ]);
    }

    /**
     * Create seller Watch & Buy reel.
     */
    public function store(WatchBuyReelRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', WatchBuyReel::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }
        abort_unless($this->watchBuy->enabled(), 404);
        $profile = $this->profile($request);
        $data = [...$request->validated(), 'source' => 'seller'];
        $reel = $this->watchBuy->saveReel(new WatchBuyReel, $profile, $data, $request->file('video'), $request->file('cover'), $request->user()->id);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_created', new WatchBuyReelResource($reel->load('profile')), 201);
    }

    /**
     * Get seller Watch & Buy reel.
     */
    public function show(WatchBuyReel $reel): JsonResponse
    {
        try {
            $this->authorize('view', $reel);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_retrieved', new WatchBuyReelResource($reel->load('profile')));
    }

    /**
     * Update seller Watch & Buy reel.
     */
    public function update(WatchBuyReelRequest $request, WatchBuyReel $reel): JsonResponse
    {
        try {
            $this->authorize('update', $reel);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }
        abort_unless($this->watchBuy->enabled(), 404);
        abort_if($reel->status === 'blocked', 403);
        $data = [...$request->validated(), 'source' => 'seller'];
        $reel = $this->watchBuy->saveReel($reel, $reel->profile, $data, $request->file('video'), $request->file('cover'), $request->user()->id);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_updated', new WatchBuyReelResource($reel->load('profile')));
    }

    /**
     * Delete seller Watch & Buy reel.
     */
    public function destroy(Request $request, WatchBuyReel $reel): JsonResponse
    {
        try {
            $this->authorize('delete', $reel);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }
        abort_unless($this->watchBuy->enabled(), 404);
        abort_if($reel->status === 'blocked', 403);
        abort_if($reel->profile->status === 'suspended', 403);
        $this->watchBuy->deleteReel($reel, $request->user()->id);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_deleted');
    }

    /**
     * Publish or unpublish seller Watch & Buy reel.
     */
    public function publish(UpdateWatchBuyPublicationRequest $request, WatchBuyReel $reel): JsonResponse
    {
        try {
            $this->authorize('publish', $reel);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }
        abort_unless($this->watchBuy->enabled(), 404);
        $published = $request->boolean('published');
        $reel = $this->watchBuy->setPublished($reel, $published, $request->user()->id);

        return ApiResponseType::sendJsonResponse(
            true,
            $published ? 'labels.watch_buy_reel_published' : 'labels.watch_buy_reel_unpublished',
            new WatchBuyReelResource($reel->load('profile')),
        );
    }

    /**
     * Get eligible seller Watch & Buy products.
     */
    #[QueryParameter('cursor', description: 'Opaque cursor returned by the previous response.', type: 'string')]
    #[QueryParameter('per_page', description: 'Number of eligible products per response.', type: 'int', default: 15, example: 15)]
    #[QueryParameter('search', description: 'Search product or variant title.', type: 'string')]
    public function eligibleProducts(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewEligibleProducts', WatchBuyReel::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }
        $validated = $request->validate([
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $variants = $this->watchBuy->eligibleProductVariantsQuery($request->user()->seller()->id)
            ->when($search !== '', fn ($query) => $query->where(function ($match) use ($search) {
                $match->whereHas('product', fn ($product) => $product->where('title', 'like', "%{$search}%"))
                    ->orWhere('title', 'like', "%{$search}%");
            }))
            ->orderByDesc('product_variants.id')
            ->cursorPaginate((int) ($validated['per_page'] ?? 15));

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_eligible_products_retrieved', [
            'items' => WatchBuyEligibleProductResource::collection($variants->getCollection()),
            'pagination' => [
                'per_page' => $variants->perPage(),
                'next_cursor' => $variants->nextCursor()?->encode(),
                'previous_cursor' => $variants->previousCursor()?->encode(),
                'has_more_pages' => $variants->hasMorePages(),
            ],
        ]);
    }

    private function profile(Request $request): WatchBuyProfile
    {
        $profile = WatchBuyProfile::where('seller_id', $request->user()->seller()->id)->first();
        abort_unless($profile, 422, __('labels.watch_buy_profile_required_api'));

        return $profile;
    }
}
