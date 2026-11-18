<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Support\PricePresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InvoiceBuilderController extends Controller
{
    private const SETTING_KEY = 'invoice_builder';

    public function index(): View
    {
        $value = Setting::query()->find(self::SETTING_KEY)?->value ?? [];
        return view('admin.orders.invoice-builder', [
            'draft' => $this->mergeConfig($value['draft'] ?? $value['published'] ?? []),
            'published' => $this->mergeConfig($value['published'] ?? []),
            'hasPublished' => ! empty($value['published']),
            'orders' => Order::query()->latest('id')->get(array_values(array_filter(['uuid', 'id', 'slug', Schema::hasColumn('orders', 'order_number') ? 'order_number' : null]))),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orderUuid' => ['required', 'uuid', 'exists:orders,uuid'],
        ]);
        $sellerOrders = SellerOrder::with(
            'order',
            'order.market',
            'seller',
            'order.promoLine',
            'items.product',
            'items.orderItem.store',
            'items.variant',
            'items.orderItem',
            'items.orderItem.addons.addonGroup',
            'items.orderItem.addons.addonItem',
        )->whereHas('order', fn ($query) => $query->where('uuid', $validated['orderUuid']))->get();

        abort_if($sellerOrders->isEmpty(), 404, __('labels.order_not_found'));

        $order = $sellerOrders->first()->order;
        $currency = PricePresenter::payload($order->market);
        $items = $sellerOrders->flatMap(function ($sellerOrder) use ($currency) {
            $store = $sellerOrder->items->first()?->orderItem?->store;

            return $sellerOrder->items->map(function ($item) use ($store, $currency) {
                $orderItem = $item->orderItem;
                $quantity = max(1, (int) $item->quantity);
                $unitPrice = (float) $item->price;
                $taxPercent = (float) ($orderItem?->tax_percent ?? 0);
                $tax = round($quantity * $unitPrice * $taxPercent / 100, 2);
                $addons = $orderItem?->addons ?? collect();
                $addonTotal = round($quantity * (float) $addons->sum('price'), 2);
                $total = round(($quantity * $unitPrice) + $addonTotal + $tax, 2);
                $details = collect([
                    $orderItem?->variant_title ?: $item->variant?->title,
                    $orderItem?->sku ? 'SKU: '.$orderItem->sku : null,
                ])->filter()->implode(' · ');

                return [
                    'title' => $orderItem?->title ?: $item->product?->title ?: 'Item',
                    'details' => $details,
                    'quantity' => $quantity,
                    'status' => Str::headline((string) ($item->status ?? '—')),
                    'taxPercent' => number_format($taxPercent, 2),
                    'tax' => formatOrderMoney($tax, $currency),
                    'unitPrice' => formatOrderMoney($unitPrice, $currency),
                    'total' => formatOrderMoney($total, $currency),
                    'seller' => $store?->name ?: 'Seller',
                    'addons' => $addons->map(fn ($addon) => [
                        'name' => collect([$addon->addonGroup?->title, $addon->addonItem?->title])->filter()->implode(': '),
                        'price' => formatOrderMoney((float) $addon->price, $currency),
                    ])->values(),
                ];
            });
        })->values();
        $totals = data_get($order, 'order_summary.converted_currency', []);
        $itemsSubtotal = (float) (data_get($totals, 'items_total') ?? $order->subtotal ?? 0);
        $shippingTotal = (float) (data_get($totals, 'delivery_charge') ?? $order->delivery_charge ?? 0);
        $orderTotal = (float) (data_get($totals, 'order_total') ?? $order->final_total ?? 0);
        $payableTotal = (float) (data_get($totals, 'payable_amount') ?? $order->total_payable ?? 0);

        return response()->json([
            'order' => [
                'number' => $order->order_number ?? (string) $order->id,
                'reference' => Str::upper(Str::substr((string) $order->uuid, 0, 12)),
                'date' => $order->created_at?->format('Y-m-d H:i'),
                'billingName' => $order->billing_name ?: $order->shipping_name ?: '—',
                'billingAddress' => collect([$order->billing_address_1, $order->billing_address_2, $order->billing_city, $order->billing_state, $order->billing_zip, $order->billing_country])->filter()->implode(', '),
                'billingContact' => collect([$order->email, $order->billing_phone])->filter()->implode(' · '),
                'shippingName' => $order->shipping_name ?: '—',
                'shippingAddress' => collect([$order->shipping_address_1, $order->shipping_address_2, $order->shipping_city, $order->shipping_state, $order->shipping_zip, $order->shipping_country])->filter()->implode(', '),
                'shippingContact' => $order->shipping_phone ?: '',
                'payment' => Str::headline((string) $order->payment_method).' · '.Str::headline((string) $order->payment_status),
                'currency' => $order->currency_code ?? data_get($currency, 'code', ''),
                'seller' => $items->first()['seller'] ?? 'Seller',
                'sellerId' => $sellerOrders->first()->seller_id,
                'sellerTax' => $sellerOrders->first()->items->first()?->orderItem?->store?->tax_number ?? '',
                'itemsSubtotal' => formatOrderMoney($itemsSubtotal, $currency),
                'shippingTotal' => formatOrderMoney($shippingTotal, $currency),
                'orderTotal' => formatOrderMoney($orderTotal, $currency),
                'payableTotal' => formatOrderMoney($payableTotal, $currency),
                'items' => $items,
            ],
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['draft', 'publish', 'reset'])],
            'config' => ['required_unless:action,reset', 'array'],
            'config.title' => ['sometimes', 'string', 'max:80'],
            'config.templateName' => ['sometimes', 'string', 'max:80'],
            'config.showLogo' => ['sometimes', 'boolean'],
            'config.showItemStatus' => ['sometimes', 'boolean'],
            'config.showAddons' => ['sometimes', 'boolean'],
            'config.showPaymentStatus' => ['sometimes', 'boolean'],
            'config.previewOrderUuid' => ['nullable', 'uuid', 'exists:orders,uuid'],
            'config.previewMode' => ['sometimes', Rule::in(['sample', 'existing'])],
            'config.templateType' => ['sometimes', Rule::in(['grid', 'blue'])],
            'config.layout' => ['sometimes', Rule::in(['classic', 'modern', 'compact'])],
            'config.brandColor' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'config.pageBackgroundColor' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'config.showPageLines' => ['sometimes', 'boolean'],
            'config.pageLineColor' => ['sometimes', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'config.brandColorMode' => ['sometimes', Rule::in(['system', 'custom'])],
            'config.font' => ['sometimes', 'string', 'max:80', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} -]{0,79}$/u'],
            'config.sections.*.titleColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'config.sections.*.subtitleColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'config.sections.*.backgroundColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'config.sections.*.itemHeaderTitleColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'config.sections.*.itemHeaderBackgroundColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'config.sections.*.itemDataTitleColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'config.sections.*.itemDataSubtitleColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'config.sections.*.itemDataBackgroundColor' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'config.margin' => ['sometimes', 'integer', 'min:5', 'max:30'],
            'config.footer' => ['nullable', 'string', 'max:500'],
            'config.terms' => ['nullable', 'string', 'max:500'],
            'config.totalLabel' => ['nullable', 'string', 'max:100'],
            'config.sections' => ['sometimes', 'array', 'min:1'],
            'config.sections.*.key' => ['required', Rule::in(['header', 'details', 'addresses', 'seller_details', 'items', 'totals', 'payment', 'terms', 'signature', 'footer'])],
            'config.sections.*.enabled' => ['required', 'boolean'],
        ]);

        $setting = Setting::query()->firstOrNew(['variable' => self::SETTING_KEY]);
        $value = is_array($setting->value) ? $setting->value : [];

        if ($validated['action'] === 'reset') {
            $value['draft'] = $this->mergeConfig($value['published'] ?? []);
        } else {
            $config = $this->mergeConfig($validated['config']);
            $sectionDefaults = collect($this->defaults()['sections'])->keyBy('key');

            foreach ($config['sections'] as &$section) {
                $sectionDefault = $sectionDefaults->get($section['key']);
                $section['label'] = $sectionDefault['label'];
                $section['required'] = (bool) ($sectionDefault['required'] ?? false);
                $section['enabled'] = $section['required'] || (bool) $section['enabled'];
            }
            unset($section);

            $value['draft'] = $config;
            if ($validated['action'] === 'publish') {
                $value['published'] = $config;
            }
        }

        $setting->value = json_encode($value);
        $setting->save();

        return response()->json([
            'message' => match ($validated['action']) {
                'publish' => 'Invoice design published.',
                'reset' => 'Draft reset to the published design.',
                default => 'Draft saved.',
            },
            'draft' => $value['draft'],
            'published' => $value['published'] ?? null,
            'hasPublished' => ! empty($value['published']),
        ]);
    }

    public static function publishedConfig(): array
    {
        $value = Setting::query()->find(self::SETTING_KEY)?->value ?? [];

        return (new self)->mergeConfig($value['published'] ?? []);
    }

    private function mergeConfig(array $config): array
    {
        $merged = array_replace_recursive($this->defaults(), $config);
        if (! array_key_exists('previewMode', $config)) {
            $merged['previewMode'] = ! empty($config['previewOrderUuid']) ? 'existing' : 'sample';
        }
        if ($merged['previewMode'] === 'sample') {
            $merged['previewOrderUuid'] = null;
        }
        if (! array_key_exists('showPageLines', $config)) {
            $merged['showPageLines'] = (bool) ($config['showTopLine'] ?? false) || (bool) ($config['showBottomLine'] ?? false);
        }
        if (! array_key_exists('pageLineColor', $config)) {
            $merged['pageLineColor'] = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($config['topLineColor'] ?? ''))
                ? $config['topLineColor']
                : (preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($config['bottomLineColor'] ?? '')) ? $config['bottomLineColor'] : $merged['pageLineColor']);
        }
        unset($merged['showTopLine'], $merged['topLineColor'], $merged['showBottomLine'], $merged['bottomLineColor']);
        $brandColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($merged['brandColor'] ?? '')) ? $merged['brandColor'] : '#206bc4';
        $merged['sections'] = collect($merged['sections'])->map(function ($section) use ($brandColor) {
            $section = array_merge([
                'titleColor' => null,
                'subtitleColor' => null,
                'backgroundColor' => null,
                'itemHeaderTitleColor' => null,
                'itemHeaderBackgroundColor' => null,
                'itemDataTitleColor' => null,
                'itemDataSubtitleColor' => null,
                'itemDataBackgroundColor' => null,
            ], is_array($section) ? $section : []);
            if (($section['key'] ?? null) === 'header' && (! preg_match('/^#[0-9a-fA-F]{6}$/', (string) $section['titleColor']) || strtolower((string) $section['titleColor']) === '#182433')) {
                $section['titleColor'] = $brandColor;
            }
            if (in_array($section['key'] ?? null, ['payment', 'terms', 'footer'], true)) {
                $section['subtitleColor'] = null;
            }

            return $section;
        })->values()->all();
        $legacyTemplates = ['standard' => 'blue', 'tax' => 'blue', 'receipt' => 'grid', 'green' => 'blue'];
        if (isset($config['templateType']) && isset($legacyTemplates[$config['templateType']])) {
            $merged['templateType'] = $legacyTemplates[$config['templateType']];
        } elseif (! array_key_exists('templateType', $config)) {
            $merged['templateType'] = match ($merged['layout']) {
                'modern' => 'blue',
                'compact' => 'grid',
                default => 'blue',
            };
        }

        return $merged;
    }

    private function defaults(): array
    {
        return [
            'title' => 'Tax Invoice',
            'templateName' => 'Order Invoice',
            'showLogo' => true,
            'showItemStatus' => true,
            'showAddons' => true,
            'showPaymentStatus' => true,
            'previewOrderUuid' => null,
            'previewMode' => 'sample',
            'templateType' => 'blue',
            'layout' => 'classic',
            'brandColor' => '#f4c20d',
            'pageBackgroundColor' => '#ffffff',
            'showPageLines' => false,
            'pageLineColor' => '#f4c20d',
            'brandColorMode' => 'system',
            'font' => 'Arial',
            'margin' => 15,
            'totalLabel' => 'Total Payable',
            'terms' => 'Please retain this invoice for your records.',
            'footer' => 'Thank you for shopping with us!',
            'sections' => [
                ['key' => 'header', 'label' => 'Header', 'enabled' => true, 'required' => true],
                ['key' => 'details', 'label' => 'Invoice Details', 'enabled' => true, 'required' => true],
                ['key' => 'addresses', 'label' => 'Addresses', 'enabled' => true, 'required' => false],
                ['key' => 'seller_details', 'label' => 'Seller Details', 'enabled' => true, 'required' => false],
                ['key' => 'items', 'label' => 'Items', 'enabled' => true, 'required' => true],
                ['key' => 'totals', 'label' => 'Totals', 'enabled' => true, 'required' => true],
                ['key' => 'payment', 'label' => 'Payment', 'enabled' => true, 'required' => false],
                ['key' => 'terms', 'label' => 'Terms', 'enabled' => false, 'required' => false],
                ['key' => 'signature', 'label' => 'Signature', 'enabled' => true, 'required' => false],
                ['key' => 'footer', 'label' => 'Footer', 'enabled' => true, 'required' => false],
            ],
        ];
    }
}
