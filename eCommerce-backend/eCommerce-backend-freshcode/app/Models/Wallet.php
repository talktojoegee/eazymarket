<?php

namespace App\Models;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\Seller\SellerWithdrawalStatusEnum;
use App\Enums\Wallet\WalletTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @method static where(string $string, $id)
 * @method static firstOrCreate(array $array, array $array1)
 * @method static \Illuminate\Database\Eloquent\Builder ofType(WalletTypeEnum|string $type)
 */
class Wallet extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'type',
        'balance',
        'currency_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'user_id' => 'integer',
            'type' => WalletTypeEnum::class,
            'balance' => 'decimal:2',
            'blocked_balance' => 'decimal:2',
            'currency_code' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope the query to wallets of a specific ownership type.
     */
    public function scopeOfType(Builder $query, WalletTypeEnum|string $type): Builder
    {
        $value = $type instanceof WalletTypeEnum ? $type->value : $type;

        return $query->where('type', $value);
    }

    public function getSellerBlockedBalance($sellerId): ?float
    {
        return SellerWithdrawalRequest::where('seller_id', $sellerId)
            ->where('status', SellerWithdrawalStatusEnum::PENDING())
            ->sum('amount');
    }


    public static function captureRefund($transactionId, $refundAmount = null): array
    {
        DB::beginTransaction();
        try {
            $transaction = WalletTransaction::where('id', $transactionId)->first();

            if (!$transaction) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Transaction not found.',
                ];
            }

            if (!in_array($transaction->status, [PaymentStatusEnum::COMPLETED(), PaymentStatusEnum::PARTIALLY_REFUNDED()])) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Transaction not eligible for refund. Status: ' . $transaction->status,
                ];
            }

            $wallet = self::where('id', $transaction->wallet_id)->first();
            if (!$wallet) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Wallet not found.',
                ];
            }

            $refundAmount = $refundAmount ?? $transaction->amount;

            if ($refundAmount <= 0) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Refund amount must be greater than zero.',
                ];
            }

            if ($refundAmount > $transaction->amount) {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Refund amount cannot exceed the original transaction amount.',
                ];
            }

            $totalRefundedAmount = $refundAmount;
            if ($transaction->status === PaymentStatusEnum::PARTIALLY_REFUNDED()) {
                $previousRefunds = WalletTransaction::where('order_id', $transaction->order_id)
                    ->where('transaction_type', 'refund')
                    ->where('status', PaymentStatusEnum::COMPLETED())
                    ->sum('amount');

                $totalRefundedAmount = $previousRefunds + $refundAmount;

                if ($totalRefundedAmount > $transaction->amount) {
                    DB::rollBack();
                    return [
                        'success' => false,
                        'message' => 'Total refund amount would exceed the original transaction amount.',
                    ];
                }
            }

            self::where('id', $wallet->id)->increment('balance', (float) $refundAmount);
            $wallet->refresh();

            if ($totalRefundedAmount >= $transaction->amount) {
                $transaction->status = PaymentStatusEnum::REFUNDED();
                $statusMessage = 'Wallet full refund successful.';
            } else {
                $transaction->status = PaymentStatusEnum::PARTIALLY_REFUNDED();
                $statusMessage = 'Wallet partial refund successful.';
            }

            $transactionSaved = $transaction->save();

            if ($transactionSaved) {
                DB::commit();
                return [
                    'success' => true,
                    'message' => $statusMessage,
                    'refunded_amount' => $refundAmount,
                    'total_refunded' => $totalRefundedAmount,
                    'original_amount' => $transaction->amount,
                    'status' => $transaction->status,
                ];
            } else {
                DB::rollBack();
                return [
                    'success' => false,
                    'message' => 'Failed to update transaction status.',
                ];
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ];
        }
    }

    public static function captureRecharge($transactionId): array
    {
        try {
            return DB::transaction(function () use ($transactionId) {
                $claimed = WalletTransaction::where('id', $transactionId)
                    ->where('status', PaymentStatusEnum::PENDING())
                    ->update(['status' => PaymentStatusEnum::COMPLETED()]);

                if ($claimed === 0) {
                    return [
                        'success' => false,
                        'message' => 'Transaction not found or already completed.',
                    ];
                }

                $transaction = WalletTransaction::find($transactionId);
                $credited = self::where('id', $transaction->wallet_id)
                    ->increment('balance', (float) $transaction->amount);

                if ($credited === 0) {
                    throw new \RuntimeException('Wallet not found.');
                }

                return [
                    'success' => true,
                    'message' => 'Wallet recharge successful.',
                ];
            });
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
            ];
        }
    }
}
