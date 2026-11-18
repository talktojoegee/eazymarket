<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\WatchBuy\BulkUpdateWatchBuyLikesRequest;
use App\Http\Resources\WatchBuy\WatchBuyLikeResource;
use App\Services\WatchBuyService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Watch & Buy')]
class WatchBuyLikeApiController extends Controller
{
    public function __construct(private readonly WatchBuyService $watchBuy) {}

    /**
     * Update Watch & Buy reel likes.
     */
    public function update(BulkUpdateWatchBuyLikesRequest $request): JsonResponse
    {
        $likes = $this->watchBuy->setLikes($request->validated('items'), $request->user()->id);

        return ApiResponseType::sendJsonResponse(
            true,
            'labels.watch_buy_reel_likes_updated',
            WatchBuyLikeResource::collection($likes),
        );
    }
}
