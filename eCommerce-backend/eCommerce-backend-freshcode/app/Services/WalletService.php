<?php

namespace App\Services;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Http\Controllers\Payments\FlutterwaveController;
use App\Http\Controllers\Payments\PaystackController;
use App\Http\Controllers\Payments\RazorpayController;
use App\Http\Controllers\Payments\StripeController;
use App\Models\Market;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Payments\MercadoPagoService;
use App\Services\Payments\XenditService;
use App\Support\DatabaseSchema;
use App\Support\GatewayAmount;
use App\Support\PricePresenter;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    protected RazorpayController $razorpayController;

    protected StripeController $stripeController;

    protected PaystackController $paystackController;

    protected FlutterwaveController $flutterwaveController;

    protected XenditService $xenditService;

    protected MercadoPagoService $mercadoPagoService;

    protected SettingService $settingService;

    public function __construct()
    {
        try {
            if (app(DatabaseSchema::class)->hasTable('settings')) {
                $this->razorpayController = app(RazorpayController::class);
                $this->stripeController = app(StripeController::class);
                $this->paystackController = app(PaystackController::class);
                $this->flutterwaveController = app(FlutterwaveController::class);
                $this->xenditService = app(XenditService::class);
                $this->mercadoPagoService = app(MercadoPagoService::class);
                $this->settingService = app(SettingService::class);
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * Normalize a wallet type input (enum, string, or null) to its string value.
     * Unknown values fall back to the customer wallet so legacy callers that
     * don't pass a type stay on customer funds.
     */
    protected function normalizeType(WalletTypeEnum|string|null $type): string
    {
        if ($type instanceof WalletTypeEnum) {
            return $type->value;
        }

        if (is_string($type) && WalletTypeEnum::tryFrom($type) !== null) {
            return $type;
        }

        return WalletTypeEnum::CUSTOMER->value;
    }

    public function formatBalance($market, $balance): array
    {
        return $market instanceof Market
            ? PricePresenter::forMarket((float) $balance, $market)
            : PricePresenter::forCurrent((float) $balance);
    }

    /**
     * Get or create a user's wallet for the given ownership type.
     *
     * @param  int  $userId  The user ID
     * @param  WalletTypeEnum|string|null  $type  Wallet owner type (customer/seller/delivery_boy); defaults to customer
     * @return array Result containing success status, message, and wallet data
     */
    public function getWallet(int $userId, WalletTypeEnum|string|null $type = null): array
    {
        try {
            $market = request()?->attributes->get('market');

            $walletType = $this->normalizeType($type);

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $userId, 'type' => $walletType],
                [
                    'balance' => 0.00,
                    'blocked_balance' => 0.00,
                    'currency_code' => app(CurrencyService::class)->getCode(),
                ]
            );
            $balanceFormat = $this->formatBalance($market, $wallet->balance);
            $availableBalanceFormat = $this->formatBalance($market, $wallet->balance - $wallet->blocked_balance);
            $wallet->display_balance = $balanceFormat['price'];
            $wallet->formatted_balance = $balanceFormat['formatted'];
            $wallet->available_balance = $availableBalanceFormat['price'];
            $wallet->available_formatted_balance = $availableBalanceFormat['formatted'];


            return [
                'success' => true,
                'message' => __('labels.wallet_retrieved_successfully'),
                'data' => $wallet,
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving wallet: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Add balance to the user wallet
     *
     * @param  int  $userId  The user ID
     * @param  array  $data  Balance data including amount, payment method, etc.
     * @param  WalletTypeEnum|string|null  $type  Wallet owner type; defaults to customer
     * @return array Result containing success status, message, and wallet data
     */
    public function addBalance(int $userId, array $data, WalletTypeEnum|string|null $type = null): array
    {
        try {
            DB::beginTransaction();

            $walletType = $this->normalizeType($type);

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $userId, 'type' => $walletType],
                [
                    'balance' => 0.00,
                    'currency_code' => app(CurrencyService::class)->getCode(),
                ]
            );

            $idempotencyKey = $data['idempotency_key'] ?? null;

            if ($idempotencyKey !== null) {
                $existing = WalletTransaction::where('wallet_id', $wallet->id)
                    ->where('transaction_reference', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    DB::commit();

                    return [
                        'success' => true,
                        'message' => __('labels.wallet_balance_added_successfully'),
                        'data' => ['wallet' => $wallet, 'transaction' => $existing],
                    ];
                }
            }

            $amount = (float) $data['amount'];
            Wallet::where('id', $wallet->id)->increment('balance', $amount);
            $wallet->refresh();

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $userId,
                'transaction_type' => WalletTransactionTypeEnum::DEPOSIT(),
                'payment_method' => $data['payment_method'],
                'amount' => $amount,
                'currency_code' => $wallet->currency_code,
                'status' => WalletTransactionStatusEnum::COMPLETED(),
                'transaction_reference' => $idempotencyKey ?? ($data['transaction_reference'] ?? null),
                'description' => $data['description'] ?? 'Wallet balance added',
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.wallet_balance_added_successfully'),
                'data' => [
                    'wallet' => $wallet,
                    'transaction' => $transaction,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error adding wallet balance: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Prepare wallet recharge
     *
     * @param  int  $userId  The user ID
     * @param  array  $data  Balance data including amount, payment method, etc.
     * @param  WalletTypeEnum|string|null  $type  Wallet owner type; defaults to customer
     * @return array Result containing success status, message, and wallet data
     */
    public function prepareWalletRecharge(int $userId, array $data, WalletTypeEnum|string|null $type = null): array
    {
        try {
            DB::beginTransaction();

            $walletType = $this->normalizeType($type);

            $wallet = Wallet::firstOrCreate(
                ['user_id' => $userId, 'type' => $walletType],
                [
                    'balance' => 0.00,
                    'currency_code' => app(CurrencyService::class)->getCode(),
                ]
            );

            $currencyCode = $wallet->currency_code;

            $transaction = $this->makeWalletPaymentTransaction(wallet: $wallet, userId: $userId, data: $data, status: WalletTransactionStatusEnum::PENDING(), transactionType: WalletTransactionTypeEnum::DEPOSIT(), currencyCode: $currencyCode);
            $data['transaction_id'] = (string) $transaction->id;

            $paymentResponse = $this->paymentIntent($data['payment_method'], $data);
            if (! $paymentResponse['success']) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => $paymentResponse['message'],
                    'data' => $paymentResponse['data'],
                ];
            }

            DB::commit();

            $paymentUrl = $this->buildWalletPaymentUrl($data['payment_method'], $transaction->id);

            return [
                'success' => true,
                'message' => __('labels.wallet_recharge_prepared_successfully'),
                'data' => [
                    'wallet' => $wallet,
                    'transaction' => $transaction,
                    'payment_response' => $paymentResponse['data'],
                    'payment_url' => $paymentUrl,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error adding wallet balance: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Build the wallet payment page URL for the given gateway.
     */
    private function buildWalletPaymentUrl(string $paymentMethod, int $transactionId): ?string
    {
        $routeMap = [
            PaymentTypeEnum::STRIPE() => 'wallet-payment.stripe',
            PaymentTypeEnum::RAZORPAY() => 'wallet-payment.razorpay',
            PaymentTypeEnum::PAYSTACK() => 'wallet-payment.paystack',
            PaymentTypeEnum::FLUTTERWAVE() => 'wallet-payment.flutterwave',
        ];

        $routeName = $routeMap[$paymentMethod] ?? null;

        return $routeName ? route($routeName, ['transaction' => $transactionId]) : null;
    }

    private function paymentIntent(string $paymentMethod, array $data): array
    {
        return match ($paymentMethod) {
            PaymentTypeEnum::RAZORPAY() => $this->razorpayController->createWalletRechargeOrder(data: $data),
            PaymentTypeEnum::STRIPE() => $this->stripeController->createWalletRechargeOrderPaymentIntent(data: $data),
            PaymentTypeEnum::PAYSTACK() => $this->paystackController->createWalletRechargeOrderPaymentIntent(data: $data),
            PaymentTypeEnum::FLUTTERWAVE() => $this->flutterwaveController->createWalletRechargeOrder(data: $data),
            PaymentTypeEnum::XENDIT() => $this->xenditService->initiateWalletRecharge(
                WalletTransaction::whereKey((int) $data['transaction_id'])->firstOrFail(),
                $data['redirect_url'] ?? null,
            ),
            PaymentTypeEnum::MERCADO_PAGO() => $this->mercadoPagoService->initiateWalletRecharge(
                WalletTransaction::whereKey((int) $data['transaction_id'])->firstOrFail(),
                $data['redirect_url'] ?? null,
            ),
            default => throw new Exception('Unsupported payment method: '.$paymentMethod),
        };

    }

    /**
     * Create a wallet payment transaction
     *
     * @param  Wallet  $wallet  The wallet instance
     * @param  int  $userId  The user ID
     * @param  array  $data  Transaction data including amount, payment method, etc.
     * @return WalletTransaction The created wallet transaction
     */
    public function makeWalletPaymentTransaction(
        Wallet $wallet,
        int $userId,
        array $data,
        string $status,
        string $transactionType,
        string $currencyCode
    ): WalletTransaction {
        $change = GatewayAmount::resolve((float) $data['amount'], GatewayAmount::orderSourceCurrency(), $currencyCode);

        return WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'user_id' => $userId,
            'transaction_type' => $transactionType ?? WalletTransactionTypeEnum::DEPOSIT(),
            'payment_method' => $data['payment_method'],
            'amount' => $change['amount'],
            'currency_code' => $currencyCode,
            'status' => $status ?? WalletTransactionStatusEnum::PENDING(),
            'transaction_reference' => $data['transaction_reference'] ?? null,
            'description' => $data['description'] ?? 'Wallet recharge initiated',
        ]);
    }

    /**
     * Deduct balance from the user wallet.
     *
     * Kept static for backward compatibility with callers in Order, OrderService,
     * SellerSubscriptionService and SellerEarningController. The third argument
     * scopes the deduction to a specific wallet type; omit it to keep the
     * historical "customer wallet" behavior.
     *
     * @param  int  $userId  The user ID
     * @param  array  $data  Deduction data including amount, order ID, etc.
     * @param  WalletTypeEnum|string|null  $type  Wallet owner type; defaults to customer
     * @return array Result containing success status, message, and wallet data
     */
    public static function deductBalance(
        int $userId,
        array $data,
        WalletTypeEnum|string|null $type = null
    ): array {
        try {
            $walletType = match (true) {
                $type instanceof WalletTypeEnum => $type->value,
                is_string($type) && WalletTypeEnum::tryFrom($type) !== null => $type,
                default => WalletTypeEnum::CUSTOMER->value,
            };

            $wallet = Wallet::where('user_id', $userId)
                ->where('type', $walletType)
                ->first();

            if (! $wallet) {
                return [
                    'success' => false,
                    'message' => __('labels.wallet_not_found'),
                    'data' => [],
                ];
            }

            $amount = (float) $data['amount'];

            DB::beginTransaction();

            $deducted = Wallet::where('id', $wallet->id)
                ->where('balance', '>=', $amount)
                ->decrement('balance', $amount);

            if ($deducted === 0) {
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
                'user_id' => $userId,
                'order_id' => $data['order_id'] ?? null,
                'store_id' => $data['store_id'] ?? null,
                'transaction_type' => WalletTransactionTypeEnum::PAYMENT(),
                'payment_method' => 'wallet',
                'amount' => $amount,
                'currency_code' => $wallet->currency_code,
                'status' => WalletTransactionStatusEnum::COMPLETED(),
                'description' => $data['description'] ?? 'Payment made from wallet',
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => __('labels.wallet_balance_deducted_successfully'),
                'data' => [
                    'wallet' => $wallet,
                    'transaction' => $transaction,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error deducting wallet balance: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Get wallet transactions with filtering and pagination
     *
     * @param  int  $userId  The user ID
     * @param  array  $filters  Filter parameters
     * @param  WalletTypeEnum|string|null  $type  Wallet owner type to scope the listing to; defaults to customer
     * @return array Result containing success status, message, and transactions data
     */
    public function getTransactions(
        int $userId,
        array $filters = [],
        WalletTypeEnum|string|null $type = null
    ): array {
        try {
            $walletType = $this->normalizeType($type);

            $query = WalletTransaction::where('user_id', $userId)
                ->whereHas('wallet', fn ($q) => $q->where('type', $walletType));

            $perPage = min(100, max(1, (int) ($filters['per_page'] ?? 15)));

            if (isset($filters['query'])) {
                $searchQuery = $filters['query'];
                $query->where(function ($q) use ($searchQuery) {
                    $q->where('description', 'LIKE', '%'.$searchQuery.'%')
                        ->orWhere('transaction_reference', 'LIKE', '%'.$searchQuery.'%')
                        ->orWhere('payment_method', 'LIKE', '%'.$searchQuery.'%')
                        ->orWhere('amount', 'LIKE', '%'.$searchQuery.'%');
                });
            }

            if (isset($filters['transaction_type'])) {
                $query->where('transaction_type', $filters['transaction_type']);
            }

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['payment_method'])) {
                $query->where('payment_method', 'LIKE', '%'.$filters['payment_method'].'%');
            }

            if (isset($filters['min_amount'])) {
                $query->where('amount', '>=', $filters['min_amount']);
            }

            if (isset($filters['max_amount'])) {
                $query->where('amount', '<=', $filters['max_amount']);
            }

            if (isset($filters['sort']) && isset($filters['order'])) {
                $sortField = $filters['sort'];
                $sortOrder = $filters['order'];

                $allowedSortFields = [
                    'id',
                    'amount',
                    'transaction_type',
                    'status',
                    'payment_method',
                    'created_at',
                    'updated_at',
                ];

                if (in_array($sortField, $allowedSortFields)) {
                    $query->orderBy($sortField, $sortOrder);
                }
            } else {
                $query->orderBy('created_at', 'desc');
            }

            $page = isset($filters['start'])
                ? (int) floor(((int) $filters['start']) / max(1, $perPage)) + 1
                : null;

            $transactions = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'success' => true,
                'message' => __('labels.wallet_transactions_retrieved_successfully'),
                'data' => $transactions,
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving wallet transactions: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Get a single wallet transaction by ID
     *
     * @param  int  $userId  The user ID
     * @param  int  $transactionId  The transaction ID
     * @param  WalletTypeEnum|string|null  $type  Wallet owner type to scope the lookup to; defaults to customer
     * @return array Result containing success status, message, and transaction data
     */
    public function getTransaction(
        int $userId,
        int $transactionId,
        WalletTypeEnum|string|null $type = null
    ): array {
        try {
            $walletType = $this->normalizeType($type);

            $transaction = WalletTransaction::where('user_id', $userId)
                ->where('id', $transactionId)
                ->whereHas('wallet', fn ($q) => $q->where('type', $walletType))
                ->first();

            if (! $transaction) {
                return [
                    'success' => false,
                    'message' => __('labels.wallet_transaction_not_found'),
                    'data' => [],
                ];
            }

            return [
                'success' => true,
                'message' => __('labels.wallet_transaction_retrieved_successfully'),
                'data' => $transaction,
            ];
        } catch (Exception $e) {
            Log::error('Error retrieving wallet transaction: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }

    /**
     * Finalize a pending wallet deposit manually by admin (complete or fail)
     *
     * @param  bool  $approve  true to complete, false to fail
     * @param  string|null  $adminNote  optional description/note to store
     */
    public function finalizeDeposit(int $transactionId, bool $approve, ?string $adminNote = null): array
    {
        try {
            DB::beginTransaction();

            $transaction = WalletTransaction::query()
                ->where('id', $transactionId)
                ->where('transaction_type', WalletTransactionTypeEnum::DEPOSIT())
                ->lockForUpdate()
                ->first();

            if (! $transaction) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.wallet_transaction_not_found'),
                    'data' => [],
                ];
            }

            if ($transaction->status !== WalletTransactionStatusEnum::PENDING()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.transaction_already_processed'),
                    'data' => [],
                ];
            }

            $gatewayMethods = [
                (string) PaymentTypeEnum::RAZORPAY(),
                (string) PaymentTypeEnum::STRIPE(),
                (string) PaymentTypeEnum::PAYSTACK(),
                (string) PaymentTypeEnum::FLUTTERWAVE(),
            ];

            if (in_array((string) $transaction->payment_method, $gatewayMethods, true)) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => __('labels.gateway_deposit_cannot_be_processed_manually'),
                    'data' => [],
                ];
            }

            if (! empty($adminNote)) {
                $transaction->description = trim(($transaction->description ?? '')."\nAdmin: ".$adminNote);
            }

            if ($approve) {
                $wallet = Wallet::where('id', $transaction->wallet_id)->lockForUpdate()->first();
                if (! $wallet) {
                    DB::rollBack();

                    return [
                        'success' => false,
                        'message' => __('labels.wallet_not_found'),
                        'data' => [],
                    ];
                }

                $wallet->balance = (float) $wallet->balance + (float) $transaction->amount;
                $wallet->save();
                $transaction->status = WalletTransactionStatusEnum::COMPLETED();
            } else {
                $transaction->status = WalletTransactionStatusEnum::FAILED();
            }

            $transaction->save();

            DB::commit();

            return [
                'success' => true,
                'message' => $approve ? __('labels.wallet_recharge_success') : __('labels.wallet_recharge_failed'),
                'data' => [
                    'transaction' => $transaction,
                ],
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error finalizing wallet deposit: '.$e->getMessage());

            return [
                'success' => false,
                'message' => __('labels.something_went_wrong'),
                'data' => ['error' => $e->getMessage()],
            ];
        }
    }
}
