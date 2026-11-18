<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Wallet\WalletTransactionStatusEnum;
use App\Enums\Wallet\WalletTransactionTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\CurrencyService;
use App\Services\MarketService;
use App\Services\WalletService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WalletTransactionController extends Controller
{
    use AuthorizesRequests;

    protected WalletService $walletService;
    protected CurrencyService $currencyService;

    public function __construct(WalletService $walletService, CurrencyService $currencyService)
    {
        $this->walletService = $walletService;
        $this->currencyService = $currencyService;
    }

    /**
     * Format a wallet transaction's amount using the currency snapshotted on
     * the transaction itself (`currency_code`), NOT the live site currency.
     * The wallet ledger is held in the base currency, and each row records the
     * code it was written in — so the symbol stays correct even if the site
     * currency is later changed. Falls back to the base code for legacy rows.
     */
    private function formatTransactionAmount(WalletTransaction $t): string
    {
        $code = $t->currency_code ?: \App\Support\PricePresenter::baseCode();
        return \App\Support\PricePresenter::symbolFor($code) . number_format((float) $t->amount, 2);
    }

    public function transactions(): View
    {
        $this->authorize('viewAny', Wallet::class);

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'user', 'name' => 'user', 'title' => __('labels.customer')],
            ['data' => 'amount', 'name' => 'amount', 'title' => __('labels.amount')],
            ['data' => 'transaction_type', 'name' => 'transaction_type', 'title' => __('labels.type')],
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'payment_method', 'name' => 'payment_method', 'title' => __('labels.payment_method')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
        ];
        return view('admin.wallet.transactions', compact('columns'));
    }

    public function deposits(): View
    {
        $this->authorize('viewAny', Wallet::class);

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'user', 'name' => 'user', 'title' => __('labels.customer')],
            ['data' => 'amount', 'name' => 'amount', 'title' => __('labels.amount')],
            ['data' => 'payment_method', 'name' => 'payment_method', 'title' => __('labels.payment_method')],
            ['data' => 'created_at', 'name' => 'created_at', 'title' => __('labels.created_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];
        return view('admin.wallet.deposits', compact('columns'));
    }

    public function transactionsDatatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Wallet::class);

        $draw = $request->get('draw');
        $start = (int)$request->get('start', 0);
        $length = (int)$request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';
        $columns = ['id', 'user_id', 'amount', 'transaction_type', 'status', 'payment_method', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $query = WalletTransaction::query()->with('user');

        $marketId = app(MarketService::class)->adminSelectedId();
        $query->whereHas('user', function ($uq) use ($marketId) {
            $uq->whereHas('markets', fn ($mq) => $mq->where('markets.id', $marketId))
                ->orWhereDoesntHave('markets');
        });

        $totalRecords = $query->count();

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('amount', 'like', "%{$searchValue}%")
                    ->orWhere('payment_method', 'like', "%{$searchValue}%")
                    ->orWhere('transaction_reference', 'like', "%{$searchValue}%")
                    ->orWhereHas('user', function ($uq) use ($searchValue) {
                        $uq->where('name', 'like', "%{$searchValue}%");
                    });
            });
        }

        $filteredRecords = !empty($searchValue) ? $query->count() : $totalRecords;

        $data = $query->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function (WalletTransaction $t) {
                return [
                    'id' => $t->id,
                    'user' => e($t->user->name ?? 'N/A'),
                    'amount' => $this->formatTransactionAmount($t) . '<p class="mt-2 mb-0 fs-5">' . __('labels.transaction_reference') . " - " . e($t->transaction_reference ?? "-") . ' </p>',
                    'transaction_type' => $t->transaction_type,
                    'status' => ucfirst($t->status),
                    'payment_method' => e($t->payment_method),
                    'created_at' => $t->created_at?->format('Y-m-d H:i:s'),
                ];
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function depositsDatatable(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Wallet::class);

        $draw = $request->get('draw');
        $start = (int)$request->get('start', 0);
        $length = (int)$request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $orderColumnIndex = $request->get('order')[0]['column'] ?? 0;
        $orderDirection = $request->get('order')[0]['dir'] ?? 'desc';
        $columns = ['id', 'user_id', 'amount', 'payment_method', 'created_at'];
        $orderColumn = $columns[$orderColumnIndex] ?? 'id';

        $query = WalletTransaction::query()
            ->with('user')
            ->where('transaction_type', WalletTransactionTypeEnum::DEPOSIT())
            ->where('status', WalletTransactionStatusEnum::PENDING());

        $marketId = app(MarketService::class)->adminSelectedId();
        $query->whereHas('user', function ($uq) use ($marketId) {
            $uq->whereHas('markets', fn ($mq) => $mq->where('markets.id', $marketId))
                ->orWhereDoesntHave('markets');
        });

        $totalRecords = $query->count();

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('id', 'like', "%{$searchValue}%")
                    ->orWhere('amount', 'like', "%{$searchValue}%")
                    ->orWhere('payment_method', 'like', "%{$searchValue}%")
                    ->orWhereHas('user', function ($uq) use ($searchValue) {
                        $uq->where('name', 'like', "%{$searchValue}%");
                    });
            });
        }

        $filteredRecords = !empty($searchValue) ? $query->count() : $totalRecords;

        $data = $query->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function (WalletTransaction $t) {
                $action = view('admin.wallet.partials.deposit_actions', [
                    'id' => $t->id,
                    'userName' => $t->user->name ?? 'N/A',
                    'amount' => $this->formatTransactionAmount($t),
                ])->render();
                return [
                    'id' => $t->id,
                    'user' => $t->user->name ?? 'N/A',
                    'amount' => $this->formatTransactionAmount($t) . '<p class="mt-2 mb-0 fs-5">' . __('labels.transaction_reference') . ' - ' . ($t->transaction_reference ?? "-") . ' </p>',
                    'payment_method' => $t->payment_method,
                    'created_at' => $t->created_at?->format('Y-m-d H:i:s'),
                    'action' => $action,
                ];
            });

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    public function processDeposit(Request $request, int $id): JsonResponse
    {
        $this->authorize('processDeposit', Wallet::class);

        $request->validate([
            'action' => 'required|in:approve,reject',
            'note' => 'nullable|string|max:500',
        ]);

        $approve = $request->input('action') === 'approve';
        $note = $approve ? __('labels.deposit_approve_by_admin') : __('labels.deposit_rejected_by_admin');

        $result = $this->walletService->finalizeDeposit($id, $approve, $note);

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
