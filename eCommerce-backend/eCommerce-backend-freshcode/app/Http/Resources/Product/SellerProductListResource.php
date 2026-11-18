<?php

namespace App\Http\Resources\Product;

class SellerProductListResource extends ProductListResource
{
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'verification_status' => $this->verification_status,
            'rejection_reason' => (string) $this->verification_status === 'rejected' ? $this->rejection_reason : null,
        ]);
    }
}
