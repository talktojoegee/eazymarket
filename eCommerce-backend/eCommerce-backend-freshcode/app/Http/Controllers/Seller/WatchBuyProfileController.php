<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyProfileRequest;
use App\Models\WatchBuyProfile;
use App\Services\WatchBuyService;
use App\Services\WatchBuyStatusService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WatchBuyProfileController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        private readonly WatchBuyService $watchBuy,
        private readonly WatchBuyStatusService $statuses,
    ) {}

    public function edit(Request $request): View
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_PROFILE_VIEW());

        return $this->profilePage($request, false);
    }

    public function statuses(Request $request): View|RedirectResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_STATUS_VIEW());
        $profile = WatchBuyProfile::where('seller_id', $request->user()->seller()->id)->first();
        if (! $profile) {
            return redirect()
                ->route('seller.watch-and-buy.profile.edit')
                ->with('watch_buy_profile_required', __('labels.watch_buy_status_profile_required'));
        }

        return $this->profilePage($request, true, $profile);
    }

    private function profilePage(Request $request, bool $statusManagementOnly, ?WatchBuyProfile $profile = null): View
    {
        $profile ??= WatchBuyProfile::where('seller_id', $request->user()->seller()->id)->first();
        $config = $this->watchBuy->config();
        $statusViewPermission = $this->permitted(SellerPermissionEnum::WATCH_BUY_STATUS_VIEW());
        $search = trim((string) $request->input('search', ''));

        return view('seller.watch_buy.profile', [
            'profile' => $profile,
            'statusManagementOnly' => $statusManagementOnly,
            'statuses' => $statusViewPermission && $profile
                ? $profile->statuses()
                    ->active()
                    ->when($search !== '', fn ($query) => $query->where(function ($match) use ($search) {
                        $match->where('text', 'like', "%{$search}%")
                            ->orWhere('content_type', 'like', "%{$search}%")
                            ->orWhereHas('creator', fn ($creator) => $creator->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('products.product', fn ($product) => $product->where('title', 'like', "%{$search}%"))
                            ->orWhereHas('products.variant', fn ($variant) => $variant->where('title', 'like', "%{$search}%"));
                    }))
                    ->with(['media', 'creator', 'products.product', 'products.variant'])
                    ->oldest('created_at')
                    ->orderBy('id')
                    ->cursorPaginate(15)
                    ->withQueryString()
                : collect(),
            'statusViewPermission' => $statusViewPermission,
            'watchBuyEnabled' => $this->statuses->enabled(),
            'statusCreatePermission' => $this->permitted(SellerPermissionEnum::WATCH_BUY_STATUS_CREATE()),
            'statusDeletePermission' => $this->permitted(SellerPermissionEnum::WATCH_BUY_STATUS_DELETE()),
            'maxUploadMb' => $config['effectiveMaxUploadMb'],
            'maxProductsPerStatus' => $config['maxProductsPerStatus'],
            'productOptionsEndpoint' => route('seller.watch-and-buy.statuses.product-options'),
        ]);
    }

    public function update(WatchBuyProfileRequest $request): JsonResponse|RedirectResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_PROFILE_EDIT());
        abort_unless($this->watchBuy->enabled(), 404);
        $seller = $request->user()->seller();
        $profile = WatchBuyProfile::firstOrNew(['seller_id' => $seller->id]);
        $this->watchBuy->saveProfile($profile, [
            ...$request->validated(),
            'profile_type' => 'seller',
            'seller_id' => $seller->id,
        ], $request->file('photo'), $request->user()->id);

        return $request->expectsJson()
            ? ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_saved')
            : back()->with('success', __('labels.watch_buy_profile_saved'));
    }

    private function allow(string $permission): void
    {
        $user = auth()->user();
        abort_unless($user?->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission), 403, trans('labels.permission_denied'));
    }

    private function permitted(string $permission): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole(DefaultSystemRolesEnum::SELLER()) || $this->hasPermission($permission));
    }
}
