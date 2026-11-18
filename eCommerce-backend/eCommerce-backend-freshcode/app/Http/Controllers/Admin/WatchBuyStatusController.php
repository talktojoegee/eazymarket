<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyStatusRequest;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyStatus;
use App\Services\WatchBuyService;
use App\Services\WatchBuyStatusService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WatchBuyStatusController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        private readonly WatchBuyService $watchBuy,
        private readonly WatchBuyStatusService $statuses,
    ) {}

    public function index(Request $request): View
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_STATUS_VIEW());
        $search = trim((string) $request->input('search', ''));
        $profiles = WatchBuyProfile::query()
            ->whereHas('statuses', fn (Builder $status) => $status->active())
            ->with([
                'media',
                'seller.user',
                'statuses' => fn ($query) => $query->active()->oldest('created_at')->with(['media', 'creator', 'products.product', 'products.variant']),
            ])
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $match) use ($search) {
                $match->where('username', 'like', "%{$search}%")
                    ->orWhereHas('seller.user', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"));
            }))
            ->orderBy('profile_type')
            ->latest('watch_buy_profiles.id')
            ->cursorPaginate(15)
            ->withQueryString();
        $config = $this->watchBuy->config();

        return view('admin.watch_buy.statuses.index', [
            'profiles' => $profiles,
            'createPermission' => $this->hasPermission(AdminPermissionEnum::WATCH_BUY_STATUS_CREATE()),
            'deletePermission' => $this->hasPermission(AdminPermissionEnum::WATCH_BUY_STATUS_DELETE()),
            'watchBuyEnabled' => (bool) $config['enabled'],
            'maxUploadMb' => $config['effectiveMaxUploadMb'],
            'maxProductsPerStatus' => $config['maxProductsPerStatus'],
            'profileOptionsEndpoint' => route('admin.watch-and-buy.profiles.options'),
            'productOptionsEndpoint' => route('admin.watch-and-buy.statuses.product-options'),
        ]);
    }

    public function productOptions(Request $request): JsonResponse
    {
        abort_unless(
            $this->hasPermission(AdminPermissionEnum::WATCH_BUY_STATUS_VIEW())
            || $this->hasPermission(AdminPermissionEnum::WATCH_BUY_STATUS_CREATE()),
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

    public function store(WatchBuyStatusRequest $request): JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_STATUS_CREATE());
        $data = $request->validated();
        $source = ($data['source'] ?? null) === 'seller_behalf' ? 'seller_behalf' : 'admin_direct';
        $profile = $source === 'admin_direct'
            ? WatchBuyProfile::where('profile_type', 'platform')->firstOrFail()
            : WatchBuyProfile::where('profile_type', 'seller')->findOrFail($data['profile_id'] ?? null);
        $status = $this->statuses->save(
            $profile,
            [...$data, 'source' => $source],
            $request->file('media'),
            $request->user()->id,
        );

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_status_created', [
            'status_id' => $status->id,
            'redirect_url' => route('admin.watch-and-buy.statuses.index'),
        ], 201);
    }

    public function destroy(WatchBuyStatus $status): JsonResponse
    {
        $this->allow(AdminPermissionEnum::WATCH_BUY_STATUS_DELETE());
        $this->statuses->delete($status);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_status_deleted');
    }

    private function allow(string $permission): void
    {
        abort_unless($this->hasPermission($permission), 403, trans('labels.permission_denied'));
    }
}
