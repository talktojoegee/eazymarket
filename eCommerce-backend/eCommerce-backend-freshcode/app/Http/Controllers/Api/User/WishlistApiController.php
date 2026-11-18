<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Wishlist\CreateWishlistRequest;
use App\Http\Requests\User\Wishlist\ToggleFavoriteRequest;
use App\Http\Resources\User\WishlistItemResource;
use App\Http\Resources\User\WishlistResource;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Group('Wishlist')]
class WishlistApiController extends Controller
{
    /**
     * Get all user wishlists with their items.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'int', default: 15, example: 15)]
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        $wishlists = Wishlist::where('user_id', auth()->id())
            ->with(['items.product', 'items.variant', 'items.store'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $wishlists->getCollection()->transform(fn($wishlist) => new WishlistResource($wishlist));

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.wishlists_fetched_successfully'),
            data: $wishlists
        );
    }

    /**
     * Get all user wishlist titles.
     */
    public function getTitles(): JsonResponse
    {
        $wishlists = Wishlist::where('user_id', auth()->id())
            ->withCount('items')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'title', 'slug', 'created_at'])
            ->map(function ($wishlist) {
                return [
                    'id' => $wishlist->id,
                    'title' => $wishlist->title,
                    'slug' => $wishlist->slug,
                    'items_count' => $wishlist->items_count,
                    'created_at' => $wishlist->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.wishlist_titles_fetched_successfully'),
            data: $wishlists
        );
    }


    /**
     * Get the default "Favorite" wishlist with its items.
     *
     * Powers the simplified single-list favorites screen. The Favorite
     * wishlist is created on demand so the response is never empty-by-absence.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'int', default: 15, example: 15)]
    #[QueryParameter('latitude', description: 'User latitude for location-based filtering.', type: 'float', example: '23.23232801')]
    #[QueryParameter('longitude', description: 'User longitude for location-based filtering.', type: 'float', example: '69.64359362')]
    public function favorite(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        $wishlist = Wishlist::favoriteFor(auth()->id());
        $wishlist->load(['items' => function ($query) use ($perPage) {
            $query->with(['product', 'variant', 'store'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
        }]);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.wishlist_fetched_successfully'),
            data: new WishlistResource($wishlist)
        );
    }

    /**
     * Toggle a product in the default "Favorite" wishlist.
     *
     * Single round-trip for the one-tap heart: adds the item if absent, removes
     * it if present. The Favorite wishlist is created on demand.
     */
    public function toggleFavorite(ToggleFavoriteRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $data = $request->validated();
            $variantId = $data['product_variant_id'] ?? null;

            $wishlist = Wishlist::favoriteFor(auth()->id());

            $existing = WishlistItem::where('wishlist_id', $wishlist->id)
                ->where('product_id', $data['product_id'])
                ->when(
                    $variantId === null,
                    fn($q) => $q->whereNull('product_variant_id'),
                    fn($q) => $q->where('product_variant_id', $variantId)
                )
                ->where('store_id', $data['store_id'])
                ->first();

            if ($existing) {
                $existing->delete();
                $isFavorited = false;
                $itemId = null;
                $message = __('labels.item_removed_from_wishlist');
            } else {
                $item = WishlistItem::create([
                    'wishlist_id' => $wishlist->id,
                    'product_id' => $data['product_id'],
                    'product_variant_id' => $variantId,
                    'store_id' => $data['store_id'],
                ]);
                $isFavorited = true;
                $itemId = $item->id;
                $message = __('labels.item_added_to_wishlist');
            }

            DB::commit();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: $message,
                data: [
                    'is_favorited' => $isFavorited,
                    'item_id' => $itemId,
                    'items_count' => $wishlist->items()->count(),
                ]
            );
        } catch (Exception $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: []
            );
        }
    }

    /**
     * Get a specific wishlist by ID.
     */
    #[QueryParameter('id', description: 'Wishlist ID.', type: 'int', example: 1)]
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'int', default: 15, example: 15)]
    #[QueryParameter('latitude', description: 'User latitude for location-based filtering.', type: 'float', example: '23.23232801')]
    #[QueryParameter('longitude', description: 'User longitude for location-based filtering.', type: 'float', example: '69.64359362')]
    public function show($id, Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);

        $wishlist = Wishlist::where('user_id', auth()->id())
            ->with(['items' => function ($query) use ($perPage) {
                $query->with(['product', 'variant', 'store'])
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage);
            }])
            ->find($id);

        if (!$wishlist) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.wishlist_not_found'),
                data: []
            );
        }
        $wishlist->user_latitude = $request->input('latitude');
        $wishlist->user_longitude = $request->input('longitude');

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.wishlist_fetched_successfully'),
            data: new WishlistResource($wishlist)
        );
    }

    /**
     * Update a wishlist.
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
            ]);

            $wishlist = Wishlist::where('user_id', auth()->id())->find($id);

            if (!$wishlist) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.wishlist_not_found'),
                    data: []
                );
            }

            $wishlist->update([
                'title' => $request->title,
            ]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.wishlist_updated_successfully'),
                data: new WishlistResource($wishlist->load(['items.product', 'items.variant', 'items.store']))
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_error'),
                data: $e->errors()
            );
        }
    }

    /**
     * Create a new wishlist without items.
     */
    public function createWishlist(CreateWishlistRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $validatedData = $request->validated();

            $title = $validatedData['title'];

            $existingWishlist = Wishlist::where(['title'=> $title, 'user_id' => auth()->id()])->first();
            if ($existingWishlist) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.wishlist_already_exists'),
                    data: []
                );
            }

            $wishlist = Wishlist::create([
                'user_id' => auth()->id(),
                'title' => $title,
            ]);

            DB::commit();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.wishlist_created_successfully'),
                data: new WishlistResource($wishlist)
            );

        } catch (ValidationException $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_error'),
                data: $e->errors()
            );
        } catch (Exception $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.something_went_wrong'),
                data: $e
            );
        }
    }

    /**
     * Delete a wishlist.
     */
    public function destroy($id): JsonResponse
    {
        $wishlist = Wishlist::where('user_id', auth()->id())->find($id);

        if (!$wishlist) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.wishlist_not_found'),
                data: []
            );
        }

        if (strtolower($wishlist->slug) === 'favorite') {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.cannot_delete_default_wishlist'),
                data: []
            );
        }

        $wishlist->delete();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.wishlist_deleted_successfully'),
            data: []
        );
    }

    /**
     * Move item to another wishlist.
     */
    public function moveItem(Request $request, $itemId): JsonResponse
    {
        try {
            $request->validate([
                'target_wishlist_id' => 'required|exists:wishlists,id',
            ]);

            $wishlistItem = WishlistItem::whereHas('wishlist', function ($query) {
                $query->where('user_id', auth()->id());
            })->find($itemId);

            if (!$wishlistItem) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.wishlist_item_not_found'),
                    data: []
                );
            }

            $targetWishlist = Wishlist::where('user_id', auth()->id())
                ->find($request->target_wishlist_id);

            if (!$targetWishlist) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.target_wishlist_not_found'),
                    data: []
                );
            }

            $existingItem = WishlistItem::where('wishlist_id', $targetWishlist->id)
                ->where('product_id', $wishlistItem->product_id)
                ->where('product_variant_id', $wishlistItem->product_variant_id)
                ->where('store_id', $wishlistItem->store_id)
                ->first();

            if ($existingItem) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.item_already_in_target_wishlist'),
                    data: []
                );
            }

            $wishlistItem->update(['wishlist_id' => $request->target_wishlist_id]);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('labels.item_moved_successfully'),
                data: new WishlistItemResource($wishlistItem->load(['product', 'variant', 'store', 'wishlist']))
            );
        } catch (ValidationException $e) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.validation_error'),
                data: $e->errors()
            );
        }
    }
}
