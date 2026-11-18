<?php

namespace App\Models;

use App\Enums\Order\OrderItemReturnStatusEnum;
use App\Enums\Order\ReturnAdjudicationStatusEnum;
use App\Enums\Order\ReturnReasonCodeEnum;
use App\Enums\Order\ReturnReasonTypeEnum;
use App\Enums\SpatieMediaCollectionName;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class OrderItemReturn extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'order_item_returns';

    protected $appends = ['images'];

    protected $fillable = [
        'order_item_id',
        'order_id',
        'user_id',
        'seller_id',
        'store_id',
        'quantity',
        'reason',
        'reason_code',
        'reason_type',
        'shipping_cost_payer',
        'reimbursement_amount',
        'return_shipment_id',
        'source_shipment_id',
        'admin_adjudication_status',
        'admin_notes',
        'refund_amount',
        'return_shipping_charge',
        'return_carrier',
        'return_tracking_number',
        'return_tracking_url',
        'return_label_url',
        'no_shipping_required',
        'seller_comment',
        'return_status',
        'seller_approved_at',
        'picked_up_at',
        'received_at',
        'refund_processed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'no_shipping_required' => 'boolean',
        'refund_amount' => 'decimal:2',
        'return_shipping_charge' => 'decimal:2',
        'reimbursement_amount' => 'decimal:2',
        'reason_code' => ReturnReasonCodeEnum::class,
        'reason_type' => ReturnReasonTypeEnum::class,
        'admin_adjudication_status' => ReturnAdjudicationStatusEnum::class,
        'seller_approved_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'received_at' => 'datetime',
        'refund_processed_at' => 'datetime',
    ];

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function sourceShipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class, 'source_shipment_id');
    }

    public function getImagesAttribute()
    {
        return $this->getMedia(SpatieMediaCollectionName::ITEM_RETURN_IMAGES())
            ->map(fn($media) => $media->getUrl());
    }

    /** First uploaded return-label file URL (Spatie media), or null. */
    public function labelFileUrl(): ?string
    {
        return $this->getFirstMedia(SpatieMediaCollectionName::RETURN_LABEL())?->getUrl();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    protected
    static function booted(): void
    {
        static::deleted(function ($orderReturn) {
            $orderReturn->clearMediaCollection();
        });
    }
}
