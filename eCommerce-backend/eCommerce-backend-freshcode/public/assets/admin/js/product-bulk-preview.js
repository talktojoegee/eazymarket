'use strict';

(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const cfg = window.CATALOG_BULK_UPLOAD_CFG || {};
        if (cfg.module !== 'products') return;

        const review = document.getElementById('bulk-product-review');
        const status = document.getElementById('bulk-product-review-status');
        const progress = document.getElementById('bulk-product-review-progress');
        const counts = document.getElementById('bulk-product-review-counts');
        const attributeCounts = document.getElementById('bulk-product-review-attributes');
        const sample = document.getElementById('bulk-product-review-sample');
        const refresh = document.getElementById('bulk-product-preview-refresh');
        const open = document.getElementById('bulk-product-preview-open');
        const confirm = document.getElementById('bulk-upload-confirm-submit');
        const confirmForm = document.getElementById('bulk-upload-confirm-form');
        const seller = document.getElementById('bulk-upload-seller');
        const modalElement = document.getElementById('bulk-product-preview-modal');
        const listPanel = document.getElementById('bulk-product-preview-list-panel');
        const detailPanel = document.getElementById('bulk-product-preview-detail-panel');
        const list = document.getElementById('bulk-product-preview-list');
        const modalSummary = document.getElementById('bulk-product-preview-modal-summary');
        const pageSummary = document.getElementById('bulk-product-preview-page-summary');
        const search = document.getElementById('bulk-product-preview-search');
        const issue = document.getElementById('bulk-product-preview-issue-filter');
        const pageSize = document.getElementById('bulk-product-preview-page-size');
        const pagination = document.getElementById('bulk-product-preview-pagination');
        const goPage = document.getElementById('bulk-product-preview-go-page');
        const go = document.getElementById('bulk-product-preview-go');
        const back = document.getElementById('bulk-product-preview-back');
        const detail = document.getElementById('bulk-product-preview-detail');
        const productsTab = document.getElementById('bulk-preview-products-tab');
        const attributesTab = document.getElementById('bulk-preview-attributes-tab');
        const attributesPanel = document.getElementById('bulk-product-preview-attributes-panel');
        const attributeSearch = document.getElementById('bulk-product-preview-attribute-search');
        const attributeStatus = document.getElementById('bulk-product-preview-attribute-status');
        const attributePageSize = document.getElementById('bulk-product-preview-attribute-page-size');
        const attributeList = document.getElementById('bulk-product-preview-attribute-list');
        const attributePageSummary = document.getElementById('bulk-product-preview-attribute-summary');
        const attributePagination = document.getElementById('bulk-product-preview-attribute-pagination');
        const i18n = cfg.i18n || {};
        let token = '';
        let currentSummary = null;
        let page = 1;
        let lastPage = 1;
        let buildGeneration = 0;
        let searchTimer = null;
        let attributePage = 1;
        let attributeSearchTimer = null;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>"']/g, function (character) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character];
            });
        }

        function url(template, previewId) {
            let result = String(template || '').replace('00000000-0000-4000-8000-000000000000', token);
            if (previewId !== undefined) result = result.replace(/\/products\/0(?=\?|$)/, '/products/' + previewId);
            const seller = document.getElementById('bulk-upload-seller');
            if (seller && seller.value && !result.includes('seller_id=')) {
                result += (result.includes('?') ? '&' : '?') + 'seller_id=' + encodeURIComponent(seller.value);
            }
            return result;
        }

        function mappingPayload() {
            const mapping = {};
            confirmForm.querySelectorAll('[name^="mapping["]').forEach(function (select) {
                const match = select.name.match(/^mapping\[(.+)]$/);
                if (match) mapping[match[1]] = select.value;
            });
            const operation = document.getElementById('bulk-operation-select');
            const matchField = document.getElementById('bulk-match-by-select');
            const seller = document.getElementById('bulk-upload-seller');
            return {
                mapping: mapping,
                operation: operation ? operation.value : 'create',
                match_field: matchField ? matchField.value : null,
                seller_id: seller ? seller.value : cfg.sellerId,
            };
        }

        function setQueueState(allowed) {
            window.CATALOG_PRODUCT_PREVIEW_CAN_QUEUE = Boolean(allowed);
            if (confirm) confirm.disabled = !allowed;
        }

        function countCard(label, value, tone) {
            return '<div class="col-6 col-md-4 col-xl"><div class="bulk-review-metric bulk-review-metric--' + tone + ' h-100">'
                + '<div class="small text-muted">' + escapeHtml(label) + '</div><div class="bulk-review-metric-value">'
                + Number(value || 0).toLocaleString() + '</div></div></div>';
        }

        function attributeSummary(label, count, names) {
            const shown = names || [];
            const amount = Number(count || 0);
            const preview = shown.slice(0, 2).join(' · ') || i18n.product_preview_no_attributes || 'None in this file';
            const heading = '<span class="bulk-review-attribute-copy"><span class="fw-semibold">' + escapeHtml(label)
                + '</span><span class="small text-muted d-block bulk-review-attribute-preview" title="' + escapeHtml(preview) + '">'
                + escapeHtml(preview) + '</span></span><strong class="bulk-review-attribute-count">' + amount.toLocaleString() + '</strong>';
            if (!amount) {
                return '<div class="col-md-6"><div class="bulk-review-attribute h-100"><div class="bulk-review-attribute-heading">'
                    + heading + '</div></div></div>';
            }
            const items = shown.slice(0, 5).map(function (name) {
                return '<li>' + escapeHtml(name) + '</li>';
            }).join('');
            const more = amount > 5 ? '<li><button type="button" class="btn btn-sm btn-outline-primary bulk-review-view-attributes">'
                + escapeHtml(i18n.product_preview_view_all_attributes || 'Browse all attributes') + '</button></li>' : '';
            return '<div class="col-md-6"><details class="bulk-review-attribute h-100"><summary>' + heading
                + '<i class="ti ti-chevron-down bulk-review-attribute-chevron" aria-hidden="true"></i></summary>'
                + '<ul class="bulk-review-attribute-list">' + items + more + '</ul></details></div>';
        }

        function issueBadges(row) {
            const badges = [];
            if (row.critical_count) badges.push('<span class="bulk-review-issue bulk-review-issue--critical"><i class="ti ti-alert-circle" aria-hidden="true"></i>'
                + escapeHtml(i18n.product_preview_critical_label || 'Critical') + ' · ' + Number(row.critical_count).toLocaleString() + '</span>');
            if (row.warning_count) badges.push('<span class="bulk-review-issue bulk-review-issue--warning"><i class="ti ti-alert-triangle" aria-hidden="true"></i>'
                + escapeHtml(i18n.product_preview_warning_label || 'Warning') + ' · ' + Number(row.warning_count).toLocaleString() + '</span>');
            if (!badges.length) badges.push('<span class="bulk-review-issue bulk-review-issue--ready"><i class="ti ti-circle-check" aria-hidden="true"></i>'
                + escapeHtml(i18n.product_preview_ready || 'Ready') + '</span>');
            return badges.join(' ');
        }

        function formatMoney(value) {
            if (value === null || value === undefined || value === '' || !Number.isFinite(Number(value))) return '—';
            try {
                return new Intl.NumberFormat(undefined, {
                    style: 'currency',
                    currency: cfg.currencyCode || 'USD',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(Number(value));
            } catch (error) {
                return String(cfg.currencySymbol || '') + Number(value).toLocaleString(undefined, {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            }
        }

        function imageMarkup(row, size) {
            const imageUrl = escapeHtml(row.main_image_url || cfg.productPlaceholderUrl || '');
            const fallback = '<span class="bulk-review-thumb-fallback"><i class="ti ti-photo-off" aria-hidden="true"></i></span>';
            if (!imageUrl) return '<span class="bulk-review-thumb is-fallback" style="--bulk-thumb-size:' + size + 'px">' + fallback + '</span>';
            return '<a class="bulk-review-thumb" style="--bulk-thumb-size:' + size + 'px" href="' + imageUrl
                + '" data-fslightbox="product-bulk-preview" data-type="image"><img src="' + imageUrl
                + '" loading="lazy" referrerpolicy="no-referrer" alt="">' + fallback + '</a>';
        }

        function renderSummary(summary) {
            currentSummary = summary;
            review.classList.remove('d-none');
            const complete = summary.status === 'completed';
            const failed = summary.status === 'failed';
            const hasCritical = Number(summary.critical || 0) > 0;
            status.textContent = failed ? (summary.failure_reason || i18n.product_preview_failed) : (complete
                ? (hasCritical ? i18n.product_preview_has_critical : i18n.product_preview_ready)
                : i18n.product_preview_building + ' ' + Number(summary.processed_rows || 0).toLocaleString()
                    + ' / ' + Number(summary.total_rows || 0).toLocaleString());
            const progressPercentage = summary.total_rows > 0
                ? Math.min(100, Math.round((summary.processed_rows / summary.total_rows) * 100))
                : 0;
            progress.style.width = complete ? '100%' : progressPercentage + '%';
            progress.className = 'progress-bar ' + (failed ? 'bg-danger' : complete ? 'bg-success' : 'progress-bar-striped progress-bar-animated bg-primary');
            counts.innerHTML = countCard(i18n.products || 'Products', summary.products, 'neutral')
                + countCard(i18n.variants || 'Variants', summary.variants, 'neutral')
                + countCard(i18n.product_unique_locations || 'Unique locations', summary.locations, 'neutral')
                + countCard(i18n.product_preview_critical || 'Critical errors', summary.critical, 'critical')
                + countCard(i18n.product_preview_warnings || 'Warnings', summary.warnings, 'warning');
            const attributes = summary.attributes || {};
            attributeCounts.innerHTML = attributeSummary(i18n.product_preview_new_attributes || 'New attributes', attributes.new_count, attributes.new)
                + attributeSummary(i18n.product_preview_existing_attributes || 'Existing attributes reused', attributes.existing_count, attributes.existing);
            sample.innerHTML = (summary.sample || []).map(function (row) {
                const title = escapeHtml(row.title || row.handle || i18n.product_preview_untitled || 'Untitled product');
                const handle = escapeHtml(row.handle || '');
                return '<div class="col-12 col-md-6 col-xl-4"><div class="bulk-review-sample"><div class="d-flex gap-3">'
                    + imageMarkup(row, 56) + '<div class="bulk-review-sample-content"><div class="fw-semibold bulk-review-sample-title" title="' + title + '">'
                    + title + '</div><div class="small text-muted bulk-review-sample-handle mt-1" title="' + handle + '">'
                    + handle + '</div></div></div><div class="d-flex flex-wrap gap-1 mt-3">' + issueBadges(row) + '</div></div></div>';
            }).join('');
            open.disabled = !complete;
            setQueueState(Boolean(summary.can_queue));
            if (typeof window.refreshFsLightbox === 'function') window.refreshFsLightbox();
        }

        function markStale() {
            if (!token) return;
            buildGeneration++;
            setQueueState(false);
            status.textContent = i18n.product_preview_stale || 'Refresh the product preview.';
            progress.className = 'progress-bar bg-secondary';
            progress.style.width = '100%';
        }

        function buildIndex() {
            if (!token) return;
            const generation = ++buildGeneration;
            setQueueState(false);
            refresh.disabled = true;
            status.textContent = i18n.product_preview_building || 'Reviewing products and running validation checks…';
            progress.className = 'progress-bar progress-bar-striped progress-bar-animated bg-secondary';
            axios.post(url(cfg.productPreviewIndexUrl), mappingPayload(), {
                headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' },
            }).then(function (response) {
                if (generation !== buildGeneration) return;
                const summary = response.data && response.data.data;
                if (!summary) throw new Error(i18n.product_preview_failed || 'Preview failed');
                renderSummary(summary);
                if (summary.status === 'building' || summary.status === 'pending') {
                    window.setTimeout(buildIndex, 80);
                } else {
                    refresh.disabled = false;
                }
            }).catch(function (error) {
                if (generation !== buildGeneration) return;
                status.textContent = error.response?.data?.message || error.message || i18n.product_preview_failed;
                progress.className = 'progress-bar bg-danger';
                progress.style.width = '100%';
                refresh.disabled = false;
                setQueueState(false);
            });
        }

        function renderPagination(currentPage, totalPages, target) {
            const pages = [];
            for (let candidate = 1; candidate <= totalPages; candidate++) {
                if (candidate === 1 || candidate === totalPages || Math.abs(candidate - currentPage) <= 2) pages.push(candidate);
            }
            let previousPage = 0;
            let items = '<li class="page-item ' + (currentPage <= 1 ? 'disabled' : '') + '"><button type="button" class="page-link" data-page="'
                + (currentPage - 1) + '" aria-label="' + escapeHtml(i18n.previous || 'Previous') + '"><i class="ti ti-chevron-left" aria-hidden="true"></i></button></li>';
            pages.forEach(function (pageNumber) {
                if (previousPage && pageNumber - previousPage > 1) {
                    items += '<li class="page-item disabled" aria-hidden="true"><span class="page-link">…</span></li>';
                }
                items += '<li class="page-item ' + (pageNumber === currentPage ? 'active' : '') + '"><button type="button" class="page-link" data-page="'
                    + pageNumber + '"' + (pageNumber === currentPage ? ' aria-current="page"' : '') + '>' + pageNumber + '</button></li>';
                previousPage = pageNumber;
            });
            items += '<li class="page-item ' + (currentPage >= totalPages ? 'disabled' : '') + '"><button type="button" class="page-link" data-page="'
                + (currentPage + 1) + '" aria-label="' + escapeHtml(i18n.next || 'Next') + '"><i class="ti ti-chevron-right" aria-hidden="true"></i></button></li>';
            (target || pagination).innerHTML = items;
            if (!target) goPage.max = totalPages;
        }

        function showTab(name) {
            const isAttributes = name === 'attributes';
            listPanel.classList.toggle('d-none', isAttributes);
            attributesPanel.classList.toggle('d-none', !isAttributes);
            detailPanel.classList.add('d-none');
            productsTab.className = 'btn btn-sm ' + (isAttributes ? 'btn-outline-secondary' : 'btn-primary');
            attributesTab.className = 'btn btn-sm ' + (isAttributes ? 'btn-primary' : 'btn-outline-secondary');
            productsTab.setAttribute('aria-selected', String(!isAttributes));
            attributesTab.setAttribute('aria-selected', String(isAttributes));
            if (isAttributes) loadAttributes(1);
        }

        function loadAttributes(targetPage) {
            attributePage = Math.max(1, targetPage || 1);
            attributeList.innerHTML = '<tr><td colspan="2" class="text-center py-5"><span class="spinner-border spinner-border-sm"></span></td></tr>';
            axios.get(url(cfg.productPreviewAttributesUrl), { params: {
                page: attributePage, per_page: attributePageSize.value,
                search: attributeSearch.value, status: attributeStatus.value,
            }}).then(function (response) {
                const data = response.data.data;
                attributePage = Number(data.pagination.page || 1);
                attributeList.innerHTML = data.rows.length ? data.rows.map(function (row) {
                    const statusLabel = row.status === 'new' ? i18n.product_preview_new : i18n.product_preview_existing;
                    return '<tr><td class="fw-semibold">' + escapeHtml(row.name) + '</td><td><span class="bulk-review-issue bulk-review-issue--'
                        + (row.status === 'new' ? 'warning' : 'ready') + '">' + escapeHtml(statusLabel) + '</span></td></tr>';
                }).join('') : '<tr><td colspan="2" class="text-center text-muted py-5">' + escapeHtml(i18n.product_preview_no_attributes) + '</td></tr>';
                attributePageSummary.textContent = String(i18n.product_preview_attribute_page || ':total attributes · Page :page of :last')
                    .replace(':total', data.pagination.total).replace(':page', data.pagination.page).replace(':last', data.pagination.last_page);
                renderPagination(attributePage, Number(data.pagination.last_page || 1), attributePagination);
            }).catch(function (error) {
                attributeList.innerHTML = '<tr><td colspan="2" class="text-center text-danger py-5">'
                    + escapeHtml(error.response?.data?.message || i18n.product_preview_failed) + '</td></tr>';
            });
        }

        function loadProducts(targetPage) {
            page = Math.max(1, targetPage || 1);
            list.innerHTML = '<tr><td colspan="7" class="text-center py-5"><span class="spinner-border spinner-border-sm"></span></td></tr>';
            axios.get(url(cfg.productPreviewProductsUrl), { params: {
                page: page,
                per_page: pageSize.value,
                search: search.value,
                issue: issue.value,
            }}).then(function (response) {
                const data = response.data.data;
                page = Number(data.pagination.page || 1);
                lastPage = Number(data.pagination.last_page || 1);
                modalSummary.textContent = Number(data.summary.products || 0).toLocaleString() + ' products · '
                    + Number(data.summary.critical || 0).toLocaleString() + ' critical · '
                    + Number(data.summary.warnings || 0).toLocaleString() + ' warnings';
                list.innerHTML = data.rows.length ? data.rows.map(function (row) {
                    const price = row.minimum_price === null ? '—' : (Number(row.minimum_price) === Number(row.maximum_price)
                        ? formatMoney(row.minimum_price)
                        : formatMoney(row.minimum_price) + ' – ' + formatMoney(row.maximum_price));
                    return '<tr><td><div class="d-flex align-items-center gap-2 bulk-review-table-product">' + imageMarkup(row, 48)
                        + '<div class="product-list-content bulk-review-sample-content"><div class="product-list-title bulk-review-sample-title" title="' + escapeHtml(row.title || '') + '">' + escapeHtml(row.title || i18n.product_preview_untitled || 'Untitled product') + '</div><div class="product-list-meta bulk-review-sample-handle">'
                        + escapeHtml(row.handle || '') + (row.product_id ? ' · #' + escapeHtml(row.product_id) : '') + '</div></div></div></td>'
                        + '<td><div>' + escapeHtml(row.category || '—') + '</div><div class="small text-muted">' + escapeHtml(row.brand || '—') + '</div></td>'
                        + '<td>' + row.variant_count + '<div class="small text-muted">' + row.location_count + ' ' + escapeHtml((i18n.locations || 'Locations').toLowerCase()) + '</div></td>'
                        + '<td>' + (row.inventory_total ?? '—') + '</td><td>' + escapeHtml(price) + '</td><td>' + issueBadges(row) + '</td>'
                        + '<td><button type="button" class="btn btn-sm btn-outline-primary product-preview-detail" data-id="' + row.id + '">'
                        + escapeHtml(i18n.product_preview_details || 'Details') + '</button></td></tr>';
                }).join('') : '<tr><td colspan="7" class="text-center text-muted py-5">' + escapeHtml(i18n.product_preview_empty) + '</td></tr>';
                pageSummary.textContent = String(i18n.product_preview_page || ':total products · Page :page of :last')
                    .replace(':total', data.pagination.total).replace(':page', data.pagination.page).replace(':last', data.pagination.last_page);
                renderPagination(page, lastPage);
                if (typeof window.refreshFsLightbox === 'function') window.refreshFsLightbox();
            }).catch(function (error) {
                list.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-5">' + escapeHtml(error.response?.data?.message || i18n.product_preview_failed) + '</td></tr>';
            });
        }

        function showDetail(id) {
            detailPanel.classList.remove('d-none');
            listPanel.classList.add('d-none');
            detail.innerHTML = '<div class="text-center py-5"><span class="spinner-border"></span></div>';
            axios.get(url(cfg.productPreviewDetailUrl, id)).then(function (response) {
                const data = response.data.data;
                const product = data.product || {};
                const media = [product.main_image_url || cfg.productPlaceholderUrl].concat(product.additional_image_urls || []).filter(Boolean);
                const issues = data.issues || {};
                const attributeDetails = (data.attributes || []).map(function (attribute) {
                    const label = attribute.status === 'new' ? i18n.product_preview_new : i18n.product_preview_existing;
                    const values = (attribute.values || []).map(function (value) {
                        const valueLabel = value.status === 'new' ? i18n.product_preview_new : i18n.product_preview_existing;
                        return '<li class="d-flex justify-content-between gap-2"><span>' + escapeHtml(value.name)
                            + '</span><span class="small text-muted">' + escapeHtml(valueLabel) + '</span></li>';
                    }).join('');
                    const heading = escapeHtml(attribute.name) + (attribute.id ? ' · #' + escapeHtml(attribute.id) : '')
                        + ' <span class="small text-muted">(' + escapeHtml(label) + ')</span>';
                    return '<details class="bulk-review-attribute mb-2"><summary><span class="bulk-review-attribute-copy fw-semibold">'
                        + heading + '</span><strong class="bulk-review-attribute-count">' + (attribute.values || []).length
                        + '</strong><i class="ti ti-chevron-down bulk-review-attribute-chevron" aria-hidden="true"></i></summary>'
                        + '<ul class="bulk-review-attribute-list">' + values + '</ul></details>';
                }).join('');
                const issueBlock = ['critical', 'warning', 'info'].map(function (severity) {
                    const messages = issues[severity] || [];
                    if (!messages.length) return '';
                    const tone = severity === 'critical' ? 'danger' : severity === 'warning' ? 'warning' : 'secondary';
                    return '<div class="alert alert-' + tone + '"><div class="fw-semibold text-capitalize mb-1">' + severity + '</div><ul class="mb-0">'
                        + messages.map(function (message) { return '<li>' + escapeHtml(message) + '</li>'; }).join('') + '</ul></div>';
                }).join('');
                const variants = (data.variants || []).map(function (variant) {
                    const options = (variant.options || []).map(function (option) {
                        let visual = '';
                        if (option.swatch_image_url) {
                            const image = escapeHtml(option.swatch_image_url);
                            visual = '<a href="' + image + '" data-fslightbox="product-bulk-option-preview" data-type="image"><img src="' + image
                                + '" width="24" height="24" loading="lazy" class="rounded border object-fit-cover" alt=""></a> ';
                        } else if (/^#[0-9a-f]{6}$/i.test(option.swatch_color || '')) {
                            visual = '<span class="rounded border d-inline-block align-middle" style="width:24px;height:24px;background-color:'
                                + escapeHtml(option.swatch_color) + '"></span> ';
                        }
                        return visual + escapeHtml(option.name + ': ' + option.value);
                    }).join(', ');
                    const locations = (variant.locations || []).map(function (location) {
                        return '<tr><td>' + escapeHtml(location.name || location.id || location.store_id || '—') + '</td><td>'
                            + escapeHtml(location.quantity || '0') + '</td><td>' + escapeHtml(location.tracked || '—') + '</td></tr>';
                    }).join('');
                    return '<details class="product-detail-variant mb-2"><summary><span class="product-detail-variant-name"><strong>' + escapeHtml(variant.sku || (i18n.variants || 'Variants'))
                        + '</strong><span>' + options + '</span></span><span><small>' + escapeHtml(i18n.price || 'Price') + '</small><strong>' + escapeHtml(formatMoney(variant.price)) + '</strong></span></summary><div class="bulk-preview-variant-body">'
                        + '<div class="row g-2 mb-3"><div class="col-md-3"><strong>' + escapeHtml(i18n.barcode || 'Barcode') + '</strong><br>' + escapeHtml(variant.barcode || '—')
                        + '</div><div class="col-md-3"><strong>' + escapeHtml(i18n.price || 'Price') + '</strong><br>' + escapeHtml(formatMoney(variant.price))
                        + '</div><div class="col-md-3"><strong>' + escapeHtml(i18n.special_price || 'Special price') + '</strong><br>' + escapeHtml(formatMoney(variant.special_price))
                        + '</div><div class="col-md-3"><strong>' + escapeHtml(i18n.cost || 'Cost') + '</strong><br>' + escapeHtml(formatMoney(variant.cost)) + '</div></div>'
                        + (locations ? '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>' + escapeHtml(i18n.locations || 'Locations') + '</th><th>' + escapeHtml(i18n.quantity || 'Quantity') + '</th><th>' + escapeHtml(i18n.track_inventory || 'Track inventory') + '</th></tr></thead><tbody>' + locations + '</tbody></table></div>' : '')
                        + '</div></details>';
                }).join('');
                detail.innerHTML = '<div class="product-detail-page bulk-preview-detail"><div class="product-detail-hero"><div class="product-detail-hero-main">'
                    + imageMarkup(product, 76) + '<div class="min-w-0"><h1>'
                    + escapeHtml(product.title || product.handle || i18n.product_preview_untitled || 'Untitled product') + '</h1><div class="product-detail-subtitle"><span>' + escapeHtml(product.handle || '')
                    + '</span><span>' + escapeHtml(String(i18n.product_preview_rows || '').replace(':first', data.source_rows?.[0] || data.first_row_number).replace(':last', data.source_rows?.slice(-1)[0] || data.last_row_number))
                    + '</span></div></div></div><div>' + issueBadges(data) + '</div></div>' + issueBlock
                    + '<section class="product-detail-section"><div class="product-detail-section-heading"><h2>' + escapeHtml(i18n.overview || 'Overview') + '</h2></div>'
                    + '<div class="product-detail-grid"><div class="product-detail-field"><span>' + escapeHtml(i18n.category || 'Category') + '</span><strong>' + escapeHtml(product.category || '—')
                    + '</strong></div><div class="product-detail-field"><span>' + escapeHtml(i18n.brand || 'Brand') + '</span><strong>' + escapeHtml(product.brand || '—')
                    + '</strong></div><div class="product-detail-field"><span>' + escapeHtml(i18n.product_preview_type_status || 'Type / status') + '</span><strong>' + escapeHtml((product.type || '—') + ' / ' + (product.status || '—'))
                    + '</strong></div><div class="product-detail-field"><span>' + escapeHtml(i18n.shipping || 'Shipping') + '</span><strong>' + escapeHtml(product.shipping_profile || '—')
                    + '</strong></div><div class="product-detail-field"><span>' + escapeHtml(i18n.tax_classes || 'Tax classes') + '</span><strong>' + escapeHtml(product.tax_classes || '—') + '</strong></div></div></section>'
                    + '<section class="product-detail-section"><div class="product-detail-section-heading"><h2>' + escapeHtml(i18n.media || 'Media') + '</h2></div><div class="d-flex flex-wrap gap-2">' + media.map(function (mediaUrl) {
                        const safe = escapeHtml(mediaUrl); return '<a href="' + safe + '" data-fslightbox="product-bulk-preview-detail" data-type="image"><img src="' + safe + '" width="88" height="88" class="rounded border object-fit-cover" loading="lazy" referrerpolicy="no-referrer" alt=""></a>';
                    }).join('') + '</div></section>'
                    + (attributeDetails ? '<section class="product-detail-section"><div class="product-detail-section-heading"><h2>' + escapeHtml(i18n.product_preview_attribute_values || 'Attribute values') + '</h2></div>' + attributeDetails + '</section>' : '')
                    + '<section class="product-detail-section"><div class="product-detail-section-heading"><h2>' + escapeHtml(i18n.product_preview_variants_inventory || 'Variants and inventory') + '</h2></div><div class="product-detail-variants">' + (variants || '<div class="text-muted">' + escapeHtml(i18n.product_preview_no_variants || 'No variants') + '</div>') + '</div></section></div>';
                if (typeof window.refreshFsLightbox === 'function') window.refreshFsLightbox();
            }).catch(function (error) {
                detail.innerHTML = '<div class="alert alert-danger">' + escapeHtml(error.response?.data?.message || i18n.product_preview_failed) + '</div>';
            });
        }

        document.addEventListener('catalog-bulk-preview:ready', function (event) {
            token = event.detail.preview_token || '';
            if (seller) seller.tomselect ? seller.tomselect.disable() : seller.setAttribute('disabled', 'disabled');
            review.classList.remove('d-none');
            currentSummary = null;
            status.textContent = i18n.product_preview_mapping_ready || 'Review the field mapping and import mode, then build the product preview.';
            progress.className = 'progress-bar bg-secondary';
            progress.style.width = '0%';
            counts.innerHTML = '';
            attributeCounts.innerHTML = '';
            sample.innerHTML = '';
            refresh.disabled = false;
            open.disabled = true;
            setQueueState(false);
        });
        document.addEventListener('catalog-bulk-preview:reset', function () {
            buildGeneration++;
            if (window.jQuery && window.jQuery.fn.modal) window.jQuery(modalElement).modal('hide');
            if (token) axios.delete(url(cfg.productPreviewDestroyUrl), { headers: { 'X-CSRF-TOKEN': cfg.csrf, 'Accept': 'application/json' } }).catch(function () {});
            token = '';
            currentSummary = null;
            review.classList.add('d-none');
            setQueueState(false);
            if (seller) seller.tomselect ? seller.tomselect.enable() : seller.removeAttribute('disabled');
        });
        confirmForm.addEventListener('change', function (event) {
            if (event.target.matches('[name^="mapping["], [name="operation"], [name="match_field"]')) markStale();
        });
        refresh.addEventListener('click', buildIndex);
        open.addEventListener('click', function () {
            showTab('products');
            window.jQuery(modalElement).modal('show');
            loadProducts(1);
        });
        attributeCounts.addEventListener('click', function (event) {
            if (!event.target.closest('.bulk-review-view-attributes')) return;
            window.jQuery(modalElement).modal('show');
            showTab('attributes');
        });
        productsTab.addEventListener('click', function () { showTab('products'); loadProducts(1); });
        attributesTab.addEventListener('click', function () { showTab('attributes'); });
        attributePagination.addEventListener('click', function (event) {
            const button = event.target.closest('[data-page]');
            if (!button || button.closest('.disabled')) return;
            loadAttributes(Number(button.dataset.page));
        });
        attributeStatus.addEventListener('change', function () { loadAttributes(1); });
        attributePageSize.addEventListener('change', function () { loadAttributes(1); });
        attributeSearch.addEventListener('input', function () {
            window.clearTimeout(attributeSearchTimer);
            attributeSearchTimer = window.setTimeout(function () { loadAttributes(1); }, 300);
        });
        list.addEventListener('click', function (event) {
            const button = event.target.closest('.product-preview-detail');
            if (button) showDetail(button.dataset.id);
        });
        back.addEventListener('click', function () {
            detailPanel.classList.add('d-none');
            listPanel.classList.remove('d-none');
        });
        pagination.addEventListener('click', function (event) {
            const button = event.target.closest('[data-page]');
            if (!button || button.closest('.disabled')) return;
            loadProducts(Number(button.dataset.page));
        });
        function goToPage() {
            const requestedPage = Number(goPage.value);
            if (!Number.isInteger(requestedPage) || requestedPage < 1) return;
            loadProducts(Math.min(requestedPage, lastPage));
        }
        go.addEventListener('click', goToPage);
        goPage.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                goToPage();
            }
        });
        issue.addEventListener('change', function () { loadProducts(1); });
        pageSize.addEventListener('change', function () { loadProducts(1); });
        search.addEventListener('input', function () {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(function () { loadProducts(1); }, 300);
        });
        document.addEventListener('error', function (event) {
            if (!event.target.matches('.bulk-review-thumb img')) return;
            const thumb = event.target.closest('.bulk-review-thumb');
            thumb.classList.add('is-fallback');
            thumb.removeAttribute('href');
            thumb.removeAttribute('data-fslightbox');
        }, true);
        setQueueState(false);
    });
})();
