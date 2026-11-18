
@php
    $hasRates   = ! empty($fx['rates']);
    $hasPending = ! empty($fx['pending']);
@endphp

@if(! $fx['base_currency'])
    <div class="empty py-3">
        <p class="empty-title mb-1">{{ __('labels.fx_no_base_currency_title') }}</p>
        <p class="empty-subtitle text-muted mb-0">
            {{ __('labels.fx_no_base_currency_hint') }}
        </p>
    </div>
@elseif(! $hasRates && ! $hasPending)
    <div class="empty py-3">
        <p class="empty-title mb-1">{{ __('labels.fx_no_markets_title') }}</p>
        <p class="empty-subtitle text-muted mb-0">
            {{ __('labels.fx_no_markets_hint') }}
        </p>
    </div>
@else
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>{{ __('labels.market') }}</th>
                    <th>{{ __('labels.pair') }}</th>
                    <th class="text-end">{{ __('labels.rate') }}</th>
                    <th>{{ __('labels.valid_from') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($fx['rates'] as $r)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $r['market_name'] }}</div>
                            <small class="text-muted">{{ $r['market_code'] }}</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary-lt fs-3">{{ $r['from_currency'] }}</span>
                            <span class="mx-1 text-muted">→</span>
                            <span class="badge bg-blue-lt fs-3">{{ $r['to_currency'] }}</span>
                        </td>
                        <td class="text-end">
                            @if($r['from_currency'] === $r['to_currency'])
                                <span class="text-muted">1.000000</span>
                            @else
                                <span class="fw-semibold">{{ number_format($r['rate'], 6) }}</span>
                                <div class="text-muted small">
                                    {{ __('labels.fx_one_unit', [
                                        'amount' => number_format($r['rate'], 4),
                                        'from'   => $r['from_currency'],
                                        'to'     => $r['to_currency'],
                                    ]) }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $r['valid_from'] ?? '—' }}</td>
                    </tr>
                @endforeach

                @foreach($fx['pending'] as $p)
                    <tr class="text-muted">
                        <td>
                            <div class="fw-semibold">{{ $p['market_name'] }}</div>
                            <small>{{ $p['market_code'] }}</small>
                        </td>
                        <td>
                            <span class="badge bg-secondary-lt fs-3">{{ $fx['base_currency'] }}</span>
                            <span class="mx-1">→</span>
                            <span class="badge bg-yellow-lt fs-3">{{ $p['currency'] }}</span>
                        </td>
                        <td class="text-end">
                            <span class="badge bg-yellow-lt">{{ __('labels.fx_pending') }}</span>
                        </td>
                        <td>—</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
