@php
    $panelAlerts = collect([
        ['type' => 'success', 'title' => __('labels.success'), 'message' => session('success'), 'timer' => 4000, 'icon' => 'ti-circle-check'],
        ['type' => 'info', 'title' => __('labels.information'), 'message' => session('info'), 'timer' => 6000, 'icon' => 'ti-info-circle'],
        ['type' => 'warning', 'title' => __('labels.warning'), 'message' => session('warning'), 'timer' => 0, 'icon' => 'ti-alert-triangle'],
        ['type' => 'error', 'title' => __('labels.error'), 'message' => session('error'), 'timer' => 0, 'icon' => 'ti-alert-circle'],
    ])->filter(fn (array $alert) => filled($alert['message']));
@endphp

@if($panelAlerts->isNotEmpty() || $errors->any())
    <div class="panel-alert-region" aria-label="{{ __('labels.notifications') }}">
        @foreach($panelAlerts as $alert)
            <section class="panel-alert panel-alert--{{ $alert['type'] }}"
                     data-panel-alert data-auto-dismiss="{{ $alert['timer'] }}"
                     role="{{ $alert['type'] === 'error' ? 'alert' : 'status' }}" aria-atomic="true">
                <span class="panel-alert__icon" aria-hidden="true"><i class="ti {{ $alert['icon'] }}"></i></span>
                <div class="panel-alert__content">
                    <strong class="panel-alert__title">{{ $alert['title'] }}</strong>
                    <div class="panel-alert__message">{{ $alert['message'] }}</div>
                </div>
                <button type="button" class="panel-alert__close" data-panel-alert-close
                        aria-label="{{ __('labels.close') }}"><i class="ti ti-x" aria-hidden="true"></i></button>
                @if($alert['timer'])
                    <span class="panel-alert__progress" aria-hidden="true"><span data-panel-alert-progress></span></span>
                @endif
            </section>
        @endforeach

        @if($errors->any())
            <section class="panel-alert panel-alert--error" data-panel-alert data-auto-dismiss="0"
                     role="alert" aria-atomic="true">
                <span class="panel-alert__icon" aria-hidden="true"><i class="ti ti-alert-circle"></i></span>
                <div class="panel-alert__content">
                    <strong class="panel-alert__title">{{ __('labels.validation_errors') }}</strong>
                    <ul class="panel-alert__list">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" class="panel-alert__close" data-panel-alert-close
                        aria-label="{{ __('labels.close') }}"><i class="ti ti-x" aria-hidden="true"></i></button>
            </section>
        @endif
    </div>
@endif
