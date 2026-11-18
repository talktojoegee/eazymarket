<?php

namespace App\Http\Controllers\Api;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\MarkWatchBuyStatusesSeenRequest;
use App\Http\Requests\WatchBuy\WatchBuyStatusFeedRequest;
use App\Http\Requests\WatchBuy\WatchBuyStatusListRequest;
use App\Http\Resources\WatchBuy\WatchBuyProfileResource;
use App\Http\Resources\WatchBuy\WatchBuyStatusGroupResource;
use App\Http\Resources\WatchBuy\WatchBuyStatusResource;
use App\Http\Resources\WatchBuy\WatchBuyStatusSeenResource;
use App\Services\WatchBuyStatusService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Watch & Buy Statuses')]
class WatchBuyStatusApiController extends Controller
{
    public function __construct(private readonly WatchBuyStatusService $statuses) {}

    /** Status feed */
    #[QueryParameter('cursor', description: 'Opaque cursor returned by the previous status feed response.', type: 'string')]
    #[QueryParameter('session_id', description: 'Client status-feed session ID. A new ID is returned when omitted.', type: 'string')]
    #[QueryParameter('per_page', description: 'Number of profile groups per response.', type: 'int', default: 10, example: 10)]
    public function index(WatchBuyStatusFeedRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 10);
        $feed = $this->statuses->feed(
            $validated['session_id'] ?? null,
            $validated['cursor'] ?? null,
            $perPage,
            $this->customerId($request),
        );

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_statuses_retrieved', [
            'items' => WatchBuyStatusGroupResource::collection($feed['items']),
            'meta' => [
                'session_id' => $feed['session_id'],
                'next_cursor' => $feed['next_cursor'],
                'per_page' => $perPage,
            ],
        ]);
    }

    /** Profile statuses */
    #[QueryParameter('cursor', description: 'Opaque cursor returned by the previous profile status response.', type: 'string')]
    #[QueryParameter('per_page', description: 'Number of statuses per response.', type: 'int', default: 15, example: 15)]
    public function profile(WatchBuyStatusListRequest $request, string $username): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->statuses->profileStatuses(
            $username,
            $this->customerId($request),
            $validated['cursor'] ?? null,
            (int) ($validated['per_page'] ?? 15),
        );
        $statuses = $result['statuses'];

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_statuses_retrieved', [
            'profile' => new WatchBuyProfileResource($result['profile']),
            'items' => WatchBuyStatusResource::collection($statuses->getCollection()),
            'pagination' => [
                'per_page' => $statuses->perPage(),
                'total' => $result['total'],
                'next_cursor' => $statuses->nextCursor()?->encode(),
                'previous_cursor' => $statuses->previousCursor()?->encode(),
                'has_more_pages' => $statuses->hasMorePages(),
            ],
        ]);
    }

    /** Mark statuses seen */
    public function seen(MarkWatchBuyStatusesSeenRequest $request): JsonResponse
    {
        if ($request->user()->access_panel !== GuardNameEnum::WEB) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        }
        $seen = $this->statuses->markSeen($request->validated('status_ids'), $request->user()->id);

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_statuses_seen', WatchBuyStatusSeenResource::collection($seen));
    }

    private function customerId(Request $request): ?int
    {
        $user = $request->user('sanctum');

        return $user?->access_panel === GuardNameEnum::WEB ? $user->id : null;
    }
}
