<?php

namespace App\Models;

use App\Enums\Order\RefundMethodEnum;
use App\Enums\Order\RefundStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Single refund event tied to an order. Created whenever money is (or will be)
 * returned to the customer — wallet credit on cancel, future gateway reversals,
 * manual write-offs. The pivot {@see OrderRefundItem} carries the per-line
 * breakdown so we can answer "which cancelled units made up this refund?".
 *
 * Amount is stored in the order's market currency to match the rest of the
 * order money fields. Wallet credit conversion to base currency happens in
 * OrderService::issueWalletRefund.
 */
class OrderRefund extends Model
{
    protected $fillable = [
        'uuid',
        'order_id',
        'amount',
        'shipping_refund_amount',
        'currency_code',
        'method',
        'status',
        'settled_by_refund_id',
        'reason',
        'issued_by',
        'issued_at',
        'gateway_reference',
        'wallet_transaction_id',
        'notes',
    ];

    protected $casts = [
        'amount'                 => 'decimal:2',
        'shipping_refund_amount' => 'decimal:2',
        'method'                 => RefundMethodEnum::class,
        'status'                 => RefundStatusEnum::class,
        'issued_at'              => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (OrderRefund $refund) {
            if (empty($refund->uuid)) {
                $refund->uuid = (string) Str::uuid();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Admin who issued the refund. Named `issuer` (not `issuedBy`) on purpose —
     * `issuedBy` snake-cases to `issued_by`, which collides with the existing
     * FK column of the same name and breaks Eloquent's relation/attribute
     * resolution.
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Order items this refund covers, with per-line quantity and amount on the
     * pivot. A line can appear in multiple refunds (cancel-then-cancel-more).
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(OrderItem::class, 'order_refund_items')
            ->withPivot(['quantity', 'amount'])
            ->withTimestamps();
    }

    /**
     * If this row is OWED and a later refund covered it, points at that newer
     * refund. The pair lets us keep the historical commitment without
     * double-counting the money (see {@see Order::amount_refund_owed}).
     */
    public function settledBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'settled_by_refund_id');
    }

    /**
     * Reverse side — every previously-OWED refund row that this row paid off.
     */
    public function settles(): HasMany
    {
        return $this->hasMany(self::class, 'settled_by_refund_id');
    }

    /**
     * Item-attributable portion of this refund — everything in the pivot.
     * Lets the breakdown UI render "Items + Shipping + Other" without
     * recomputing pivot sums everywhere.
     */
    public function getItemsRefundAmountAttribute(): float
    {
        return (float) $this->items->sum('pivot.amount');
    }

    /**
     * "Goodwill" — money refunded over and above the items + shipping share.
     * E.g. admin refunded €1180 on €1000 items + €30 shipping → €150 goodwill.
     */
    public function getGoodwillAmountAttribute(): float
    {
        return max(0, (float) $this->amount
            - $this->items_refund_amount
            - (float) $this->shipping_refund_amount);
    }
}
