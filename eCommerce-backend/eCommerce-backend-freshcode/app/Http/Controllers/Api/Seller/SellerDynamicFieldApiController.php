<?php

namespace App\Http\Controllers\Api\Seller;

use App\Models\Country;
use App\Services\DynamicSellerFieldService;
use App\Types\Api\ApiResponseType;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Seller Authentication')]
class SellerDynamicFieldApiController
{
    public function index(Request $request, DynamicSellerFieldService $dynamicFields): JsonResponse
    {
        $validated = $request->validate([
            'country_iso2' => ['required', 'string', 'size:2'],
        ]);

        $country = Country::query()
            ->whereRaw('UPPER(iso2) = ?', [strtoupper(trim($validated['country_iso2']))])
            ->first();

        if (!$country) {
            return ApiResponseType::sendJsonResponse(false, 'Country not found.', [], 404);
        }

        $fields = $dynamicFields->forCountry($country->iso2);
        $sections = [
            'bank_details' => __('labels.bank_details'),
            'business_documents' => __('labels.business_documents'),
        ];

        $data = collect($sections)->mapWithKeys(function ($label, $section) use ($fields) {
            return [$section => [
                'label' => $label,
                'fields' => $fields
                    ->where('section', $section)
                    ->values()
                    ->map(fn ($field) => [
                        'key' => $field->key,
                        'label' => $field->label,
                        'type' => $field->type,
                        'required' => $field->required,
                        'placeholder' => $field->placeholder,
                        'default_value' => $field->default_value,
                        'options' => $field->type === 'select' ? ($field->options ?? []) : [],
                    ])
                    ->all(),
            ]];
        });

        return ApiResponseType::sendJsonResponse(true, 'Seller fields retrieved successfully.', [
            'country' => [
                'name' => $country->name,
                'iso2' => $country->iso2,
            ],
            'sections' => $data->all(),
        ]);
    }
}
