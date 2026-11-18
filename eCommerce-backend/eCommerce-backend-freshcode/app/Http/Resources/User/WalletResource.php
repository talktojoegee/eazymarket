<?php

namespace App\Http\Resources\User;

use App\Enums\Wallet\WalletTypeEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type instanceof WalletTypeEnum ? $this->type->value : $this->type,
            'balance' => $this->display_balance ?? $this->balance,
            'blocked_balance' => $this->blocked_balance,
            'formatted_balance' => $this->formatted_balance,
            'available_balance' => $this->available_balance,
            'available_formatted_balance' => $this->available_formatted_balance,
            'currency_code' => $this->currency_code,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
