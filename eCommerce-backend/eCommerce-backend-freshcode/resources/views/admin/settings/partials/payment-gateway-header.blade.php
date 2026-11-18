<div class="card-header payment-gateway-card-header">
    <div class="payment-gateway-identity">
        <span class="payment-gateway-logo">
            @if(!empty($gateway['logo']))
                <img src="{{ $gateway['logo'] }}" alt="{{ $gateway['name'] }}" loading="lazy">
            @else
                <i class="ti {{ $gateway['icon'] ?? 'ti-credit-card' }}"></i>
            @endif
        </span>
        <div>
            <h4 class="card-title">{{ $gateway['name'] }}</h4>
            @if(!empty($gateway['mode_field']))
                <span class="payment-gateway-mode {{ empty($gateway['mode']) ? 'd-none' : '' }}"
                      data-payment-mode-for="{{ $gateway['mode_field'] }}">
                    {{ ucfirst($gateway['mode'] ?? '') }}
                </span>
            @endif
        </div>
    </div>
    <span class="payment-gateway-status {{ $gateway['enabled'] ? 'is-enabled' : '' }}"
          data-payment-status-for="{{ $gateway['toggle'] }}"
          data-payment-status-label
          data-enabled-label="{{ __('labels.enabled') }}"
          data-disabled-label="{{ __('labels.disabled') }}">
        <span></span>
        <strong>{{ $gateway['enabled'] ? __('labels.enabled') : __('labels.disabled') }}</strong>
    </span>
</div>
