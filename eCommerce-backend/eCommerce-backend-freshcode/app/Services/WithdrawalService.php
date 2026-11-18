<?php

namespace App\Services;

use App\Enums\Seller\SellerWithdrawalStatusEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Events\DeliveryBoy\WithdrawalRequestCreated as DeliveryBoyWithdrawalRequestCreated;
use App\Events\DeliveryBoy\WithdrawalRequestProcessed as DeliveryBoyWithdrawalRequestProcessed;
use App\Events\Seller\WithdrawalRequestCreated as SellerWithdrawalRequestCreated;
use App\Events\Seller\WithdrawalRequestProcessed as SellerWithdrawalRequestProcessed;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyWithdrawalRequest;
use App\Models\Seller;
use App\Models\SellerWithdrawalRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WithdrawalService
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Create a new withdrawal request for a delivery boy
     *
     * @param  int  $deliveryBoyId  The delivery boy ID
     * @param  array  $data  Request data including amount and note
     * @return array Result containing success status, message, and request data
     */
    public function createDeliveryBoyWithdrawalRequest(int $deliveryBoyId, array $data): array
    {
        try {
            $deliveryBoy = DeliveryBoy::with('user')->findOrFail($deliveryBoyId);
            $userId = $deliveryBoy->user_id;

            $walletResult = $this->walletService->getWallet($userId, WalletTypeEnum::DELIVERY_BOY);
            if (! $walletResult['success']) {
                return $walletResult;
            }

            $wallet = $walletResult['data'];

            $availableBalance = $wallet->balance - $wallet->blocked_balance;
            $amount = sprintf('%.4F', (float) $data['amount']);

            DB::beginTransaction();

            $reserved = Wallet::where('user_id', $userId)
                ->where('type', WalletTypeEnum::DELIVERY_BOY->value)
                ->whereRaw('balance - blocked_balance >= ?', [$amount])
                ->update([
                    'blocked_balance' => DB::raw('blocked_balance + '.$amount),
                ]);

            if ($reserved === 0) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.insufficient_wallet_balance').' ('.__('labels.available_balance').': '.$availableBalance.')',
                    'data' => [],
                ];
            }

            $withdrawalRequest = DeliveryBoyWithdrawalRequest::create([
                'user_id' => $userId,
                'delivery_boy_id' => $deliveryBoyId,
                'amount' => $data['amount'],
                'status' => 'pending',
                'request_note' => $data['note'] ?? null,
            ]);

            event(new DeliveryBoyWithdrawalRequestCreated($withdrawalRequest));

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.withdrawal_request_created_successfully'),
                'data' => $withdrawalRequest,
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => __('labels.delivery_boy_not_found'),
                'data' => [],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating delivery boy withdrawal request: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Create a new withdrawal request for a seller
     *
     * @param  int  $sellerId  The seller ID
     * @param  array  $data  Request data including amount and note
     * @return array Result containing success status, message, and request data
     */
    public function createSellerWithdrawalRequest(int $sellerId, array $data): array
    {
        try {
            $seller = Seller::with('user')->findOrFail($sellerId);
            $userId = $seller->user_id;

            $walletResult = $this->walletService->getWallet($userId, WalletTypeEnum::SELLER);
            if (! $walletResult['success']) {
                return $walletResult;
            }

            $wallet = $walletResult['data'];
            $availableBalance = $wallet->balance - $wallet->blocked_balance;
            $amount = sprintf('%.4F', (float) $data['amount']);

            DB::beginTransaction();

            $reserved = Wallet::where('user_id', $userId)
                ->where('type', WalletTypeEnum::SELLER->value)
                ->whereRaw('balance - blocked_balance >= ?', [$amount])
                ->update([
                    'blocked_balance' => DB::raw('blocked_balance + '.$amount),
                ]);

            if ($reserved === 0) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.insufficient_wallet_balance').' ('.__('labels.available_balance').': '.$availableBalance.')',
                    'data' => [],
                ];
            }

            $withdrawalRequest = SellerWithdrawalRequest::create([
                'user_id' => $userId,
                'seller_id' => $sellerId,
                'amount' => $data['amount'],
                'status' => SellerWithdrawalStatusEnum::PENDING(),
                'request_note' => $data['note'] ?? null,
            ]);

            event(new SellerWithdrawalRequestCreated($withdrawalRequest));

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.withdrawal_request_created_successfully'),
                'data' => $withdrawalRequest,
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => __('labels.seller_not_found'),
                'data' => [],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating seller withdrawal request: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Create a new withdrawal request (for backward compatibility)
     *
     * @param  int  $id  The delivery boy or seller ID
     * @param  array  $data  Request data including amount and note
     * @param  string  $type  The type of withdrawal request ('delivery_boy' or 'seller')
     * @return array Result containing success status, message, and request data
     */
    public function createWithdrawalRequest(int $id, array $data, string $type = 'delivery_boy'): array
    {
        if ($type === 'seller') {
            return $this->createSellerWithdrawalRequest($id, $data);
        }

        return $this->createDeliveryBoyWithdrawalRequest($id, $data);
    }

    /**
     * Process a delivery boy withdrawal request (approve or reject)
     *
     * @param  int  $requestId  The withdrawal request ID
     * @param  array  $data  Processing data including status and remark
     * @param  int  $adminId  The admin user ID
     * @return array Result containing success status, message, and request data
     */
    public function processDeliveryBoyWithdrawalRequest(int $requestId, array $data, int $adminId): array
    {
        try {
            $withdrawalRequest = DeliveryBoyWithdrawalRequest::findOrFail($requestId);

            DB::beginTransaction();

            $claimed = DeliveryBoyWithdrawalRequest::where('id', $requestId)
                ->where('status', 'pending')
                ->update([
                    'status' => $data['status'],
                    'admin_remark' => $data['remark'] ?? null,
                    'processed_at' => now(),
                    'processed_by' => $adminId,
                ]);

            if ($claimed === 0) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.withdrawal_request_already_processed'),
                    'data' => [],
                ];
            }

            $previousStatus = 'pending';
            $withdrawalRequest->refresh();

            if ($data['status'] === 'approved') {
                $wallet = Wallet::where('user_id', $withdrawalRequest->user_id)
                    ->where('type', WalletTypeEnum::DELIVERY_BOY->value)
                    ->first();

                if (! $wallet) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => __('labels.wallet_not_found'),
                        'data' => [],
                    ];
                }

                $amount = sprintf('%.4F', (float) $withdrawalRequest->amount);

                $debited = Wallet::where('id', $wallet->id)
                    ->where('balance', '>=', $amount)
                    ->where('blocked_balance', '>=', $amount)
                    ->update([
                        'balance' => DB::raw('balance - '.$amount),
                        'blocked_balance' => DB::raw('blocked_balance - '.$amount),
                    ]);

                if ($debited === 0) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => __('labels.insufficient_wallet_balance'),
                        'data' => [],
                    ];
                }

                $wallet->refresh();

                $transaction = WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $withdrawalRequest->user_id,
                    'transaction_type' => WalletTransactionTypeEnum::PAYMENT(),
                    'payment_method' => 'withdrawal',
                    'amount' => $withdrawalRequest->amount,
                    'currency_code' => $wallet->currency_code,
                    'status' => WalletTransactionStatusEnum::COMPLETED(),
                    'description' => 'Delivery Boy Withdrawal request #'.$withdrawalRequest->id.' approved',
                ]);

                $withdrawalRequest->transaction_id = $transaction->id;
            } elseif ($data['status'] === 'rejected') {
                $wallet = Wallet::where('user_id', $withdrawalRequest->user_id)
                    ->where('type', WalletTypeEnum::DELIVERY_BOY())
                    ->first();

                if ($wallet) {
                    $wallet->blocked_balance = max(0, $wallet->blocked_balance - $withdrawalRequest->amount);
                    $wallet->save();
                }
            }

            $withdrawalRequest->save();

            event(new DeliveryBoyWithdrawalRequestProcessed($withdrawalRequest, $previousStatus));

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.withdrawal_request_processed_successfully'),
                'data' => $withdrawalRequest,
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => __('labels.withdrawal_request_not_found'),
                'data' => [],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing delivery boy withdrawal request: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Process a seller withdrawal request (approve or reject)
     *
     * @param  int  $requestId  The withdrawal request ID
     * @param  array  $data  Processing data including status and remark
     * @param  int  $adminId  The admin user ID
     * @return array Result containing success status, message, and request data
     */
    public function processSellerWithdrawalRequest(int $requestId, array $data, int $adminId): array
    {
        try {
            $withdrawalRequest = SellerWithdrawalRequest::findOrFail($requestId);

            DB::beginTransaction();

            $claimed = SellerWithdrawalRequest::where('id', $requestId)
                ->where('status', SellerWithdrawalStatusEnum::PENDING())
                ->update([
                    'status' => $data['status'],
                    'admin_remark' => $data['remark'] ?? null,
                    'processed_at' => now(),
                    'processed_by' => $adminId,
                ]);

            if ($claimed === 0) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.withdrawal_request_already_processed'),
                    'data' => [],
                ];
            }

            $previousStatus = SellerWithdrawalStatusEnum::PENDING();
            $withdrawalRequest->refresh();

            if ($data['status'] === SellerWithdrawalStatusEnum::APPROVED()) {
                $wallet = Wallet::where('user_id', $withdrawalRequest->user_id)
                    ->where('type', WalletTypeEnum::SELLER->value)
                    ->first();

                if (! $wallet) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => __('labels.wallet_not_found'),
                        'data' => [],
                    ];
                }
                $amount = sprintf('%.4F', (float) $withdrawalRequest->amount);

                $debited = Wallet::where('id', $wallet->id)
                    ->where('balance', '>=', $amount)
                    ->where('blocked_balance', '>=', $amount)
                    ->update([
                        'balance' => DB::raw('balance - '.$amount),
                        'blocked_balance' => DB::raw('blocked_balance - '.$amount),
                    ]);

                if ($debited === 0) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => __('labels.insufficient_wallet_balance'),
                        'data' => [],
                    ];
                }

                $wallet->refresh();

                $transaction = WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $withdrawalRequest->user_id,
                    'transaction_type' => WalletTransactionTypeEnum::PAYMENT(),
                    'payment_method' => 'withdrawal',
                    'amount' => $withdrawalRequest->amount,
                    'currency_code' => $wallet->currency_code,
                    'status' => WalletTransactionStatusEnum::COMPLETED(),
                    'description' => 'Seller Withdrawal request #'.$withdrawalRequest->id.' approved',
                ]);

                $withdrawalRequest->transaction_id = $transaction->id;
            } elseif ($data['status'] === SellerWithdrawalStatusEnum::REJECTED()) {
                $wallet = Wallet::where('user_id', $withdrawalRequest->user_id)
                    ->where('type', WalletTypeEnum::SELLER->value)
                    ->first();
                if ($wallet) {
                    $wallet->blocked_balance = max(0, (float) $wallet->blocked_balance - (float) $withdrawalRequest->amount);
                    $wallet->save();
                }
            }

            $withdrawalRequest->save();

            event(new SellerWithdrawalRequestProcessed($withdrawalRequest, $previousStatus));

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.withdrawal_request_processed_successfully'),
                'data' => $withdrawalRequest,
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => __('labels.withdrawal_request_not_found'),
                'data' => [],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error processing seller withdrawal request: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Process a withdrawal request (approve or reject)
     *
     * @param  int  $requestId  The withdrawal request ID
     * @param  array  $data  Processing data including status and remark
     * @param  int  $adminId  The admin user ID
     * @param  string  $type  The type of withdrawal request ('delivery_boy' or 'seller')
     * @return array Result containing success status, message, and request data
     */
    public function processWithdrawalRequest(int $requestId, array $data, int $adminId, string $type = 'delivery_boy'): array
    {
        if ($type === 'seller') {
            return $this->processSellerWithdrawalRequest($requestId, $data, $adminId);
        }

        return $this->processDeliveryBoyWithdrawalRequest($requestId, $data, $adminId);
    }

    /**
     * Get delivery boy withdrawal requests with filtering and pagination
     *
     * @param  array  $filters  Filter parameters
     * @return array Result containing success status, message, and requests data
     */
    public function getDeliveryBoyWithdrawalRequests(array $filters = []): array
    {
        try {
            $query = DeliveryBoyWithdrawalRequest::with(['deliveryBoy.user', 'processedBy']);
            $perPage = $filters['per_page'] ?? 15;

            if (isset($filters['delivery_boy_id'])) {
                $query->where('delivery_boy_id', $filters['delivery_boy_id']);
            }

            if (isset($filters['user_id'])) {
                $query->where('user_id', $filters['user_id']);
            }

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['from_date'])) {
                $query->whereDate('created_at', '>=', $filters['from_date']);
            }

            if (isset($filters['to_date'])) {
                $query->whereDate('created_at', '<=', $filters['to_date']);
            }

            $sortField = $filters['sort'] ?? 'created_at';
            $sortOrder = strtolower((string) ($filters['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

            $allowedSortFields = [
                'id', 'amount', 'status', 'created_at', 'processed_at',
            ];

            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('amount', 'like', "%{$search}%")
                        ->orWhere('request_note', 'like', "%{$search}%")
                        ->orWhere('admin_remark', 'like', "%{$search}%");
                });
            }

            $page = isset($filters['start'])
                ? (int) floor(((int) $filters['start']) / max(1, (int) $perPage)) + 1
                : null;

            $requests = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'success' => true,
                'message' => __('labels.withdrawal_requests_retrieved_successfully'),
                'data' => $requests,
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving delivery boy withdrawal requests: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Get seller withdrawal requests with filtering and pagination
     *
     * @param  array  $filters  Filter parameters
     * @return array Result containing success status, message, and requests data
     */
    public function getSellerWithdrawalRequests(array $filters = []): array
    {
        try {
            $query = SellerWithdrawalRequest::with(['seller.user', 'processedBy']);
            $perPage = $filters['per_page'] ?? 15;

            if (isset($filters['seller_id'])) {
                $query->where('seller_id', $filters['seller_id']);
            }

            if (isset($filters['user_id'])) {
                $query->where('user_id', $filters['user_id']);
            }

            if (isset($filters['status'])) {
                if (is_array($filters['status'])) {
                    $query->whereIn('status', $filters['status']);
                } else {
                    $query->where('status', $filters['status']);
                }
            }

            if (isset($filters['from_date'])) {
                $query->whereDate('created_at', '>=', $filters['from_date']);
            }

            if (isset($filters['to_date'])) {
                $query->whereDate('created_at', '<=', $filters['to_date']);
            }

            $sortField = $filters['sort'] ?? 'created_at';
            $sortOrder = strtolower((string) ($filters['order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
            $allowedSortFields = [
                'id', 'amount', 'status', 'created_at', 'processed_at',
            ];

            if (in_array($sortField, $allowedSortFields)) {
                $query->orderBy($sortField, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            if (!empty($filters['search'])) {
                $search = $filters['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%")
                        ->orWhere('amount', 'like', "%{$search}%")
                        ->orWhere('request_note', 'like', "%{$search}%")
                        ->orWhere('admin_remark', 'like', "%{$search}%");
                });
            }

            $page = isset($filters['start'])
                ? (int) floor(((int) $filters['start']) / max(1, (int) $perPage)) + 1
                : null;

            $requests = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'success' => true,
                'message' => __('labels.withdrawal_requests_retrieved_successfully'),
                'data' => $requests,
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving seller withdrawal requests: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Get withdrawal requests with filtering and pagination
     *
     * @param  array  $filters  Filter parameters
     * @param  string  $type  The type of withdrawal request ('delivery_boy' or 'seller')
     * @return array Result containing success status, message, and requests data
     */
    public function getWithdrawalRequests(array $filters = [], string $type = 'delivery_boy'): array
    {
        if ($type === 'seller') {
            return $this->getSellerWithdrawalRequests($filters);
        }

        return $this->getDeliveryBoyWithdrawalRequests($filters);
    }

    /**
     * Get a single delivery boy withdrawal request by ID
     *
     * @param  int  $requestId  The withdrawal request ID
     * @return array Result containing success status, message, and request data
     */
    public function getDeliveryBoyWithdrawalRequest(int $requestId): array
    {
        try {
            $withdrawalRequest = DeliveryBoyWithdrawalRequest::with(['deliveryBoy.user', 'processedBy', 'transaction'])
                ->findOrFail($requestId);

            return [
                'success' => true,
                'message' => __('labels.withdrawal_request_retrieved_successfully'),
                'data' => $withdrawalRequest,
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => __('labels.withdrawal_request_not_found'),
                'data' => [],
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving delivery boy withdrawal request: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Get a single seller withdrawal request by ID
     *
     * @param  int  $requestId  The withdrawal request ID
     * @return array Result containing success status, message, and request data
     */
    public function getSellerWithdrawalRequest(int $requestId): array
    {
        try {
            $withdrawalRequest = SellerWithdrawalRequest::with(['seller.user', 'processedBy', 'transaction'])
                ->findOrFail($requestId);

            return [
                'success' => true,
                'message' => __('labels.withdrawal_request_retrieved_successfully'),
                'data' => $withdrawalRequest,
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => __('labels.withdrawal_request_not_found'),
                'data' => [],
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving seller withdrawal request: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => [],
            ];
        }
    }

    /**
     * Get a single withdrawal request by ID
     *
     * @param  int  $requestId  The withdrawal request ID
     * @param  string  $type  The type of withdrawal request ('delivery_boy' or 'seller')
     * @return array Result containing success status, message, and request data
     */
    public function getWithdrawalRequest(int $requestId, string $type = 'delivery_boy'): array
    {
        if ($type === 'seller') {
            return $this->getSellerWithdrawalRequest($requestId);
        }

        return $this->getDeliveryBoyWithdrawalRequest($requestId);
    }
}
