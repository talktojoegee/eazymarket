<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandingPageBuilderDocument;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

#[Group('Custom Landing Pages')]
class LandingPageBuilderApiController extends Controller
{
    #[Endpoint(title: 'Get landing page')]
    public function show(string $slug): JsonResponse
    {
        try {
            $document = LandingPageBuilderDocument::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'Landing page not found',
                data: null,
                status: 404,
            );
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'Landing page fetched successfully',
            data: [
                'title' => $document->title,
                'slug' => $document->slug,
                'html' => $document->html ?? '',
                'css' => $document->css ?? '',
                'show_header_footer' => (bool) $document->show_header_footer,
                'is_active' => (bool) $document->is_active,
                'updated_at' => $document->updated_at?->toISOString(),
            ],
        );
    }
}
