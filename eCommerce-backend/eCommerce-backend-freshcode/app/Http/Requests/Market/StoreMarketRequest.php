<?php

namespace App\Http\Requests\Market;

use App\Enums\LanguageSurfaceEnum;
use App\Enums\Market\MarketStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreMarketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:8', 'alpha_num', 'uppercase', 'unique:markets,code'],
            'name' => ['required', 'string', 'max:64'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'default_language' => ['required', 'string', 'max:20', Rule::exists('languages', 'code')->where(fn ($query) => $query
                ->where('surface', LanguageSurfaceEnum::PANEL->value)
                ->where('is_active', true))],
            'status' => ['nullable', new Enum(MarketStatusEnum::class)],
            'priority' => ['nullable', 'integer', 'min:0'],
            'is_default' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],

            'country_iso2' => ['required', 'array', 'min:1'],
            'country_iso2.*' => ['string', 'size:2', 'exists:countries,iso2'],

            'symbol_position' => ['nullable', Rule::in(['before', 'after'])],
            'space_between_symbol' => ['nullable', 'boolean'],
            'thousand_separator' => ['nullable', 'string', 'max:3'],
            'decimal_separator' => ['nullable', 'string', 'max:3'],
            'grouping_style' => ['nullable', Rule::in(['western', 'indian'])],
            'decimal_places' => ['nullable', 'integer', 'min:0', 'max:4'],
            'negative_format' => ['nullable', 'string', 'max:16', 'regex:/\{n\}/'],

            'platform_fee' => ['nullable', 'numeric', 'min:0'],
            'cod_fee' => ['nullable', 'numeric', 'min:0'],
            'max_cod_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $iso2List = (array) $this->input('country_iso2', []);
            if (empty($iso2List)) {
                return;
            }

            $conflicts = DB::table('market_country')
                ->join('markets', 'markets.id', '=', 'market_country.market_id')
                ->whereIn('market_country.country_iso2', $iso2List)
                ->select('market_country.country_iso2', 'markets.name as market_name')
                ->get();

            foreach ($conflicts as $row) {
                $validator->errors()->add(
                    'country_iso2',
                    __('labels.country_already_in_market', [
                        'country' => $row->country_iso2,
                        'market' => $row->market_name,
                    ])
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.unique' => __('labels.market_code_unique'),
            'currency_code.exists' => __('labels.currency_code_invalid'),
            'country_iso2.required' => __('labels.market_country_required'),
            'negative_format.regex' => __('labels.negative_format_must_contain_placeholder'),
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => strtoupper($this->input('code'))]);
        }
        if ($this->has('currency_code')) {
            $this->merge(['currency_code' => strtoupper($this->input('currency_code'))]);
        }
        if ($this->has('country_iso2')) {
            $this->merge([
                'country_iso2' => array_map('strtoupper', (array) $this->input('country_iso2')),
            ]);
        }
    }
}
