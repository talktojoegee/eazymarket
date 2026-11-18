<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OnboardingPageResource;
use App\Models\OnboardingPage;
use App\Types\Api\ApiResponseType;
use App\Services\OnboardingPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingPageApiController extends Controller
{
    public function index(Request $request, OnboardingPageService $onboardingPages): JsonResponse
    {
        $data = $request->validate([
            'surface' => ['required', Rule::in(['customer_app', 'seller_app'])],
        ]);

        if (! $onboardingPages->isEnabled($data['surface'])) {
            return ApiResponseType::sendJsonResponse(true, 'labels.onboarding_pages_fetched', []);
        }

        $pages = OnboardingPage::query()
            ->where('surface', $data['surface'])
            ->orderBy('id')
            ->get();

        $skipEnabled = $onboardingPages->isSkipEnabled($data['surface']);
        $data = array_map(
            fn (array $page): array => [...$page, 'show_skip' => $skipEnabled],
            OnboardingPageResource::collection($pages)->resolve($request)
        );

        return ApiResponseType::sendJsonResponse(true, 'labels.onboarding_pages_fetched', $data);
    }
}
