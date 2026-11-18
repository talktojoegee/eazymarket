<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyReelRequest;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyReel;
use App\Services\WatchBuyService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WatchBuyReelController extends Controller
{
    use ChecksPermissions;

    public function __construct(private readonly WatchBuyService $watchBuy) {}

    public function index(): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_VIEW());

        $columns = [
            ['data' => 'preview', 'name' => 'preview', 'title' => __('labels.preview'), 'orderable' => false, 'searchable' => false],
            ['data' => 'caption', 'name' => 'caption', 'title' => __('labels.watch_buy_caption')],
            ['data' => 'profile', 'name' => 'profile', 'title' => __('labels.profile'), 'orderable' => false],
            ['data' => 'source', 'name' => 'source', 'title' => __('labels.source')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'like_count', 'name' => 'like_count', 'title' => __('labels.watch_buy_likes'), 'searchable' => false],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        return view('admin.watch_buy.reels.index', [
            'columns' => $columns,
            'createPermission' => $this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_CREATE()),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_VIEW());
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $length = min(max((int) $request->input('length', 10), 1), 100);
        $start = max((int) $request->input('start', 0), 0);
        $orderColumnIndex = (int) data_get($request->input('order'), '0.column', 6);
        $orderDirection = data_get($request->input('order'), '0.dir') === 'asc' ? 'asc' : 'desc';
        $orderColumnName = data_get($request->input('columns'), $orderColumnIndex.'.name', 'created_at');
        $orderColumn = in_array($orderColumnName, ['caption', 'source', 'status', 'like_count', 'created_at'], true) ? $orderColumnName : 'created_at';
        $query = WatchBuyReel::query()->with(['profile.seller.user', 'media']);
        $totalRecords = (clone $query)->count();

        $profileId = $request->integer('profile_id');
        $source = (string) $request->input('source', '');
        $status = (string) $request->input('status', '');

        $query
            ->when($profileId > 0, fn ($builder) => $builder->where('profile_id', $profileId))
            ->when(in_array($source, ['seller', 'seller_behalf', 'admin_direct'], true), fn ($builder) => $builder->where('source', $source))
            ->when(in_array($status, ['published', 'unpublished', 'blocked'], true), fn ($builder) => $builder->where('status', $status));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('caption', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('source', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('profile', fn ($profile) => $profile->where('username', 'like', "%{$search}%"));
            });
        }

        $hasFilters = $profileId > 0
            || in_array($source, ['seller', 'seller_behalf', 'admin_direct'], true)
            || in_array($status, ['published', 'unpublished', 'blocked'], true)
            || $search !== '';
        $filteredRecords = $hasFilters ? (clone $query)->count() : $totalRecords;
        $editPermission = $this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_EDIT());
        $deletePermission = $this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_DELETE());
        $blockPermission = $this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_BLOCK());
        $data = $query->orderBy($orderColumn, $orderDirection)->skip($start)->take($length)->get()
            ->map(fn (WatchBuyReel $reel) => [
                'preview' => view('watch_buy._reel_video_preview', ['reel' => $reel, 'gallery' => 'admin-watch-buy-reels'])->render(),
                'caption' => e($reel->caption ? Str::limit($reel->caption, 90) : '—'),
                'profile' => e('@'.$reel->profile->username),
                'source' => e(match ($reel->source) {
                    'seller_behalf' => __('labels.watch_buy_seller_behalf'),
                    'admin_direct' => __('labels.watch_buy_admin_direct'),
                    default => __('labels.seller'),
                }),
                'status' => view('partials.status', ['status' => $reel->status])->render(),
                'like_count' => number_format($reel->like_count),
                'created_at' => $reel->created_at->format('Y-m-d'),
                'action' => view('admin.watch_buy.reels._actions', [
                    'reel' => $reel,
                    'editPermission' => $editPermission,
                    'deletePermission' => $deletePermission,
                    'blockPermission' => $blockPermission,
                ])->render(),
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function create(): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_CREATE());

        return $this->form(new WatchBuyReel);
    }

    public function productOptions(Request $request): JsonResponse
    {
        abort_unless(
            $this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_VIEW())
            || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_CREATE())
            || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_REEL_EDIT()),
            403,
            trans('labels.permission_denied')
        );
        $sellerId = null;
        if ($request->input('source') === 'seller_behalf') {
            $sellerId = WatchBuyProfile::query()
                ->where('profile_type', 'seller')
                ->where('status', 'active')
                ->find($request->integer('profile_id'))?->seller_id;
            if (! $sellerId) {
                return response()->json([]);
            }
        }

        return response()->json($this->watchBuy->eligibleProductVariantOptions(
            $sellerId,
            trim((string) $request->input('search', '')),
            (array) $request->input('find_in', [])
        ));
    }

    public function store(WatchBuyReelRequest $request): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_CREATE());
        $data = $request->validated();
        $profile = $this->resolveProfile($data);
        $this->watchBuy->saveReel(new WatchBuyReel, $profile, $data, $request->file('video'), $request->file('cover'), $request->user()->id);

        $redirectUrl = route('admin.watch-and-buy.reels.index');
        if (! $request->expectsJson()) {
            return redirect($redirectUrl)->with('success', __('labels.watch_buy_reel_created'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_created', [
            'redirect_url' => $redirectUrl,
        ], 201);
    }

    public function edit(WatchBuyReel $reel): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_EDIT());

        return $this->form($reel);
    }

    public function update(WatchBuyReelRequest $request, WatchBuyReel $reel): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_EDIT());
        $data = $request->validated();
        $profile = $this->resolveProfile($data);
        $this->watchBuy->saveReel($reel, $profile, $data, $request->file('video'), $request->file('cover'), $request->user()->id);

        $redirectUrl = route('admin.watch-and-buy.reels.index');
        if (! $request->expectsJson()) {
            return redirect($redirectUrl)->with('success', __('labels.watch_buy_reel_updated'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_updated', [
            'redirect_url' => $redirectUrl,
        ]);
    }

    public function destroy(Request $request, WatchBuyReel $reel): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_DELETE());
        $this->watchBuy->deleteReel($reel, $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_deleted')
            : back()->with('success', __('labels.watch_buy_reel_deleted'));
    }

    public function publish(Request $request, WatchBuyReel $reel): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_EDIT());
        $this->watchBuy->setPublished($reel, true, $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_published')
            : back()->with('success', __('labels.watch_buy_reel_published'));
    }

    public function unpublish(Request $request, WatchBuyReel $reel): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_EDIT());
        $this->watchBuy->setPublished($reel, false, $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_unpublished')
            : back()->with('success', __('labels.watch_buy_reel_unpublished'));
    }

    public function block(Request $request, WatchBuyReel $reel): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_BLOCK());
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];
        $this->watchBuy->block($reel, $reason, $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_blocked')
            : back()->with('success', __('labels.watch_buy_reel_blocked'));
    }

    public function unblock(Request $request, WatchBuyReel $reel): JsonResponse|RedirectResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_REEL_BLOCK());
        $this->watchBuy->unblock($reel, $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_unblocked')
            : back()->with('success', __('labels.watch_buy_reel_unblocked'));
    }

    private function form(WatchBuyReel $reel): View
    {
        $config = $this->watchBuy->config();
        $selectedProfileId = (int) old('profile_id', $reel->profile_id);

        return view('admin.watch_buy.reels.form', [
            'reel' => $reel,
            'maxUploadMb' => $config['effectiveMaxUploadMb'],
            'maxProductsPerReel' => $config['maxProductsPerReel'],
            'productVariantSelection' => $this->watchBuy->reelProductVariantSelection($reel),
            'productOptionsEndpoint' => route('admin.watch-and-buy.reels.product-options'),
            'selectedProfile' => $selectedProfileId
                ? WatchBuyProfile::with('seller.user')->where('profile_type', 'seller')->find($selectedProfileId)
                : null,
        ]);
    }

    private function resolveProfile(array $data): WatchBuyProfile
    {
        if (($data['source'] ?? null) === 'admin_direct') {
            return WatchBuyProfile::where('profile_type', 'platform')->firstOrFail();
        }

        return WatchBuyProfile::where('profile_type', 'seller')->findOrFail($data['profile_id'] ?? null);
    }

    private function allow(string $permission): void
    {
        abort_unless($this->hasPermission($permission), 403, trans('labels.permission_denied'));
    }
}
