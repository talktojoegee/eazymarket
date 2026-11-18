<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveSellerLandingPageRequest;
use App\Models\LandingPage;
use App\Services\SellerLandingPagePresenter;
use App\Services\SellerLandingPageService;
use App\Services\SellerLandingRegistry;
use App\Types\Api\ApiResponseType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class SellerLandingController extends Controller
{
    use AuthorizesRequests;

    public function index(SellerLandingPageService $pages, SellerLandingRegistry $registry): View
    {
        $this->authorize('viewAny', LandingPage::class);

        return view('admin.seller-landing.index', [
            'configuration' => $pages->configuration(),
            'languages' => $registry->languages(),
            'variants' => $registry->variants(),
            'sectionTemplates' => $registry->sectionTemplates(),
        ]);
    }

    public function store(
        SaveSellerLandingPageRequest $request,
        SellerLandingPageService $pages,
        SellerLandingPagePresenter $presenter,
    ): JsonResponse {
        $this->authorize('updateAny', LandingPage::class);
        $page = $pages->save($request->configuration(), $request);

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'labels.updated_successfully',
            data: $presenter->adminConfiguration($page),
        );
    }
}
