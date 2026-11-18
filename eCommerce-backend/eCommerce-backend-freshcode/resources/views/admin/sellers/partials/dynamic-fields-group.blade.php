@if($fields->isNotEmpty())
    <x-dynamic-fields :fields="$fields" :values="$values ?? []" />
@else
    <div class="empty py-4">
        @if(empty($countrySelected))
            <p class="empty-title">{{ __('Select a country above to load its fields.') }}</p>
            <p class="empty-subtitle text-secondary">{{ __('Seller Form Fields lets you configure the fields for each country.') }}</p>
        @else
            <p class="empty-title">{{ $section === 'bank_details' ? __('No bank details fields configured for this country.') : __('No business document fields configured for this country.') }}</p>
            <p class="empty-subtitle text-secondary">{{ __('Use Seller Form Fields to add the fields sellers should provide.') }}</p>
        @endif
    </div>
@endif
