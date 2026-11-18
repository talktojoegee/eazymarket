<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Order Flow Tester</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, Segoe UI, Roboto, sans-serif; margin: 0; background: #0f172a; color: #e2e8f0; }
        .wrap { max-width: 980px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .sub { color: #94a3b8; font-size: 13px; margin-bottom: 20px; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 10px; padding: 18px; margin-bottom: 18px; }
        label { display: block; font-size: 12px; color: #94a3b8; margin: 10px 0 4px; }
        input, select { width: 100%; padding: 9px 11px; background: #0f172a; border: 1px solid #334155; border-radius: 7px; color: #e2e8f0; font-size: 14px; }
        .row { display: flex; gap: 14px; flex-wrap: wrap; }
        .row > div { flex: 1; min-width: 160px; }
        .check { display: flex; align-items: center; gap: 8px; margin-top: 14px; }
        .check input { width: auto; }
        button { cursor: pointer; border: none; border-radius: 7px; padding: 10px 16px; font-size: 14px; font-weight: 600; margin-top: 16px; }
        .primary { background: #3b82f6; color: #fff; }
        .green { background: #10b981; color: #fff; }
        .red { background: #ef4444; color: #fff; }
        .ghost { background: #334155; color: #e2e8f0; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th, td { text-align: left; padding: 7px 9px; border-bottom: 1px solid #334155; }
        th { color: #94a3b8; font-weight: 600; }
        .pill { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .pill.pending { background: #78350f; color: #fde68a; }
        .pill.completed { background: #064e3b; color: #6ee7b7; }
        .pill.failed { background: #7f1d1d; color: #fecaca; }
        .pill.reserved { background: #1e3a8a; color: #bfdbfe; }
        .pill.committed { background: #064e3b; color: #6ee7b7; }
        .pill.released { background: #713f12; color: #fde68a; }
        .kv { display: flex; gap: 8px; font-size: 13px; padding: 3px 0; }
        .kv b { color: #94a3b8; font-weight: 600; min-width: 130px; }
        pre { background: #0f172a; border: 1px solid #334155; border-radius: 7px; padding: 12px; overflow-x: auto; font-size: 12px; }
        .muted { color: #64748b; font-size: 12px; margin-top: 6px; }
        #card-el { background: #fff; padding: 11px; border-radius: 7px; margin-top: 10px; }
    </style>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body>
<div class="wrap">
    <h1>Order Flow Tester</h1>
    <div class="sub">Local-only harness for the order-first payment flow. Place an order as a user, then simulate the payment webhook and watch stock reserve → commit / release.</div>

    <div class="card">
        <div class="row">
            <div>
                <label>User ID</label>
                <input id="user_id" type="number" placeholder="e.g. 1">
            </div>
            <div>
                <label>Payment method</label>
                <select id="payment_type">
                    <option value="stripePayment">Stripe (order-first)</option>
                    <option value="cod">Cash on Delivery</option>
                    <option value="wallet">Wallet</option>
                    <option value="flutterwavePayment">Flutterwave (order-first)</option>
                    <option value="razorpayPayment">Razorpay (pay-first, not migrated)</option>
                    <option value="paystackPayment">Paystack (pay-first, not migrated)</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div>
                <label>Address ID (blank = user's first address)</label>
                <input id="address_id" type="number" placeholder="auto">
            </div>
            <div>
                <label>Idempotency key (optional)</label>
                <input id="idempotency_key" type="text" placeholder="reuse to test dedup">
            </div>
        </div>
        <div class="check">
            <input id="use_wallet" type="checkbox">
            <label for="use_wallet" style="margin:0">Use wallet balance</label>
        </div>
        <button type="button" class="primary" onclick="placeOrder(event)">Place Order</button>
        <div class="muted">The user must have a cart with items and at least one address. Stripe/COD/Wallet/Flutterwave work here; Razorpay/Paystack still expect a pre-paid transaction_id.</div>
    </div>

    <div id="result"></div>
</div>

<script>
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    async function api(method, url, body) {
        try {
            const res = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: body ? JSON.stringify(body) : undefined,
            });
            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                return { success: false, message: `Non-JSON response (HTTP ${res.status})`, raw: text.slice(0, 800) };
            }
        } catch (e) {
            return { success: false, message: 'Request failed: ' + e.message };
        }
    }

    async function placeOrder(e) {
        if (e) e.preventDefault();
        const payload = {
            user_id: document.getElementById('user_id').value,
            payment_type: document.getElementById('payment_type').value,
            address_id: document.getElementById('address_id').value || null,
            idempotency_key: document.getElementById('idempotency_key').value || null,
            use_wallet: document.getElementById('use_wallet').checked,
        };
        const data = await api('POST', '{{ url('dev/order-tester/place') }}', payload);
        render(data);
    }

    async function capture(id) { render(await api('POST', `{{ url('dev/order-tester') }}/${id}/capture`)); }
    async function fail(id) { render(await api('POST', `{{ url('dev/order-tester') }}/${id}/fail`)); }
    async function retry(id) { render(await api('POST', `{{ url('dev/order-tester') }}/${id}/retry`)); }
    async function cancelPay(id) { render(await api('POST', `{{ url('dev/order-tester') }}/${id}/cancel-payment`)); }
    async function refresh(id) { render(await api('GET', `{{ url('dev/order-tester') }}/${id}/snapshot`)); }

    function pill(v) { return v ? `<span class="pill ${v}">${v}</span>` : '<span class="muted">—</span>'; }

    let lastStripePk = null;

    function render(data) {
        if (data && 'stripe_pk' in data) lastStripePk = data.stripe_pk;
        const el = document.getElementById('result');
        if (!data.success) {
            el.innerHTML = `<div class="card"><b style="color:#fca5a5">Failed:</b> ${data.message || 'error'}<pre>${JSON.stringify(data, null, 2)}</pre></div>`;
            return;
        }
        const o = data.order;
        if (!o || !o.id) {
            el.innerHTML = `<div class="card"><b style="color:#6ee7b7">OK.</b> ${data.message || ''}<pre>${JSON.stringify(data, null, 2)}</pre></div>`;
            return;
        }
        const rows = (o.items || []).map(i => `
            <tr>
                <td>${i.product_variant_id}</td>
                <td>${i.quantity}</td>
                <td>${i.stock ?? '—'}</td>
                <td>${i.reserved ?? '—'}</td>
                <td>${i.available ?? '—'}</td>
                <td>${i.item_status ?? '—'}</td>
            </tr>`).join('');
        const t = o.transaction;
        el.innerHTML = `
            <div class="card">
                <div class="kv"><b>Order</b> #${o.id} (${o.slug ?? ''})</div>
                <div class="kv"><b>Payment method</b> ${o.payment_method}</div>
                <div class="kv"><b>Payment status</b> ${pill(o.payment_status)}</div>
                <div class="kv"><b>Order status</b> ${o.status}</div>
                <div class="kv"><b>Stock status</b> ${pill(o.stock_status)}</div>
                <div class="kv"><b>Total payable</b> ${o.total_payable} ${o.currency_code}</div>
                <div class="kv"><b>payment_response</b> <code>${o.payment_response ? JSON.stringify(o.payment_response) : '—'}</code></div>
                ${t ? `<div class="kv"><b>Txn</b> #${t.id} · ${t.transaction_id ?? '—'} · ${pill(t.payment_status)} · ${t.amount} ${t.currency}</div>` : ''}
                <div id="pay-area"></div>
                <table>
                    <thead><tr><th>Variant</th><th>Qty</th><th>Stock</th><th>Reserved</th><th>Available</th><th>Item</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
                <div class="actions">
                    <button type="button" class="green" onclick="capture(${o.id})">Simulate payment success</button>
                    <button type="button" class="red" onclick="fail(${o.id})">Simulate payment failed</button>
                    <button type="button" class="primary" onclick="retry(${o.id})">Retry payment</button>
                    <button type="button" class="ghost" onclick="refresh(${o.id})">Refresh</button>
                </div>
                <div class="muted">"Pay now" opens the real gateway (test mode). After paying, the gateway webhook captures the order server-side — hit Refresh to see it flip to completed. "Simulate payment success" bypasses the gateway entirely.</div>
            </div>`;
        mountPay(o);
    }

    function mountPay(o) {
        const area = document.getElementById('pay-area');
        if (!area) return;
        const pr = o.payment_response || {};
        if (o.payment_status !== 'pending' || !pr) { area.innerHTML = ''; return; }

        if (o.payment_method === 'razorpayPayment') {
            area.innerHTML = `<button type="button" class="primary" onclick="payRazorpay(${o.id})">Pay now (Razorpay)</button>`;
            window['pr_' + o.id] = pr;
        } else if (o.payment_method === 'paystackPayment') {
            const url = pr.authorization_url;
            area.innerHTML = url ? `<button type="button" class="primary" onclick="window.open('${url}','_blank')">Pay now (Paystack) — opens tab</button>` : '';
        } else if (o.payment_method === 'flutterwavePayment') {
            const url = pr.link;
            area.innerHTML = url ? `<button type="button" class="primary" onclick="window.open('${url}','_blank')">Pay now (Flutterwave) — opens tab</button>` : '';
        } else if (o.payment_method === 'stripePayment') {
            if (!lastStripePk) { area.innerHTML = '<div class="muted">Stripe publishable key not configured.</div>'; return; }
            area.innerHTML = `<div id="card-el"></div><button type="button" class="primary" id="stripe-pay">Pay now (Stripe)</button><div class="muted">Test card 4242 4242 4242 4242 · any future date · any CVC.</div>`;
            const stripe = Stripe(lastStripePk);
            const card = stripe.elements().create('card');
            card.mount('#card-el');
            const errBox = document.createElement('div');
            errBox.className = 'muted';
            area.appendChild(errBox);
            document.getElementById('stripe-pay').onclick = async () => {
                errBox.textContent = 'Processing…';
                const res = await stripe.confirmCardPayment(pr.clientSecret, { payment_method: { card } });
                if (res.error) { errBox.textContent = res.error.message; return; }
                area.innerHTML = '<div class="muted">Payment submitted. Webhook is capturing — click Refresh in a few seconds.</div>';
            };
        } else {
            area.innerHTML = '';
        }

        if (['razorpayPayment', 'stripePayment', 'paystackPayment', 'flutterwavePayment'].includes(o.payment_method) && o.payment_status === 'pending' && area.innerHTML) {
            area.insertAdjacentHTML('beforeend', `<div style="margin-top:8px"><button type="button" class="red" onclick="cancelPay(${o.id})">Cancel payment</button></div>`);
        }
    }

    function payRazorpay(id) {
        const pr = window['pr_' + id];
        const rzp = new Razorpay({
            key: pr.key_id,
            order_id: pr.razorpay_order_id,
            amount: pr.amount,
            currency: pr.currency,
            name: 'Order Tester',
            handler: function () {
                document.getElementById('pay-area').innerHTML = '<div class="muted">Payment done. Webhook is capturing — click Refresh in a few seconds.</div>';
            },
            modal: { ondismiss: function () { cancelPay(id); } },
        });
        rzp.open();
    }
</script>
</body>
</html>
