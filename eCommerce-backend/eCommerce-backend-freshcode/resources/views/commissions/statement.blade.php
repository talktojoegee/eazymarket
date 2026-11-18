<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $docTitle }} · {{ $sellerName }}</title>
    <link href="{{ asset('assets/theme/css/tabler.css') }}" rel="stylesheet"/>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 24px; background: #f1f3f5;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #16181d;
        }
        .ps-sheet { max-width: 900px; margin: 0 auto; background:#fff; border:1px solid #d9dce1;
                    box-shadow:0 1px 6px rgba(16,24,40,.10); padding: 28px 32px; }
        .ps-toolbar { max-width:900px; margin:0 auto 16px; display:flex; justify-content:flex-end; gap:8px; }
        .ps-btn-print { background:#206bc4; color:#fff; border:0; border-radius:7px; padding:8px 16px;
                        font-size:13px; font-weight:600; cursor:pointer; }
        .ps-toggle { display:inline-block; border:1px solid #d9dce1; background:#fff; color:#1a2233;
                     font-size:13px; font-weight:500; padding:7px 13px; cursor:pointer; text-decoration:none; }
        .ps-toggle + .ps-toggle { border-left:0; }
        .ps-toggle.active { background:#16181d; color:#fff; border-color:#16181d; }
        .ps-head { display:flex; justify-content:space-between; align-items:flex-start;
                   border-bottom:2px solid #16181d; padding-bottom:14px; margin-bottom:18px; }
        .ps-cap { text-transform:uppercase; letter-spacing:.06em; font-size:10px; color:#8a93a0; }
        table.ps-table { width:100%; border-collapse:collapse; font-size:12.5px; }
        table.ps-table th, table.ps-table td { padding:7px 8px; border-bottom:1px solid #e6e8eb; text-align:left; }
        table.ps-table th { background:#f8f9fa; text-transform:uppercase; letter-spacing:.04em; font-size:10px; color:#5a6472; }
        .text-end { text-align:right !important; }
        .ps-totals { margin-top:16px; margin-left:auto; width:300px; }
        .ps-totals .ps-row { display:flex; justify-content:space-between; padding:5px 0; font-size:13px; }
        .ps-totals .ps-net { border-top:2px solid #16181d; font-weight:700; font-size:15px; padding-top:8px; }
        .text-danger { color:#d63939; }
        .text-success { color:#2fb344; }
        @media print {
            @page { margin: 10mm; }
            html, body { background:#fff !important; padding:0 !important; }
            .ps-toolbar { display:none !important; }
            .ps-sheet { border:0 !important; box-shadow:none !important; max-width:none; padding:0; }
            * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
        }
    </style>
</head>
<body>
    <div class="ps-toolbar" style="justify-content:space-between;">
        <div role="group">
            <a class="ps-toggle {{ $mode === 'full' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['mode' => 'full']) }}">{{ __('labels.full_statement') }}</a>
            <a class="ps-toggle {{ $mode === 'settled' ? 'active' : '' }}" href="{{ request()->fullUrlWithQuery(['mode' => 'settled']) }}">{{ __('labels.settled_only') }}</a>
        </div>
        <button class="ps-btn-print" onclick="window.print()">{{ __('labels.print') }}</button>
    </div>

    <div class="ps-sheet">
        <div class="ps-head">
            <div>
                <div style="font-size:20px; font-weight:700;">{{ $docTitle }}</div>
                <div class="ps-cap" style="margin-top:4px;">{{ $appName }}</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:15px; font-weight:600;">{{ $sellerName }}</div>
                <div class="ps-cap" style="margin-top:4px;">{{ __('labels.period') }}: {{ $periodLabel }}</div>
                <div class="ps-cap">{{ __('labels.generated') }}: {{ $generatedAt }}</div>
            </div>
        </div>

        <table class="ps-table">
            <thead>
            <tr>
                <th>{{ __('labels.id') }}</th>
                <th>{{ __('labels.date') }}</th>
                <th>{{ __('labels.order') }} / {{ __('labels.item') }}</th>
                <th>{{ __('labels.store') }}</th>
                <th>{{ __('labels.type') }}</th>
                <th>{{ __('labels.status') }}</th>
                <th>{{ __('labels.settled_by') }}</th>
                <th>{{ __('labels.currency') }}</th>
                <th class="text-end">{{ __('labels.amount') }}</th>
            </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td>{{ $r['id'] }}</td>
                    <td>{{ $r['date'] }}</td>
                    <td>
                        <div>#{{ $r['order_id'] }}@if($r['item']) · {{ $r['item'] }}@endif</div>
                        <div class="ps-cap">{{ $r['reference'] }}</div>
                    </td>
                    <td>{{ $r['store'] ?? '—' }}</td>
                    <td>
                        <span class="{{ $r['is_debit'] ? 'text-danger' : 'text-success' }}" style="text-transform:capitalize;">
                            {{ $r['is_debit'] ? __('labels.deduction') : __('labels.payout') }}
                        </span>
                    </td>
                    <td><span style="text-transform:capitalize;" class="{{ $r['is_settled'] ? 'text-success' : '' }}">{{ $r['status_label'] }}</span></td>
                    <td>
                        @if($r['is_settled'])
                            {{ $r['settled_by'] ?? '—' }}
                            @if($r['method'])<div class="ps-cap">{{ $r['method'] }}</div>@endif
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $r['currency'] ?? '—' }}</td>
                    <td class="text-end {{ $r['is_debit'] ? 'text-danger' : '' }}">{{ $r['is_debit'] ? '− ' : '' }}{{ $r['amount_formatted'] }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center; color:#8a93a0; padding:24px;">{{ __('labels.no_data_available') }}</td></tr>
            @endforelse
            </tbody>
        </table>

        <div class="ps-totals">
            <div class="ps-row"><span>{{ __('labels.total_payouts') }}</span><span class="text-success">{{ $creditTotalFormatted }}</span></div>
            <div class="ps-row"><span>{{ __('labels.total_deductions') }}</span><span class="text-danger">− {{ $debitTotalFormatted }}</span></div>
            <div class="ps-row ps-net"><span>{{ __('labels.net_total') }}</span><span>{{ $netTotalFormatted }}</span></div>
            @if($mode === 'full')
                <div class="ps-row"><span class="text-secondary">{{ __('labels.net_settled') }}</span><span class="text-secondary">{{ $settledNetFormatted }}</span></div>
            @endif
        </div>
    </div>
</body>
</html>
