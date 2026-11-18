<?php

namespace App\Http\Requests\Advertisement;

use Illuminate\Foundation\Http\FormRequest;

class BulkAdEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'events'               => 'required|array|min:1|max:100',
            'events.*.campaign_id' => 'required|integer|exists:ad_campaigns,id',
            'events.*.visitor_key' => 'required|string|max:64',
            'events.*.timestamp'   => 'nullable|date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'events.*.campaign_id' => __('labels.validation_attribute_campaign_id'),
            'events.*.visitor_key' => __('labels.validation_attribute_visitor_key'),
            'events.*.timestamp'   => __('labels.validation_attribute_event_timestamp'),
        ];
    }
}
