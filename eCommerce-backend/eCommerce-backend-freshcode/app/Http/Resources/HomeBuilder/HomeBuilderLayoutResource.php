<?php

namespace App\Http\Resources\HomeBuilder;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeBuilderLayoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'is_active' => (bool) $this->is_active,
            'sections' => HomeBuilderSectionResource::collection($this->sections)->resolve($request),
        ];
    }
}
