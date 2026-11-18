<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderRefundResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'amount' => (float) $this->amount,
            'shipping_refund_amount' => (float) $this->shipping_refund_amount,
            'currency_code' => $this->currency_code,
            'status' => $this->status->value,
            'method' => $this->method->value,
            'settled_by_refund_id' => $this->settled_by_refund_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'items' => $this->items->map(fn ($item) => [
                'order_item_id' => (int) $item->id,
                'quantity' => (int) $item->pivot->quantity,
                'amount' => (float) $item->pivot->amount,
            ])->values()->all(),
        ];
    }
}
