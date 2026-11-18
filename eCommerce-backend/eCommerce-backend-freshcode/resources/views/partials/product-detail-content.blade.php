@php
    use App\Enums\Product\ProductVarificationStatusEnum;
    use App\Support\PricePresenter;
    use App\Support\SafeHtml;
    use Illuminate\Support\Str;

    $isAdmin = $panel === 'admin';
    $indexRoute = route($panel.'.products.index');
    $editRoute = route($panel.'.products.edit', $product->id);
    $tags = is_array($product->tags) ? $product->tags : (json_decode((string) $product->tags, true) ?: []);
    $customFields = is_array($product->custom_fields) ? $product->custom_fields : [];
    $metadata = is_array($product->metadata) ? $product->metadata : [];
    $kitComponents = $product->comboPack?->items ?? collect();
    $approvalTone = $product->verification_status === 'approved' ? 'green' : ($product->verification_status === 'rejected' ? 'red' : 'yellow');
    $priceRange = $productViewStats['minimum_price'] ?? null;
    if ($priceRange && ($productViewStats['maximum_price'] ?? null) && $productViewStats['maximum_price'] !== $priceRange) {
        $priceRange .= ' – '.$productViewStats['maximum_price'];
    }
@endphp

<div class="product-detail-page">
    <div class="product-detail-hero">
        <div class="product-detail-hero-main">
            <div class="product-detail-main-image">
                @if($product->main_image)
                    <img src="{{ $product->main_image }}" alt="{{ $product->title }}">
                @else
                    <i class="ti ti-photo-off"></i>
                @endif
            </div>
            <div class="min-w-0">
                <h1>{{ $product->title }}</h1>
                <div class="product-detail-subtitle">
                    <span>#{{ $product->id }}</span>
                    @if($isAdmin)<span>{{ $product->seller?->user?->name ?? '—' }}</span>@endif
                    <span>{{ $product->category?->title ?? '—' }}</span>
                    @if($product->brand)<span>{{ $product->brand->title }}</span>@endif
                </div>
                <div class="product-detail-metrics">
                    @if($priceRange)<span><i class="ti ti-tag"></i>{{ $priceRange }}</span>@endif
                    <span><i class="ti ti-layers-subtract"></i>{{ $productViewStats['variant_count'] }} {{ __('labels.variants') }}</span>
                    <span><i class="ti ti-infinity"></i>{{ $productViewStats['has_unlimited_stock'] ? __('labels.unlimited') : ($productViewStats['available_stock'].' '.__('labels.in_stock')) }}</span>
                    <span class="badge bg-{{ $product->status === 'active' ? 'green' : 'secondary' }}-lt">{{ Str::headline($product->status) }}</span>
                </div>
            </div>
        </div>
        <div class="product-detail-hero-actions">
            <span class="badge bg-{{ $approvalTone }}-lt product-detail-approval"><i class="ti ti-shield-check me-1"></i>{{ Str::headline($product->verification_status) }}</span>
            <span class="text-secondary small">{{ __('labels.updated_at') }} {{ $product->updated_at->diffForHumans() }}</span>
            <div class="d-flex gap-2 mt-2">
                <a href="{{ $indexRoute }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>{{ __('labels.back_to_products') }}</a>
                @if($editPermission ?? false)
                    <a href="{{ $editRoute }}" class="btn btn-primary"><i class="ti ti-edit me-1"></i>{{ __('labels.edit') }}</a>
                @endif
            </div>
        </div>
    </div>

    @if($product->verification_status !== 'approved' || $product->rejection_reason)
        <div class="alert alert-{{ $product->verification_status === 'rejected' ? 'danger' : 'warning' }}">
            <div class="d-flex gap-2">
                <i class="ti ti-alert-circle fs-2"></i>
                <div>
                    <strong>{{ __('labels.product_approval_notice_title') }}: {{ Str::headline($product->verification_status) }}</strong>
                    @if($product->rejection_reason)
                        <div class="mt-1"><strong>{{ __('labels.rejection_reason') }}:</strong> {{ $product->rejection_reason }}</div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <nav class="product-detail-nav">
        <a href="#product-overview">{{ __('labels.overview') }}</a>
        @if($product->type === 'kit')<a href="#product-kit-contents">{{ __('labels.combo_pack_contents') }}</a>@endif
        <a href="#product-variants">{{ __('labels.variants') }}</a>
        <a href="#product-content">{{ __('labels.product_content') }}</a>
        <a href="#product-tax-policies">{{ __('labels.tax_and_policies') }}</a>
        <a href="#product-extra">{{ __('labels.custom_fields') }}</a>
    </nav>

    <div class="product-detail-layout">
        <main>
            <section class="product-detail-section" id="product-overview">
                <div class="product-detail-section-heading">
                    <div><h2>{{ __('labels.overview') }}</h2><p>{{ __('labels.catalog_information') }}</p></div>
                </div>
                <div class="product-detail-grid">
                    <div class="product-detail-field"><span>{{ __('labels.product_type') }}</span><strong>{{ Str::headline($product->type) }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.category') }}</span><strong>{{ $product->category?->title ?? '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.brand') }}</span><strong>{{ $product->brand?->title ?? '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.product_condition') }}</span><strong>{{ $product->productCondition?->title ?? '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.slug') }}</span><strong>{{ $product->slug ?: '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.hsn_code') }}</span><strong>{{ $product->hsn_code ?: '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.indicator') }}</span><strong>{{ $product->indicator ?: '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.made_in') }}</span><strong>{{ $product->made_in ?: '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.created_at') }}</span><strong>{{ $product->created_at->format('Y-m-d H:i') }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.updated_at') }}</span><strong>{{ $product->updated_at->format('Y-m-d H:i') }}</strong></div>
                </div>
                @if($product->short_description)
                    <div class="product-detail-callout"><strong>{{ __('labels.short_description') }}</strong><p>{{ $product->short_description }}</p></div>
                @endif
            </section>

            @if($product->type === 'kit')
                <section class="product-detail-section" id="product-kit-contents">
                    <div class="product-detail-section-heading">
                        <div><h2>{{ __('labels.combo_pack_contents') }} ({{ $kitComponents->count() }})</h2><p>{{ __('labels.combo_pack_component') }}</p></div>
                    </div>
                    @if($kitComponents->isNotEmpty())
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('labels.product') }}</th>
                                        <th>{{ __('labels.variant') }}</th>
                                        <th>{{ __('labels.sku') }}</th>
                                        <th class="text-end">{{ __('labels.combo_pack_quantity') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($kitComponents as $component)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="product-detail-variant-image">
                                                        @if($component->product?->main_image)<img src="{{ $component->product->main_image }}" alt="{{ $component->product->title }}">@else<i class="ti ti-photo-off"></i>@endif
                                                    </span>
                                                    <strong>{{ $component->product?->title ?? '—' }}</strong>
                                                </div>
                                            </td>
                                            <td>{{ $component->variant?->title ?: '—' }}</td>
                                            <td>{{ $component->variant?->sku ?: '—' }}</td>
                                            <td class="text-end">{{ $component->quantity }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="empty py-4">
                            <div class="empty-icon"><i class="ti ti-package-off"></i></div>
                            <p class="empty-title">{{ __('labels.combo_pack_components_required') }}</p>
                        </div>
                    @endif
                </section>
            @endif

            <section class="product-detail-section" id="product-variants">
                <div class="product-detail-section-heading">
                    <div><h2>{{ __('labels.variants') }} ({{ $productViewStats['variant_count'] }})</h2><p>{{ __('labels.pricing_stock_locations') }}</p></div>
                    @if($editPermission ?? false)<a href="{{ $editRoute }}#section-pricing" class="btn btn-sm btn-outline-primary">{{ __('labels.manage') }}</a>@endif
                </div>
                <div class="product-detail-variants">
                    @forelse($product->variants as $variant)
                        @php
                            $variantInventory = $variant->locationInventories->where('is_fulfillment_location', true);
                            $variantUnlimited = $variantInventory->contains(fn($inventory) => ! $inventory->tracked);
                            $variantStock = $variantUnlimited ? null : $variantInventory->sum(fn($inventory) => max(0, (int) $inventory->stock - (int) $inventory->reserved));
                        @endphp
                        <details class="product-detail-variant" @if($loop->first) open @endif>
                            <summary>
                                <span class="product-detail-variant-image">
                                    @if($variant->image)<img src="{{ $variant->image }}" alt="{{ $variant->title }}">@else<i class="ti ti-photo-off"></i>@endif
                                </span>
                                <span class="product-detail-variant-name">
                                    <strong>{{ $variant->title }}</strong>
                                    <span class="badge bg-secondary-lt user-select-all">#{{ $variant->id }}</span>
                                    <span>
                                        @foreach($variant->attributes as $attribute)
                                            <em>{{ $attribute->attribute?->title }} · {{ $attribute->attributeValue?->title }}</em>
                                        @endforeach
                                    </span>
                                </span>
                                <span><small>{{ __('labels.sku') }}</small><strong>{{ $variant->sku ?: '—' }}</strong></span>
                                <span><small>{{ __('labels.price') }}</small><strong>{{ PricePresenter::forCurrent((float) $variant->price_exclude_tax)['formatted'] }}</strong></span>
                                <span><small>{{ __('labels.stock') }}</small><strong>{{ $variantUnlimited ? __('labels.unlimited') : $variantStock }}</strong></span>
                                <span class="badge bg-{{ $variant->availability ? 'green' : 'red' }}-lt">{{ $variant->availability ? __('labels.available') : __('labels.unavailable') }}</span>
                            </summary>
                            <div class="product-detail-variant-body">
                                <div class="product-detail-grid compact">
                                    <div class="product-detail-field"><span>{{ __('labels.barcode') }}</span><strong>{{ $variant->barcode ?: '—' }}</strong></div>
                                    <div class="product-detail-field"><span>{{ __('labels.special_price') }}</span><strong>{{ $variant->special_price_exclude_tax ? PricePresenter::forCurrent((float) $variant->special_price_exclude_tax)['formatted'] : '—' }}</strong></div>
                                    <div class="product-detail-field"><span>{{ __('labels.cost') }}</span><strong>{{ $variant->cost ? PricePresenter::forCurrent((float) $variant->cost)['formatted'] : '—' }}</strong></div>
                                    <div class="product-detail-field"><span>{{ __('labels.dimensions') }}</span><strong>{{ $variant->length ?: 0 }} × {{ $variant->breadth ?: 0 }} × {{ $variant->height ?: 0 }}</strong></div>
                                    <div class="product-detail-field"><span>{{ __('labels.weight') }}</span><strong>{{ $variant->weight ?: 0 }}</strong></div>
                                    <div class="product-detail-field"><span>{{ __('labels.default') }}</span><strong>{{ $variant->is_default ? __('labels.yes') : __('labels.no') }}</strong></div>
                                </div>
                                <div class="table-responsive mt-3">
                                    <table class="table table-vcenter mb-0">
                                        <thead><tr><th>{{ __('labels.location') }}</th><th>{{ __('labels.track_inventory') }}</th><th>{{ __('labels.stock') }}</th><th>{{ __('labels.selling') }}</th></tr></thead>
                                        <tbody>
                                        @forelse($variant->locationInventories as $inventory)
                                            @php $available = max(0, (int) $inventory->stock - (int) $inventory->reserved); @endphp
                                            <tr>
                                                <td><strong>{{ $inventory->storeLocation?->store?->name ?? '—' }}</strong><div class="text-secondary small">{{ $inventory->storeLocation?->name ?? '—' }}</div></td>
                                                <td>{{ $inventory->tracked ? __('labels.yes') : __('labels.no') }}</td>
                                                <td>{{ $inventory->tracked ? $available : __('labels.unlimited') }}</td>
                                                <td><span class="badge bg-{{ $inventory->is_fulfillment_location ? 'green' : 'red' }}-lt">{{ $inventory->is_fulfillment_location ? __('labels.available') : __('labels.unavailable') }}</span></td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="4" class="text-center text-secondary py-3">{{ __('labels.no_store_pricing_available') }}</td></tr>
                                        @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </details>
                    @empty
                        <div class="empty py-4"><p class="empty-title">{{ __('labels.no_store_pricing_available') }}</p></div>
                    @endforelse
                </div>
            </section>

            <section class="product-detail-section" id="product-content">
                <div class="product-detail-section-heading"><div><h2>{{ __('labels.product_content') }}</h2><p>{{ __('labels.product_media') }}</p></div></div>
                <div class="product-detail-media">
                    @if($product->main_image)<a href="{{ $product->main_image }}" target="_blank"><img src="{{ $product->main_image }}" alt="{{ $product->title }}"></a>@endif
                    @foreach($product->additional_images ?? [] as $image)<a href="{{ $image }}" target="_blank"><img src="{{ $image }}" alt="{{ $product->title }}"></a>@endforeach
                    @if(!$product->main_image && empty($product->additional_images))<span class="text-secondary">{{ __('labels.no_media_available') }}</span>@endif
                </div>
                <div class="product-detail-description">{!! $product->description ? SafeHtml::clean($product->description) : '<p class="text-secondary">'.e(__('labels.no_description_available')).'</p>' !!}</div>
                @if($product->video_link)<a href="{{ $product->video_link }}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="ti ti-video me-1"></i>{{ __('labels.video_link') }}</a>@endif
            </section>

            <section class="product-detail-section" id="product-tax-policies">
                <div class="product-detail-section-heading"><div><h2>{{ __('labels.tax_and_policies') }}</h2><p>{{ __('labels.technical_details') }}</p></div></div>
                <div class="product-detail-grid">
                    <div class="product-detail-field"><span>{{ __('labels.tax') }}</span><strong>{{ $product->taxClasses->pluck('title')->implode(', ') ?: '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.is_inclusive_tax') }}</span><strong>{{ $product->is_inclusive_tax ? __('labels.yes') : __('labels.no') }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.minimum_order_quantity') }}</span><strong>{{ $product->minimum_order_quantity ?? '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.quantity_step_size') }}</span><strong>{{ $product->quantity_step_size ?? '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.total_allowed_quantity') }}</span><strong>{{ $product->total_allowed_quantity ?? '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.allow_cod') }}</span><strong>{{ $product->allow_cod ? __('labels.yes') : __('labels.no') }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.is_returnable') }}</span><strong>{{ $product->is_returnable ? __('labels.yes') : __('labels.no') }}{{ $product->is_returnable ? ' · '.$product->returnable_days.' '.__('labels.days') : '' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.is_cancelable') }}</span><strong>{{ $product->is_cancelable ? __('labels.yes') : __('labels.no') }}{{ $product->is_cancelable && $product->cancelable_till ? ' · '.Str::headline($product->cancelable_till) : '' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.is_attachment_required') }}</span><strong>{{ $product->is_attachment_required ? __('labels.yes') : __('labels.no') }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.attachment_mode') }}</span><strong>{{ $product->attachment_mode ? Str::headline($product->attachment_mode) : '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.warranty_period') }}</span><strong>{{ $product->warranty_period ?: '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.guarantee_period') }}</span><strong>{{ $product->guarantee_period ?: '—' }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.base_prep_time') }}</span><strong>{{ $product->base_prep_time ?? 0 }} {{ __('labels.minutes') }}</strong></div>
                    <div class="product-detail-field"><span>{{ __('labels.featured') }}</span><strong>{{ $product->featured ? __('labels.yes') : __('labels.no') }}</strong></div>
                </div>
            </section>

            <section class="product-detail-section" id="product-extra">
                <div class="product-detail-section-heading"><div><h2>{{ __('labels.custom_fields') }}</h2><p>{{ __('labels.product_information') }}</p></div></div>
                @if($customFields)
                    <div class="product-detail-grid mb-4">
                        @foreach($customFields as $key => $value)<div class="product-detail-field"><span>{{ Str::headline($key) }}</span><strong>{{ is_array($value) ? implode(', ', $value) : $value }}</strong></div>@endforeach
                    </div>
                @endif
                @if($tags)
                    <div class="mb-4"><h3 class="h4">{{ __('labels.tags') }}</h3><div class="d-flex flex-wrap gap-2">@foreach($tags as $tag)<span class="badge bg-blue-lt">{{ $tag }}</span>@endforeach</div></div>
                @endif
                @if($metadata)
                    <div class="product-detail-grid mb-4">@foreach($metadata as $key => $value)@if(is_scalar($value) && filled($value))<div class="product-detail-field"><span>{{ Str::headline($key) }}</span><strong>{{ $value }}</strong></div>@endif @endforeach</div>
                @endif
                @if($product->faqs->isNotEmpty())
                    <div class="accordion" id="product-detail-faqs">@foreach($product->faqs as $faq)<div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#product-faq-{{ $faq->id }}">{{ $faq->question }}</button></h3><div id="product-faq-{{ $faq->id }}" class="accordion-collapse collapse" data-bs-parent="#product-detail-faqs"><div class="accordion-body">{{ $faq->answer }}</div></div></div>@endforeach</div>
                @endif
            </section>

            @if($product->customProductSections->isNotEmpty())
                <section class="product-detail-section">
                    <div class="product-detail-section-heading"><div><h2>{{ __('labels.custom_product_sections') }}</h2></div></div>
                    @foreach($product->customProductSections as $customSection)
                        <div class="product-detail-callout"><strong>{{ $customSection->title }}</strong>@if($customSection->description)<p>{{ $customSection->description }}</p>@endif<div class="product-detail-grid compact mt-3">@foreach($customSection->fields as $field)<div class="product-detail-field"><span>{{ $field->title }}</span><strong>{{ $field->description ?: '—' }}</strong></div>@endforeach</div></div>
                    @endforeach
                </section>
            @endif

            <div class="row">@include('partials.product-attached-addons', ['variantAddons' => $variantAddons ?? []])</div>
        </main>

        <aside>
            @if($isAdmin && ($updateStatusPermission ?? false))
                <div class="product-detail-side-card sticky-top">
                    <h3>{{ __('labels.approval_management') }}</h3>
                    <form class="form-submit" method="POST" action="{{ route('admin.products.update-verification-status', $product->id) }}">
                        @csrf
                        <div class="mb-3"><label class="form-label">{{ __('labels.verification_status') }}</label><select class="form-select" name="verification_status" id="verification_status">@foreach(ProductVarificationStatusEnum::values() as $status)<option value="{{ $status }}" @selected($product->verification_status === $status)>{{ Str::headline($status) }}</option>@endforeach</select></div>
                        <div class="mb-3" id="rejection-reason-wrapper" style="display: {{ $product->verification_status === ProductVarificationStatusEnum::REJECTED() ? 'block' : 'none' }};"><label class="form-label">{{ __('labels.rejection_reason') }}</label><textarea class="form-control" name="rejection_reason" id="rejection_reason" rows="4">{{ old('rejection_reason', $product->rejection_reason) }}</textarea></div>
                        <button type="submit" class="btn btn-primary w-100" id="update-verification-status">{{ __('labels.update_approval_status') }}</button>
                    </form>
                    <div class="mt-2" id="verification-status-result" style="display:none;"></div>
                </div>
            @else
                <div class="product-detail-side-card sticky-top">
                    <h3>{{ __('labels.product_summary') }}</h3>
                    <div class="product-detail-side-stat"><span>{{ __('labels.verification_status') }}</span><strong>{{ Str::headline($product->verification_status) }}</strong></div>
                    <div class="product-detail-side-stat"><span>{{ __('labels.locations') }}</span><strong>{{ $productViewStats['location_count'] }}</strong></div>
                    <div class="product-detail-side-stat"><span>{{ __('labels.store') }}</span><strong>{{ $productViewStats['store_count'] }}</strong></div>
                    <div class="product-detail-side-stat"><span>{{ __('labels.product_faqs') }}</span><strong>{{ $product->faqs->count() }}</strong></div>
                </div>
            @endif
        </aside>
    </div>
</div>
