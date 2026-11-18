@php
    /** @var \Illuminate\Support\Collection $items */
    /** @var bool $editPermission */
    /** @var string $detailRoute */
@endphp
<div class="p-3 border-top">
    @if($items->isEmpty())
        <div class="empty py-4">
            <div class="empty-icon"><i class="ti ti-package-off"></i></div>
            <p class="empty-title mb-0">{{ __('labels.no_items_for_this_order') }}</p>
        </div>
    @else
        <div class="d-flex flex-column gap-2">
            @foreach($items as $item)
                <div class="card shadow-none border">
                    <div class="card-body p-3">
                        <div class="row align-items-center g-3">
                            <div class="col-auto">
                                @if(!empty($item['image']))
                                    <a href="{{ $item['image'] }}" data-fslightbox="gallery"
                                       class="d-flex justify-content-center align-items-center rounded border overflow-hidden"
                                       style="width: 56px; height: 56px;">
                                        <img src="{{ $item['image'] }}" alt=""
                                             style="width: 100%; height: 100%; object-fit: cover;">
                                    </a>
                                @else
                                    <div class="rounded border d-flex align-items-center justify-content-center text-secondary"
                                         style="width: 56px; height: 56px; background: var(--tblr-bg-surface-secondary, #f8fafc);">
                                        <i class="ti ti-package fs-3"></i>
                                    </div>
                                @endif
                            </div>
                            <div class="col">
                                <div class="fw-semibold">{{ $item['product_title'] }}</div>
                                <div class="text-secondary small d-flex flex-wrap gap-3 mt-1">
                                    @if(!empty($item['variant_title']))
                                        <span><i class="ti ti-versions me-1"></i>{{ $item['variant_title'] }}</span>
                                    @endif
                                    @if(!empty($item['store']))
                                        <span><i class="ti ti-building-store me-1"></i>{{ $item['store'] }}</span>
                                    @endif
                                    @if(!empty($item['sku']))
                                        <span><i class="ti ti-barcode me-1"></i>{{ $item['sku'] }}</span>
                                    @endif
                                </div>
                                @if(!empty($item['addons']) && $item['addons']->count() > 0)
                                    <div class="mt-1 small text-secondary">
                                        <span class="me-1">{{ __('labels.addons') }}:</span>
                                        @foreach($item['addons'] as $addon)
                                            <span class="badge bg-secondary-lt me-1">
                                                {{ $addon->addonGroup?->title }} — {{ $addon->addonItem?->title }}
                                                @if((float) $addon->price > 0)
                                                    · {{ formatOrderMoney($addon->price, $orderCurrency ?? null) }}
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                @if(!empty($item['attachments']))
                                    @php
                                        $gallery = 'order-item-attachments-' . (int) $item['id'];
                                        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
                                    @endphp
                                    <div class="mt-2">
                                        @foreach($item['attachments'] as $url)
                                            @php
                                                $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
                                                $isImage = in_array($extension, $imageExtensions, true);
                                            @endphp
                                            <a href="{{ $url }}" target="_blank" rel="noopener"
                                               @if($isImage) data-fslightbox="{{ $gallery }}" @endif
                                               class="badge bg-orange-lt text-decoration-none me-1">
                                                <i class="ti {{ $isImage ? 'ti-photo' : ($extension === 'pdf' ? 'ti-file-type-pdf' : 'ti-file-description') }} me-1"></i>
                                                {{ __('labels.prescription') }}{{ $extension ? ' ' . strtoupper($extension) : '' }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="col-auto text-center">
                                <div class="text-secondary small">{{ __('labels.quantity') }}</div>
                                <div class="fw-semibold">× {{ (int) $item['quantity'] }}</div>
                            </div>
                            <div class="col-auto text-end">
                                <div class="text-secondary small">{{ __('labels.subtotal') }}</div>
                                <div class="fw-semibold">{{ formatOrderMoney($item['subtotal'], $orderCurrency ?? null) }}</div>
                            </div>
                            <div class="col-auto">
                                @include('partials.order-status', ['status' => $item['status']])
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="text-end mt-3">
            <a href="{{ $detailRoute }}" class="btn btn-sm btn-outline-primary py-2 px-4">
                <i class="ti ti-external-link me-1"></i>{{ __('labels.view_full_order') }}
            </a>
        </div>
    @endif
</div>
