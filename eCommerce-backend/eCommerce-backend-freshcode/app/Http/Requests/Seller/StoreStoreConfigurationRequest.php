<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class StoreStoreConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Configuration page validates only the fields still on the page.
     *
     * Dropped (Phase B/C cleanup):
     *  - timing                          → moves to shipping_zones.operating_hours
     *  - status                          → store_status active/inactive duplicates the verification flow
     *  - order_preparation_time          → hyperlocal kitchen prep, not relevant in ecommerce
     *  - max_delivery_distance           → hyperlocal
     *  - domestic_shipping_charges       → hyperlocal — shipping_zones on the profile owns this
     *  - international_shipping_charges  → hyperlocal — shipping_zones on the profile owns this
     *  - delivery_policy                 → hyperlocal
     */
    public function rules(): array
    {
        return [
            'description'                => 'nullable|string|max:5000',
            'about_us'                   => 'nullable|string|max:5000',
            'promotional_text'           => 'nullable|string|max:1000',
            'return_replacement_policy'  => 'required|string|max:10000',
            'refund_policy'              => 'required|string|max:10000',
            'terms_and_conditions'       => 'required|string|max:10000',

            'metadata'                   => 'nullable|array',
            'metadata.seo_title'         => 'nullable|string|max:255',
            'metadata.seo_keywords'      => 'nullable|array',
            'metadata.seo_keywords.*'    => 'nullable|string|max:60',
            'metadata.seo_description'   => 'nullable|string',

            'pos_upi_vpa'                                  => ['nullable', 'string', 'max:100'],
            'pos_upi_payee_name'                           => ['nullable', 'string', 'max:100'],
            'receipt_template'                             => ['nullable', 'array'],
            'receipt_template.footer_note'                 => ['nullable', 'string', 'max:500'],
            'pos_payment_config'                           => ['nullable', 'array'],
            'pos_payment_config.cash'                      => ['nullable', 'boolean'],
            'pos_payment_config.upi'                       => ['nullable', 'boolean'],
            'pos_payment_config.online_qr'                 => ['nullable', 'boolean'],
            'pos_payment_config.split'                     => ['nullable', 'boolean'],
            'pos_payment_config.custom_methods'            => ['nullable', 'array'],
            'pos_payment_config.custom_methods.*.name'     => ['required_with:pos_payment_config.custom_methods', 'string', 'max:50'],
            'pos_payment_config.custom_methods.*.instructions' => ['nullable', 'string', 'max:250'],
            'pos_payment_config.custom_methods.*.icon'     => ['nullable', 'string', 'max:10'],
            'pos_payment_config.custom_methods.*.enabled'  => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'return_replacement_policy.required' => __('labels.validation_message_return_and_replacement_policy_is_required'),
            'refund_policy.required'             => __('labels.validation_message_refund_policy_is_required'),
            'terms_and_conditions.required'      => __('labels.validation_message_terms_and_conditions_are_required'),
        ];
    }
}
