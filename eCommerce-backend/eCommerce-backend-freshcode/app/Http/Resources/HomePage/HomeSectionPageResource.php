<?php

namespace App\Http\Resources\HomePage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeSectionPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'current_page' => $this->resource['current_page'],
            'last_page' => $this->resource['last_page'],
            'per_page' => $this->resource['per_page'],
            'total' => $this->resource['total'],
            'data' => $this->resource['data'] ?? [],
        ];
    }
}
