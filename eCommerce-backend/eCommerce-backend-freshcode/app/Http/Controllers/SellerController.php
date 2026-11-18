<?php

namespace App\Http\Controllers;

use App\Enums\Seller\SellerSettlementStatusEnum;
use App\Enums\Order\OrderItemStatusEnum;
use App\Models\OrderItem;
use App\Models\Product;
use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Enums\Seller\SellerVisibilityStatusEnum;
use App\Events\Seller\SellerStatusUpdated;
use App\Events\Seller\SellerUpdated;
use App\Http\Requests\Seller\StoreSellerRequest;
use App\Http\Requests\Seller\UpdateSellerRequest;
use App\Models\Seller;
use App\Models\SellerFeedback;
use App\Models\SellerStatement;
use App\Models\SellerOrder;
use App\Models\SellerWithdrawalRequest;
use App\Models\ShippingProfile;
use App\Models\StoreLocation;
use App\Models\OrderItemReturn;
use App\Support\PricePresenter;
use App\Enums\SettingTypeEnum;
use App\Services\SettingService;
use App\Services\DynamicSellerFieldService;
use App\Support\DemoMode;
use App\Services\SellerService;
use App\Traits\ChecksPermissions;
use App\Traits\AppliesDatatableDateRange;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerController extends Controller
{
    use ChecksPermissions, PanelAware, AuthorizesRequests, AppliesDatatableDateRange;

    protected bool $editPermission = false;
    protected bool $deletePermission = false;
    protected bool $createPermission = false;

    protected $sellerService;
    protected SettingService $settingService;

    public function __construct(SellerService $sellerService, SettingService $settingService)
    {
        $this->sellerService = $sellerService;
        $this->settingService = $settingService;
        if ($this->getPanel() === 'admin') {
            $this->editPermission = $this->hasPermission(AdminPermissionEnum::SELLER_EDIT());
            $this->deletePermission = $this->hasPermission(AdminPermissionEnum::SELLER_DELETE());
            $this->createPermission = $this->hasPermission(AdminPermissionEnum::SELLER_CREATE());
        }
    }

    private function isDemoModeEnabled(): bool
    {
        return DemoMode::isDemoUser(auth()->user());
    }

    private function maskMobile(?string $mobile): string
    {
        if (!$mobile) {
            return '';
        }
        $len = strlen($mobile);
        if ($len <= 3) {
            return str_repeat('*', $len);
        }
        $visible = substr($mobile, -3);
        return str_repeat('*', $len - 3) . $visible;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Seller::class);
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'seller', 'name' => 'seller', 'title' => __('labels.seller')],
            ['data' => 'email', 'name' => 'email', 'title' => __('labels.email')],
            ['data' => 'mobile', 'name' => 'mobile', 'title' => __('labels.mobile')],
            ['data' => 'verification_status', 'name' => 'verification_status', 'title' => __('labels.verification_status')],
            ['data' => 'visibility_status', 'name' => 'visibility_status', 'title' => __('labels.visibility_status')],
            ['data' => 'stores', 'name' => 'stores', 'title' => __('labels.stores'), 'orderable' => false],
            ['data' => 'plan', 'name' => 'plan', 'title' => __('labels.plan'), 'orderable' => false],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];
        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;
        $createPermission = $this->createPermission;

        return view($this->panelView('sellers.index'), compact('columns', 'editPermission', 'deletePermission', 'createPermission'));
    }

    /**
     * Show the form for creating a new resource.
     * @throws AuthorizationException
     */
    public function create(): View
    {
        $this->authorize('create', Seller::class);
        $dynamicFieldGroups = collect();
        $dynamicFieldValues = [];
        $canManageFields = $this->hasPermission(AdminPermissionEnum::SELLER_EDIT());
        $countrySelected = false;
        return view($this->panelView('sellers.form'), compact('dynamicFieldGroups', 'dynamicFieldValues', 'canManageFields', 'countrySelected'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSellerRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Seller::class);

            $seller = $this->sellerService->createSeller(
                $request->validated(),
                $request->allFiles()
            );

            return ApiResponseType::sendJsonResponse(
                true,
                'labels.seller_created_successfully',
                $seller,
                201
            );
        } catch (\Exception $e) {
            $statusCode = method_exists($e, 'getCode') && $e->getCode() ? $e->getCode() : 500;
            $statusCode = in_array($statusCode, [404, 422, 500]) ? $statusCode : 500;

            $message = match ($statusCode) {
                404 => 'labels.user_not_found',
                422 => $e->getMessage() === 'Seller already exists for this user'
                    ? 'labels.seller_already_exists'
                    : 'labels.validation_failed',
                default => 'labels.error_occurred',
            };

            return ApiResponseType::sendJsonResponse(
                false,
                $message,
                ['error' => $e->getMessage()],
                $statusCode
            );
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): View
    {
        $seller = Seller::with([
            'user',
            'stores',
            'wallet',
            'countryDetails',
            'activeSubscription.plan',
        ])->find($id);

        if (!$seller) {
            abort(404);
        }

        $this->authorize('viewAny', $seller);
        $dynamicDocumentFields = app(DynamicSellerFieldService::class)->forCountry($seller->countryDetails?->iso2 ?? '')->where('section', 'business_documents');
        $dynamicFieldValues = app(DynamicSellerFieldService::class)->valuesForSeller($seller, $dynamicDocumentFields);

        $orderStats = $this->sellerService->getOrderStats($seller);
        $earningsSummary = $this->sellerService->getEarningsSummary($seller);
        $recentNotifications = $this->sellerService->getRecentNotifications($seller);
        $fcmTokens = $this->sellerService->getFcmTokens($seller);

        $reviewData = SellerFeedback::getSellerFeedbackStatistics($seller->id);

        $profileImageUrl = $seller->user?->getFirstMediaUrl('profile_image')
            ?: asset('assets/images/user-placeholder.png');

        $feedbackColumns = [
            ['data' => 'customer', 'name' => 'customer', 'title' => __('labels.customer'), 'label' => __('labels.customer')],
            ['data' => 'store', 'name' => 'store', 'title' => __('labels.store'), 'label' => __('labels.store')],
            ['data' => 'order', 'name' => 'order', 'title' => __('labels.order'), 'label' => __('labels.order')],
            ['data' => 'rating', 'name' => 'rating', 'title' => __('labels.rating'), 'label' => __('labels.rating')],
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title'), 'label' => __('labels.title')],
            ['data' => 'description', 'name' => 'description', 'title' => __('labels.description'), 'label' => __('labels.description')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $statementColumns = [
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
            ['data' => 'entry_type', 'name' => 'entry_type', 'title' => __('labels.entry_type'), 'label' => __('labels.entry_type')],
            ['data' => 'amount', 'name' => 'amount', 'title' => __('labels.amount'), 'label' => __('labels.amount')],
            ['data' => 'order', 'name' => 'order', 'title' => __('labels.order'), 'label' => __('labels.order')],
            ['data' => 'settlement_status', 'name' => 'settlement_status', 'title' => __('labels.settlement_status'), 'label' => __('labels.settlement_status')],
            ['data' => 'description', 'name' => 'description', 'title' => __('labels.description'), 'label' => __('labels.description')],
        ];

        $notificationColumns = [
            ['data' => 'title', 'name' => 'title', 'title' => __('labels.title'), 'label' => __('labels.title')],
            ['data' => 'message', 'name' => 'message', 'title' => __('labels.message'), 'label' => __('labels.message')],
            ['data' => 'type', 'name' => 'type', 'title' => __('labels.type'), 'label' => __('labels.type')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $productColumns = [
            ['data' => 'product', 'name' => 'product', 'title' => __('labels.product'), 'label' => __('labels.product')],
            ['data' => 'category', 'name' => 'category', 'title' => __('labels.category'), 'label' => __('labels.category')],
            ['data' => 'brand', 'name' => 'brand', 'title' => __('labels.brand'), 'label' => __('labels.brand')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'verification_status', 'name' => 'verification_status', 'title' => __('labels.verification_status'), 'label' => __('labels.verification_status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $locationColumns = [
            ['data' => 'name', 'name' => 'name', 'title' => __('labels.name'), 'label' => __('labels.name')],
            ['data' => 'store', 'name' => 'store', 'title' => __('labels.store'), 'label' => __('labels.store')],
            ['data' => 'address', 'name' => 'address', 'title' => __('labels.address'), 'label' => __('labels.address')],
            ['data' => 'flags', 'name' => 'flags', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $shippingProfileColumns = [
            ['data' => 'name', 'name' => 'name', 'title' => __('labels.name'), 'label' => __('labels.name')],
            ['data' => 'store', 'name' => 'store', 'title' => __('labels.store'), 'label' => __('labels.store')],
            ['data' => 'zones', 'name' => 'zones', 'title' => __('labels.zones'), 'label' => __('labels.zones')],
            ['data' => 'products', 'name' => 'products', 'title' => __('labels.products'), 'label' => __('labels.products')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $sellerOrderColumns = [
            ['data' => 'order', 'name' => 'order', 'title' => __('labels.order'), 'label' => __('labels.order')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'fulfillment_status', 'name' => 'fulfillment_status', 'title' => __('labels.fulfillment_status'), 'label' => __('labels.fulfillment_status')],
            ['data' => 'total', 'name' => 'total', 'title' => __('labels.total'), 'label' => __('labels.total')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $returnColumns = [
            ['data' => 'item', 'name' => 'item', 'title' => __('labels.item'), 'label' => __('labels.item')],
            ['data' => 'quantity', 'name' => 'quantity', 'title' => __('labels.quantity'), 'label' => __('labels.quantity')],
            ['data' => 'reason', 'name' => 'reason', 'title' => __('labels.reason'), 'label' => __('labels.reason')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'refund_amount', 'name' => 'refund_amount', 'title' => __('labels.refund_amount'), 'label' => __('labels.refund_amount')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $withdrawalColumns = [
            ['data' => 'amount', 'name' => 'amount', 'title' => __('labels.amount'), 'label' => __('labels.amount')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status'), 'label' => __('labels.status')],
            ['data' => 'request_note', 'name' => 'request_note', 'title' => __('labels.note'), 'label' => __('labels.note')],
            ['data' => 'admin_remark', 'name' => 'admin_remark', 'title' => __('labels.admin_remark'), 'label' => __('labels.admin_remark')],
            ['data' => 'processed_at', 'name' => 'processed_at', 'title' => __('labels.processed_at'), 'label' => __('labels.processed_at')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.date'), 'label' => __('labels.date')],
        ];

        $storeIds = $seller->stores->pluck('id');
        $sellerCounts = [
            'products' => Product::where('seller_id', $seller->id)->count(),
            'locations' => StoreLocation::whereIn('store_id', $storeIds)->count(),
            'shipping_profiles' => ShippingProfile::whereIn('store_id', $storeIds)->count(),
            'orders' => SellerOrder::where('seller_id', $seller->id)->count(),
            'returns' => OrderItemReturn::where('seller_id', $seller->id)->count(),
            'withdrawals' => SellerWithdrawalRequest::where('seller_id', $seller->id)->count(),
        ];

        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;

        return view('admin.sellers.view', compact(
            'seller',
            'orderStats',
            'earningsSummary',
            'recentNotifications',
            'fcmTokens',
            'reviewData',
            'profileImageUrl',
            'feedbackColumns',
            'statementColumns',
            'notificationColumns',
            'productColumns',
            'locationColumns',
            'shippingProfileColumns',
            'sellerOrderColumns',
            'returnColumns',
            'withdrawalColumns',
            'sellerCounts',
            'editPermission',
            'deletePermission',
            'dynamicDocumentFields',
            'dynamicFieldValues',
        ));
    }

    /**
     * Server-side DataTable for seller wallet/settlement statements.
     */
    public function statementsDatatable(Request $request, string $id): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_VIEW()), 403, trans('labels.permission_denied'));
        try {
            $seller = Seller::findOrFail($id);

            $draw = $request->get('draw');
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = SellerStatement::where('seller_id', $seller->id)
                ->with('order:id');

            $totalRecords = (clone $query)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('entry_type', 'like', "%{$searchValue}%")
                        ->orWhere('settlement_status', 'like', "%{$searchValue}%")
                        ->orWhere('description', 'like', "%{$searchValue}%")
                        ->orWhereHas('order', function ($orderQuery) use ($searchValue) {
                            $orderQuery->where('id', 'like', "%{$searchValue}%");
                        });
                });
            }

            $filteredRecords = $query->count();
            $statements = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $statements->map(function ($statement) {
                $entryTypeBadge = '<span class="badge bg-azure-lt">' . e(ucfirst(str_replace('_', ' ', $statement->entry_type ?? ''))) . '</span>';

                $settlementBadge = $statement->settlement_status === 'settled'
                    ? '<span class="badge bg-success-lt">' . e(__('labels.settled')) . '</span>'
                    : '<span class="badge bg-warning-lt">' . e(__('labels.pending')) . '</span>';

                return [
                    'created_at' => $statement->created_at?->format('d M Y'),
                    'entry_type' => $entryTypeBadge,
                    'amount' =>  getCurrencySymbol() . number_format((float) $statement->amount, 2),
                    'order' => $statement->order ? '#' . $statement->order->id : 'N/A',
                    'settlement_status' => $settlementBadge,
                    'description' => e(Str::limit($statement->description, 50)),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Seller statements datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id): View
    {
        $seller = Seller::with(['user', 'countryDetails'])->find($id);

        if (!$seller) {
            abort(404);
        }
        $this->authorize('update', $seller);
        $dynamicFieldGroups = app(DynamicSellerFieldService::class)->forCountry($seller->countryDetails?->iso2 ?? '')->groupBy('section');
        $dynamicFieldValues = app(DynamicSellerFieldService::class)->valuesForSeller($seller, $dynamicFieldGroups->flatten(1));
        $canManageFields = $this->hasPermission(AdminPermissionEnum::SELLER_EDIT());
        $countrySelected = (bool) $seller->countryDetails?->iso2;
        return view($this->panelView('sellers.form'), compact('seller', 'dynamicFieldGroups', 'dynamicFieldValues', 'canManageFields', 'countrySelected'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSellerRequest $request, string $id): JsonResponse
    {
        DB::beginTransaction();
        try {
            $seller = Seller::find($id);
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', [], 404);
            }
            $this->authorize('update', $seller);
            $request->validated();
            $currentVerificationStatus = $seller->verification_status;
            $user = $seller->user;
            if (!$user->hasRole(DefaultSystemRolesEnum::SELLER())) {
                $user->assignRole('seller');
            }
            $user->update($request->only(['name']));

            $validated = $request->safe()->except(['dynamic_fields']);

            $dynamicValues = $request->input('dynamic_fields', []);
            $dynamicFiles = $request->file('dynamic_fields', []);
            $storedValues = $this->sellerService->storeDynamicFieldImages($seller, $dynamicFiles);
            $validated['dynamic_fields'] = array_merge($storedValues, $dynamicValues);

            $seller->update($validated);

            DB::commit();
            if ($request->verification_status !== $currentVerificationStatus) {
                event(new SellerStatusUpdated($seller, $user));
            }
            event(new SellerUpdated($seller, $user));

            return ApiResponseType::sendJsonResponse(true, 'labels.seller_updated_successfully', $seller);
        } catch (ValidationException $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(false, 'labels.validation_failed', $e->errors(), 422);
        } catch (Exception $e) {
            DB::rollBack();
            return ApiResponseType::sendJsonResponse(false, 'labels.error_occurred', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $seller = Seller::find($id);
            if (!$seller) {
                return ApiResponseType::sendJsonResponse(false, 'labels.seller_not_found', [], 404);
            }
            $this->authorize('delete', $seller);

            $blockers = [];

            if (Product::where('seller_id', $seller->id)->exists()) {
                $blockers[] = __('labels.products');
            }

            $openOrders = OrderItem::whereHas(
                'sellerOrderItem.sellerOrder',
                fn ($q) => $q->where('seller_id', $seller->id)
            )
                ->where('status', OrderItemStatusEnum::ACTIVE())
                ->exists();

            if ($openOrders) {
                $blockers[] = __('labels.orders');
            }

            if ($seller->statements()->where('settlement_status', SellerSettlementStatusEnum::PENDING())->exists()) {
                $blockers[] = __('labels.unsettled_earnings');
            }

            if ((float) ($seller->wallet?->balance ?? 0) > 0) {
                $blockers[] = __('labels.wallet_balance');
            }

            if (!empty($blockers)) {
                return ApiResponseType::sendJsonResponse(
                    false,
                    __('labels.seller_delete_blocked', ['items' => implode(', ', $blockers)]),
                    []
                );
            }

            $seller->delete();
            $seller->media()->each(function ($media) {
                $media->delete();
            });
            return ApiResponseType::sendJsonResponse(true, 'labels.seller_deleted_successfully');
        } catch (Exception $e) {
            return ApiResponseType::sendJsonResponse(false, 'labels.error_occurred', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Server-side DataTable for seller feedback.
     */
    public function feedbackDatatable(Request $request, string $id): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_VIEW()), 403, trans('labels.permission_denied'));
        try {
            $seller = Seller::findOrFail($id);

            $draw = $request->get('draw');
            $start = $request->get('start', 0);
            $length = $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = SellerFeedback::where('seller_id', $seller->id)
                ->with(['user', 'order', 'store']);

            $totalRecords = SellerFeedback::where('seller_id', $seller->id)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('title', 'like', "%{$searchValue}%")
                        ->orWhere('description', 'like', "%{$searchValue}%")
                        ->orWhereHas('user', function ($uq) use ($searchValue) {
                            $uq->where('name', 'like', "%{$searchValue}%")
                                ->orWhere('email', 'like', "%{$searchValue}%")
                                ->orWhere('mobile', 'like', "%{$searchValue}%");
                        });
                });
            }

            $filteredRecords = $query->count();
            $feedbacks = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $feedbacks->map(function ($feedback) {
                $user = $feedback->user;
                $customerHtml = '<div class="d-flex align-items-center">'
                    . '<div>'
                    . '<div class="fw-bold">' . e($user->name ?? 'N/A') . '</div>'
                    . '<div class="text-secondary small">' . e($user->email ?? '') . '</div>'
                    . '<div class="text-secondary small">' . e($user->mobile ?? '') . '</div>'
                    . '</div></div>';

                $stars = '';
                for ($i = 1; $i <= 5; $i++) {
                    $stars .= $i <= $feedback->rating
                        ? '<i class="ti ti-star-filled text-warning"></i>'
                        : '<i class="ti ti-star text-muted"></i>';
                }

                return [
                    'customer' => $customerHtml,
                    'store' => e($feedback->store->name ?? 'N/A'),
                    'order' => $feedback->order ? '#' . $feedback->order->id : 'N/A',
                    'rating' => $stars,
                    'title' => e($feedback->title ?? ''),
                    'description' => e(Str::limit($feedback->description, 80)),
                    'created_at' => $feedback->created_at?->format('d M Y'),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Seller feedback datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    /**
     * Server-side DataTable for seller notifications.
     */
    public function notificationsDatatable(Request $request, string $id): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_VIEW()), 403, trans('labels.permission_denied'));
        try {
            $seller = Seller::with('user')->findOrFail($id);

            $draw = $request->get('draw');
            $start = $request->get('start', 0);
            $length = $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = \App\Models\Notification::where('notifiable_id', $seller->user_id)
                ->where('notifiable_type', \App\Models\User::class)
                ->roleType(\App\Enums\Notification\NotificationRoleTypeEnum::SELLER);

            $totalRecords = (clone $query)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('data->title', 'like', "%{$searchValue}%")
                        ->orWhere('data->message', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = $query->count();
            $notifications = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $notifications->map(function ($notification) {
                $isRead = !is_null($notification->read_at);
                $statusBadge = $isRead
                    ? '<span class="badge bg-success-lt">' . __('labels.read') . '</span>'
                    : '<span class="badge bg-warning-lt">' . __('labels.unread') . '</span>';

                $type = $notification->data['type'] ?? 'general';
                $typeBadge = '<span class="badge bg-azure-lt">' . e(ucfirst(str_replace('_', ' ', $type))) . '</span>';

                return [
                    'title' => e($notification->title ?? ''),
                    'message' => e(Str::limit($notification->message ?? '', 150)),
                    'type' => $typeBadge,
                    'status' => $statusBadge,
                    'created_at' => $notification->created_at?->format('d M Y H:i'),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Seller notifications datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    public function productsDatatable(Request $request, string $id): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_VIEW()), 403, trans('labels.permission_denied'));
        try {
            $seller = Seller::findOrFail($id);

            $draw = $request->get('draw');
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = Product::where('seller_id', $seller->id)->with(['category', 'brand']);
            $totalRecords = Product::where('seller_id', $seller->id)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('title', 'like', "%{$searchValue}%")
                        ->orWhere('slug', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = $query->count();
            $products = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $products->map(function ($product) {
                $statusValue = (string) ($product->status ?? '');
                $verificationValue = (string) ($product->verification_status ?? '');
                $statusClass = $statusValue === 'active' ? 'bg-success-lt' : 'bg-secondary-lt';
                $verificationClass = match ($verificationValue) {
                    'approved' => 'bg-success-lt',
                    'rejected' => 'bg-danger-lt',
                    default => 'bg-warning-lt',
                };

                return [
                    'product' => '<div class="fw-bold">' . e($product->title ?? '') . '</div>'
                        . '<div class="text-secondary small">' . e($product->slug ?? '') . '</div>',
                    'category' => e($product->category->title ?? '—'),
                    'brand' => e($product->brand->title ?? '—'),
                    'status' => '<span class="badge ' . $statusClass . '">' . e(ucfirst(str_replace('_', ' ', $statusValue))) . '</span>',
                    'verification_status' => '<span class="badge ' . $verificationClass . '">' . e(ucfirst(str_replace('_', ' ', $verificationValue))) . '</span>',
                    'created_at' => $product->created_at?->format('d M Y'),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Seller products datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    public function locationsDatatable(Request $request, string $id): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_VIEW()), 403, trans('labels.permission_denied'));
        try {
            $seller = Seller::findOrFail($id);
            $storeIds = $seller->stores()->pluck('id');

            $draw = $request->get('draw');
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = StoreLocation::whereIn('store_id', $storeIds)->with('store');
            $totalRecords = StoreLocation::whereIn('store_id', $storeIds)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('name', 'like', "%{$searchValue}%")
                        ->orWhere('city', 'like', "%{$searchValue}%")
                        ->orWhere('country_name', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = $query->count();
            $locations = $query->orderByDesc('is_default')->orderBy('name')->skip($start)->take($length)->get();

            $data = $locations->map(function ($location) {
                $address = collect([
                    $location->address_1,
                    $location->city,
                    $location->state,
                    $location->country_name,
                    $location->pin_code,
                ])->filter()->implode(', ');

                $badges = '';
                if ($location->is_default) {
                    $badges .= '<span class="badge bg-primary-lt me-1">' . __('labels.default') . '</span>';
                }
                $badges .= $location->is_active
                    ? '<span class="badge bg-success-lt me-1">' . __('labels.active') . '</span>'
                    : '<span class="badge bg-secondary-lt me-1">' . __('labels.inactive') . '</span>';
                if ($location->fulfills_online_orders) {
                    $badges .= '<span class="badge bg-azure-lt me-1">' . __('labels.online') . '</span>';
                }

                return [
                    'name' => '<div class="fw-bold">' . e($location->name ?? '') . '</div>',
                    'store' => e($location->store->name ?? '—'),
                    'address' => e($address),
                    'flags' => $badges,
                    'created_at' => $location->created_at?->format('d M Y'),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Seller locations datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    public function shippingProfilesDatatable(Request $request, string $id): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_VIEW()), 403, trans('labels.permission_denied'));
        try {
            $seller = Seller::findOrFail($id);
            $storeIds = $seller->stores()->pluck('id');

            $draw = $request->get('draw');
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = ShippingProfile::whereIn('store_id', $storeIds)
                ->with('store')
                ->withCount(['zones', 'products']);
            $totalRecords = ShippingProfile::whereIn('store_id', $storeIds)->count();

            if ($searchValue) {
                $query->where('name', 'like', "%{$searchValue}%");
            }

            $filteredRecords = $query->count();
            $profiles = $query->orderByDesc('is_default')->orderBy('name')->skip($start)->take($length)->get();

            $data = $profiles->map(function ($profile) {
                $name = '<div class="fw-bold">' . e($profile->name ?? '') . '</div>';
                if ($profile->is_default) {
                    $name .= '<span class="badge bg-primary-lt">' . __('labels.default') . '</span>';
                }

                return [
                    'name' => $name,
                    'store' => e($profile->store->name ?? '—'),
                    'zones' => (int) ($profile->zones_count ?? 0),
                    'products' => (int) ($profile->products_count ?? 0),
                    'created_at' => $profile->created_at?->format('d M Y'),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Seller shipping profiles datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    public function ordersDatatable(Request $request, string $id): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_VIEW()), 403, trans('labels.permission_denied'));
        try {
            $seller = Seller::findOrFail($id);

            $draw = $request->get('draw');
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = SellerOrder::where('seller_id', $seller->id)->with(['order.user', 'order.market.currency']);
            $totalRecords = SellerOrder::where('seller_id', $seller->id)->count();

            if ($searchValue) {
                $query->whereHas('order', function ($oq) use ($searchValue) {
                    $oq->where('uuid', 'like', "%{$searchValue}%")
                        ->orWhere('id', 'like', "%{$searchValue}%")
                        ->orWhere('billing_name', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = $query->count();
            $sellerOrders = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $sellerOrders->map(function ($sellerOrder) {
                $order = $sellerOrder->order;
                $orderCell = '<div class="fw-bold">#' . e($order->id ?? $sellerOrder->order_id) . '</div>'
                    . '<div class="text-secondary small">' . e($order->billing_name ?? '') . '</div>';

                $fulfillment = (string) ($sellerOrder->fulfillment_status ?? '');

                return [
                    'order' => $order
                        ? '<a href="' . route('admin.orders.show', ['id' => $order->id]) . '">' . $orderCell . '</a>'
                        : $orderCell,
                    'status' => '<span class="badge bg-secondary-lt">' . e(ucfirst(str_replace('_', ' ', (string) $sellerOrder->status))) . '</span>',
                    'fulfillment_status' => '<span class="badge ' . fulfillmentStatusBadgeClass($fulfillment) . '">'
                        . e(ucfirst(str_replace('_', ' ', $fulfillment))) . '</span>',
                    'total' => formatOrderMoney($sellerOrder->total_price, PricePresenter::payload($sellerOrder->order?->market)),
                    'created_at' => $sellerOrder->created_at?->format('d M Y H:i'),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Seller orders datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    public function returnsDatatable(Request $request, string $id): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_VIEW()), 403, trans('labels.permission_denied'));
        try {
            $seller = Seller::findOrFail($id);

            $draw = $request->get('draw');
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = OrderItemReturn::where('seller_id', $seller->id)->with(['orderItem', 'order.market.currency']);
            $totalRecords = OrderItemReturn::where('seller_id', $seller->id)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('reason', 'like', "%{$searchValue}%")
                        ->orWhereHas('orderItem', function ($iq) use ($searchValue) {
                            $iq->where('title', 'like', "%{$searchValue}%");
                        });
                });
            }

            $filteredRecords = $query->count();
            $returns = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $returns->map(function ($return) {
                $item = $return->orderItem;
                $status = (string) $return->return_status;

                return [
                    'item' => '<div class="fw-bold">' . e($item->title ?? '—') . '</div>'
                        . '<div class="text-secondary small">' . __('labels.order') . ' #' . e($return->order_id) . '</div>',
                    'quantity' => (int) $return->quantity,
                    'reason' => e($return->reason_code?->label() ?? ($return->reason ?: '—')),
                    'status' => '<span class="badge bg-secondary-lt">' . e(ucwords(str_replace('_', ' ', $status))) . '</span>',
                    'refund_amount' => formatOrderMoney($return->refund_amount, PricePresenter::payload($return->order?->market)),
                    'created_at' => $return->created_at?->format('d M Y'),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Seller returns datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    public function withdrawalsDatatable(Request $request, string $id): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::SELLER_VIEW()), 403, trans('labels.permission_denied'));
        try {
            $seller = Seller::findOrFail($id);

            $draw = $request->get('draw');
            $start = (int) $request->get('start', 0);
            $length = (int) $request->get('length', 10);
            $searchValue = $request->input('search.value', '');

            $query = SellerWithdrawalRequest::where('seller_id', $seller->id);
            $totalRecords = (clone $query)->count();

            if ($searchValue) {
                $query->where(function ($q) use ($searchValue) {
                    $q->where('request_note', 'like', "%{$searchValue}%")
                        ->orWhere('admin_remark', 'like', "%{$searchValue}%")
                        ->orWhere('transaction_id', 'like', "%{$searchValue}%");
                });
            }

            $filteredRecords = $query->count();
            $withdrawals = $query->orderByDesc('created_at')->skip($start)->take($length)->get();

            $data = $withdrawals->map(function ($withdrawal) {
                $status = (string) $withdrawal->status;
                $statusClass = match ($status) {
                    'approved' => 'bg-success-lt',
                    'rejected' => 'bg-danger-lt',
                    default => 'bg-warning-lt',
                };

                return [
                    'amount' => getCurrencySymbol() . number_format((float) $withdrawal->amount, 2),
                    'status' => '<span class="badge ' . $statusClass . '">' . e(ucfirst($status)) . '</span>',
                    'request_note' => e(Str::limit($withdrawal->request_note ?? '—', 60)),
                    'admin_remark' => e(Str::limit($withdrawal->admin_remark ?? '—', 60)),
                    'processed_at' => $withdrawal->processed_at?->format('d M Y H:i') ?? '—',
                    'created_at' => $withdrawal->created_at?->format('d M Y H:i'),
                ];
            });

            return response()->json([
                'draw' => intval($draw),
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            Log::error('Seller withdrawals datatable error: ' . $e->getMessage());
            return response()->json(['draw' => 0, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        }
    }

    public function getSellers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Seller::class);

        $draw = $request->get('draw');
        $start = $request->get('start', 0);
        $length = $request->get('length', 10);
        $searchValue = $request->input('search.value', '');

        $orderColumnIndex = $request->input('order.0.column', 0);
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $columns = ['id', 'seller', 'email', 'mobile', 'verification_status', 'visibility_status', null, null, 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? null ?: 'id';

        $query = Seller::with(['user', 'stores', 'activeSubscription.plan']);
        $totalRecords = Seller::count();
        $filteredRecords = $totalRecords;

        if ($searchValue) {
            $query->where(function ($q) use ($searchValue) {
                $q->whereHas('user', function ($uq) use ($searchValue) {
                    $uq->where('name', 'like', "%{$searchValue}%")
                        ->orWhere('email', 'like', "%{$searchValue}%")
                        ->orWhere('mobile', 'like', "%{$searchValue}%");
                })
                    ->orWhere('verification_status', 'like', "%{$searchValue}%")
                    ->orWhere('visibility_status', 'like', "%{$searchValue}%");
            });
            $filteredRecords = $query->count();
        }

        $this->applyDatatableDateRange($query, $request, 'sellers.created_at');
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $filteredRecords = $query->count();
        }


        if (in_array($orderColumn, ['seller', 'email', 'mobile'])) {
            $query->join('users', 'sellers.user_id', '=', 'users.id');
            if ($orderColumn === 'seller') {
                $query->orderBy('users.name', $orderDirection);
            } elseif ($orderColumn === 'email') {
                $query->orderBy('users.email', $orderDirection);
            } else {
                $query->orderBy('users.mobile', $orderDirection);
            }
            $query->select('sellers.*');
        } else {
            $query->orderBy($orderColumn, $orderDirection);
        }

        $sellers = $query->skip($start)->take($length)->get();

        $editPermission = $this->editPermission;
        $deletePermission = $this->deletePermission;

        $demo = $this->isDemoModeEnabled();
        $data = $sellers->map(function ($seller) use ($editPermission, $deletePermission, $demo) {
            $stores_count = count($seller->stores);
            $email = $seller->user->email ?? '';
            $mobile = $seller->user->mobile ?? '';
            return [
                'id' => $seller->id,
                'seller' => '<a href="' . route('admin.sellers.show', ['id' => $seller->id]) . '" title="' . __('labels.view') . '">'. e($seller->user->name ?? "") . '</i></a>',
                'email' => e($demo ? Str::mask($email, '****', 3, 4) : $email),
                'mobile' => e($demo ? Str::mask($mobile, '****', 3, 4) : $mobile),
                'verification_status' => view('partials.status', ['status' => $seller->verification_status])->render(),
                'visibility_status' => view('partials.status', ['status' => $seller->visibility_status])->render(),
                'stores' => '<a href="' . route('admin.sellers.store.show.index', ['id' => $seller->id]) . '" class="' . ($stores_count <= 0 ? 'pointer-events-none' : '') . '"><span class="badge bg-info-lt text-uppercase">' . $stores_count . " " . ($stores_count > 1 ? __('labels.stores') : __('labels.store')) . '</span></a>',
                'plan' => e($seller->activeSubscription?->plan?->name ?? 'N/A'),
                'created_at' => $seller->created_at->toDateTimeString(),
                'action' => view('partials.actions', [
                    'modelName' => 'seller',
                    'id' => $seller->id,
                    'title' => $seller->user?->name,
                    'mode' => 'page_view',
                    'route' => route('admin.sellers.edit', ['id' => $seller->id]),
                    'editPermission' => $editPermission,
                    'deletePermission' => $deletePermission
                ])->render(),
            ];
        });

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('search', '');
        if (!empty($request->input('find'))) {
            $sellers = Seller::whereHas('user', function ($q) use ($request) {
                $q->where('name', $request->input('find'));
            })->with('user')->get();
        } else {
            $sellers = Seller::whereHas('user', function ($q) use ($query) {
                $q->where(function ($sub) use ($query) {
                    $sub->where('name', 'like', "%$query%")
                        ->orWhere('email', 'like', "%$query%")
                        ->orWhere('mobile', 'like', "%$query%");
                });
            })
                ->with('user')
                ->limit(20)
                ->get();
        }
        $demo = $this->isDemoModeEnabled();
        $results = $sellers->map(function ($seller) use ($demo) {
            $email = $seller->user->email ?? '';
            $mobile = $seller->user->mobile ?? '';
            $maskedEmail = $demo ? Str::mask($email, '****', 3, 4) : $email;
            $maskedMobile = $demo ? Str::mask($mobile, '****', 3, 4) : $mobile;
            return [
                'id' => $seller->id,
                'value' => $seller->id,
                'text' => $seller->user->name,
                'email' => $maskedEmail,
                'mobile' => $maskedMobile,
                'customProperties' => '<span class="badge bg-info-lt">' . e($maskedEmail) . '</span>',
            ];
        });

        return response()->json($results);
    }
}
