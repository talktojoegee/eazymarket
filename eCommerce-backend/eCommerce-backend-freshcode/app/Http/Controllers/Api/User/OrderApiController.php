<?php

namespace App\Http\Controllers\Api\User;

use App\Enums\DateRangeFilterEnum;
use App\Enums\Order\OrderStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\Order\CreateItemReturnRequest;
use App\Http\Requests\User\Order\CreateOrderRequest;
use App\Http\Resources\User\OrderItemListResource;
use App\Http\Resources\User\OrderPaymentResource;
use App\Http\Resources\User\OrderResource;
use App\Services\CartService;
use App\Services\OrderService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

#[Group('Orders')]
class OrderApiController extends Controller
{
    protected OrderService $orderService;
    protected CartService $cartService;

    protected string $dataFilter;

    public function __construct(OrderService $orderService, CartService $cartService)
    {
        $this->orderService = $orderService;
        $this->cartService = $cartService;
        $this->dataFilter = implode(',', DateRangeFilterEnum::values());
    }

    /**
     * Create a new order
     *
     * Creates a new order from the user's cart with the provided payment and address information.
     */
    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }
        $result = $this->orderService->createOrder($user, $request->validated());

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] ? new OrderResource($result['data']) : $result['data']
        );
    }

    /**
     * Get order details
     *
     * Retrieves the details of a specific order by its slug.
     */
    public function getOrder(string $orderSlug): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->orderService->getOrder($user, $orderSlug);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] ? new OrderResource($result['data']) : $result['data']
        );
    }

    /**
     * Get user's orders
     *
     * Retrieves all orders for the authenticated user.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of Orders Per Page', type: 'int', default: 1, example: 1)]
    #[QueryParameter('date_range', description: 'Filter orders by date range', type: 'string', example: 'last_30_minutes,last_1_hour,last_5_hours,last_1_day,last_7_days,last_30_days,last_365_days')]
    #[QueryParameter('status', description: 'Filter by customer-facing item status (same codes returned as customer_status and listed in settings orderStatusEnum): processing, shipped, delivered, on_hold, return_requested, returned, refunded, cancelled.', type: 'string', example: 'processing,shipped,delivered,on_hold,return_requested,returned,refunded,cancelled')]
    public function getUserOrders(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = $request->input('per_page', 15);
        $dateRange = $request->input('date_range');
        $status = $request->input('status');

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $filters = [
            'date_range' => $dateRange,
            'status' => $status,
        ];
        $result = $this->orderService->getUserOrderItems(
            user: $user,
            perPage: $perPage,
            filters: $filters
        );

        $items = $result['data'];
        $items->getCollection()->transform(fn($item) => new OrderItemListResource($item));

        return ApiResponseType::sendJsonResponse(
            success: $result['success'],
            message: $result['message'],
            data: [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'data' => $items->items(),
            ]
        );
    }

    /**
     * Retry payment for an order.
     */

    public function payOrder(string $orderSlug): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->orderService->retryOrderPayment($user, $orderSlug);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] ? new OrderResource($result['data']) : $result['data']
        );
    }

    public function cancelPayment(string $orderSlug): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->orderService->cancelOrderPayment($user, $orderSlug);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['success'] ? new OrderResource($result['data']) : $result['data']
        );
    }

    public function getOrderTracking($orderSlug): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        if (!$orderSlug) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.order_slug_required'),
                []
            );
        }

        $result = $this->orderService->getOrderTracking(user: $user, orderSlug: $orderSlug);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data']
        );
    }

    /**
     * Cancel an order item
     *
     * Cancels a specific order item if it meets the cancellation criteria.
     */
    public function cancelOrderItem(int $orderItemId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $result = $this->orderService->cancelOrderItem($user, $orderItemId);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data']
        );
    }

    /**
     * Get user's payment transactions
     *
     * Retrieves all payment transactions for the authenticated user.
     */
    #[QueryParameter('page', description: 'Page number for pagination.', type: 'int', default: 1, example: 1)]
    #[QueryParameter('per_page', description: 'Number of Orders Per Page', type: 'int', default: 1, example: 1)]
    #[QueryParameter('payment_status', description: 'Filter by payment status', type: 'string', example: 'completed')]
    #[QueryParameter('search', description: 'Search by transaction reference or other fields', type: 'string', example: 'TX123')]
    public function getTransactions(Request $request): JsonResponse
    {
        $user = Auth::user();
        $perPage = $request->input('per_page', 15);
        $paymentStatus = $request->input('payment_status');
        $search = $request->input('search');

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $query = $user->OrderPaymentTransactions()->latest();

        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('order_id', 'like', "%{$search}%")
                    ->orWhere('payment_method', 'like', "%{$search}%")
                    ->orWhere('payment_status', 'like', "%{$search}%")
                    ->orWhere('transaction_id', 'like', "%{$search}%");
            });
        }

        $transactions = $query->paginate($perPage);

        $transactions->getCollection()->transform(function ($transaction) {
            return new OrderPaymentResource($transaction);
        });

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.transactions_retrieved_successfully'),
            [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'data' => $transactions->items(),
            ]
        );
    }

    /**
     * Get a specific payment transaction
     *
     * Retrieves details of a specific payment transaction by its ID.
     */
    public function getTransaction($id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $transaction = $user->OrderPaymentTransactions()->where('id', $id)->first();

        if (!$transaction) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.transaction_not_found'),
                []
            );
        }

        return ApiResponseType::sendJsonResponse(
            true,
            __('labels.transaction_retrieved_successfully'),
            OrderPaymentResource::make($transaction)
        );
    }

    /**
     * Return Order Item
     *
     * Return an order item if it meets the return criteria.
     */
    public function returnOrderItem(int $orderItemId, CreateItemReturnRequest $request): JsonResponse
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
        $validated['order_item_id'] = $orderItemId;

        $result = $this->orderService->returnOrderItem($user, $validated);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data'] ?? []
        );
    }

    /**
     * Cancel Return Request
     * @param $orderItemId
     * @return JsonResponse
     */

    public function cancelReturnRequest(Request $request, $orderItemId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(
                false,
                __('labels.user_not_authenticated'),
                []
            );
        }

        $validated = $request->validate(['return_id' => 'nullable|integer']);
        $result = $this->orderService->cancelReturnRequest(
            user: $user,
            orderItemId: $orderItemId,
            returnId: isset($validated['return_id']) ? (int) $validated['return_id'] : null,
        );

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data'] ?? []
        );
    }

    /**
     * Reorder: Add all items from a previous order back into the user's cart.
     *
     * Quantity behavior: Ignores any provided quantities and uses each product's
     * quantity_step_size (default 1) when adding to cart.
     */
    public function reorder(int $orderId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return ApiResponseType::sendJsonResponse(false, __('labels.user_not_authenticated'), []);
        }

        $result = $this->cartService->reorderFromOrder($user, $orderId);

        return ApiResponseType::sendJsonResponse(
            $result['success'],
            $result['message'],
            $result['data'] ?? []
        );
    }
}
