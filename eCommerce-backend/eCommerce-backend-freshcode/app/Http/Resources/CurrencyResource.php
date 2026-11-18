<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'code'           => $this->code,
            'name'           => $this->name,
            'symbol'         => $this->symbol,
            'decimal_places' => (int) $this->decimal_places,
            'is_base'        => (bool) $this->is_base,
        ];
    }
}
