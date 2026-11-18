<?php

namespace App\Services;

use App\Enums\Payment\PaymentTypeEnum;
use App\Enums\Payment\RefundOutcome;
use App\Http\Controllers\Payments\FlutterwaveController;
use App\Http\Controllers\Payments\PaystackController;
use App\Http\Controllers\Payments\RazorpayController;
use App\Http\Controllers\Payments\StripeController;
use App\Models\OrderPaymentTransaction;
use App\Services\Payments\MercadoPagoService;
use App\Services\Payments\XenditService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    protected RazorpayController $razorpayController;

    protected StripeController $stripeController;

    protected PaystackController $paystackController;

    protected FlutterwaveController $flutterwaveController;

    protected XenditService $xenditService;

    protected MercadoPagoService $mercadoPagoService;

    public function __construct(
        RazorpayController $razorpayController,
        StripeController $stripeController,
        PaystackController $paystackController,
        FlutterwaveController $flutterwaveController,
        XenditService $xenditService,
        MercadoPagoService $mercadoPagoService,
    ) {
        $this->razorpayController = $razorpayController;
        $this->stripeController = $stripeController;
        $this->paystackController = $paystackController;
        $this->flutterwaveController = $flutterwaveController;
        $this->xenditService = $xenditService;
        $this->mercadoPagoService = $mercadoPagoService;
    }

    public function postPaymentInitialtion($order, $redirectUrl = null): array
    {
        switch ($order->payment_method) {
            case PaymentTypeEnum::FLUTTERWAVE():
                $data = [
                    'order_id' => $order->id,
                    'amount' => $order->total_payable,
                    'source_currency' => $order->currency_code,
                    'redirect_url' => $redirectUrl ?? config('app.url'),
                ];
                $result = $this->flutterwaveController->createOrder($data);
                if ($result['success'] === false) {
                    throw new \Exception($result['message']);
                }

                return $result;
            case PaymentTypeEnum::STRIPE():
                $result = $this->stripeController->initiateOrderPayment($order);
                if ($result['success'] === false) {
                    throw new \Exception($result['message']);
                }

                return $result;
            case PaymentTypeEnum::PAYSTACK():
                $result = $this->paystackController->initiateOrderPayment($order);
                if ($result['success'] === false) {
                    throw new \Exception($result['message']);
                }

                return $result;
            case PaymentTypeEnum::RAZORPAY():
                $result = $this->razorpayController->initiateOrderPayment($order);
                if ($result['success'] === false) {
                    throw new \Exception($result['message']);
                }

                return $result;
            case PaymentTypeEnum::XENDIT():
                return $this->xenditService->initiateOrderPayment($order, $redirectUrl);
            case PaymentTypeEnum::MERCADO_PAGO():
                return $this->mercadoPagoService->initiateOrderPayment($order, $redirectUrl);
            default:
                return [];
        }

    }

    public function makeOrderPaymentTransaction($order, $paymentData, $status): void
    {
        $transactionData = [
            'uuid' => Str::uuid(),
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'transaction_id' => $paymentData['transaction_id'] ?? null,
            'amount' => $paymentData['amount'] ?? 0,
            'currency' => $paymentData['currency'] ?? 'INR',
            'payment_method' => $paymentData['payment_method'] ?? 'unknown',
            'payment_status' => $status,
            'message' => $paymentData['message'] ?? null,
            'payment_details' => isset($paymentData['details']) ? json_encode($paymentData['details']) : null,
        ];

        if (empty($paymentData['transaction_id'])) {
            OrderPaymentTransaction::create($transactionData);
        } else {
            OrderPaymentTransaction::updateOrCreate(
                ['transaction_id' => $paymentData['transaction_id']],
                $transactionData
            );
        }
    }

    public function verifyOnlinePayment(array $data): array
    {
        $verifiedResponse = [
            'success' => true,
            'message' => __('labels.payment_verified_successfully'),
        ];
        switch ($data['payment_type']) {
            case PaymentTypeEnum::COD():
            case PaymentTypeEnum::FLUTTERWAVE():
            case PaymentTypeEnum::STRIPE():
            case PaymentTypeEnum::PAYSTACK():
            case PaymentTypeEnum::RAZORPAY():
            case PaymentTypeEnum::XENDIT():
            case PaymentTypeEnum::MERCADO_PAGO():
            case PaymentTypeEnum::WALLET():
                return $verifiedResponse;

            default:
                return [
                    'success' => false,
                    'message' => __('labels.unsupported_payment_method'),
                    'data' => [],
                ];
        }
    }

    public function processOrderRefund($paymentMethod, $transactionId): RefundOutcome
    {
        try {
            if ((string) $paymentMethod === (string) PaymentTypeEnum::XENDIT()) {
                return $this->xenditService->refundPayment((string) $transactionId);
            }

            if ((string) $paymentMethod === (string) PaymentTypeEnum::MERCADO_PAGO()) {
                return $this->mercadoPagoService->refundPayment((string) $transactionId);
            }

            $result = match ((string) $paymentMethod) {
                (string) PaymentTypeEnum::RAZORPAY() => $this->razorpayController->refundPayment($transactionId),
                (string) PaymentTypeEnum::STRIPE() => $this->stripeController->refundPayment($transactionId),
                (string) PaymentTypeEnum::PAYSTACK() => $this->paystackController->refundPayment($transactionId),
                (string) PaymentTypeEnum::FLUTTERWAVE() => $this->flutterwaveController->refundPayment($transactionId),
                default => ['success' => false, 'message' => 'Unsupported payment method'],
            };

            if (($result['success'] ?? false) === false) {
                Log::error('Error refunding payment: '.($result['message'] ?? 'unknown'), ['transaction_id' => $transactionId, 'method' => (string) $paymentMethod]);

                return RefundOutcome::FAILED;
            }

            Log::info('Payment refunded successfully', ['transaction_id' => $transactionId, 'method' => (string) $paymentMethod]);

            return RefundOutcome::COMPLETED;
        } catch (\Exception $e) {
            Log::error('Error processing refund', ['transaction_id' => $transactionId, 'error' => $e->getMessage()]);

            return RefundOutcome::FAILED;
        }
    }
}
