<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialized shape consumed by the customer web (Next.js) and customer/seller
 * Flutter apps. The `code` field is the stable public identifier — mobile
 * clients persist this in Hive / cookies, not the numeric `id`.
 */
class MarketResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'code'             => $this->code,
            'name'             => $this->name,
            'currency_code'    => $this->currency_code,
            'default_language' => $this->default_language,
            'is_default'       => (bool) $this->is_default,
            'priority'         => (int) $this->priority,
            'status'           => $this->status,
            'countries'        => $this->whenLoaded(
                'countries',
                fn () => $this->countries->map(fn ($c) => [
                    'id'   => $c->id,
                    'iso2' => $c->iso2,
                    'name' => $c->name,
                ])->values(),
            ),
            'currency'         => new CurrencyResource($this->whenLoaded('currency')),

            'format'           => [
                'symbol_position'      => $this->symbol_position ?? 'before',
                'space_between_symbol' => (bool) ($this->space_between_symbol ?? false),
                'thousand_separator'   => (string) ($this->thousand_separator ?? ','),
                'decimal_separator'    => (string) ($this->decimal_separator ?? '.'),
                'grouping_style'       => $this->grouping_style ?? 'western',
                'decimal_places'       => $this->resource->resolveDecimals(),
                'negative_format'      => $this->negative_format ?: '-{n}',
            ],
        ];
    }
}
