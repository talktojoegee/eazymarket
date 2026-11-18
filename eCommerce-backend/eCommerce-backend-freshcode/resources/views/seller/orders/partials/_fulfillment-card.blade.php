@if(!empty($fulfillment))
    @php
        $fstatus = $fulfillment['fulfillment_status'] ?? 'unfulfilled';
        $fBadge = fulfillmentStatusBadgeClass($fstatus);
        $fLines = $fulfillment['lines'] ?? [];
        $fShipments = $fulfillment['shipments'] ?? [];
    @endphp
    <div class="col-12 g-0">
        <div class="card order-fulfillment-card js-fulfillment-card mb-3"
             id="fulfillmentCard-{{ $fulfillment['seller_order_id'] }}"
             data-seller-order-id="{{ $fulfillment['seller_order_id'] }}">
            <div class="card-header py-2">
                <div class="order-overview-min-w-0">
                    <h3 class="card-title mb-0">
                        <i class="ti ti-package-export me-1"></i>{{ __('labels.fulfillment') ?? 'Fulfillment' }}
                        @if(!empty($fulfillment['seller_name']))
                            <span class="text-secondary fw-normal">· {{ $fulfillment['seller_name'] }}</span>
                        @endif
                    </h3>
                    <div class="text-secondary small mt-1 d-flex flex-wrap gap-2">
                        <span><i class="ti ti-map-pin me-1"></i>{{ $fulfillment['location_name'] ?? '—' }}</span>
                        <span>·</span>
                        <span>{{ $fulfillment['zone_name'] ?? '—' }}</span>
                        <span>·</span>
                        <span>{{ __('labels.shipping_charge') ?? 'Shipping' }}: {{ formatOrderMoney($fulfillment['shipping_charge'] ?? 0, $order['currency'] ?? null) }}</span>
                        @if(!empty($fulfillment['ship_by']))
                            <span>·</span>
                            <span class="{{ $fulfillment['ship_by_overdue'] ? 'text-danger fw-semibold' : '' }}">
                                {{ __('labels.ship_by') }}: {{ $fulfillment['ship_by'] }}
                                @if($fulfillment['ship_by_overdue'])
                                    <span class="badge bg-red-lt text-red ms-1">{{ __('labels.overdue') }}</span>
                                @endif
                            </span>
                        @endif
                    </div>
                </div>
                <div class="card-actions d-flex align-items-center flex-wrap gap-1">
                    <span class="badge bg-secondary-lt text-secondary text-uppercase">{{ $fulfillment['method_label'] }}</span>
                    <span class="badge {{ $fBadge }} text-uppercase">{{ Str::ucfirst(Str::replace('_', ' ', $fstatus)) }}</span>
                </div>
            </div>

            @if(!$fulfillment['supported'])
                <div class="card-body py-2">
                    <div class="alert alert-secondary mb-0 py-2">
                        <i class="ti ti-clock-pause me-1"></i>
                        {{ __('labels.fulfillment_method_coming_soon') ?? 'This fulfillment method is not available yet.' }}
                    </div>
                </div>
            @elseif(!empty($fLines))
                <div class="list-group list-group-flush" data-fulfillment-items>
                    @foreach($fLines as $ln)
                        <div class="list-group-item px-3 py-2" data-order-item-amount="{{ $ln['line_total'] ?? 0 }}">
                            <div class="d-flex align-items-center gap-3">
                                <span class="avatar avatar-lg flex-shrink-0 bg-secondary-lt overflow-hidden">
                                    @if(!empty($ln['image']))
                                        <img src="{{ $ln['image'] }}" alt="{{ $ln['title'] }}" class="avatar-img object-fit-cover">
                                    @else
                                        <i class="ti ti-photo text-secondary fs-2"></i>
                                    @endif
                                </span>
                                <div class="flex-fill order-overview-min-w-0">
                                    <div class="d-flex align-items-start justify-content-between gap-3">
                                        <div class="order-overview-min-w-0">
                                            @php
                                                $productUrl = !empty($ln['product_id'])
                                                    ? route(($isSeller ? 'seller' : 'admin') . '.products.show', ['id' => $ln['product_id']])
                                                    : null;
                                            @endphp
                                            <div class="fw-semibold text-truncate">
                                                @if($productUrl)
                                                    <a href="{{ $productUrl }}" class="text-reset text-decoration-underline-hover">{{ $ln['title'] }}</a>
                                                @else
                                                    {{ $ln['title'] }}
                                                @endif
                                            </div>
                                            @if(!empty($ln['variant']))
                                                <div class="text-secondary small text-truncate">{{ $ln['variant'] }}</div>
                                            @endif
                                            @if(!empty($ln['combo_components']))
                                                <div class="text-secondary small mt-1">
                                                    {{ __('labels.combo_pack_contents') }}:
                                                    @foreach($ln['combo_components'] as $component)
                                                        <span>{{ $component['title'] }} ({{ $component['variant'] }}) × {{ $component['quantity'] }}@if(!$loop->last), @endif</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="text-end text-nowrap">
                                            <div class="fw-semibold">{{ formatOrderMoney($ln['line_total'] ?? 0, $order['currency'] ?? null) }}</div>
                                            <div class="text-secondary small">{{ __('labels.unit_price') }}: {{ formatOrderMoney($ln['unit_price'] ?? 0, $order['currency'] ?? null) }}</div>
                                        </div>
                                    </div>
                                    <div class="d-flex flex-wrap align-items-center gap-2 mt-1 small">
                                        <span class="text-secondary">{{ __('labels.quantity') }}: <span class="text-body fw-medium">{{ $ln['ordered'] }}</span></span>
                                        <span class="text-secondary">{{ __('labels.shipped') ?? 'Shipped' }}: <span class="text-body fw-medium">{{ $ln['shipped'] }}</span></span>
                                        @if(($ln['delivered'] ?? 0) > 0)
                                            <span class="text-success">{{ __('labels.delivered') }}: <span class="fw-medium">{{ $ln['delivered'] }}</span></span>
                                        @endif
                                        @if($ln['remaining'] > 0)
                                            <span class="badge bg-yellow-lt text-yellow">{{ $ln['remaining'] }} {{ __('labels.remaining') ?? 'remaining' }}</span>
                                        @else
                                            <span class="badge bg-green-lt text-green"><i class="ti ti-check me-1"></i>{{ __('labels.fulfilled') }}</span>
                                        @endif
                                    </div>
                                    @if(!empty($ln['delivery_estimate']))
                                        <div class="text-secondary small mt-1">
                                            <i class="ti ti-truck-delivery me-1"></i>{{ $ln['delivery_estimate'] }}
                                        </div>
                                    @endif
                                    @if(!empty($ln['addons']))
                                        <div class="small mt-1">
                                            <span class="text-secondary me-1">{{ __('labels.addons') }}:</span>
                                            @foreach($ln['addons'] as $addon)
                                                <span class="badge bg-secondary-lt me-1">
                                                    {{ $addon['label'] }}
                                                    @if(($addon['price'] ?? 0) > 0)
                                                        · {{ formatOrderMoney($addon['price'], $order['currency'] ?? null) }}
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                    @if(!empty($ln['attachments']))
                                        @php
                                            $lnGallery = 'order-item-attachments-' . (int) ($ln['order_item_id'] ?? 0);
                                            $lnImageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
                                        @endphp
                                        <div class="mt-1">
                                            @foreach($ln['attachments'] as $attachmentUrl)
                                                @php
                                                    $lnExtension = strtolower(pathinfo(parse_url($attachmentUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                                                    $lnIsImage = in_array($lnExtension, $lnImageExtensions, true);
                                                @endphp
                                                <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener"
                                                   @if($lnIsImage) data-fslightbox="{{ $lnGallery }}" @endif
                                                   class="badge bg-orange-lt text-decoration-none me-1">
                                                    <i class="ti {{ $lnIsImage ? 'ti-photo' : ($lnExtension === 'pdf' ? 'ti-file-type-pdf' : 'ti-file-description') }} me-1"></i>
                                                    {{ __('labels.prescription') }}{{ $lnExtension ? ' ' . strtoupper($lnExtension) : '' }}
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
            @if($fulfillment['supported'])
            <div class="card-footer p-2">
                @if($fulfillment['can_fulfill'])
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="text-secondary small">
                            @if($fulfillment['is_pickup'])
                                <i class="ti ti-building-store me-1"></i>{{ __('labels.local_pickup_fulfill_hint') ?? 'Mark this order ready for the customer to pick up.' }}
                            @else
                                <i class="ti ti-truck me-1"></i>{{ __('labels.manual_fulfill_hint') ?? 'Create a fulfillment for some or all remaining items.' }}
                            @endif
                        </div>
                        <div class="btn-list">
                            @if($fulfillment['is_pickup'])
                                <button type="button" class="btn btn-primary btn-sm js-fulfill-now"
                                        data-fulfill-url="{{ $fulfillment['fulfill_url'] }}">
                                    <i class="ti ti-package-export me-1"></i>{{ __('labels.mark_as_fulfilled') ?? 'Mark as fulfilled' }}
                                </button>
                            @else
                                <button type="button" class="btn btn-primary btn-sm js-fulfill-open"
                                        data-bs-toggle="modal" data-bs-target="#fulfillShipmentModal"
                                        data-fulfill-url="{{ $fulfillment['fulfill_url'] }}"
                                        data-lines='@json($fLines)'
                                        data-shiprocket="{{ !empty($fulfillment['is_shiprocket']) ? '1' : '0' }}"
                                        data-title="{{ $fulfillment['shiprocket_title'] ?? 'Shiprocket' }}"
                                        data-packages='@json($fulfillment['packages'] ?? [])'
                                        data-defaults='@json($fulfillment['shiprocket_defaults'] ?? [])'>
                                    <i class="ti ti-package-export me-1"></i>{{ __('labels.mark_as_fulfilled') ?? 'Mark as fulfilled' }}
                                </button>
                            @endif

                            @if($fulfillment['can_set_status'])
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown"
                                            aria-expanded="false">
                                        {{ __('labels.more_actions') ?? 'More actions' }}
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <button type="button" class="dropdown-item js-set-fstatus"
                                                data-status-url="{{ $fulfillment['set_status_url'] }}"
                                                data-status="in_progress">
                                            <i class="ti ti-progress me-2 text-blue"></i>{{ __('labels.mark_as_in_progress') ?? 'Mark as in progress' }}
                                        </button>
                                        <button type="button" class="dropdown-item js-set-fstatus"
                                                data-status-url="{{ $fulfillment['set_status_url'] }}"
                                                data-status="on_hold">
                                            <i class="ti ti-player-pause me-2 text-orange"></i>{{ __('labels.mark_as_on_hold') ?? 'Mark as on hold' }}
                                        </button>
                                        <button type="button" class="dropdown-item js-set-fstatus"
                                                data-status-url="{{ $fulfillment['set_status_url'] }}"
                                                data-status="unfulfilled">
                                            <i class="ti ti-x me-2 text-secondary"></i>{{ __('labels.mark_as_unfulfilled') ?? 'Mark as unfulfilled' }}
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @elseif(empty($fShipments))
                    <div class="alert alert-warning mb-0 py-2">
                        <i class="ti ti-checklist me-1"></i>
                        {{ __('labels.no_items_to_fulfill_card') ?? 'No items available to fulfill.' }}
                    </div>
                @endif
                @foreach($fShipments as $shipment)
                    @php
                        $sBadge = match ($shipment['status']) {
                            'delivered'                           => 'bg-green-lt text-green',
                            'confirmed', 'in_transit', 'out_for_delivery' => 'bg-blue-lt text-blue',
                            'failure', 'lost'                     => 'bg-red-lt text-red',
                            'cancelled', 'returned', 'rto_initiated', 'rto_delivered' => 'bg-secondary-lt',
                            default                               => 'bg-yellow-lt text-yellow',
                        };
                    @endphp
                    <div class="border rounded p-2 mt-2">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                    <span
                                        class="badge {{ $sBadge }} text-uppercase me-2">{{ $shipment['status'] }}</span>
                                @if(!empty($shipment['tracking_number']))
                                    @php
                                        $safeTrackingUrl = !empty($shipment['tracking_url'])
                                            && \Illuminate\Support\Str::startsWith(strtolower($shipment['tracking_url']), ['http://', 'https://'])
                                            ? $shipment['tracking_url'] : null;
                                    @endphp
                                    <span class="text-secondary small">
                                            <i class="ti ti-barcode me-1"></i>
                                            @if($safeTrackingUrl)
                                            <a href="{{ $safeTrackingUrl }}" target="_blank"
                                               rel="noopener noreferrer" class="text-decoration-none">
                                                    {{ $shipment['carrier_name'] ?? (__('labels.carrier') ?? 'Carrier') }}
                                                    · {{ $shipment['tracking_number'] }} <i
                                                    class="ti ti-external-link"></i>
                                                </a>
                                        @else
                                            {{ $shipment['carrier_name'] ?? (__('labels.carrier') ?? 'Carrier') }}
                                            · {{ $shipment['tracking_number'] }}
                                        @endif
                                        </span>
                                @elseif(!empty($shipment['carrier_name']))
                                    <span class="text-secondary small">
                                            <i class="ti ti-truck-delivery me-1"></i>{{ $shipment['carrier_name'] }}
                                        </span>
                                @endif
                                @if(!empty($shipment['reconcile']))
                                    @php $rc = $shipment['reconcile']; @endphp
                                    <div class="text-secondary small mt-1">
                                        <i class="ti ti-receipt-2 me-1"></i>{{ __('labels.customer_paid') }}: {{ $rc['symbol'] }}{{ number_format($rc['paid'], 2) }}
                                        · {{ __('labels.shiprocket_charged') }}: {{ $rc['symbol'] }}{{ number_format($rc['actual'], 2) }}
                                        @if($rc['delta'] != 0)
                                            · <span class="{{ $rc['delta'] > 0 ? 'text-danger' : 'text-success' }}">{{ __('labels.platform_absorbs') }}: {{ $rc['symbol'] }}{{ number_format($rc['delta'], 2) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="btn-list">
                                @if(!empty($shipment['can_sync']))
                                    <button type="button" class="btn btn-sm btn-icon js-shipment-sync"
                                            title="{{ __('labels.sync_from_shiprocket') ?? 'Sync from Shiprocket' }}"
                                            data-sync-url="{{ str_replace('SHIPMENT_ID', $shipment['id'], $fulfillment['sync_url_template']) }}">
                                        <i class="ti ti-refresh"></i>
                                    </button>
                                @endif
                                @if(!empty($shipment['can_retry']))
                                    <button type="button" class="btn btn-sm btn-outline-warning js-shipment-retry"
                                            data-retry-url="{{ str_replace('SHIPMENT_ID', $shipment['id'], $fulfillment['retry_url_template']) }}">
                                        <i class="ti ti-refresh me-1"></i>{{ __('labels.retry_booking') ?? 'Retry booking' }}
                                    </button>
                                @endif
                                @if(!empty($shipment['can_ndr']))
                                    <button type="button" class="btn btn-sm btn-outline-primary js-shipment-ndr"
                                            data-ndr-action="re-attempt"
                                            data-ndr-title="{{ __('labels.ndr_reattempt') ?? 'Reattempt delivery' }}"
                                            data-ndr-url="{{ str_replace('SHIPMENT_ID', $shipment['id'], $fulfillment['ndr_url_template']) }}">
                                        <i class="ti ti-truck-delivery me-1"></i>{{ __('labels.ndr_reattempt') ?? 'Reattempt delivery' }}
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger js-shipment-ndr"
                                            data-ndr-action="return"
                                            data-ndr-title="{{ __('labels.ndr_return_to_origin') ?? 'Return to origin' }}"
                                            data-ndr-url="{{ str_replace('SHIPMENT_ID', $shipment['id'], $fulfillment['ndr_url_template']) }}">
                                        <i class="ti ti-arrow-back-up me-1"></i>{{ __('labels.ndr_return_to_origin') ?? 'Return to origin' }}
                                    </button>
                                @endif
                                @if(!empty($shipment['is_pending']) && !empty($shipment['edit_lines']))
                                    <button type="button" class="btn btn-sm js-fulfill-open"
                                            data-bs-toggle="modal" data-bs-target="#fulfillShipmentModal"
                                            data-fulfill-url="{{ str_replace('SHIPMENT_ID', $shipment['id'], $fulfillment['update_lines_url_template']) }}"
                                            data-lines='@json($shipment['edit_lines'])'
                                            data-edit="1"
                                            data-shiprocket="1"
                                            data-title="{{ $fulfillment['shiprocket_title'] ?? 'Shiprocket' }}"
                                            data-packages='@json($fulfillment['packages'] ?? [])'
                                            data-defaults='@json($shipment['dims'])'>
                                        <i class="ti ti-pencil me-1"></i>{{ __('labels.shiprocket_edit_items') ?? 'Edit items' }}
                                    </button>
                                @endif
                                @if($shipment['can_deliver'])
                                    <button type="button" class="btn btn-sm btn-outline-success js-shipment-action"
                                            data-transition-url="{{ $fulfillment['transition_url_template'] }}"
                                            data-shipment-id="{{ $shipment['id'] }}" data-to="delivered">
                                        <i class="ti ti-circle-check me-1"></i>{{ __('labels.mark_as_delivered') ?? 'Mark as delivered' }}
                                    </button>
                                @endif
                                @if($shipment['can_edit'])
                                    <button type="button" class="btn btn-sm js-shipment-edit"
                                            data-bs-toggle="modal" data-bs-target="#editFulfillmentModal"
                                            data-edit-url="{{ str_replace('SHIPMENT_ID', $shipment['id'], $fulfillment['edit_url_template']) }}"
                                            data-carrier="{{ $shipment['carrier_name'] }}"
                                            data-tracking="{{ $shipment['tracking_number'] }}"
                                            data-tracking-url="{{ $shipment['tracking_url'] }}">
                                        <i class="ti ti-edit me-1"></i>{{ __('labels.edit_fulfillment') ?? 'Edit fulfillment' }}
                                    </button>
                                @endif
                                @unless($shipment['has_return'] ?? false)
                                    @if(!empty($shipment['is_shiprocket']))
                                        @if(!empty($shipment['label_url']))
                                            <a href="{{ $shipment['label_url'] }}"
                                               target="_blank" rel="noopener noreferrer"
                                               class="btn btn-sm">
                                                <i class="ti ti-printer me-1"></i>{{ __('labels.print_shiprocket_label') }}
                                            </a>
                                        @endif
                                        @if(!empty($shipment['manifest_url']))
                                            <a href="{{ $shipment['manifest_url'] }}"
                                               target="_blank" rel="noopener noreferrer"
                                               class="btn btn-sm">
                                                <i class="ti ti-clipboard-list me-1"></i>{{ __('labels.shiprocket_manifest') ?? 'Manifest' }}
                                            </a>
                                        @endif
                                        @if(!empty($shipment['invoice_url']))
                                            <a href="{{ $shipment['invoice_url'] }}"
                                               target="_blank" rel="noopener noreferrer"
                                               class="btn btn-sm">
                                                <i class="ti ti-file-invoice me-1"></i>{{ __('labels.shiprocket_invoice') ?? 'Invoice' }}
                                            </a>
                                        @endif
                                    @else
                                        <a href="{{ str_replace('SHIPMENT_ID', $shipment['id'], $fulfillment['packing_slip_url_template']) }}"
                                           target="_blank" rel="noopener noreferrer"
                                           class="btn btn-sm">
                                            <i class="ti ti-printer me-1"></i>{{ __('labels.print_packing_slip') ?? 'Print packing slip' }}
                                        </a>
                                    @endif
                                @endunless
                                @if($shipment['can_cancel'])
                                    <button type="button" class="btn btn-sm btn-outline-danger js-confirm-open"
                                            data-bs-toggle="modal" data-bs-target="#cancelFulfillmentModal"
                                            data-action-url="{{ str_replace('SHIPMENT_ID', $shipment['id'], $fulfillment['transition_url_template']) }}"
                                            data-action-payload='{"status":"cancelled"}'>
                                        <i class="ti ti-x me-1"></i>{{ __('labels.cancel_fulfillment') ?? 'Cancel fulfillment' }}
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if(!empty($shipment['sr_details']))
                            <div class="text-secondary small mt-2 d-flex flex-wrap gap-3">
                                @if(!empty($shipment['sr_details']['shiprocket_order_id']))
                                    <span><i class="ti ti-hash me-1"></i>{{ __('labels.shiprocket_order_id') ?? 'Shiprocket order' }}: {{ $shipment['sr_details']['shiprocket_order_id'] }}</span>
                                @endif
                                @if(!empty($shipment['sr_details']['pickup_scheduled']))
                                    <span><i class="ti ti-calendar-time me-1"></i>{{ __('labels.pickup_scheduled') ?? 'Pickup' }}: {{ $shipment['sr_details']['pickup_scheduled'] }}</span>
                                @endif
                                @if(!empty($shipment['sr_details']['etd']))
                                    <span><i class="ti ti-truck-delivery me-1"></i>{{ __('labels.estimated_delivery') ?? 'Est. delivery' }}: {{ $shipment['sr_details']['etd'] }}</span>
                                @endif
                                @if(!empty($shipment['sr_details']['applied_weight']))
                                    <span><i class="ti ti-weight me-1"></i>{{ __('labels.applied_weight') ?? 'Billed weight' }}: {{ $shipment['sr_details']['applied_weight'] }} kg</span>
                                @endif
                                @if(!empty($shipment['sr_details']['pickup_token']))
                                    <span><i class="ti ti-ticket me-1"></i>{{ __('labels.pickup_token') ?? 'Pickup token' }}: {{ $shipment['sr_details']['pickup_token'] }}</span>
                                @endif
                            </div>
                        @endif
                        @if(!empty($shipment['is_pending']))
                            <div class="alert alert-warning mt-2 mb-0 py-2 small">
                                <i class="ti ti-alert-triangle me-1"></i>
                                {{ $shipment['booking_error'] ?: (__('labels.shiprocket_booking_pending') ?? 'Booking pending — courier not assigned yet.') }}
                            </div>
                        @elseif(!empty($shipment['booking_warning']))
                            <div class="alert alert-warning mt-2 mb-0 py-2 small">
                                <i class="ti ti-alert-triangle me-1"></i>
                                {{ __('labels.shiprocket_booking_incomplete') ?? 'Booked, but a document step is pending:' }} {{ $shipment['booking_warning'] }}
                            </div>
                        @endif
                        @if(!empty($shipment['products']))
                            <div class="mt-2 pt-2 border-top">
                                <div class="text-secondary small mb-1">
                                    <i class="ti ti-package me-1"></i>{{ __('labels.products_in_shipment') ?? 'Products in this fulfillment' }}
                                </div>
                                <ul class="list-unstyled mb-0 small">
                                    @foreach($shipment['products'] as $product)
                                        <li class="d-flex justify-content-between">
                                                <span>
                                                    {{ $product['title'] }}
                                                    @if(!empty($product['variant']))
                                                        <span class="text-muted">· {{ $product['variant'] }}</span>
                                                    @endif
                                                </span>
                                            <span class="text-muted ms-2">× {{ $product['quantity'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
@endif
