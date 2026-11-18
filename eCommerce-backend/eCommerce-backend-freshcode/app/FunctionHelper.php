<?php

use Illuminate\Support\Str;

if (!function_exists('hyperAsset')) {
    function hyperAsset($path, $secure = null)
    {
        $url = app('url')->asset($path, $secure);

        $fullPath = public_path($path);
        if (file_exists($fullPath)) {
            $timestamp = filemtime($fullPath);
            return $url . '?v=' . $timestamp;
        }

        return $url;
    }
}

if (!function_exists('generateUniqueSlug')) {
    function generateUniqueSlug($model, $title, $slugField = 'slug', $id = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $i = 1;

        while (
            $model::withoutGlobalScopes()
                ->where($slugField, $slug)
                ->when($id, function ($query) use ($id) {
                    $query->where('id', '!=', $id);
                })
                ->exists()
        ) {
            $slug = $originalSlug . '-' . $i++;
        }
        return $slug;
    }
}

if (!function_exists('getCurrencySymbol')) {
    function getCurrencySymbol(): string
    {
        $setting = App\Models\Setting::where('variable', 'system')->first();
        return $setting->value['currencySymbol'] ?? '$';
    }
}

if (!function_exists('formatOrderMoney')) {
    /**
     * Format a money amount for an order using its market's currency rules
     * (symbol, symbol position, decimal places, thousand/decimal separators,
     * grouping style). Order amounts are already snapshotted in the order's
     * settled currency, so this formats — it does NOT convert via FX.
     *
     * `$currency` is the `currency` payload exposed by OrderResource /
     * PricePresenter::payload — shape: ['symbol' => '₹', 'format' => [...rules]].
     * When null (e.g. legacy orders with no market) we fall back to the
     * platform-default currency symbol with 2 decimals so a price is never
     * shown as a bare number.
     *
     * @param array<string, mixed>|null $currency
     */
    function formatOrderMoney($amount, ?array $currency = null): string
    {
        $amount = (float) ($amount ?? 0);
        if (is_array($currency) && isset($currency['format'], $currency['symbol'])) {
            return App\Support\PricePresenter::formatWith(
                $amount,
                $currency['format'],
                (string) $currency['symbol'],
            );
        }

        return app(App\Services\CurrencyService::class)->format($amount);
    }
}

if (!function_exists('formatOrderStatusLabel')) {
    /**
     * Convert a raw order/order-item status string into a localised, human-friendly
     * label. Looks up labels.<status> first; falls back to title-cased underscore
     * replacement when no translation key exists. Used by API resources so mobile
     * apps don't have to mirror the copy.
     */
    function formatOrderStatusLabel(?string $status): string
    {
        if (empty($status)) {
            return '';
        }

        $key = 'labels.' . $status;
        $translated = __($key);
        if ($translated !== $key) {
            return (string) $translated;
        }

        return Str::title(str_replace('_', ' ', $status));
    }
}

if (!function_exists('fulfillmentStatusBadgeClass')) {
    /**
     * Badge class for the fulfillment axis (FulfillmentStatusEnum). Maps onto the
     * custom dot-badge classes in custom.css (*-badge).
     */
    function fulfillmentStatusBadgeClass(?string $status): string
    {
        return match ((string) $status) {
            'fulfilled'           => 'fulfilled-badge',
            'partially_fulfilled' => 'partially-fulfilled-badge',
            'in_progress'         => 'in-progress-badge',
            'on_hold'             => 'onhold-badge',
            default               => 'unfulfilled-badge',
        };
    }
}

if (!function_exists('paymentStatusBadgeClass')) {
    /**
     * Badge class for the payment axis (PaymentStatusEnum). Maps onto the custom
     * dot-badge classes in custom.css (*-badge).
     */
    function paymentStatusBadgeClass(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'completed', 'paid'  => 'completed-badge',
            'partially_paid'     => 'partially-paid-badge',
            'authorized'         => 'authorized-badge',
            'partially_refunded' => 'partially-refunded-badge',
            'refunded'           => 'refunded-badge',
            'voided'             => 'voided-badge',
            'failed'             => 'failed-badge',
            default              => 'pending-badge',
        };
    }
}
