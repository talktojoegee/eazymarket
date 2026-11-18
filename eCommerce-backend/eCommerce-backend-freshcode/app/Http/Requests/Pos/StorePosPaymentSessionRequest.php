<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class StorePosPaymentSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'                => ['required', 'integer'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.store_product_variant_id' => ['required', 'integer'],
            'items.*.quantity'        => ['required', 'integer', 'min:1'],
            'items.*.addons'                  => ['sometimes', 'array'],
            'items.*.addons.*.addon_group_id' => ['required_with:items.*.addons', 'integer'],
            'items.*.addons.*.addon_item_id'  => ['required_with:items.*.addons', 'integer'],
            'customer_id'             => ['nullable', 'integer'],
            'walkin_customer_name'    => ['nullable', 'string', 'max:255'],
            'walkin_customer_mobile'  => ['nullable', 'string', 'max:32'],
            'order_note'              => ['nullable', 'string', 'max:500'],
            'amount'                  => ['required', 'numeric', 'min:1'],
            'cash_portion'            => ['nullable', 'numeric', 'min:0'],
            'discount_type'           => ['nullable', 'in:percent,fixed'],
            'discount_value'          => ['nullable', 'numeric', 'min:0'],
            'promo_code'              => ['nullable', 'string', 'max:64'],
            'wallet_amount'           => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'store_id'                           => __('labels.validation_attribute_store'),
            'items'                              => __('labels.validation_attribute_order_items'),
            'items.*.store_product_variant_id'   => __('labels.validation_attribute_product_variant'),
            'items.*.quantity'                   => __('labels.validation_attribute_item_quantity'),
            'items.*.addons'                     => __('labels.validation_attribute_item_add_ons'),
            'items.*.addons.*.addon_group_id'    => __('labels.validation_attribute_add_on_group'),
            'items.*.addons.*.addon_item_id'     => __('labels.validation_attribute_add_on_item'),
            'customer_id'                        => __('labels.validation_attribute_customer'),
            'walkin_customer_name'               => __('labels.validation_attribute_walk_in_customer_name'),
            'walkin_customer_mobile'             => __('labels.validation_attribute_walk_in_customer_mobile'),
            'order_note'                         => __('labels.validation_attribute_order_note'),
            'amount'                             => __('labels.validation_attribute_session_amount'),
            'cash_portion'                       => __('labels.validation_attribute_cash_portion'),
            'discount_type'                      => __('labels.validation_attribute_discount_type'),
            'discount_value'                     => __('labels.validation_attribute_discount_value'),
            'promo_code'                         => __('labels.validation_attribute_promo_code'),
            'wallet_amount'                      => __('labels.validation_attribute_wallet_amount'),
        ];
    }
}
