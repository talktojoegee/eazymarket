<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyProfileRequest;
use App\Http\Resources\WatchBuy\WatchBuyProfileResource;
use App\Models\WatchBuyProfile;
use App\Services\WatchBuyService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Seller Watch & Buy')]
class SellerWatchBuyProfileApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly WatchBuyService $watchBuy) {}

    /**
     * Get seller Watch & Buy profile.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', WatchBuyProfile::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }
        $profile = WatchBuyProfile::where('seller_id', $request->user()->seller()->id)->first();

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_retrieved', $profile ? new WatchBuyProfileResource($profile) : null);
    }

    /**
     * Save seller Watch & Buy profile.
     */
    public function store(WatchBuyProfileRequest $request): JsonResponse
    {
        abort_unless($this->watchBuy->enabled(), 404);
        $seller = $request->user()->seller();
        $profile = WatchBuyProfile::firstOrNew(['seller_id' => $seller->id]);
        try {
            if ($profile->exists) {
                $this->authorize('update', $profile);
            } else {
                $this->authorize('create', WatchBuyProfile::class);
            }
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }
        $profile = $this->watchBuy->saveProfile($profile, [
            ...$request->validated(),
            'profile_type' => 'seller',
            'seller_id' => $seller->id,
        ], $request->file('photo'), $request->user()->id);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_saved', new WatchBuyProfileResource($profile));
    }
}
