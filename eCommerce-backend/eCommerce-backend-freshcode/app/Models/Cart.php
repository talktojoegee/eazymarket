<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static where(string $string, mixed $id)
 */
class Cart extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'market_id',
        'shipping_address_id',
        'billing_address_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The market this cart was created in. Snapshot gets locked to the shipping
     * address country at checkout (Shopify Markets parity) — see
     * CartService::lockMarketAtCheckout.
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }
}
