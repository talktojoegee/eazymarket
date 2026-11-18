<?php

namespace App\Http\Resources\HomePage;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeLayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'scope_type' => $this->resource['scope_type'],
            'scope_id' => $this->resource['scope_id'],
            'current_page' => $this->resource['current_page'],
            'last_page' => $this->resource['last_page'],
            'per_page' => $this->resource['per_page'],
            'total' => $this->resource['total'],
            'sections' => HomeSectionResource::collection($this->resource['sections'] ?? [])->resolve($request),
        ];
    }
}
