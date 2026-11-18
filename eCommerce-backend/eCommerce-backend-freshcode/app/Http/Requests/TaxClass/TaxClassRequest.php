<?php

namespace App\Http\Requests\TaxClass;

use App\Models\TaxClass;
use App\Services\MarketService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The tax-class screen is scoped to the admin's selected market, so the form
     * no longer posts a market. Editing keeps the record's own market.
     */
    protected function prepareForValidation(): void
    {
        $id = $this->route('id') ?? $this->route('tax_class');
        $marketId = $id
            ? TaxClass::whereKey($id)->value('market_id')
            : null;

        $this->merge([
            'market_id' => $marketId ?? app(MarketService::class)->adminSelectedId(),
        ]);
    }

    public function rules(): array
    {
        $id       = $this->route('id') ?? $this->route('tax_class');
        $marketId = (int) $this->input('market_id');

        return [
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tax_classes', 'title')
                    ->where(fn ($q) => $q->where('market_id', $marketId))
                    ->ignore($id),
            ],
            'market_id'      => ['required', 'integer', 'exists:markets,id'],
            'tax_rate_ids'   => ['required', 'array', 'min:1'],
            'tax_rate_ids.*' => ['exists:tax_rates,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'market_id.required' => __('labels.tax_class_market_required'),
            'market_id.exists'   => __('labels.tax_class_market_invalid'),
            'title.unique'       => __('labels.tax_class_title_unique_in_market'),
        ];
    }
}
