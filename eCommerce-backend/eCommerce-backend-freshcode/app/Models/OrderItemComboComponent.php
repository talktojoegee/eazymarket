<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItemComboComponent extends Model
{
    protected $fillable = [
        'order_item_id',
        'product_id',
        'product_variant_id',
        'product_title',
        'variant_title',
        'quantity',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
