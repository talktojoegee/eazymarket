@extends('layouts.admin.app', ['page' => 'invoice-builder', 'sub_page' => ''])

@section('title', __('labels.invoice_builder'))

@section('header_data')
    @php
        $page_title = __('labels.invoice_builder');
        $page_pretitle = __('labels.orders');
        $breadcrumbs = [
            ['title' => __('labels.home'), 'url' => route('admin.dashboard')],
            ['title' => __('labels.orders'), 'url' => route('admin.orders.index')],
            ['title' => $page_title, 'url' => null],
        ];
    @endphp
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ hyperAsset('assets/css/invoice-builder.css') }}">
@endpush

@section('admin-content')
    <div class="row row-cards" id="invoice-builder"
         data-save-url="{{ route('admin.orders.invoice-builder.save') }}"
         data-preview-url="{{ route('admin.orders.invoice-builder.preview') }}"
         data-csrf="{{ csrf_token() }}"
         data-config="{{ json_encode($draft, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-published="{{ json_encode($published, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG) }}"
         data-has-published="{{ $hasPublished ? '1' : '0' }}">
        <div class="col-12">
            <div class="card invoice-builder-card">
                <div class="card-header invoice-builder-header">
                    <div>
                        <h3 class="card-title">{{ $page_title }}</h3>
                        <x-breadcrumb :items="$breadcrumbs"/>
                        <div class="text-muted small" id="invoice-builder-status">Draft · {{ $hasPublished ? 'Published design available' : 'Not published yet' }}</div>
                    </div>
                    <div class="card-actions invoice-builder-actions">
                        <button class="btn btn-outline-secondary" type="button" id="invoice-undo">Undo</button>
                        <button class="btn btn-outline-secondary" type="button" id="invoice-reset">Reset to published</button>
                        <button class="btn btn-outline-primary" type="button" id="invoice-print">Print or PDF</button>
                        <button class="btn btn-primary" type="button" id="invoice-save">Save draft</button>
                        <button class="btn btn-success" type="button" id="invoice-publish">Publish</button>
                    </div>
                </div>
                <div class="invoice-builder-workspace">
                    <aside class="invoice-builder-sections" aria-label="Invoice sections">
                        <h4>Page sections</h4>
                        <p class="text-muted">Drag to reorder. Switch off sections you don’t need.</p>
                        <div id="invoice-section-list"></div>
                    </aside>
                    <section class="invoice-builder-preview-area" aria-label="Live invoice preview">
                        <div class="invoice-preview-toolbar">
                            <strong>Live A4 preview</strong>
                            <label for="invoice-zoom">Zoom</label>
                            <input id="invoice-zoom" type="range" min="35" max="100" value="55" aria-label="Preview zoom">
                            <span id="invoice-zoom-value">55%</span>
                        </div>
                        <div class="invoice-preview-scroll">
                            <article class="invoice-preview-paper" id="invoice-preview-paper">
                                <header class="preview-header" data-preview-section="header">
                                    <div class="preview-issuer">
                                        @if(!empty($systemSettings['darkLogo']) || !empty($systemSettings['logo']))
                                            <img class="preview-logo" src="{{ !empty($systemSettings['darkLogo']) ? $systemSettings['darkLogo'] : $systemSettings['logo'] }}" alt="{{ $systemSettings['appName'] ?? config('app.name', 'HyperCommerce') }}">
                                        @endif
                                        <strong class="preview-company-name">{{ $systemSettings['appName'] ?? config('app.name', 'HyperCommerce') }}</strong>
                                        <small>{{ $systemSettings['companyAddress'] ?? '' }}</small>
                                        <small>{{ collect([$systemSettings['sellerSupportEmail'] ?? null, $systemSettings['sellerSupportNumber'] ?? null])->filter()->implode(' · ') }}</small>
                                    </div>
                                    <div class="preview-invoice-heading"><h1 data-preview-title>Tax Invoice</h1><small>Invoice # <span data-preview-field="reference">INV-SAMPLE-1001</span></small></div>
                                </header>
                                <section class="preview-invoice-details" data-preview-section="details"><strong>Invoice details</strong><div class="preview-order-meta"><span><strong>Order #</strong> <span data-preview-field="number">ORDER-SAMPLE-1001</span></span><span><strong>Date:</strong> <span data-preview-field="date">{{ now()->format('Y-m-d H:i') }}</span></span></div></section>
                                <div class="preview-addresses" data-preview-section="addresses"><div class="preview-details"><div><strong>Billing information</strong><span data-preview-field="billingName">Sample Customer</span><span data-preview-field="billingAddress">123 Sample Street, Sample City</span><span data-preview-field="billingContact">customer@example.com · +91 98765 43210</span></div></div></div>
                                <div class="preview-seller" data-preview-section="seller_details"><strong>Sold by: <span data-preview-field="seller">Sample Store 1</span></strong><small>Seller ID: <span data-preview-field="sellerId">1</span> · GSTIN: <span data-preview-field="sellerTax">22AAAAA0000A1Z5</span></small></div>
                                <section class="preview-items-section" data-preview-section="items"><h2>Order Summary</h2>
                                <table class="preview-table"><thead><tr><th>Item</th><th>Qty</th><th class="preview-item-status">Status</th><th>Tax</th><th>Unit price</th><th>Total</th></tr></thead><tbody id="invoice-preview-items">
                                    <tr><td><strong>Sample Product 1</strong><small>Product description · <span class="preview-addon">Option: Sample add-on</span></small></td><td>1</td><td class="preview-item-status">Delivered</td><td>₹ 5.00 (5%)</td><td>₹ 100.00</td><td>₹ 105.00</td></tr>
                                    <tr><td><strong>Sample Product 2</strong><small>Product description · <span class="preview-addon">Option: Sample add-on</span></small></td><td>1</td><td class="preview-item-status">Delivered</td><td>₹ 5.00 (5%)</td><td>₹ 100.00</td><td>₹ 105.00</td></tr>
                                    <tr><td><strong>Sample Product 3</strong><small>Product description · <span class="preview-addon">Option: Sample add-on</span></small></td><td>1</td><td class="preview-item-status">Delivered</td><td>₹ 5.00 (5%)</td><td>₹ 100.00</td><td>₹ 105.00</td></tr>
                                </tbody></table></section>
                                <div class="preview-total" data-preview-section="totals"><div><span>Items Subtotal</span><b data-preview-field="itemsSubtotal">₹ 300.00</b></div><div><span>Shipping &amp; Handling</span><b data-preview-field="shippingTotal">₹ 20.00</b></div><div><span>Total</span><b data-preview-field="orderTotal">₹ 320.00</b></div><div class="preview-grand-total"><span data-preview-total-label>Total Payable</span><b data-preview-field="payableTotal">₹ 320.00</b></div></div>
                                <div class="preview-payment" data-preview-section="payment">Payment: <span data-preview-field="payment">Online · completed</span></div>
                                <div class="preview-terms" data-preview-section="terms">Please retain this invoice for your records.</div>
                                <div class="preview-signature" data-preview-section="signature">@if(!empty($systemSettings['adminSignature']))<img class="preview-signature-image" src="{{ $systemSettings['adminSignature'] }}" alt="Admin signature">@endif<strong>Authorized signatory</strong></div>
                                <footer class="preview-footer" data-preview-section="footer"><span data-preview-footer>Thank you for shopping with us!</span><span>No Copyright · Enjoy for Free</span></footer>
                            </article>
                        </div>
                    </section>
                    <aside class="invoice-builder-settings">
                        <nav class="invoice-settings-tabs" aria-label="Builder settings">
                            <button type="button" class="active" data-settings-tab="style">Style</button>
                            <button type="button" data-settings-tab="wording">Wording</button>
                            <button type="button" data-settings-tab="details">Details</button>
                        </nav>
                        <div class="invoice-settings-content" data-settings-panel="style">
                            <h4>Invoice templates</h4><p class="text-muted">Choose a template for your customer invoices.</p>
                            <div class="invoice-style-choices">
                                <button type="button" class="invoice-template-card" data-template="grid"><span class="invoice-template-sample invoice-template-sample-grid"><i></i><i></i><i></i><i></i></span><span>Grid tax</span></button>
                                <button type="button" class="invoice-template-card" data-template="blue"><span class="invoice-template-sample invoice-template-sample-blue"><i></i><i></i><i></i><i></i></span><span>Business</span></button>
                            </div>
                            <label class="form-label mt-3" for="invoice-page-background-color">Page background</label>
                            <div class="invoice-page-color-control"><input type="color" id="invoice-page-background-color" aria-label="Page background color"></div>
                            <div class="invoice-page-lines-control mt-3">
                                <label for="invoice-show-page-lines">Page border lines</label>
                                <div class="invoice-page-lines-options">
                                    <input class="form-control form-control-color" type="color" id="invoice-page-line-color" aria-label="Page border line color">
                                    <input class="invoice-section-toggle" type="checkbox" id="invoice-show-page-lines" aria-label="Show page border lines">
                                </div>
                            </div>
                            <label class="form-label mt-3" for="invoice-font">Font</label>
                            <select class="form-select" id="invoice-font" data-google-font-select data-selected="{{ $draft['font'] ?? 'Inter' }}" data-placeholder="{{ __('labels.google_font_family_placeholder') }}">
                                <option value="Arial">Arial</option><option value="Georgia">Georgia</option><option value="Helvetica">Helvetica</option><option value="Times New Roman">Times New Roman</option>
                                @include('components.google-font-options')
                            </select>
                            <label class="form-label mt-3" for="invoice-margin">Page margin (mm)</label>
                            <div class="invoice-margin-control"><input type="range" min="5" max="30" id="invoice-margin-range"><input class="form-control" type="number" min="5" max="30" id="invoice-margin"></div>
                        </div>
                        <div class="invoice-settings-content d-none" data-settings-panel="wording">
                            <label class="form-label" for="invoice-title">Invoice title</label><input class="form-control" id="invoice-title" maxlength="80">
                            <label class="form-label mt-3" for="invoice-total-label">Total label</label><input class="form-control" id="invoice-total-label" maxlength="100">
                            <label class="form-label mt-3" for="invoice-footer-text">Footer message</label><textarea class="form-control" id="invoice-footer-text" rows="4" maxlength="500"></textarea>
                            <label class="form-label mt-3" for="invoice-terms-text">Terms</label><textarea class="form-control" id="invoice-terms-text" rows="4" maxlength="500"></textarea>
                        </div>
                        <div class="invoice-settings-content d-none" data-settings-panel="details">
                            <div class="invoice-detail-switches">
                                <label class="invoice-detail-switch"><span>Show logo</span><input class="invoice-section-toggle" type="checkbox" id="invoice-show-logo"></label>
                                <label class="invoice-detail-switch"><span>Item status column</span><input class="invoice-section-toggle" type="checkbox" id="invoice-show-item-status"></label>
                                <label class="invoice-detail-switch"><span>Product add-ons</span><input class="invoice-section-toggle" type="checkbox" id="invoice-show-addons"></label>
                                <label class="invoice-detail-switch"><span>Payment status</span><input class="invoice-section-toggle" type="checkbox" id="invoice-show-payment-status"></label>
                            </div>
                            <div class="invoice-details-form">
                                <label class="form-label" for="invoice-template-name">Template name</label>
                                <input class="form-control" id="invoice-template-name" maxlength="80">
                                <label class="form-label mt-3" for="invoice-preview-mode">Preview using</label>
                                <select class="form-select" id="invoice-preview-mode">
                                    <option value="sample">Sample order</option>
                                    <option value="existing">Existing order</option>
                                </select>
                                <div id="invoice-preview-order-field">
                                    <label class="form-label mt-3" for="invoice-preview-order">Order</label>
                                    <select class="form-select" id="invoice-preview-order">
                                        <option value="">Choose an order</option>
                                        @foreach($orders as $orderOption)
                                            <option value="{{ $orderOption->uuid }}" @selected(($draft['previewOrderUuid'] ?? '') === $orderOption->uuid)>{{ $orderOption->order_number ?? ('#'.$orderOption->id) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <p class="text-muted small mt-3 mb-0">Logo comes from System Settings. Choose Sample order or select an existing order for the live preview.</p>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ hyperAsset('assets/js/google-font-picker.js') }}" defer></script>
    <script src="{{ hyperAsset('assets/js/invoice-builder.js') }}" defer></script>
@endpush
