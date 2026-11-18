@php
    $autoSettleOn = \App\Support\SettlementSettings::autoSettleEnabled();
    $clearingDays = \App\Support\SettlementSettings::clearingDays();
@endphp
<div id="earnings-summary">
    @if($isAdmin ?? false)
        <div class="card card-sm mb-3">
            <div class="card-status-start {{ $autoSettleOn ? 'bg-green' : 'bg-secondary' }}"></div>
            <div class="card-body">
                <div class="row align-items-center g-3">
                    <div class="col-auto">
                    <span class="badge {{ $autoSettleOn ? 'bg-green-lt' : 'bg-secondary-lt' }} badge-lg">
                        <span
                            class="status-dot {{ $autoSettleOn ? 'status-dot-animated bg-green' : 'bg-secondary' }} me-1"></span>
                        {{ $autoSettleOn ? __('labels.auto_settle_on') : __('labels.auto_settle_off') }}
                    </span>
                    </div>
                    <div class="col">
                        <div class="text-secondary small">
                            @if($autoSettleOn)
                                {{ __('messages.auto_settle_enabled_info') }}
                            @else
                                {{ __('labels.settled_manually') }} — {{ __('messages.auto_settle_enabled_info') }}
                            @endif
                        </div>
                        <div class="text-secondary small mt-1">
                            {{ __('labels.settlement_clearing_days') }}:
                            <span class="fw-medium">{{ $clearingDays }} {{ __('labels.days') }}</span>
                        </div>
                    </div>
                    <div class="col-auto text-end">
                        <div class="subheader">{{ __('labels.ready_to_settle') }}</div>
                        <div class="h2 mb-0 text-green" id="sum-matured">—</div>
                        <div class="text-secondary small"><span
                                id="sum-matured-count">0</span> {{ __('labels.entries') }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row row-cards mb-3">
        <div class="col-sm-6 col-lg-3 mt-0">
            <div class="card card-sm">
                <div class="card-status-start bg-primary"></div>
                <div class="card-body">
                    <div class="subheader">{{ __('labels.pending_payouts') }}</div>
                    <div class="h1 mb-0 mt-1 text-primary" id="sum-credits">—</div>
                    <div class="text-secondary small"><span id="sum-credits-count">0</span> {{ __('labels.entries') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 mt-0">
            <div class="card card-sm">
                <div class="card-status-start bg-danger"></div>
                <div class="card-body">
                    <div class="subheader">{{ __('labels.pending_deductions') }}</div>
                    <div class="h1 mb-0 mt-1 text-danger" id="sum-debits">—</div>
                    <div class="text-secondary small"><span id="sum-debits-count">0</span> {{ __('labels.entries') }}
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 mt-0">
            <div class="card card-sm">
                <div class="card-status-start bg-azure"></div>
                <div class="card-body">
                    <div class="subheader">{{ __('labels.net_settleable') }}</div>
                    <div class="h1 mb-0 mt-1" id="sum-net">—</div>
                    <div class="text-secondary small">{{ __('labels.credits_minus_deductions') }}</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 mt-0">
            <div class="card card-sm">
                <div class="card-status-start bg-yellow"></div>
                <div class="card-body">
                    @if($isAdmin ?? false)
                        <div class="subheader">{{ __('labels.sellers_awaiting') }}</div>
                        <div class="h1 mb-0 mt-1" id="sum-sellers">—</div>
                        <div class="text-secondary small"><span
                                id="sum-pending-count">0</span> {{ __('labels.pending_entries') }}</div>
                    @else
                        <div class="subheader">{{ __('labels.pending_entries') }}</div>
                        <div class="h1 mb-0 mt-1" id="sum-pending-count">—</div>
                        <div class="text-secondary small">{{ __('labels.awaiting_settlement') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row row-cards mb-3">
        <div class="col-12">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="subheader mb-2">{{ __('labels.payouts_aging') }}</div>
                    <div class="row g-2 text-center">
                        @foreach(['0_7' => '0–7', '8_15' => '8–15', '16_30' => '16–30', '31_plus' => '31+'] as $k => $lbl)
                            <div class="col">
                                <div class="text-secondary small">{{ $lbl }} {{ __('labels.days') }}</div>
                                <div class="fw-bold" id="aging-{{ $k }}-amount">—</div>
                                <div class="text-secondary small"><span
                                        id="aging-{{ $k }}-count">0</span> {{ __('labels.entries') }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
