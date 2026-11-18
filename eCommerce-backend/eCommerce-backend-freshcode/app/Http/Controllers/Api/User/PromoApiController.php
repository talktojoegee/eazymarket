<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\PromoDiscountTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\OrderPromoLine;
use App\Models\Promo;
use App\Services\CartService;
use App\Support\PricePresenter;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#[Group('Promo')]
class PromoApiController extends Controller
{
    protected CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * Get user available promos
     */
    public function getUserAvailablePromos(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        try {
            $now = now();
            $promos = Promo::where(function ($query) use ($now) {
                $query->where('start_date', '<=', $now)
                    ->orWhereNull('start_date');
            })
                ->where(function ($query) use ($now) {
                    $query->where('end_date', '>=', $now)
                        ->orWhereNull('end_date');
                })
                ->where(function ($query) {
                    $query->whereNull('max_total_usage')
                        ->orWhereRaw('usage_count < max_total_usage');
                })
                ->orderBy('created_at', 'desc')
                ->get();

            $availablePromos = $promos->filter(function ($promo) use ($user) {
                if ($promo->max_usage_per_user) {
                    $userUsageCount = OrderPromoLine::where('promo_id', $promo->id)
                        ->whereHas('order', function ($query) use ($user) {
                            $query->where('user_id', $user->id);
                        })
                        ->count();

                    return $userUsageCount < $promo->max_usage_per_user;
                }
                return true;
            });

            $market = request()?->attributes->get('market');

            $data = $availablePromos->values()->map(function ($promo) use ($market) {
                $arr = $promo->toArray();
                $arr['min_order_total'] = $this->toMarketAmount($promo->min_order_total, $market);
                if ($promo->max_discount_value !== null) {
                    $arr['max_discount_value'] = $this->toMarketAmount($promo->max_discount_value, $market);
                }
                if ($promo->discount_type !== PromoDiscountTypeEnum::PERCENTAGE()) {
                    $arr['discount_amount'] = $this->toMarketAmount($promo->discount_amount, $market);
                }
                $arr['formatted_discount'] = $this->formattedDiscount($promo, $market);
                return $arr;
            })->values();

            return ApiResponseType::sendJsonResponse(
                true,
                __('messages.promos_retrieved_successfully'),
                $data
            );

        } catch (\Exception $e) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.something_went_wrong'),
                []
            );
        }
    }

    private function toMarketAmount($amount, ?Market $market): float
    {
        $base = (float) $amount;
        $shape = $market
            ? PricePresenter::forMarket($base, $market)
            : PricePresenter::forCurrent($base);

        return (float) ($shape['price'] ?? 0.0);
    }

    private function formattedDiscount(Promo $promo, ?Market $market): string
    {
        if ($promo->discount_type === PromoDiscountTypeEnum::PERCENTAGE()) {
            return number_format((float) $promo->discount_amount, 2) . '%';
        }

        $base = (float) $promo->discount_amount;
        $shape = $market
            ? PricePresenter::forMarket($base, $market)
            : PricePresenter::forCurrent($base);

        return (string) ($shape['formatted'] ?? $base);
    }

    /**
     * Validate promo code
     */
    #[QueryParameter('promo_code', description: 'Promo code to validate.', type: 'string', example: 'SAVE20')]
    #[QueryParameter('cart_amount', description: 'Order amount to validate against.', type: 'float', example: 1000.00)]
    #[QueryParameter('delivery_charge', description: 'Delivery Charge amount.', type: 'float', example: 100.00)]
    public function validatePromoCode(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $promoCode = $request->input('promo_code');

        if (empty($promoCode)) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('messages.promo_code_required'),
                []
            );
        }

        $cart = CartService::getUserCart($user);

        if (!$cart) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('messages.cart_amount_required'),
                []
            );
        }

        $summary = $this->cartService->getPaymentSummary($cart);
        $cartAmount = (float) ($summary['items_total'] ?? 0);
        $deliveryCharge = (float) ($summary['total_delivery_charges'] ?? 0);

        if ($cartAmount <= 0) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('messages.cart_amount_required'),
                []
            );
        }

        $result = $this->cartService->validatePromoCode(promoCode: $promoCode, user: $user, cartTotal: $cartAmount, deliveryCharge: $deliveryCharge);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            [
                'promo_code' => $promoCode,
                'discount' => $result['discount'],
                'promo_details' => $result['promo']
            ]
        );
    }
}
