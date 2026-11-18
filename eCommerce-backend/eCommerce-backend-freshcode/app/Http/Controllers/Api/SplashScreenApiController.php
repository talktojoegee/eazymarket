<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SplashScreenResource;
use App\Services\SplashScreenService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SplashScreenApiController extends Controller
{
    public function show(Request $request, SplashScreenService $screens): JsonResponse
    {
        $data = $request->validate([
            'surface' => ['required', Rule::in(['customer_app', 'seller_app'])],
        ]);
        $screen = $screens->effective($data['surface']);

        return ApiResponseType::sendJsonResponse(
            true,
            'labels.splash_screen_fetched',
            $screen ? (new SplashScreenResource($screen))->resolve($request) : null
        );
    }
}
