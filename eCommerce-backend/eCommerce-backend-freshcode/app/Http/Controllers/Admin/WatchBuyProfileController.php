<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyProfileRequest;
use App\Models\Seller;
use App\Models\WatchBuyProfile;
use App\Services\WatchBuyService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WatchBuyProfileController extends Controller
{
    use ChecksPermissions;

    public function __construct(private readonly WatchBuyService $watchBuy) {}

    public function index(): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_VIEW());

        $columns = [
            ['data' => 'photo', 'name' => 'photo', 'title' => __('labels.watch_buy_photo'), 'orderable' => false, 'searchable' => false],
            ['data' => 'username', 'name' => 'username', 'title' => __('labels.watch_buy_username')],
            ['data' => 'profile_type', 'name' => 'profile_type', 'title' => __('labels.type')],
            ['data' => 'seller', 'name' => 'seller', 'title' => __('labels.seller'), 'orderable' => false],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        return view('admin.watch_buy.profiles', [
            'columns' => $columns,
            'editPermission' => $this->hasPermission(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT()),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_VIEW());
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $length = min(max((int) $request->input('length', 10), 1), 100);
        $start = max((int) $request->input('start', 0), 0);
        $orderColumnIndex = (int) data_get($request->input('order'), '0.column', 5);
        $orderDirection = data_get($request->input('order'), '0.dir') === 'asc' ? 'asc' : 'desc';
        $orderColumnName = data_get($request->input('columns'), $orderColumnIndex.'.name', 'created_at');
        $orderColumn = in_array($orderColumnName, ['username', 'profile_type', 'status', 'created_at'], true) ? $orderColumnName : 'created_at';
        $query = WatchBuyProfile::query()->with(['seller.user', 'media']);
        $totalRecords = (clone $query)->count();

        $status = (string) $request->input('status', '');
        $query->when(in_array($status, ['active', 'suspended'], true), fn ($builder) => $builder->where('status', $status));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('username', 'like', "%{$search}%")
                    ->orWhere('bio', 'like', "%{$search}%")
                    ->orWhere('profile_type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('seller.user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            });
        }

        $hasFilters = in_array($status, ['active', 'suspended'], true) || $search !== '';
        $filteredRecords = $hasFilters ? (clone $query)->count() : $totalRecords;
        $editPermission = $this->hasPermission(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT());
        $suspendPermission = $this->hasPermission(AdminPermissionEnum::WATCH_BUY_PROFILE_SUSPEND());
        $data = $query->orderBy($orderColumn, $orderDirection)->skip($start)->take($length)->get()
            ->map(fn (WatchBuyProfile $profile) => [
                'photo' => view('watch_buy._profile_photo', ['profile' => $profile])->render(),
                'username' => e('@'.$profile->username),
                'profile_type' => e($profile->profile_type === 'platform' ? __('labels.watch_buy_platform') : __('labels.seller')),
                'seller' => e($profile->seller?->user?->name ?? 'HyperCommerce'),
                'status' => view('partials.status', ['status' => $profile->status])->render(),
                'created_at' => $profile->created_at->format('Y-m-d'),
                'action' => view('admin.watch_buy._profile_actions', [
                    'profile' => $profile,
                    'editPermission' => $editPermission,
                    'suspendPermission' => $suspendPermission,
                ])->render(),
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless(
            $this->hasPermission(AdminPermissionEnum::WATCH_BUY_PROFILE_VIEW())
            || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_CREATE())
            || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_EDIT())
            || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_STATUS_CREATE()),
            403,
            trans('labels.permission_denied')
        );
        $findIn = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('find_in', [])))));
        $search = trim((string) $request->input('search', ''));
        $filterMode = $request->input('scope') === 'filter';
        $profiles = WatchBuyProfile::query()
            ->with(['seller.user', 'media'])
            ->when(! $filterMode, fn ($query) => $query->where('profile_type', 'seller')->where('status', 'active'))
            ->when($findIn !== [], fn ($query) => $query->whereKey($findIn))
            ->when($findIn === [] && $search !== '', fn ($query) => $query->where(function ($match) use ($search) {
                $match->where('username', 'like', "%{$search}%")
                    ->orWhereHas('seller.user', fn ($user) => $user->where('name', 'like', "%{$search}%"));
            }))
            ->latest('watch_buy_profiles.id')
            ->limit($findIn === [] ? 15 : 20)
            ->get();

        return response()->json($profiles->map(fn (WatchBuyProfile $profile) => [
            'value' => (string) $profile->id,
            'text' => '@'.$profile->username,
            'subtitle' => $profile->seller?->user?->name,
            'image_url' => $profile->photo_url,
        ])->values());
    }

    public function sellerOptions(Request $request): JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT());
        $findIn = array_values(array_unique(array_filter(array_map('intval', (array) $request->input('find_in', [])))));
        $search = trim((string) $request->input('search', ''));
        $sellers = Seller::query()
            ->with('user')
            ->whereDoesntHave('watchBuyProfile')
            ->when($findIn !== [], fn ($query) => $query->whereKey($findIn))
            ->when($findIn === [] && $search !== '', fn ($query) => $query->whereHas('user', fn ($user) => $user
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->latest('sellers.id')
            ->limit($findIn === [] ? 15 : 20)
            ->get();

        return response()->json($sellers->map(fn (Seller $seller) => [
            'value' => (string) $seller->id,
            'text' => $seller->user?->name ?: __('labels.watch_buy_seller_number', ['id' => $seller->id]),
            'subtitle' => Str::limit((string) $seller->user?->email, 60),
        ])->values());
    }

    public function store(WatchBuyProfileRequest $request): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT());
        $validated = $request->validate(['seller_id' => ['required', 'exists:sellers,id', 'unique:watch_buy_profiles,seller_id']]) + $request->validated();
        $this->watchBuy->saveProfile(new WatchBuyProfile, [
            ...$validated,
            'profile_type' => 'seller',
        ], $request->file('photo'), $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_created', null, 201)
            : back()->with('success', __('labels.watch_buy_profile_created'));
    }

    public function official(): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_VIEW());

        return view('admin.watch_buy.official-profile', ['profile' => WatchBuyProfile::where('profile_type', 'platform')->firstOrFail()]);
    }

    public function edit(WatchBuyProfile $profile): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT());

        return view('admin.watch_buy.profile-edit', ['profile' => $profile]);
    }

    public function update(WatchBuyProfileRequest $request, WatchBuyProfile $profile): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT());
        $this->watchBuy->saveProfile($profile, $request->validated(), $request->file('photo'), $request->user()->id, true);

        $redirectUrl = route('admin.watch-and-buy.profiles.index');

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_updated', ['redirect_url' => $redirectUrl])
            : redirect($redirectUrl)->with('success', __('labels.watch_buy_profile_updated'));
    }

    public function updateOfficial(WatchBuyProfileRequest $request): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_EDIT());
        $profile = WatchBuyProfile::where('profile_type', 'platform')->firstOrFail();
        $this->watchBuy->saveProfile($profile, $request->validated(), $request->file('photo'), $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_official_profile_updated')
            : back()->with('success', __('labels.watch_buy_official_profile_updated'));
    }

    public function suspend(Request $request, WatchBuyProfile $profile): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_SUSPEND());
        $validated = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->watchBuy->suspend($profile, $validated['reason'], $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_suspended_success')
            : back()->with('success', __('labels.watch_buy_profile_suspended_success'));
    }

    public function unsuspend(Request $request, WatchBuyProfile $profile): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_PROFILE_SUSPEND());
        $this->watchBuy->unsuspend($profile, $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_restored')
            : back()->with('success', __('labels.watch_buy_profile_restored'));
    }

    private function allow(string $permission): void
    {
        abort_unless($this->hasPermission($permission), 403, trans('labels.permission_denied'));
    }
}
