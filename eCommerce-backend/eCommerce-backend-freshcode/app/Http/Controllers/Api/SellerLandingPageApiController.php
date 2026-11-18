<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SellerLandingPagePresenter;
use App\Services\SellerLandingPageService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group('Seller Landing Page')]
class SellerLandingPageApiController extends Controller
{
    public function __invoke(
        SellerLandingPageService $pages,
        SellerLandingPagePresenter $presenter,
    ): JsonResponse {
        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'Seller landing page fetched successfully',
            data: $presenter->publicConfiguration($pages->get()),
        )->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
