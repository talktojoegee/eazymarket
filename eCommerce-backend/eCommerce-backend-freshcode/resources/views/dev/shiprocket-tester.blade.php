<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Shiprocket Tester</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: #94a3b8; font-size: 13px; margin-bottom: 20px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 18px; margin-bottom: 18px; }
        h2 { font-size: 14px; margin: 0 0 10px; color: #cbd5e1; }
        label { display: block; font-size: 12px; color: #94a3b8; margin: 10px 0 4px; }
        input, select { padding: 9px 11px; background: #0f172a; border: 1px solid #334155; border-radius: 7px; color: #e2e8f0; font-size: 14px; }
        button { cursor: pointer; border: none; border-radius: 7px; padding: 8px 14px; font-size: 13px; font-weight: 600; }
        .primary { background: #3b82f6; color: #fff; }
        .green { background: #10b981; color: #fff; }
        .red { background: #ef4444; color: #fff; }
        .amber { background: #d97706; color: #fff; }
        .ghost { background: #334155; color: #e2e8f0; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th, td { text-align: left; padding: 7px 9px; border-bottom: 1px solid #334155; vertical-align: top; }
        th { color: #94a3b8; font-weight: 600; }
        .pill { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #1e3a8a; color: #bfdbfe; }
        .pill.delivered { background: #064e3b; color: #6ee7b7; }
        .pill.pending { background: #78350f; color: #fde68a; }
        .pill.cancelled { background: #7f1d1d; color: #fecaca; }
        pre { background: #0f172a; border: 1px solid #334155; border-radius: 7px; padding: 12px; overflow-x: auto; font-size: 11px; max-height: 420px; overflow-y: auto; }
        .muted { color: #64748b; font-size: 12px; margin-top: 6px; }
        .badge-fake { padding: 3px 10px; border-radius: 999px; font-weight: 700; font-size: 12px; }
        .on { background: #064e3b; color: #6ee7b7; }
        .off { background: #7f1d1d; color: #fecaca; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Shiprocket Tester</h1>
    <div class="sub">Local harness: fake the Shiprocket API end to end — book a parcel for a seller order, walk it pickup → delivered via real webhook payloads, then run the return leg. Every exchange is logged in Shiprocket's exact payload/response shapes.</div>

    <div class="card">
        <h2>Fake mode <span id="fakeBadge" class="badge-fake off">…</span></h2>
        <div class="actions">
            <button class="green" onclick="toggleFake(true, false)">Enable fake Shiprocket</button>
            <button class="green" onclick="toggleFake(true, true)">Enable + reset state</button>
            <button class="red" onclick="toggleFake(false, false)">Disable (use real API)</button>
        </div>
        <div class="muted">When enabled, every Shiprocket call from the whole app (fulfil buttons, retry, sync, poll cron, returns) hits the simulator instead of the real API. Real API is used when disabled. Marker: <code>storage/shiprocket-fake</code>.</div>
    </div>

    <div class="card">
        <h2>Seller order</h2>
        <div class="actions">
            <input id="sellerOrderId" type="number" placeholder="Seller order ID" style="width:180px">
            <button class="primary" onclick="loadSnapshot()">Load</button>
            <button class="ghost" onclick="createParcel()">Create parcel (all remaining)</button>
            <button class="amber" onclick="createReturn()">Create + approve return (1 unit, first line)</button>
        </div>
        <div id="soSummary" class="muted"></div>
        <table id="shipmentsTable" style="display:none">
            <thead><tr><th>#</th><th>Type</th><th>Status</th><th>AWB / Courier</th><th>Pickup</th><th>Items</th><th>Drive</th></tr></thead>
            <tbody></tbody>
        </table>
        <div id="returnsBox" class="muted"></div>
    </div>

    <div class="card">
        <h2>Last action — webhook payload &amp; response</h2>
        <pre id="lastAction">—</pre>
    </div>

    <div class="card">
        <h2>Fake Shiprocket exchange log (newest first)</h2>
        <pre id="fakeLog">—</pre>
    </div>
</div>

<script>
const csrf = document.querySelector('meta[name="csrf-token"]').content;
const EVENTS = ['PICKED UP', 'IN TRANSIT', 'OUT FOR DELIVERY', 'DELIVERED', 'UNDELIVERED', 'CANCELED', 'RTO INITIATED', 'RTO DELIVERED'];

async function api(method, url, body) {
    const res = await fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: body ? JSON.stringify(body) : undefined,
    });
    return res.json();
}

function soId() { return document.getElementById('sellerOrderId').value; }

function show(el, data) { document.getElementById(el).textContent = JSON.stringify(data, null, 2); }

async function toggleFake(enable, reset) {
    const data = await api('POST', '/dev/shiprocket-tester/toggle-fake', { enable: enable, reset: reset });
    renderFakeBadge(data.fake);
    show('lastAction', data);
}

function renderFakeBadge(on) {
    const badge = document.getElementById('fakeBadge');
    badge.textContent = on ? 'ON — simulated' : 'OFF — real API';
    badge.className = 'badge-fake ' + (on ? 'on' : 'off');
}

async function loadSnapshot() {
    if (!soId()) return;
    const data = await api('GET', '/dev/shiprocket-tester/' + soId() + '/snapshot');
    if (!data.success) { show('lastAction', data); return; }
    renderFakeBadge(data.fake);

    const so = data.seller_order;
    document.getElementById('soSummary').textContent =
        'Seller order #' + so.id + ' · order #' + so.order_id + ' · payment ' + so.payment_method +
        ' · fulfillment: ' + so.fulfillment_status + ' · order status: ' + so.order_status;

    const table = document.getElementById('shipmentsTable');
    const tbody = table.querySelector('tbody');
    tbody.innerHTML = '';
    (data.shipments || []).forEach(function (s) {
        const tr = document.createElement('tr');
        const pillClass = s.status === 'delivered' ? 'delivered' : (s.status === 'pending' ? 'pending' : (s.status === 'cancelled' ? 'cancelled' : ''));
        let drive = '<select id="ev-' + s.id + '">' + EVENTS.map(e => '<option>' + e + '</option>').join('') + '</select> ' +
            '<button class="primary" onclick="fireEvent(' + s.id + ')">Webhook</button> ' +
            '<button class="ghost" onclick="syncShipment(' + s.id + ')">Sync</button>';
        tr.innerHTML =
            '<td>' + s.id + '</td>' +
            '<td>' + (s.is_return ? 'RETURN' : 'forward') + '</td>' +
            '<td><span class="pill ' + pillClass + '">' + s.status + '</span></td>' +
            '<td>' + (s.awb || '—') + '<br><span class="muted">' + (s.courier || '') + '</span></td>' +
            '<td>' + (s.pickup_at || '—') + '</td>' +
            '<td>' + (s.items || []).join('<br>') + '</td>' +
            '<td class="actions">' + drive + '</td>';
        tbody.appendChild(tr);
    });
    table.style.display = (data.shipments || []).length ? '' : 'none';

    document.getElementById('returnsBox').textContent = (data.returns || []).length
        ? 'Returns: ' + data.returns.map(r => '#' + r.id + ' item ' + r.order_item_id + ' → ' + r.status + (r.tracking ? ' (awb ' + r.tracking + ')' : '')).join(' · ')
        : '';

    show('fakeLog', data.fake_log || []);
}

async function createParcel() {
    if (!soId()) return;
    const data = await api('POST', '/dev/shiprocket-tester/' + soId() + '/create-parcel', {});
    show('lastAction', data);
    loadSnapshot();
}

async function createReturn() {
    if (!soId()) return;
    const data = await api('POST', '/dev/shiprocket-tester/' + soId() + '/create-return', {});
    show('lastAction', data);
    loadSnapshot();
}

async function fireEvent(shipmentId) {
    const status = document.getElementById('ev-' + shipmentId).value;
    const data = await api('POST', '/dev/shiprocket-tester/shipments/' + shipmentId + '/event', { status: status });
    show('lastAction', data);
    loadSnapshot();
}

async function syncShipment(shipmentId) {
    const data = await api('POST', '/dev/shiprocket-tester/shipments/' + shipmentId + '/sync', {});
    show('lastAction', data);
    loadSnapshot();
}
</script>
</body>
</html>
