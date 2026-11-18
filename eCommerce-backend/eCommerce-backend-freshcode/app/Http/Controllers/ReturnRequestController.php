<?php

namespace App\Http\Controllers;

use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Models\OrderItemReturn;
use App\Models\Seller;
use App\Types\Api\ApiResponseType;
use App\Traits\AppliesDatatableDateRange;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReturnRequestController extends Controller
{
    use AuthorizesRequests, AppliesDatatableDateRange;
    /**
     * Display seller returns list page.
     */
    public function index(): View
    {
        $this->authorize('viewAny', OrderItemReturn::class);

        $columns = [
            ['title' => __('labels.id'), 'data' => 'id'],
            ['title' => __('labels.order_date'), 'data' => 'order_date'],
            ['title' => __('labels.order_details'), 'data' => 'order_details'],
            ['title' => __('labels.return_reason') ?? 'Reason', 'data' => 'reason'],
            ['title' => __('labels.refund_amount'), 'data' => 'refund_amount'],
            ['title' => __('labels.return_status'), 'data' => 'return_status'],
            ['title' => __('labels.actions'), 'data' => 'actions', 'orderable' => false, 'searchable' => false],
        ];

        return view('seller.returns.index', compact('columns'));
    }

    /**
     * Datatable endpoint for seller returns
     */
    public function datatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OrderItemReturn::class);

        $draw = (int)$request->get('draw');
        $start = (int)$request->get('start', 0);
        $length = (int)$request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';

        $columns = ['id', 'created_at', 'order_details', 'reason', 'refund_amount', 'return_status', 'actions'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $seller = $request->user()->seller();
        if (empty($seller)) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('messages.seller_not_found'),
                data: []
            );
        }

        $query = OrderItemReturn::with([
                'order.market',
                'media',
                'orderItem.product',
                'orderItem.variant',
                'orderItem.addons.addonGroup',
                'orderItem.addons.addonItem',
                'store',
                'user',
            ])
            ->where('seller_id', $seller->id)
            ->where('return_status', '!=', OrderItemReturnStatusEnum::CANCELLED());

        $storeId = app(\App\Services\SellerStoreService::class)->selectedId();
        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $returnStatus = $request->get('return_status');
        if ($returnStatus) {
            $query->where('return_status', $returnStatus);
        }

        $totalRecords = $query->count();

        $this->applyDatatableDateRange($query, $request, 'order_item_returns.created_at');

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('reason', 'like', "%{$searchValue}%")
                    ->orWhere('seller_comment', 'like', "%{$searchValue}%")
                    ->orWhereHas('order', fn($qo) => $qo->where('order_id', 'like', "%{$searchValue}%"));
            });
        }

        $filteredRecords = $query->count();

        $results = $query->orderBy($orderColumn === 'order_details' ? 'created_at' : $orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $results->map(fn($ret) => $this->formatReturnRow($ret))->toArray();

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data
        ]);
    }

    /**
     * Approve / Reject action from seller
     */
    public function decision(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'seller_comment' => 'nullable|string|max:1000'
        ]);

        $user = Auth::user();
        $seller = Seller::where('user_id', $user->id)->first();

        if (!$seller) {
            return ApiResponseType::sendJsonResponse(false, __('messages.seller_not_found'), [], 404);
        }

        $orderReturn = OrderItemReturn::where('id', $id)
            ->where('seller_id', $seller->id)
            ->with('orderItem.order')
            ->first();

        if (!$orderReturn) {
            return ApiResponseType::sendJsonResponse(false, __('messages.return_not_found'), [], 404);
        }

        if ($orderReturn->orderItem?->order
            && (string) $orderReturn->orderItem->order->status === (string) \App\Enums\Order\OrderStatusEnum::ARCHIVED()) {
            return ApiResponseType::sendJsonResponse(false, __('labels.order_archived_reopen_first'), [], 422);
        }

        $this->authorize('decide', $orderReturn);

        if (in_array($orderReturn->return_status, [
            OrderItemReturnStatusEnum::APPROVED(),
            OrderItemReturnStatusEnum::DECLINED(),
            OrderItemReturnStatusEnum::CANCELLED(),
            OrderItemReturnStatusEnum::IN_TRANSIT(),
            OrderItemReturnStatusEnum::RECEIVED(),
            OrderItemReturnStatusEnum::REFUNDED(),
            OrderItemReturnStatusEnum::CLOSED()
        ], true)) {
            return ApiResponseType::sendJsonResponse(false, __('messages.return_already_processed'), [], 422);
        }

        $action = $request->post('action');
        $sellerComment = $request->post('seller_comment');

        if (!empty($sellerComment)) {
            $orderReturn->seller_comment = Str::limit($sellerComment, 1000);
            $orderReturn->save();
        }

        $orderService = app(\App\Services\OrderService::class);

        $result = $action === 'approve'
            ? $orderService->approveReturnByAdmin(
                $orderReturn->id,
                (int) $user->id,
                $sellerComment ? ['reason' => $sellerComment] : []
            )
            : $orderService->declineReturnByAdmin(
                $orderReturn->id,
                (int) $user->id,
                $sellerComment ?: null
            );

        if (!($result['success'] ?? false)) {
            return ApiResponseType::sendJsonResponse(false, $result['message'] ?? __('labels.something_went_wrong'), [], 422);
        }

        $message = $action === 'approve'
            ? __('messages.return_approved_successfully')
            : __('messages.return_rejected_successfully');

        return ApiResponseType::sendJsonResponse(true, $message, $this->formatReturnRow($orderReturn->fresh()), 200);
    }

    /**
     * Format a single OrderItemReturn to datatable row (HTML where needed)
     */
    private function formatReturnRow(OrderItemReturn $r): array
    {
        $created = $r->created_at;
        $orderDateHtml = $created
            ? "<div><div class='fw-medium'>{$created->format('M j, Y')}</div><small class='text-muted'>{$created->format('H:i')}</small></div>"
            : '—';

        $productTitle = $r->orderItem && $r->orderItem->product
            ? e($r->orderItem->product->title)
            : __('labels.n_a');

        $variantTitle = $r->orderItem && $r->orderItem->variant ? e($r->orderItem->variant->title) : '';
        $qty = (int) ($r->quantity ?: 1);
        $images = '';
        if (!empty($r->images)) {
            foreach ($r->images as $image) {
                $images .= view('partials.image', ['image' => $image])->render();
            }
        }

        $addonsHtml = $this->renderReturnAddons($r->orderItem);

        $orderDetails = "<div>
                <div class='fw-medium'>{$productTitle} " . ($variantTitle ? "<small class='text-muted'>{$variantTitle}</small>" : "") . "</div>
                <small class='text-muted'>" . __('labels.order') . " #{$r->order_id} · × {$qty}</small>
                {$addonsHtml}
                <div class='mt-2 d-flex align-items-center gap-1'>{$images}</div>
            </div>";

        $reasonLabel = $r->reason_code?->label() ?? ($r->reason ?: '—');
        $reason = '<div>' . e($reasonLabel) . '</div>';

        $refundAmount = '<div class="fw-medium">' . formatOrderMoney($r->refund_amount, $r->order?->currency) . '</div>';

        $statusVal = (string) $r->return_status;
        $statusClass = match ($statusVal) {
            (string) OrderItemReturnStatusEnum::REFUNDED()  => 'bg-green-lt text-green',
            (string) OrderItemReturnStatusEnum::DECLINED()  => 'bg-red-lt text-red',
            (string) OrderItemReturnStatusEnum::APPROVED()  => 'bg-blue-lt text-blue',
            (string) OrderItemReturnStatusEnum::REQUESTED() => 'bg-yellow-lt text-yellow',
            default => 'bg-secondary-lt',
        };
        $returnStatus = "<span class='badge {$statusClass} text-uppercase'>" . ucwords(str_replace('_', ' ', $statusVal)) . "</span>";
        $actions = '';
        if ($r->return_status === (string)OrderItemReturnStatusEnum::REQUESTED()) {
            $actions .= "
        <div class='d-flex me-2 mt-2'>
            <button class='btn btn-success me-2' data-bs-toggle='modal' data-bs-target='#acceptModel' data-id='{$r->id}'>
                " . __('labels.approve') . "
            </button>
            <button class='btn btn-outline-danger' data-bs-toggle='modal' data-bs-target='#rejectModel' data-id='{$r->id}'>
                " . __('labels.reject') . "
            </button>
        </div>
    ";
        }


        return [
            'id' => $r->id,
            'order_date' => $orderDateHtml,
            'order_details' => $orderDetails,
            'reason' => $reason,
            'refund_amount' => $refundAmount,
            'return_status' => $returnStatus,
            'actions' => $actions,
        ];
    }

    /**
     * Build the "Add-ons" block rendered under each product in the returns
     * datatable. Empty string when the order item has no addon snapshot —
     * keeps the row tidy for product-only returns.
     *
     * Falls back to a lazy query when the `addons` relation isn't eager-
     * loaded (e.g. when `formatReturnRow` is called after `decision()` with
     * a freshly loaded model — the decision path does not eager-load).
     */
    private function renderReturnAddons(?\App\Models\OrderItem $orderItem): string
    {
        if (!$orderItem) {
            return '';
        }

        $addons = $orderItem->relationLoaded('addons')
            ? $orderItem->getRelation('addons')
            : $orderItem->addons()->with(['addonGroup', 'addonItem'])->get();

        if (!$addons || $addons->isEmpty()) {
            return '';
        }

        $rows = $addons->map(function ($addon) {
            $groupTitle = $addon->addonGroup?->title;
            $itemTitle  = $addon->addonItem?->title ?? '—';
            $price      = number_format((float) $addon->price, 2);
            $label      = $groupTitle ? e($groupTitle) . ' — ' . e($itemTitle) : e($itemTitle);

            return "<li>{$label} <span class='text-muted'>· {$price}</span></li>";
        })->implode('');

        return "<div class='mt-2'>
                    <span class='fw-medium'>" . __('labels.addons') . ":</span>
                    <ul class='list-unstyled mb-0 ps-3 small'>{$rows}</ul>
                </div>";
    }
}
