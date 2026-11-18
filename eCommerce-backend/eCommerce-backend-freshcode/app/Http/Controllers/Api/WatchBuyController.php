<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WatchBuy\WatchBuyProfileResource;
use App\Http\Resources\WatchBuy\WatchBuyReelResource;
use App\Models\WatchBuyProfile;
use App\Services\WatchBuyService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Watch & Buy')]
class WatchBuyController extends Controller
{
    public function __construct(private readonly WatchBuyService $watchBuy) {}

    /**
     * Get Watch & Buy reels.
     */
    #[QueryParameter('cursor', description: 'Opaque cursor returned by the previous feed response.', type: 'string')]
    #[QueryParameter('session_id', description: 'Client feed session ID. A new ID is returned when omitted.', type: 'string')]
    #[QueryParameter('slug', description: 'Shared reel slug to place first when it is publicly available.', type: 'string')]
    #[QueryParameter('per_page', description: 'Number of reels per response.', type: 'int', default: 10, example: 10)]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cursor' => ['nullable', 'string'],
            'session_id' => ['nullable', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $feed = $this->watchBuy->feed(
            $validated['session_id'] ?? null,
            $validated['cursor'] ?? null,
            $validated['slug'] ?? null,
            (int) ($validated['per_page'] ?? 10),
        );

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_reels_retrieved', [
            'items' => WatchBuyReelResource::collection($feed['items']),
            'meta' => [
                'session_id' => $feed['session_id'],
                'next_cursor' => $feed['next_cursor'],
                'per_page' => (int) ($validated['per_page'] ?? 10),
                'slug_not_found' => $feed['slug_not_found'],
            ],
        ]);
    }

    /**
     * Get Watch & Buy profile.
     */
    #[QueryParameter('cursor', description: 'Opaque cursor returned by the previous profile reel response.', type: 'string')]
    #[QueryParameter('per_page', description: 'Number of profile reels per response.', type: 'int', default: 15, example: 15)]
    public function profile(Request $request, string $username): JsonResponse
    {
        abort_unless($this->watchBuy->enabled(), 404);
        $validated = $request->validate([
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $profile = WatchBuyProfile::query()
            ->where('username', strtolower($username))
            ->where('status', 'active')
            ->firstOrFail();
        $reelsQuery = $this->watchBuy->eligibleReelsQuery()
            ->where('profile_id', $profile->id)
            ->with('profile')
            ->latest('published_at')
            ->orderByDesc('id');
        $total = (clone $reelsQuery)->count();
        $reels = $reelsQuery->cursorPaginate(
            (int) ($validated['per_page'] ?? 15),
            ['*'],
            'cursor',
            $validated['cursor'] ?? null,
        );

        return ApiResponseType::sendJsonResponse(true, 'labels.watch_buy_profile_retrieved', [
            'profile' => new WatchBuyProfileResource($profile),
            'reels' => WatchBuyReelResource::collection($reels->getCollection()),
            'pagination' => [
                'per_page' => $reels->perPage(),
                'total' => $total,
                'next_cursor' => $reels->nextCursor()?->encode(),
                'previous_cursor' => $reels->previousCursor()?->encode(),
                'has_more_pages' => $reels->hasMorePages(),
            ],
        ]);
    }
}
