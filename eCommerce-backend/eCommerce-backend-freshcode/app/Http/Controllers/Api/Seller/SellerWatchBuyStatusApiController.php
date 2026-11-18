<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\WatchBuyStatusListRequest;
use App\Http\Requests\WatchBuy\WatchBuyStatusRequest;
use App\Http\Resources\WatchBuy\WatchBuyStatusResource;
use App\Models\WatchBuyProfile;
use App\Models\WatchBuyStatus;
use App\Services\WatchBuyStatusService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Seller Watch & Buy Statuses')]
class SellerWatchBuyStatusApiController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly WatchBuyStatusService $statuses) {}

    /** Seller statuses */
    #[QueryParameter('cursor', description: 'Opaque cursor returned by the previous seller status response.', type: 'string')]
    #[QueryParameter('per_page', description: 'Number of statuses per response.', type: 'int', default: 15, example: 15)]
    public function index(WatchBuyStatusListRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', WatchBuyStatus::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }
        $profile = $this->profile($request);
        $validated = $request->validated();
        $query = $this->statuses->withApiRelations(
            $profile->statuses()->active()->with('profile')->oldest('created_at')->orderBy('id'),
        );
        $total = (clone $query)->count();
        $statuses = $query->cursorPaginate((int) ($validated['per_page'] ?? 15), ['*'], 'cursor', $validated['cursor'] ?? null);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_statuses_retrieved', [
            'items' => WatchBuyStatusResource::collection($statuses->getCollection()),
            'pagination' => [
                'per_page' => $statuses->perPage(),
                'total' => $total,
                'next_cursor' => $statuses->nextCursor()?->encode(),
                'previous_cursor' => $statuses->previousCursor()?->encode(),
                'has_more_pages' => $statuses->hasMorePages(),
            ],
        ]);
    }

    /** Create seller status */
    public function store(WatchBuyStatusRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', WatchBuyStatus::class);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }
        $profile = $this->profile($request);
        $status = $this->statuses->save(
            $profile,
            [...$request->validated(), 'source' => 'seller'],
            $request->file('media'),
            $request->user()->id,
        );

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_status_created', new WatchBuyStatusResource($status), 201);
    }

    /** Delete seller status */
    public function destroy(WatchBuyStatus $status): JsonResponse
    {
        try {
            $this->authorize('delete', $status);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }
        abort_unless($this->statuses->enabled(), 404);
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
}
