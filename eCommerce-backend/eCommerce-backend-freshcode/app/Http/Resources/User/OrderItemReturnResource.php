<?php

namespace App\Http\Resources\User;

use App\Services\OrderCustomerStatusPresenter;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemReturnResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        $customerStatus = new OrderCustomerStatusPresenter()->returnStatus($this->resource);

        return [
            'id' => $this->id,
            'quantity' => (int) $this->quantity,
            'return_status' => $this->return_status,
            'customer_status' => [
                'code'         => $customerStatus['code'],
                'label'        => $customerStatus['label'],
                'description'  => $customerStatus['description'],
                'stage'        => $customerStatus['stage'],
                'is_exception' => $customerStatus['is_exception'],
            ],
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
