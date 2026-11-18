<?php

namespace App\Http\Requests\Pos;

use Illuminate\Foundation\Http\FormRequest;

class CreatePosOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id'                => ['required', 'integer'],
            'payment_method'          => ['required', 'string', 'in:cash,upi,custom,razorpay,stripe,paystack,flutterwave'],
            'custom_payment_method_name' => ['nullable', 'required_if:payment_method,custom', 'string', 'max:50'],
            'customer_id'             => ['nullable', 'integer'],
            'walkin_customer_name'    => ['nullable', 'string', 'max:255'],
            'walkin_customer_mobile'  => ['nullable', 'string', 'max:32'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.store_product_variant_id' => ['required', 'integer'],
            'items.*.quantity'        => ['required', 'integer', 'min:1'],
            'items.*.addons'                  => ['sometimes', 'array'],
            'items.*.addons.*.addon_group_id' => ['required_with:items.*.addons', 'integer'],
            'items.*.addons.*.addon_item_id'  => ['required_with:items.*.addons', 'integer'],
            'order_note'              => ['nullable', 'string', 'max:500'],

            'discount_type'           => ['nullable', 'in:percent,fixed'],
            'discount_value'          => ['nullable', 'numeric', 'min:0'],

            'promo_code'              => ['nullable', 'string', 'max:64'],

            'wallet_amount'           => ['nullable', 'numeric', 'min:0'],

            'split'                   => ['nullable', 'array'],
            'split.cash'              => ['nullable', 'numeric', 'min:0'],
            'split.gateway_amount'    => ['nullable', 'numeric', 'min:0'],

            'split_cash_portion'      => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'store_id'                           => __('labels.validation_attribute_store'),
            'payment_method'                     => __('labels.validation_attribute_payment_method'),
            'customer_id'                        => __('labels.validation_attribute_customer'),
            'walkin_customer_name'               => __('labels.validation_attribute_walk_in_customer_name'),
            'walkin_customer_mobile'             => __('labels.validation_attribute_walk_in_customer_mobile'),
            'items'                              => __('labels.validation_attribute_order_items'),
            'items.*.store_product_variant_id'   => __('labels.validation_attribute_product_variant'),
            'items.*.quantity'                   => __('labels.validation_attribute_item_quantity'),
            'items.*.addons'                     => __('labels.validation_attribute_item_add_ons'),
            'items.*.addons.*.addon_group_id'    => __('labels.validation_attribute_add_on_group'),
            'items.*.addons.*.addon_item_id'     => __('labels.validation_attribute_add_on_item'),
            'order_note'                         => __('labels.validation_attribute_order_note'),
            'discount_type'                      => __('labels.validation_attribute_discount_type'),
            'discount_value'                     => __('labels.validation_attribute_discount_value'),
            'promo_code'                         => __('labels.validation_attribute_promo_code'),
            'wallet_amount'                      => __('labels.validation_attribute_wallet_amount'),
            'split.cash'                         => __('labels.validation_attribute_split_cash_amount'),
            'split.gateway_amount'               => __('labels.validation_attribute_split_gateway_amount'),
            'split_cash_portion'                 => __('labels.validation_attribute_split_cash_portion'),
            'custom_payment_method_name'         => __('labels.validation_attribute_custom_payment_method_name'),
        ];
    }
}
