<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\Cart\AddToCartRequest;
use App\Http\Requests\User\Cart\CartSyncRequest;
use App\Http\Requests\User\Cart\UpdateCartItemQuantityRequest;
use App\Http\Resources\User\CartResource;
use App\Models\Address;
use App\Services\CartService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#[Group('Cart')]
class CartApiController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Add item to cart
     */
    public function addToCart(AddToCartRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->cartService->addToCart($user, $request->validated());

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] ? new CartResource($result['data']) : $result['data']
        );
    }

    public function buyNow(AddToCartRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->cartService->buyNow($user, $request->validated());

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] ? new CartResource($result['data']) : $result['data']
        );
    }

    /**
     * Sync cart items from multiple stores
     */

    public function syncCart(CartSyncRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->cartService->syncMultiStoreCart($user, $request->validated());

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success']
                ? [
                'cart' => new CartResource($result['data']['cart']),
                'synced_items' => $result['data']['synced_items'],
                'failed_items' => $result['data']['failed_items'],
            ]
                : []
        );
    }
    /**
     * Get user's cart
     *
     * Uses the user's most recent address for location data if available
     */
    #[QueryParameter('address_id', description: 'Address Id.', type: 'integer', example: 1)]
    #[QueryParameter('use_wallet', description: 'Whether to use wallet balance for payment.', type: 'boolean', example: true)]
    #[QueryParameter('promo_code', description: 'Promo code to apply discount.', type: 'string', example: 'SAVE20')]
    #[QueryParameter('payment_type', description: 'select the payment type', type: 'string', example: 'cod')]
    public function getCart(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $addressId = $request->input('address_id');

        if (!empty($addressId)) {
            $address = Address::where(['user_id' => $user->id, 'id' => $addressId])->first();
            if (empty($address) && $addressId != 0) {
                return ApiResponseType::sendJsonResponse(success: false, message: __('labels.address_not_found'), data: []);
            }
        }

        $useWallet = filter_var($request->input('use_wallet', false), FILTER_VALIDATE_BOOLEAN);

        $promoCode = $request->input('promo_code');

        $paymentType = $request->input('payment_type');

        $result = $this->cartService->getCart(
            user: $user,
            addressId: $addressId ? (int)$addressId : null,
            useWallet: $useWallet,
            promoCode: $promoCode,
            paymentType: $paymentType
        );

        return ApiResponseType::sendJsonResponse(
            success: $result['success'],
            message: $result['message'],
            data: $result['success'] && $result['data'] ? new CartResource($result['data']) : []
        );
    }

    /**
     * Remove item from cart
     */
    public function removeFromCart(int $cartItemId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->cartService->removeFromCart($user, $cartItemId);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] && $result['data'] ? new CartResource($result['data']) : []
        );
    }

    /**
     * Get items saved for later
     */
    #[QueryParameter('latitude', description: 'User latitude for location-based filtering.', type: 'float', example: '23.23232801')]
    #[QueryParameter('longitude', description: 'User longitude for location-based filtering.', type: 'float', example: '69.64359362')]
    public function getSaveForLaterItems(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $saveForLaterItems = $this->cartService->getSaveForLaterCart($user);
        $saveForLaterItems->user_latitude = $request->input('latitude');
        $saveForLaterItems->user_longitude = $request->input('longitude');
        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('labels.save_for_later_items_fetched_successfully'),
            data: new CartResource($saveForLaterItems) ?? []
        );
    }

    /**
     * Save cart item for later
     */
    public function saveForLater(int $cartItemId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->cartService->addToSaveForLater($user, $cartItemId);
        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] && $result['data'] ? new CartResource($result['data']) : []
        );
    }

    /**
     * Update cart item quantity
     */
    public function updateCartItemQuantity(UpdateCartItemQuantityRequest $request, int $cartItemId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $validated = $request->validated();
        $addons = array_key_exists('addons', $validated) ? $validated['addons'] : null;
        $result = $this->cartService->updateCartItemQuantity(
            user: $user,
            cartItemId: $cartItemId,
            quantity: (int) $validated['quantity'],
            addons: $addons,
        );

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] && $result['data'] ? new CartResource($result['data']) : $result['data']
        );
    }

    /**
     * Clear entire cart
     */
    public function clearCart(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->cartService->clearCart($user);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data']
        );
    }

}
