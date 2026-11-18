<?php

namespace App\Http\Requests\Concerns;

use App\Types\Api\ApiResponseType;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

trait RespondsWithApiEnvelope
{
    protected function failedValidation(Validator $validator): never
    {
        if ($this->expectsJson()) {
            throw new HttpResponseException(ApiResponseType::sendJsonResponse(
                false,
                'labels.validation_failed',
                ['errors' => $validator->errors()->toArray()],
                422,
                true,
            ));
        }

        parent::failedValidation($validator);
    }
}
