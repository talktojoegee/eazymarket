<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\SellerPermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyStatusRequest;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyStatus;
use App\Services\WatchBuyService;
use App\Services\WatchBuyStatusService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WatchBuyStatusController extends Controller
{
    use ChecksPermissions;

    public function __construct(
        private readonly WatchBuyService $watchBuy,
        private readonly WatchBuyStatusService $statuses,
    ) {}

    public function productOptions(Request $request): JsonResponse
    {
        abort_unless(
            $this->permitted(SellerPermissionEnum::WATCH_BUY_STATUS_VIEW())
            || $this->permitted(SellerPermissionEnum::WATCH_BUY_STATUS_CREATE()),
            403,
            trans('labels.permission_denied')
        );

        return response()->json($this->watchBuy->eligibleProductVariantOptions(
            $request->user()->seller()->id,
            trim((string) $request->input('search', '')),
            (array) $request->input('find_in', [])
        ));
    }

    public function store(WatchBuyStatusRequest $request): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_STATUS_CREATE());
        $profile = $this->profile($request);
        $status = $this->statuses->save(
            $profile,
            [...$request->validated(), 'source' => 'seller'],
            $request->file('media'),
            $request->user()->id,
        );

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_status_created', [
            'status_id' => $status->id,
            'redirect_url' => route('seller.watch-and-buy.profile.edit'),
        ], 201);
    }

    public function destroy(Request $request, WatchBuyStatus $status): JsonResponse
    {
        $this->allow(SellerPermissionEnum::WATCH_BUY_STATUS_DELETE());
        abort_unless($this->statuses->enabled(), 404);
        $this->owned($request, $status);
        abort_if($status->profile->status === 'suspended', 403, __('labels.watch_buy_profile_is_suspended'));
        $this->statuses->delete($status);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_status_deleted');
    }

    private function profile(Request $request): WatchBuyProfile
    {
        $profile = WatchBuyProfile::where('seller_id', $request->user()->seller()->id)->first();
        abort_unless($profile, 422, __('labels.watch_buy_profile_required_api'));

        return $profile;
    }

    private function owned(Request $request, WatchBuyStatus $status): void
    {
        abort_unless($status->profile?->seller_id === $request->user()->seller()->id, 404);
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
