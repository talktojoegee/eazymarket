<?php

namespace App\Support;

use App\Models\SellerStatement;

/**
 * Shared money math for a settlement statement row, so the seller panel tables,
 * the CSV export and the seller API all report identical gross / commission /
 * tax / net figures for the same entry.
 */
trait SellerStatementFigures
{
    protected function statementFigures(SellerStatement $st): array
    {
        $orderItem = $st->orderItem;
        $meta = is_array($st->meta) ? $st->meta : [];
        $net = (float) $st->amount;
        $isDebit = (string) $st->entry_type === 'debit';

        if (array_key_exists('marketplace_fee', $meta)) {
            $commission = (float) $meta['marketplace_fee'];

            return [
                'gross' => $isDebit ? $commission : round($net + $commission, 2),
                'commission' => $commission,
                'net' => $net,
                'tax' => null,
                'currency' => $st->currency_code,
                'has_item' => false,
                'market_amount' => isset($meta['market_amount']) ? (float) $meta['market_amount'] : null,
                'market_code' => $meta['currency_code'] ?? null,
                'rate' => isset($meta['currency_rate']) ? (float) $meta['currency_rate'] : null,
            ];
        }

        if ($orderItem) {
            return [
                'gross' => (float) $orderItem->subtotal,
                'commission' => (float) $orderItem->admin_commission_amount,
                'net' => $net,
                'tax' => (float) $orderItem->tax_amount,
                'currency' => $st->currency_code ?: ($orderItem->order->currency_code ?? null),
                'has_item' => true,
                'market_amount' => null,
                'market_code' => null,
                'rate' => null,
            ];
        }

        return [
            'gross' => $net,
            'commission' => 0.0,
            'net' => $net,
            'tax' => null,
            'currency' => $st->currency_code,
            'has_item' => false,
            'market_amount' => null,
            'market_code' => null,
            'rate' => null,
        ];
    }
}
