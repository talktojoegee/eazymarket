<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
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

    public function index(Request $request): View
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_VIEW());
        $columns = [
            ['data' => 'preview', 'name' => 'preview', 'title' => __('labels.preview'), 'orderable' => false, 'searchable' => false],
            ['data' => 'caption', 'name' => 'caption', 'title' => __('labels.watch_buy_caption')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'like_count', 'name' => 'like_count', 'title' => __('labels.watch_buy_likes'), 'searchable' => false],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        return view('seller.watch_buy.reels.index', [
            'columns' => $columns,
            'createPermission' => $this->permitted(SellerPermissionEnum::WATCH_BUY_REEL_CREATE()),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_VIEW());
        $sellerId = $request->user()->seller()->id;
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        $length = min(max((int) $request->input('length', 10), 1), 100);
        $start = max((int) $request->input('start', 0), 0);
        $orderColumnIndex = (int) data_get($request->input('order'), '0.column', 4);
        $orderDirection = data_get($request->input('order'), '0.dir') === 'asc' ? 'asc' : 'desc';
        $orderColumnName = data_get($request->input('columns'), $orderColumnIndex.'.name', 'created_at');
        $orderColumn = in_array($orderColumnName, ['caption', 'status', 'like_count', 'created_at'], true) ? $orderColumnName : 'created_at';
        $query = WatchBuyReel::query()
            ->whereHas('profile', fn ($profile) => $profile->where('seller_id', $sellerId))
            ->with(['profile', 'media']);
        $totalRecords = (clone $query)->count();

        $status = (string) $request->input('status', '');
        $query->when(in_array($status, ['published', 'unpublished', 'blocked'], true), fn ($builder) => $builder->where('status', $status));

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('caption', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%");
            });
        }

        $filteredRecords = (clone $query)->count();
        $editPermission = $this->permitted(SellerPermissionEnum::WATCH_BUY_REEL_EDIT());
        $deletePermission = $this->permitted(SellerPermissionEnum::WATCH_BUY_REEL_DELETE());
        $publishPermission = $this->permitted(SellerPermissionEnum::WATCH_BUY_REEL_PUBLISH());
        $data = $query->orderBy($orderColumn, $orderDirection)->skip($start)->take($length)->get()
            ->map(fn (WatchBuyReel $reel) => [
                'preview' => view('watch_buy._reel_video_preview', ['reel' => $reel, 'gallery' => 'seller-watch-buy-reels'])->render(),
                'caption' => e($reel->caption ? Str::limit($reel->caption, 100) : '—'),
                'status' => view('partials.status', ['status' => $reel->status])->render(),
                'like_count' => number_format($reel->like_count),
                'created_at' => $reel->created_at->format('Y-m-d'),
                'action' => view('seller.watch_buy.reels._actions', [
                    'reel' => $reel,
                    'editPermission' => $editPermission,
                    'deletePermission' => $deletePermission,
                    'publishPermission' => $publishPermission,
                ])->render(),
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_CREATE());
        if (! WatchBuyProfile::where('seller_id', $request->user()->seller()->id)->exists()) {
            return redirect()->route('seller.watch-and-buy.profile.edit')
                ->with('watch_buy_profile_required', __('labels.watch_buy_profile_required'));
        }

        return $this->form($request, new WatchBuyReel);
    }

    public function productOptions(Request $request): JsonResponse
    {
        abort_unless(
            $this->permitted(SellerPermissionEnum::WATCH_BUY_REEL_VIEW())
            || $this->permitted(SellerPermissionEnum::WATCH_BUY_REEL_CREATE())
            || $this->permitted(SellerPermissionEnum::WATCH_BUY_REEL_EDIT()),
            403,
            trans('labels.permission_denied')
        );

        return response()->json($this->watchBuy->eligibleProductVariantOptions(
            $request->user()->seller()->id,
            trim((string) $request->input('search', '')),
            (array) $request->input('find_in', [])
        ));
    }

    public function store(WatchBuyReelRequest $request): JsonResponse|RedirectResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_CREATE());
        abort_unless($this->watchBuy->enabled(), 404);
        $profile = $this->profile($request);
        $this->watchBuy->saveReel(new WatchBuyReel, $profile, [...$request->validated(), 'source' => 'seller'], $request->file('video'), $request->file('cover'), $request->user()->id);

        $redirectUrl = route('seller.watch-and-buy.reels.index');
        if (! $request->expectsJson()) {
            return redirect($redirectUrl)->with('success', __('labels.watch_buy_reel_created'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_created', [
            'redirect_url' => $redirectUrl,
        ], 201);
    }

    public function edit(Request $request, WatchBuyReel $reel): View
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_EDIT());
        $this->owned($request, $reel);

        return $this->form($request, $reel);
    }

    public function update(WatchBuyReelRequest $request, WatchBuyReel $reel): JsonResponse|RedirectResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_EDIT());
        abort_unless($this->watchBuy->enabled(), 404);
        $this->owned($request, $reel);
        abort_if($reel->status === 'blocked', 403);
        $this->watchBuy->saveReel($reel, $reel->profile, [...$request->validated(), 'source' => 'seller'], $request->file('video'), $request->file('cover'), $request->user()->id);

        $redirectUrl = route('seller.watch-and-buy.reels.index');
        if (! $request->expectsJson()) {
            return redirect($redirectUrl)->with('success', __('labels.watch_buy_reel_updated'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_updated', [
            'redirect_url' => $redirectUrl,
        ]);
    }

    public function destroy(Request $request, WatchBuyReel $reel): JsonResponse|RedirectResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_DELETE());
        abort_unless($this->watchBuy->enabled(), 404);
        $this->owned($request, $reel);
        abort_if($reel->status === 'blocked', 403);
        abort_if($reel->profile->status === 'suspended', 403);
        $this->watchBuy->deleteReel($reel, $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_deleted')
            : back()->with('success', __('labels.watch_buy_reel_deleted'));
    }

    public function publish(Request $request, WatchBuyReel $reel): JsonResponse|RedirectResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_PUBLISH());
        abort_unless($this->watchBuy->enabled(), 404);
        $this->owned($request, $reel);
        $this->watchBuy->setPublished($reel, true, $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_published')
            : back()->with('success', __('labels.watch_buy_reel_published'));
    }

    public function unpublish(Request $request, WatchBuyReel $reel): JsonResponse|RedirectResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_REEL_PUBLISH());
        abort_unless($this->watchBuy->enabled(), 404);
        $this->owned($request, $reel);
        $this->watchBuy->setPublished($reel, false, $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reel_unpublished')
            : back()->with('success', __('labels.watch_buy_reel_unpublished'));
    }

    private function form(Request $request, WatchBuyReel $reel): View
    {
        $config = $this->watchBuy->config();
        $profile = $reel->profile ?? $this->profile($request);

        return view('seller.watch_buy.reels.form', [
            'reel' => $reel,
            'profile' => $profile,
            'maxUploadMb' => $config['effectiveMaxUploadMb'],
            'maxProductsPerReel' => $config['maxProductsPerReel'],
            'productVariantSelection' => $this->watchBuy->reelProductVariantSelection($reel),
            'productOptionsEndpoint' => route('seller.watch-and-buy.reels.product-options'),
        ]);
    }

    private function profile(Request $request): WatchBuyProfile
    {
        return WatchBuyProfile::where('seller_id', $request->user()->seller()->id)->firstOrFail();
    }

    private function owned(Request $request, WatchBuyReel $reel): void
    {
        abort_unless($reel->profile?->seller_id === $request->user()->seller()->id, 404);
    }

    private function allow(string $permission): void
    {
        abort_unless($this->permitted($permission), 403, trans('labels.permission_denied'));
    }

    private function permitted(string $permission): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission));
    }
}
