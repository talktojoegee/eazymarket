<?php

namespace App\Http\Controllers\Api;

use App\Enums\LanguageSurfaceEnum;
use App\Http\Controllers\Controller;
use App\Services\ClientLanguageService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Languages')]
class LanguageApiController extends Controller
{
    public function languages(Request $request, ClientLanguageService $languages): JsonResponse
    {
        $data = $request->validate([
            'surface' => ['required', Rule::in(LanguageSurfaceEnum::clientValues())],
        ]);
        $surface = LanguageSurfaceEnum::from($data['surface']);
        $available = $languages->languages($surface);
        $default = $languages->defaultLanguage($surface);

        return ApiResponseType::sendJsonResponse(true, 'labels.languages_fetched_successfully', [
            'default' => $default?->code,
            'languages' => $available->map(fn ($language) => [
                'code' => $language->code,
                'name' => $language->name,
                'native_name' => $language->native_name,
                'direction' => $language->direction,
            ])->values()->all(),
        ]);
    }

    public function labels(Request $request, ClientLanguageService $languages): JsonResponse
    {
        $data = $request->validate([
            'surface' => ['required', Rule::in(LanguageSurfaceEnum::clientValues())],
            'locale' => ['nullable', 'string', 'max:20'],
        ]);
        $surface = LanguageSurfaceEnum::from($data['surface']);
        $language = $languages->resolveLanguage($surface, $data['locale'] ?? null);

        if (! $language) {
            return ApiResponseType::sendJsonResponse(false, 'labels.language_not_available', null, 404);
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.language_labels_fetched_successfully', [
            'locale' => $language->code,
            'direction' => $language->direction,
            'labels' => $languages->resolvedLabels($surface, $language),
        ]);
    }
}
