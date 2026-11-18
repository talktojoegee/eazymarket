<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GlobalProductAttributeValueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'title' => data_get($this->resource, 'title'),
            'swatche_value' => data_get($this->resource, 'swatche_value'),
            'swatch_type' => data_get($this->resource, 'swatch_type'),
            'swatch_color' => data_get($this->resource, 'swatch_color'),
            'swatch_image_url' => data_get($this->resource, 'swatch_image_url'),
            'enabled' => (bool) data_get($this->resource, 'enabled', true),
        ];
    }
}
