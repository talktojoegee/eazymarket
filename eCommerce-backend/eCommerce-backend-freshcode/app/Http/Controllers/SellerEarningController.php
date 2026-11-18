<?php

namespace App\Http\Controllers;

use App\Support\CsvSafe;
use App\Enums\AdminPermissionEnum;
use App\Enums\CommandRunStatusEnum;
use App\Enums\Seller\SellerSettlementStatusEnum;
use App\Enums\Seller\SellerSettlementTypeEnum;
use App\Enums\Wallet\WalletTypeEnum;
use App\Models\Seller;
use App\Models\SellerStatement;
use App\Models\Wallet;
use App\Services\CronMonitorService;
use App\Services\CurrencyService;
use App\Services\MarketService;
use App\Services\SellerStoreService;
use App\Services\WalletService;
use App\Services\SellerStatementService;
use App\Services\SettlementProcessor;
use App\Support\SellerStatementFigures;
use App\Support\SettlementSettings;
use App\Traits\ChecksPermissions;
use App\Traits\AppliesDatatableDateRange;
use App\Traits\PanelAware;
use App\Types\Api\ApiResponseType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SellerEarningController extends Controller
{
    use SellerStatementFigures;

    use ChecksPermissions, PanelAware, AuthorizesRequests, AppliesDatatableDateRange;
    protected bool $settlePermission = false;
    protected WalletService $walletService;
    protected CurrencyService $currencyService;
    protected SellerStatementService $statementService;

    public function __construct(WalletService $walletService, CurrencyService $currencyService, SellerStatementService $statementService)
    {
        $this->walletService = $walletService;
        $this->currencyService = $currencyService;
        $this->statementService = $statementService;

        if ($this->getPanel() === 'admin') {
            $this->settlePermission = $this->hasPermission(AdminPermissionEnum::COMMISSION_SETTLE());
        }
    }

    /**
     * Display a listing of unsettled commissions.
     */
    public function index(): View
    {
        $this->authorize('viewAny', SellerStatement::class);
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'order_item', 'name' => 'order_item', 'title' => __('labels.order') . ' / ' . __('labels.item'), 'orderable' => false],
            ['data' => 'store', 'name' => 'store', 'title' => __('labels.store'), 'orderable' => false],
            ['data' => 'gross_amount', 'name' => 'gross_amount', 'title' => __('labels.gross'), 'orderable' => false, 'className' => 'text-end'],
            ['data' => 'marketplace_fee', 'name' => 'marketplace_fee', 'title' => __('labels.commission'), 'orderable' => false, 'className' => 'text-end'],
            ['data' => 'payout_amount', 'name' => 'payout_amount', 'title' => __('labels.net_payout'), 'orderable' => false, 'className' => 'text-end fw-bold'],
            ['data' => 'posted_at', 'name' => 'posted_at', 'title' => __('labels.date')],
            ['data' => 'maturity', 'name' => 'maturity', 'title' => __('labels.settlement_status'), 'orderable' => false],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $columnsReturn = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'reference', 'name' => 'reference', 'title' => __('labels.type') . ' / ' . __('labels.reference'), 'orderable' => false],
            ['data' => 'order_item', 'name' => 'order_item', 'title' => __('labels.order') . ' / ' . __('labels.item'), 'orderable' => false],
            ['data' => 'store', 'name' => 'store', 'title' => __('labels.store'), 'orderable' => false],
            ['data' => 'debit_amount', 'name' => 'debit_amount', 'title' => __('labels.deduction'), 'orderable' => false, 'className' => 'text-end fw-bold'],
            ['data' => 'posted_at', 'name' => 'posted_at', 'title' => __('labels.date')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $sellerColumns = [
            ['data' => 'seller', 'name' => 'seller', 'title' => __('labels.seller'), 'orderable' => false],
            ['data' => 'credits', 'name' => 'credits', 'title' => __('labels.pending_payouts'), 'orderable' => false, 'className' => 'text-end'],
            ['data' => 'debits', 'name' => 'debits', 'title' => __('labels.pending_deductions'), 'orderable' => false, 'className' => 'text-end'],
            ['data' => 'net', 'name' => 'net', 'title' => __('labels.net_owed'), 'orderable' => false, 'className' => 'text-end fw-bold'],
            ['data' => 'wallet', 'name' => 'wallet', 'title' => __('labels.wallet_balance'), 'orderable' => false, 'className' => 'text-end'],
            ['data' => 'withdrawal', 'name' => 'withdrawal', 'title' => __('labels.pending_withdrawal'), 'orderable' => false, 'className' => 'text-end'],
            ['data' => 'entries', 'name' => 'entries', 'title' => __('labels.entries'), 'orderable' => false, 'className' => 'text-center'],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        $settlePermission = $this->settlePermission;

        return view($this->panelView('commissions.index'), compact('columns', 'settlePermission', 'columnsReturn', 'sellerColumns'));
    }

    /**
     * Aggregated summary of PENDING entries for the dashboard cards: total pending
     * payouts (credits), total pending deductions (debits), net settleable, counts,
     * sellers awaiting (admin only) and aging buckets. Honours the same store +
     * date-range filters as the tables, and is panel-scoped (a seller only sees
     * their own).
     *
     * Amounts are summed on `amount`, which is exactly what the settle action moves
     * into / out of the wallet, so the totals reflect the real settlement impact.
     */
    public function summary(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SellerStatement::class);

        $user = Auth::user();
        if ($this->getPanel() === 'seller' && empty($user->seller())) {
            return ApiResponseType::sendJsonResponse(success: false, message: __('labels.seller_not_found'), data: []);
        }

        $query = SellerStatement::query()
            ->where('settlement_status', SellerSettlementStatusEnum::PENDING())
            ->where('on_hold', false);
        if ($this->getPanel() === 'seller') {
            $query->where('seller_id', $user->seller()->id);
        }
        $this->applyCommonFilters($query, $request);

        $credit = SellerSettlementTypeEnum::CREDIT();
        $debit = SellerSettlementTypeEnum::DEBIT();
        $now = now();
        $ranges = ['0_7' => [0, 7], '8_15' => [8, 15], '16_30' => [16, 30], '31_plus' => [31, null]];
        $selects = [
            'COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END), 0) as credits_total',
            'COALESCE(SUM(CASE WHEN entry_type = ? THEN 1 ELSE 0 END), 0) as credits_count',
            'COALESCE(SUM(CASE WHEN entry_type = ? THEN amount ELSE 0 END), 0) as debits_total',
            'COALESCE(SUM(CASE WHEN entry_type = ? THEN 1 ELSE 0 END), 0) as debits_count',
            'COALESCE(SUM(CASE WHEN entry_type = ? AND (available_at IS NULL OR available_at <= ?) THEN amount ELSE 0 END), 0) as matured_total',
            'COALESCE(SUM(CASE WHEN entry_type = ? AND (available_at IS NULL OR available_at <= ?) THEN 1 ELSE 0 END), 0) as matured_count',
            'COUNT(DISTINCT seller_id) as sellers_awaiting',
        ];
        $bindings = [$credit, $credit, $debit, $debit, $credit, $now, $credit, $now];

        foreach ($ranges as $key => [$min, $max]) {
            $conditions = ['entry_type = ?', 'posted_at <= ?'];
            $conditionBindings = [$credit, $now->copy()->subDays($min)->endOfDay()];
            if ($max !== null) {
                $conditions[] = 'posted_at >= ?';
                $conditionBindings[] = $now->copy()->subDays($max)->startOfDay();
            }
            $condition = implode(' AND ', $conditions);
            $selects[] = "COALESCE(SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END), 0) as aging_{$key}_count";
            array_push($bindings, ...$conditionBindings);
            $selects[] = "COALESCE(SUM(CASE WHEN {$condition} THEN amount ELSE 0 END), 0) as aging_{$key}_amount";
            array_push($bindings, ...$conditionBindings);
        }

        $totals = $query->selectRaw(implode(', ', $selects), $bindings)->first();
        $creditsTotal = (float) $totals->credits_total;
        $creditsCount = (int) $totals->credits_count;
        $debitsTotal = (float) $totals->debits_total;
        $debitsCount = (int) $totals->debits_count;
        $maturedTotal = (float) $totals->matured_total;
        $maturedCount = (int) $totals->matured_count;
        $net = round($creditsTotal - $debitsTotal, 2);

        $aging = [];
        foreach (array_keys($ranges) as $key) {
            $aging[$key] = [
                'count' => (int) $totals->{"aging_{$key}_count"},
                'amount_formatted' => $this->currencyService->format((float) $totals->{"aging_{$key}_amount"}),
            ];
        }

        $data = [
            'credits_total_formatted' => $this->currencyService->format($creditsTotal),
            'credits_count' => $creditsCount,
            'debits_total_formatted' => $this->currencyService->format($debitsTotal),
            'debits_count' => $debitsCount,
            'net_total_formatted' => $this->currencyService->format($net),
            'pending_count' => $creditsCount + $debitsCount,
            'sellers_awaiting' => $this->getPanel() === 'admin'
                ? (int) $totals->sellers_awaiting
                : null,
            'matured_total_formatted' => $this->currencyService->format($maturedTotal),
            'matured_count' => $maturedCount,
            'auto_settle_enabled' => SettlementSettings::autoSettleEnabled(),
            'clearing_days' => SettlementSettings::clearingDays(),
            'aging' => $aging,
        ];

        return ApiResponseType::sendJsonResponse(success: true, message: 'ok', data: $data);
    }

    /**
     * Stream a CSV export of the current tab (credits | debits | history), honouring
     * the same panel scoping and store/date filters as the on-screen table. Amounts
     * are written via statementFigures() so each row is currency-consistent.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->authorize('viewAny', SellerStatement::class);

        $user = Auth::user();
        if ($this->getPanel() === 'seller' && empty($user->seller())) {
            abort(404);
        }

        $type = in_array($request->get('type'), ['credits', 'debits', 'history'], true)
            ? $request->get('type') : 'credits';

        $query = SellerStatement::with(['orderItem.store', 'seller.user', 'settledBy']);
        if ($type === 'history') {
            $query->where('settlement_status', SellerSettlementStatusEnum::SETTLED());
        } else {
            $query->where('settlement_status', SellerSettlementStatusEnum::PENDING())
                ->where('entry_type', $type === 'debits' ? SellerSettlementTypeEnum::DEBIT() : SellerSettlementTypeEnum::CREDIT());
        }
        if ($this->getPanel() === 'seller') {
            $query->where('seller_id', $user->seller()->id);
        }
        $this->applyCommonFilters($query, $request);
        $this->applyDatatableDateRange($query, $request, 'posted_at');
        $query->orderBy('id', 'desc');

        $headers = [
            __('labels.id'), __('labels.entry_type'), __('labels.order_id'), __('labels.order_item_id'),
            __('labels.title'), __('labels.variant'), __('labels.store'), __('labels.seller'),
            __('labels.currency'), __('labels.gross'), __('labels.commission'), __('labels.amount'),
            __('labels.reference_type'), __('labels.settlement_status'),
            __('labels.posted_at'), __('labels.settled_on'), __('labels.settled_by'),
        ];

        $filename = 'earnings-' . $type . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            $query->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $st) {
                    $f = $this->statementFigures($st);
                    $orderItem = $st->orderItem;
                    fputcsv($out, CsvSafe::row([
                        $st->id,
                        $st->entry_type,
                        $st->order_id,
                        $st->order_item_id,
                        $orderItem->title ?? '',
                        $orderItem->variant_title ?? '',
                        $orderItem->store->name ?? '',
                        $st->seller->user->name ?? '',
                        $f['currency'],
                        number_format((float) $f['gross'], 2, '.', ''),
                        number_format((float) $f['commission'], 2, '.', ''),
                        number_format((float) $f['net'], 2, '.', ''),
                        $st->reference_type,
                        $st->settlement_status,
                        optional($st->posted_at)->format('Y-m-d H:i'),
                        optional($st->settled_at)->format('Y-m-d H:i'),
                        $this->settledByDisplay($st) ?? '',
                    ]));
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Render a print-optimised payout statement (settled entries + totals) for one
     * seller over a date range. On the seller panel it's always the logged-in
     * seller; on the admin panel a `seller_id` is required (the button for that is
     * added with the Phase 3 per-seller rollup).
     */
    public function statement(Request $request): View
    {
        $this->authorize('viewAny', SellerStatement::class);
        $user = Auth::user();

        if ($this->getPanel() === 'seller') {
            $seller = $user->seller();
            if (empty($seller)) {
                abort(404);
            }
        } else {
            $seller = Seller::with('user')->find((int) $request->get('seller_id'));
            if (!$seller) {
                abort(404);
            }
        }

        $days = (int) $request->get('date_range', 0);

        $mode = $request->get('mode') === 'settled' ? 'settled' : 'full';

        $query = SellerStatement::with(['orderItem.store', 'settledBy'])
            ->where('seller_id', $seller->id);

        if ($mode === 'settled') {
            $query->where('settlement_status', SellerSettlementStatusEnum::SETTLED());
            if ($days > 0) {
                $query->where('settled_at', '>=', now()->subDays($days)->startOfDay());
            }
            $statements = $query->orderByDesc('settled_at')->get();
        } else {
            if ($days > 0) {
                $query->where('posted_at', '>=', now()->subDays($days)->startOfDay());
            }
            $statements = $query->orderByDesc('posted_at')->get();
        }

        $rows = $statements->map(function ($st) {
            $f = $this->statementFigures($st);
            $isSettled = (string) $st->settlement_status === (string) SellerSettlementStatusEnum::SETTLED();
            return [
                'id' => $st->id,
                'date' => optional($st->settled_at ?? $st->posted_at)->format('Y-m-d'),
                'order_id' => $st->order_id,
                'item' => $st->orderItem?->title,
                'store' => $st->orderItem?->store?->name,
                'is_debit' => (string) $st->entry_type === 'debit',
                'is_settled' => $isSettled,
                'status_label' => $isSettled ? __('labels.settled') : __('labels.pending'),
                'reference' => Str::ucfirst(Str::replace('_', ' ', (string) $st->reference_type)),
                'currency' => $f['currency'],
                'amount_formatted' => $this->currencyService->format($f['net']),
                'settled_by' => $this->settledByDisplay($st),
                'method' => $this->settlementMethod($st),
            ];
        });

        $creditTotal = (float) $statements->where('entry_type', 'credit')->sum('amount');
        $debitTotal  = (float) $statements->where('entry_type', 'debit')->sum('amount');
        $settledNet  = (float) $statements->where('settlement_status', (string) SellerSettlementStatusEnum::SETTLED())
            ->sum(fn ($s) => (string) $s->entry_type === 'debit' ? -1 * (float) $s->amount : (float) $s->amount);

        return view('commissions.statement', [
            'seller' => $seller,
            'sellerName' => $seller->user->name ?? ($seller->store_name ?? '—'),
            'rows' => $rows,
            'creditTotalFormatted' => $this->currencyService->format($creditTotal),
            'debitTotalFormatted' => $this->currencyService->format($debitTotal),
            'netTotalFormatted' => $this->currencyService->format(round($creditTotal - $debitTotal, 2)),
            'settledNetFormatted' => $this->currencyService->format(round($settledNet, 2)),
            'periodLabel' => $days > 0 ? __('labels.last_n_days', ['n' => $days]) : __('labels.all_time'),
            'generatedAt' => now()->format('Y-m-d H:i'),
            'appName' => config('app.name'),
            'mode' => $mode,
            'docTitle' => $mode === 'settled' ? __('labels.payout_receipt') : __('labels.payout_statement'),
        ]);
    }

    /**
     * Per-seller rollup datatable (admin only): one row per seller with total pending
     * payouts (credits), total pending deductions (debits), net owed, the seller's
     * wallet balance and the pending-entry count. Honours the same store/date filters.
     */
    public function sellersRollup(Request $request): JsonResponse
    {
        $this->authorize('viewAny', SellerStatement::class);
        if ($this->getPanel() !== 'admin') {
            abort(403);
        }

        $draw = $request->get('draw');
        $start = (int) $request->get('start', 0);
        $length = (int) $request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $scope = $request->get('scope') === 'all' ? 'all' : 'pending';

        $base = SellerStatement::query();
        $this->applyCommonFilters($base, $request);
        if ($scope === 'pending') {
            $base->where('settlement_status', SellerSettlementStatusEnum::PENDING());
        }

        if (!empty($searchValue)) {
            $matchingSellerIds = Seller::whereHas('user', function ($q) use ($searchValue) {
                $q->where('name', 'like', "%{$searchValue}%");
            })->pluck('id');
            $base->whereIn('seller_id', $matchingSellerIds);
        }

        $totalRecords = (int) (clone $base)->distinct('seller_id')->count('seller_id');

        $rows = (clone $base)
            ->selectRaw("seller_id,
                SUM(CASE WHEN settlement_status = 'pending' AND on_hold = 0 AND entry_type = 'credit' THEN amount ELSE 0 END) as credits_total,
                SUM(CASE WHEN settlement_status = 'pending' AND on_hold = 0 AND entry_type = 'debit' THEN amount ELSE 0 END) as debits_total,
                SUM(CASE WHEN settlement_status = 'pending' AND on_hold = 0 THEN 1 ELSE 0 END) as entries_count")
            ->groupBy('seller_id')
            ->orderByDesc('credits_total')
            ->skip($start)->take($length)->get();

        $sellerIds = $rows->pluck('seller_id')->all();
        $sellers = Seller::with('user')->whereIn('id', $sellerIds)->get()->keyBy('id');
        $userIds = $sellers->pluck('user_id')->filter()->all();
        $wallets = Wallet::where('type', WalletTypeEnum::SELLER)->whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $pendingWithdrawals = \App\Models\SellerWithdrawalRequest::where('status', 'pending')
            ->whereIn('seller_id', $sellerIds)
            ->selectRaw('seller_id, SUM(amount) as total')
            ->groupBy('seller_id')
            ->pluck('total', 'seller_id');

        $cashSvg = "<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path stroke='none' d='M0 0h24v24H0z' fill='none'/><rect x='7' y='9' width='14' height='10' rx='2'/><circle cx='14' cy='14' r='2'/><path d='M17 9v-2a2 2 0 0 0 -2 -2h-10a2 2 0 0 0 -2 2v6a2 2 0 0 0 2 2h2'/></svg>";
        $docSvg = "<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path stroke='none' d='M0 0h24v24H0z' fill='none'/><path d='M14 3v4a1 1 0 0 0 1 1h4'/><path d='M5 21v-14a2 2 0 0 1 2 -2h7l5 5v11a2 2 0 0 1 -2 2h-3'/><path d='M9 17h6'/><path d='M9 13h6'/></svg>";

        $data = $rows->map(function ($r) use ($sellers, $wallets, $pendingWithdrawals, $cashSvg, $docSvg) {
            $seller = $sellers->get($r->seller_id);
            $wallet = $seller ? $wallets->get($seller->user_id) : null;
            $name = $seller->user->name ?? ('#' . $r->seller_id);

            $credits = (float) $r->credits_total;
            $debits = (float) $r->debits_total;
            $net = round($credits - $debits, 2);

            $action = "<div class='d-flex gap-1 justify-content-center'>";
            $action .= "<button class='btn btn-icon seller-statement-btn' data-seller-id='{$r->seller_id}' data-bs-toggle='tooltip' title='" . __('labels.statement') . "'>{$docSvg}</button>";
            if ($this->settlePermission && (int) $r->entries_count > 0) {
                $action .= "<button class='btn btn-icon btn-outline-primary settle-seller-btn' data-seller-id='{$r->seller_id}' data-seller='" . e($name) . "' data-net='" . $this->currencyService->format($net) . "' data-bs-toggle='tooltip' title='" . __('labels.settle') . "'>{$cashSvg}</button>";
            }
            $action .= "</div>";

            return [
                'seller' => "<span class='fw-medium'>" . e($name) . "</span>",
                'credits' => $this->currencyService->format($credits),
                'debits' => "<span class='text-danger'>" . $this->currencyService->format($debits) . "</span>",
                'net' => "<span class='text-primary'>" . $this->currencyService->format($net) . "</span>",
                'wallet' => $this->currencyService->format($wallet ? (float) $wallet->balance : 0),
                'withdrawal' => $this->currencyService->format((float) ($pendingWithdrawals[$r->seller_id] ?? 0)),
                'entries' => (int) $r->entries_count,
                'action' => $action,
            ];
        })->toArray();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalRecords,
            'data' => $data,
        ]);
    }

    /**
     * Settle every pending entry (credits + debits) for one seller in a single
     * transaction. The net wallet impact equals credits − debits. Admin only.
     */
    public function settleSeller($sellerId): JsonResponse
    {
        $this->authorize('processSettleAll', SellerStatement::class);
        if (!$this->settlePermission) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: [], status: 403);
        }

        try {
            DB::beginTransaction();

            $statements = SellerStatement::with(['orderItem.store.seller'])
                ->where('seller_id', $sellerId)
                ->where('settlement_status', SellerSettlementStatusEnum::PENDING())
                ->where('on_hold', false)
                ->get();

            if ($statements->isEmpty()) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.no_unsettled_commissions_found',
                    data: ['settled_count' => 0]
                );
            }

            $settledCount = 0;
            $errors = [];
            foreach ($statements as $statement) {
                try {
                    $this->applySettlement($statement);
                    $settledCount++;
                } catch (\Exception $e) {
                    $errors[] = "#{$statement->id}: {$e->getMessage()}";
                }
            }

            DB::commit();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: count($errors) > 0 ? 'labels.commissions_settled_with_errors' : 'labels.all_commissions_settled_successfully',
                data: ['settled_count' => $settledCount, 'error_count' => count($errors), 'errors' => $errors]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error settling seller statements: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(success: false, message: $e->getMessage(), data: ['error' => $e->getMessage()], status: 500);
        }
    }

    /**
     * Manually kick off the settlements:auto-settle cron from the earnings screen —
     * settles all matured, non-held credits into seller wallets right now (same job the
     * daily scheduler runs). Runs even if auto-settle is disabled, so the admin keeps a
     * one-click "settle matured now" without flipping the setting. Routes through the
     * shared CronMonitorService so the run is whitelisted + logged (MANUAL trigger),
     * exactly like the cron monitor's Run Now.
     */
    public function runAutoSettle(): JsonResponse
    {
        $this->authorize('processSettleAll', SellerStatement::class);
        if (!$this->settlePermission) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: [], status: 403);
        }

        try {
            $log = app(CronMonitorService::class)->runCommand('settlements:auto-settle');

            $ok = (string) $log->status->value === CommandRunStatusEnum::SUCCESS();
            return ApiResponseType::sendJsonResponse(
                success: $ok,
                message: $ok ? 'labels.auto_settle_run_done' : 'labels.something_went_wrong',
                data: [
                    'status' => $log->status,
                    'output' => $log->output,
                    'duration_ms' => $log->duration_ms,
                ],
                status: $ok ? 200 : 500,
            );
        } catch (\Throwable $e) {
            Log::error('Manual auto-settle run failed: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.something_went_wrong', data: ['error' => $e->getMessage()], status: 500);
        }
    }

    /**
     * Place a pending entry on hold so it is skipped by settle-all / settle-seller /
     * auto-settle until an admin releases it. Admin only.
     */
    public function hold($id, Request $request): JsonResponse
    {
        $this->authorize('processSettle', SellerStatement::class);
        if (!$this->settlePermission) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: [], status: 403);
        }

        $statement = SellerStatement::where('id', $id)
            ->where('settlement_status', SellerSettlementStatusEnum::PENDING())
            ->first();
        if (!$statement) {
            return ApiResponseType::sendJsonResponse(success: false, message: __('labels.no_detail_available'), data: [], status: 404);
        }

        $statement->update([
            'on_hold' => true,
            'hold_reason' => $request->get('reason'),
            'held_by' => Auth::id(),
            'held_at' => now(),
        ]);

        return ApiResponseType::sendJsonResponse(success: true, message: __('labels.entry_held'), data: ['id' => $statement->id]);
    }

    /**
     * Release a held entry back into the settleable pool. Admin only.
     */
    public function unhold($id): JsonResponse
    {
        $this->authorize('processSettle', SellerStatement::class);
        if (!$this->settlePermission) {
            return ApiResponseType::sendJsonResponse(success: false, message: 'labels.permission_denied', data: [], status: 403);
        }

        $statement = SellerStatement::find($id);
        if (!$statement) {
            return ApiResponseType::sendJsonResponse(success: false, message: __('labels.no_detail_available'), data: [], status: 404);
        }

        $statement->update([
            'on_hold' => false,
            'hold_reason' => null,
            'held_by' => null,
            'held_at' => null,
        ]);

        return ApiResponseType::sendJsonResponse(success: true, message: __('labels.entry_released'), data: ['id' => $statement->id]);
    }

    /**
     * Settle a single statement into the seller wallet (credit = add, debit = deduct)
     * and mark it settled with the acting admin. Throws on any failure so the caller's
     * transaction can roll back. Delegates to the shared SettlementProcessor so the
     * manual settle flows and the auto-settle cron share one money path.
     */
    private function applySettlement(SellerStatement $statement): void
    {
        app(SettlementProcessor::class)->settle($statement, Auth::id());
    }

    /**
     * Resolve a safe, whitelisted DB column to order by. Front-end column indices
     * that don't map to a real sortable column fall back to `id`, so the
     * server-side ordering can never reference a non-existent / computed column.
     */
    private function safeOrderColumn(Request $request, array $map): string
    {
        $index = (int) ($request->get('order')[0]['column'] ?? 0);
        return $map[$index] ?? 'id';
    }

    /**
     * Apply the shared store + date-range filters to a statements query so the
     * summary cards and the datatables always agree. `date_range` is a number of
     * days (7/15/30/90); empty/0 means all time.
     */
    private function applyCommonFilters($query, Request $request): void
    {
        $storeId = $this->getPanel() === 'seller'
            ? app(SellerStoreService::class)->selectedId()
            : $request->get('store_id');
        if (!empty($storeId)) {
            $query->whereHas('orderItem', function ($qi) use ($storeId) {
                $qi->where('store_id', $storeId);
            });
        }

        $days = (int) $request->get('date_range', 0);
        if ($days > 0) {
            $query->where('posted_at', '>=', now()->subDays($days)->startOfDay());
        }

        if ($this->getPanel() === 'admin') {
            $marketId = app(MarketService::class)->adminSelectedId();
            $query->where(function ($q) use ($marketId) {
                $q->whereHas('order', fn ($oq) => $oq->where('market_id', $marketId))
                    ->orWhereDoesntHave('order');
            });
        }

        $this->activeSellerConstraint($query);
    }

    /**
     * Restrict a statements query to entries whose seller is live and active:
     * soft-deleted sellers/users are dropped by the SoftDeletes global scope on the
     * relations, and `user.status = active` drops deactivated accounts. Keeps deleted /
     * banned sellers out of every settlement list.
     */
    private function activeSellerConstraint($query): void
    {
        $query->whereHas('seller.user', function ($u) {
            $u->where('status', 'active');
        });
    }

    /**
     * Compact "Order / Item" cell: order id (+ item id), product title and variant.
     */
    private function orderItemCell(SellerStatement $st): string
    {
        $orderItem = $st->orderItem;
        $html = "<div class='small'>";
        $html .= "<span class='fw-medium text-primary'>#" . e($st->order_id ?? '—') . "</span>"
            . ($st->order_item_id ? " <span class='text-secondary'>· " . __('labels.item') . " #" . e($st->order_item_id) . "</span>" : "");
        if ($orderItem) {
            $html .= "<div class='text-truncate' style='max-width:240px'>" . e($orderItem->title) . "</div>";
            if (!empty($orderItem->variant_title)) {
                $html .= "<div class='text-secondary text-truncate' style='max-width:240px'>" . e($orderItem->variant_title) . "</div>";
            }
        } elseif (!empty($st->description)) {
            $html .= "<div class='text-secondary text-truncate' style='max-width:240px'>" . e($st->description) . "</div>";
        }
        $html .= "</div>";
        return $html;
    }

    /**
     * Store name cell (with seller name underneath on the admin panel).
     */
    private function storeCell(SellerStatement $st): string
    {
        $store = $st->orderItem?->store;
        $meta = is_array($st->meta) ? $st->meta : [];
        $html = "<div class='small'>" . e($store->name ?? '—') . "</div>";
        if ($this->getPanel() === 'admin') {
            $sellerName = $st->seller->user->name ?? ($meta['seller_name'] ?? null);
            if (!empty($sellerName)) {
                $html .= "<div class='text-secondary small'>" . e($sellerName) . "</div>";
            }
        }
        return $html;
    }

    /**
     * Type / reference cell for debit (returns & deductions) rows.
     */
    private function referenceCell(SellerStatement $st): string
    {
        $type = Str::ucfirst(Str::replace('_', ' ', (string) $st->reference_type));
        $html = "<div class='small'><span class='fw-medium'>" . e($type ?: '—') . "</span>";
        if ($st->return_id) {
            $html .= "<div class='text-secondary'>" . __('labels.return_id') . " #" . e($st->return_id) . "</div>";
        }
        $html .= "</div>";
        return $html;
    }

    /**
     * The per-statement detail endpoint URL for the current panel.
     */
    private function detailUrl($id): string
    {
        return route($this->getPanel() . '.commissions.detail', ['id' => $id]);
    }

    /**
     * Maturity badge for a PENDING credit: green "Matured" once it's eligible for
     * auto-settle (available_at reached, or null = legacy/immediate), else a muted
     * "Matures on <date>" so admin/seller can see when the payout cycle will pick it up.
     */
    private function maturityCell(SellerStatement $st): string
    {
        $avail = $st->available_at;
        if (!$avail || $avail->lte(now())) {
            return "<span class='badge bg-success-lt'>" . __('labels.matured') . "</span>";
        }
        return "<span class='text-secondary small'>" . __('labels.matures_on') . " "
            . e($avail->format('Y-m-d')) . "</span>";
    }

    /**
     * Who settled a statement: the acting admin's name, or "System" for entries the
     * auto-settle cron processed (settled but no settled_by). Null while still pending.
     */
    private function settledByDisplay(SellerStatement $st): ?string
    {
        if ((string) $st->settlement_status !== (string) SellerSettlementStatusEnum::SETTLED()) {
            return null;
        }
        return $st->settledBy?->name ?? __('labels.system');
    }

    /**
     * Settlement method label: "Manual" when an admin settled it, "Auto" for the
     * cron (no settled_by). Null while still pending.
     */
    private function settlementMethod(SellerStatement $st): ?string
    {
        if ((string) $st->settlement_status !== (string) SellerSettlementStatusEnum::SETTLED()) {
            return null;
        }
        return $st->settled_by ? __('labels.manual') : __('labels.auto');
    }

    /**
     * Resolve a currency-consistent set of money figures for a statement.
     *
     * The ledger stores amounts in two different currencies depending on how the
     * entry was created, which previously caused the screen to show gross in the
     * market currency next to commission in the base currency. To avoid mixing:
     *
     *  - Per-seller-order settlement entries (the canonical archive flow) carry
     *    `meta.marketplace_fee` and store `amount` in BASE currency, with no linked
     *    order item. For these everything is derived in BASE (gross = net + fee),
     *    and the original market figure is kept separately for context only.
     *  - Per-item entries (e.g. delivery credits) have a linked order item and
     *    store `amount` in the order's MARKET currency; gross/commission come
     *    straight off the order item so all three share that one currency.
     *
     * The returned `gross`, `commission` and `net` are therefore always in the
     * same currency (`currency`), so they add up and can be formatted together.
     */
    /**
     * Build the row action cell (a "view detail" button, plus a settle button when
     * the admin has the settle permission).
     */
    private function actionCell(SellerStatement $st, bool $canSettle, ?string $settleUrl, float $marketplaceFee): string
    {
        $orderItem = $st->orderItem;
        return view('partials.actions', [
            'modelName' => 'commission',
            'id' => $st->id,
            'title' => $orderItem?->title ?? ('Statement #' . $st->id),
            'mode' => $canSettle ? 'settle' : 'view',
            'settlePermission' => $canSettle,
            'detailUrl' => $this->detailUrl($st->id),
            'orderId' => $st->order_id,
            'adminCommissionAmount' => $this->currencyService->format($marketplaceFee),
            'amountToPay' => $this->currencyService->format($st->amount),
            'productTitle' => $orderItem ? ($orderItem->title . ' - ' . $orderItem->variant_title) : ($st->description ?? ''),
            'settleUrl' => $settleUrl,
            'isHeld' => (bool) $st->on_hold,
            'holdReason' => $st->hold_reason,
            'holdUrl' => $canSettle ? route('admin.commissions.hold', ['id' => $st->id]) : null,
            'unholdUrl' => $canSettle ? route('admin.commissions.unhold', ['id' => $st->id]) : null,
        ])->render();
    }

    /**
     * Get unsettled commissions for datatable.
     */
    public function getUnsettledCommissions(Request $request): JsonResponse
    {
        $this->authorize('view', SellerStatement::class);
        $draw = $request->get('draw');
        $start = (int)$request->get('start', 0);
        $length = (int)$request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';
        $orderColumn = $this->safeOrderColumn($request, [0 => 'id', 5 => 'amount', 6 => 'posted_at']);

        $user = Auth::user();
        if ($this->getPanel() === 'seller') {
            if (empty($user->seller())) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.seller_not_found'),
                    data: [],
                );
            }
        }

        $query = SellerStatement::with(['orderItem.store', 'order', 'seller.user'])
            ->where('entry_type', SellerSettlementTypeEnum::CREDIT())
            ->where('settlement_status', SellerSettlementStatusEnum::PENDING());

        if ($this->getPanel() === 'seller') {
            $query->where('seller_id', $user->seller()->id);
        }

        $this->applyCommonFilters($query, $request);
        $this->applyDatatableDateRange($query, $request, 'posted_at');

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('reference_id', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('orderItem', function ($qi) use ($searchValue) {
                        $qi->where('title', 'like', "%{$searchValue}%")
                            ->orWhere('variant_title', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('orderItem.store', function ($qs) use ($searchValue) {
                        $qs->where('name', 'like', "%{$searchValue}%");
                    });
            });
        }

        $totalRecords = $query->count();
        $filteredRecords = $totalRecords;

        $statements = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $statements->map(function ($st) {
            $f = $this->statementFigures($st);
            $settleUrl = $this->getPanel() === 'admin' ? route('admin.commissions.settle', ['id' => $st->id]) : null;

            return [
                'id' => $st->id,
                'order_item' => $this->orderItemCell($st),
                'store' => $this->storeCell($st),
                'gross_amount' => $this->currencyService->format($f['gross']),
                'marketplace_fee' => $this->currencyService->format($f['commission']),
                'payout_amount' => "<span class='text-primary'>" . $this->currencyService->format($f['net']) . "</span>",
                'posted_at' => optional($st->posted_at ?? $st->updated_at)->format('Y-m-d'),
                'maturity' => $this->maturityCell($st),
                'action' => $this->actionCell($st, $this->settlePermission, $settleUrl, $f['commission']),
            ];
        })->toArray();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Settle commission for a specific order item.
     */
    public function settleCommission($id): JsonResponse
    {
        $this->authorize('processSettle', SellerStatement::class);
        if (!$this->settlePermission) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: [],
                status: 403
            );
        }

        try {
            DB::beginTransaction();

            $statement = SellerStatement::with(['orderItem.store.seller', 'order'])
                ->where('id', $id)
                ->where('entry_type', SellerSettlementTypeEnum::CREDIT())
                ->where('settlement_status', SellerSettlementStatusEnum::PENDING())
                ->lockForUpdate()
                ->firstOrFail();

            if ($statement->on_hold) {
                DB::rollBack();
                return ApiResponseType::sendJsonResponse(success: false, message: __('labels.entry_on_hold'), data: [], status: 422);
            }

            $seller = $statement?->seller;

            if (!$seller) {
                throw new \Exception('Seller not found for this statement');
            }

            $walletResult = $this->walletService->getWallet($seller->user_id, WalletTypeEnum::SELLER);
            if (!$walletResult['success']) {
                throw new \Exception('Failed to get seller wallet: ' . $walletResult['message']);
            }

            $amount = (float)$statement->amount;

            $transactionData = [
                'amount' => $amount,
                'payment_method' => 'commission_settlement',
                'idempotency_key' => 'STMT-' . $statement->id,
                'description' => $statement->description ?? ('Commission settlement for order #' . ($statement->order_id ?? '')),
                'order_id' => $statement->order_id,
                'store_id' => $store->id ?? null,
            ];

            $addBalanceResult = $this->walletService->addBalance($seller->user_id, $transactionData, WalletTypeEnum::SELLER);

            if (!$addBalanceResult['success']) {
                throw new \Exception('Failed to add balance to seller wallet: ' . $addBalanceResult['message']);
            }

            $this->statementService->markSettled($statement, $addBalanceResult['data']['transaction']['id'] ?? ('STMT-' . $statement->id), settledBy: Auth::id());

            DB::commit();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.commission_settled_successfully',
                data: [
                    'statement' => $statement->fresh(),
                    'transaction' => $addBalanceResult['data']['transaction'] ?? null
                ]
            );

        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.transaction_already_processed'),
                data: [],
                status: 422
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error settling commission: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: ['error' => $e->getMessage()],
                status: 500
            );
        }
    }

    /**
     * Settle all unsettled commissions.
     */
    public function settleAllCommissions(Request $request): JsonResponse
    {
        $this->authorize('processSettleAll', SellerStatement::class);
        if (!$this->settlePermission) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: [],
                status: 403
            );
        }

        try {
            $query = SellerStatement::with(['orderItem.store.seller', 'order'])
                ->where('entry_type', SellerSettlementTypeEnum::CREDIT())
                ->where('settlement_status', SellerSettlementStatusEnum::PENDING())
                ->where('on_hold', false);

            $this->applyCommonFilters($query, $request);

            $unsettledStatements = $query->get();

            if ($unsettledStatements->count() === 0) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.no_unsettled_commissions_found',
                    data: ['settled_count' => 0]
                );
            }

            $settledCount = 0;
            $errors = [];

            foreach ($unsettledStatements as $statement) {
                try {
                    DB::transaction(function () use ($statement, &$settledCount) {
                        $this->applySettlement($statement);
                        $settledCount++;
                    });
                } catch (\Exception $e) {
                    $errors[] = "Error processing statement #{$statement->id}: {$e->getMessage()}";
                }
            }

            if (count($errors) > 0) {
                return ApiResponseType::sendJsonResponse(
                    success: true,
                    message: "labels.commissions_settled_with_errors",
                    data: [
                        'settled_count' => $settledCount,
                        'error_count' => count($errors),
                        'errors' => $errors
                    ]
                );
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.all_commissions_settled_successfully',
                data: ['settled_count' => $settledCount]
            );

        } catch (\Exception $e) {
            Log::error('Error settling all commissions: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: ['error' => $e->getMessage()],
                status: 500
            );
        }
    }

    /**
     * Display a listing of settled commissions.
     */
    public function history(): View
    {
        $this->authorize('viewHistory', SellerStatement::class);
        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'entry_type', 'name' => 'entry_type', 'title' => __('labels.entry_type'), 'orderable' => false],
            ['data' => 'order_item', 'name' => 'order_item', 'title' => __('labels.order') . ' / ' . __('labels.item'), 'orderable' => false],
            ['data' => 'store', 'name' => 'store', 'title' => __('labels.store'), 'orderable' => false],
            ['data' => 'description', 'name' => 'description', 'title' => __('labels.description'), 'orderable' => false],
            ['data' => 'amount', 'name' => 'amount', 'title' => __('labels.amount'), 'className' => 'text-end fw-bold'],
            ['data' => 'settlement_date', 'name' => 'settled_at', 'title' => __('labels.settled_on')],
            ['data' => 'method', 'name' => 'method', 'title' => __('labels.settlement_method'), 'orderable' => false],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        return view($this->panelView('commissions.history'), compact('columns'));
    }

    /**
     * Get settled commissions for datatable.
     */
    public function getSettledCommissions(Request $request): JsonResponse
    {
        $this->authorize('viewSettled', SellerStatement::class);
        $draw = $request->get('draw');
        $start = (int)$request->get('start', 0);
        $length = (int)$request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';
        $orderColumn = $this->safeOrderColumn($request, [0 => 'id', 5 => 'amount', 6 => 'settled_at']);
        $user = Auth::user();
        if ($this->getPanel() === 'seller') {
            if (empty($user->seller())) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.seller_not_found',
                    data: [],
                );
            }
        }

        $query = SellerStatement::with(['orderItem.store', 'order', 'seller', 'settledBy'])
            ->where('settlement_status', SellerSettlementStatusEnum::SETTLED());

        $this->activeSellerConstraint($query);

        if ($this->getPanel() === 'seller') {
            $query->where('seller_id', $user->seller()->id);
        }

        $storeId = $this->getPanel() === 'seller'
            ? app(SellerStoreService::class)->selectedId()
            : $request->get('store_id');
        if (!empty($storeId)) {
            $query->whereHas('orderItem', function ($qi) use ($storeId) {
                $qi->where('store_id', $storeId);
            });
        }

        if ($this->getPanel() === 'admin') {
            $marketId = app(MarketService::class)->adminSelectedId();
            $query->where(function ($q) use ($marketId) {
                $q->whereHas('order', fn ($oq) => $oq->where('market_id', $marketId))
                    ->orWhereDoesntHave('order');
            });
        }

        $this->applyDatatableDateRange($query, $request, 'posted_at');

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('reference_id', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('orderItem', function ($qi) use ($searchValue) {
                        $qi->where('title', 'like', "%{$searchValue}%")
                            ->orWhere('variant_title', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('orderItem.store', function ($qs) use ($searchValue) {
                        $qs->where('name', 'like', "%{$searchValue}%");
                    });
            });
        }

        $totalRecords = (clone $query)->count();
        $filteredRecords = $totalRecords;

        $statements = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $statements->map(function ($st) {
            $isDebit = (string) $st->entry_type === 'debit';
            $isAuto = !$st->settled_by;
            $methodHtml = "<span class='badge " . ($isAuto ? 'bg-azure-lt' : 'bg-secondary-lt') . "'>"
                . e($this->settlementMethod($st)) . "</span>"
                . "<div class='text-secondary small'>" . e($this->settledByDisplay($st)) . "</div>";
            return [
                'id' => $st->id,
                'entry_type' => "<span class='text-capitalize badge badge-lg "
                    . ($isDebit ? "bg-danger-lt" : "bg-success-lt")
                    . "'>" . e($st->entry_type) . "</span>",
                'order_item' => $this->orderItemCell($st),
                'store' => $this->storeCell($st),
                'description' => "<span class='small'>" . e($st->description) . "</span>",
                'amount' => "<span class='" . ($isDebit ? 'text-danger' : 'text-success') . "'>" . $this->currencyService->format($st->amount) . "</span>",
                'settlement_date' => optional($st->settled_at)->format('Y-m-d H:i:s'),
                'method' => $methodHtml,
                'action' => view('partials.actions', [
                    'modelName' => 'commission',
                    'id' => $st->id,
                    'title' => $st->orderItem?->title ?? ('Statement #' . $st->id),
                    'mode' => 'view',
                    'detailUrl' => $this->detailUrl($st->id),
                ])->render(),
            ];
        })->toArray();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * List pending debit statements (e.g., return refunds to be debited from seller).
     */
    public function getUnsettledDebits(Request $request): JsonResponse
    {
        $this->authorize('viewUnsettledDebits', SellerStatement::class);
        $draw = $request->get('draw');
        $start = (int)$request->get('start', 0);
        $length = (int)$request->get('length', 10);
        $searchValue = $request->get('search')['value'] ?? '';

        $orderDirection = $request->get('order')[0]['dir'] ?? 'asc';
        $orderColumn = $this->safeOrderColumn($request, [0 => 'id', 4 => 'amount', 5 => 'posted_at']);

        $user = Auth::user();
        if ($this->getPanel() === 'seller') {
            if (empty($user->seller())) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.seller_not_found'),
                    data: [],
                );
            }
        }

        $query = SellerStatement::with(['orderItem.store', 'order', 'seller'])
            ->where('entry_type', SellerSettlementTypeEnum::DEBIT())
            ->where('settlement_status', SellerSettlementStatusEnum::PENDING());

        if ($this->getPanel() === 'seller') {
            $query->where('seller_id', $user->seller()->id);
        }

        $this->applyCommonFilters($query, $request);
        $this->applyDatatableDateRange($query, $request, 'posted_at');

        if (!empty($searchValue)) {
            $query->where(function ($q) use ($searchValue) {
                $q->where('reference_id', 'like', "%{$searchValue}%")
                    ->orWhere('description', 'like', "%{$searchValue}%")
                    ->orWhereHas('orderItem', function ($qi) use ($searchValue) {
                        $qi->where('title', 'like', "%{$searchValue}%")
                            ->orWhere('variant_title', 'like', "%{$searchValue}%");
                    })
                    ->orWhereHas('orderItem.store', function ($qs) use ($searchValue) {
                        $qs->where('name', 'like', "%{$searchValue}%");
                    });
            });
        }

        $totalRecords = (clone $query)->count();
        $filteredRecords = $totalRecords;

        $statements = $query
            ->orderBy($orderColumn, $orderDirection)
            ->skip($start)
            ->take($length)
            ->get();

        $data = $statements->map(function ($st) {
            $f = $this->statementFigures($st);
            $settleUrl = $this->getPanel() === 'admin' ? route('admin.commissions.debits.settle', ['id' => $st->id]) : null;

            return [
                'id' => $st->id,
                'reference' => $this->referenceCell($st),
                'order_item' => $this->orderItemCell($st),
                'store' => $this->storeCell($st),
                'debit_amount' => "<span class='text-danger'>" . $this->currencyService->format($f['net']) . "</span>",
                'posted_at' => optional($st->posted_at ?? $st->updated_at)->format('Y-m-d'),
                'action' => $this->actionCell($st, $this->settlePermission, $settleUrl, $f['commission']),
            ];
        })->toArray();

        return response()->json([
            'draw' => intval($draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $data,
        ]);
    }

    /**
     * Settle a single debit statement (admin): deduct from seller wallet and mark settled.
     */
    public function settleDebit($id): JsonResponse
    {
        $this->authorize('settleDebit', SellerStatement::class);
        if (!$this->settlePermission) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: [],
                status: 403
            );
        }

        try {
            DB::beginTransaction();

            $statement = SellerStatement::with(['orderItem.store.seller', 'order'])
                ->where('id', $id)
                ->where('entry_type', SellerSettlementTypeEnum::DEBIT())
                ->where('settlement_status', SellerSettlementStatusEnum::PENDING())
                ->lockForUpdate()
                ->firstOrFail();

            if ($statement->on_hold) {
                DB::rollBack();
                return ApiResponseType::sendJsonResponse(success: false, message: __('labels.entry_on_hold'), data: [], status: 422);
            }

            $seller = $statement->orderItem?->store?->seller;
            if (!$seller) {
                $seller = Seller::find($statement->seller_id);
            }
            if (!$seller) {
                throw new \Exception('Seller not found for this statement');
            }

            $amount = (float)$statement->amount;
            $transactionData = [
                'amount' => $amount,
                'payment_method' => 'commission_debit_settlement',
                'transaction_reference' => 'STMT-DEBIT-' . $statement->id,
                'description' => $statement->description ?? ('Debit settlement for return/order #' . ($statement->order_id ?? '')),
                'order_id' => $statement->order_id,
                'store_id' => $statement->orderItem?->store_id,
            ];

            $deductResult = WalletService::deductBalance($seller->user_id, $transactionData, WalletTypeEnum::SELLER);
            if (!$deductResult['success']) {
                DB::rollBack();

                $wallet = Wallet::where('user_id', $seller->user_id)
                    ->where('type', WalletTypeEnum::SELLER->value)
                    ->first();
                $available = (float) ($wallet->balance ?? 0);

                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('labels.debit_settlement_insufficient_balance', [
                        'amount' => number_format($amount, 2),
                        'available' => number_format($available, 2),
                    ]),
                    data: [
                        'required' => $amount,
                        'available' => $available,
                        'shortfall' => round($amount - $available, 2),
                    ],
                    status: 422
                );
            }

            $this->statementService->markSettled($statement, $deductResult['data']['transaction']['id'] ?? ('STMT-DEBIT-' . $statement->id), settledBy: Auth::id());

            DB::commit();

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.commission_settled_successfully',
                data: [
                    'statement' => $statement->fresh(),
                    'transaction' => $deductResult['data']['transaction'] ?? null
                ]
            );
        } catch (ModelNotFoundException $e) {
            DB::rollBack();

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('labels.transaction_already_processed'),
                data: [],
                status: 422
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error settling debit: ' . $e->getMessage());
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: ['error' => $e->getMessage()],
                status: 500
            );
        }
    }

    /**
     * Settle all pending debit statements (admin)
     */
    public function settleAllDebits(Request $request): JsonResponse
    {
        $this->authorize('settleAllDebits', SellerStatement::class);
        if (!$this->settlePermission) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.permission_denied',
                data: [],
                status: 403
            );
        }

        try {
            DB::beginTransaction();

            $query = SellerStatement::with(['orderItem.store.seller', 'order'])
                ->where('entry_type', SellerSettlementTypeEnum::DEBIT())
                ->where('settlement_status', SellerSettlementStatusEnum::PENDING())
                ->where('on_hold', false);

            $this->applyCommonFilters($query, $request);

            $unsettledStatements = $query->lockForUpdate()->get();

            if ($unsettledStatements->count() === 0) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: 'labels.no_unsettled_debit_found',
                    data: ['settled_count' => 0]
                );
            }

            $settledCount = 0;
            $errors = [];

            foreach ($unsettledStatements as $statement) {
                try {
                    $seller = $statement->orderItem?->store?->seller ?? Seller::find($statement->seller_id);
                    if (!$seller) {
                        $errors[] = "Seller not found for statement #{$statement->id}";
                        continue;
                    }

                    $amount = (float)$statement->amount;
                    $transactionData = [
                        'amount' => $amount,
                        'payment_method' => 'commission_debit_settlement',
                        'transaction_reference' => 'STMT-DEBIT-' . $statement->id,
                        'description' => $statement->description ?? ('Debit settlement for return/order #' . ($statement->order_id ?? '')),
                        'order_id' => $statement->order_id,
                        'store_id' => $statement->orderItem?->store_id,
                    ];

                    $deductResult = WalletService::deductBalance($seller->user_id, $transactionData, WalletTypeEnum::SELLER);
                    if (!$deductResult['success']) {
                        $errors[] = "Failed to deduct balance for statement #{$statement->id}: {$deductResult['message']}";
                        continue;
                    }

                    $this->statementService->markSettled($statement, $deductResult['data']['transaction']['id'] ?? ('STMT-DEBIT-' . $statement->id), settledBy: Auth::id());
                    $settledCount++;
                } catch (\Exception $e) {
                    $errors[] = "Error processing debit statement #{$statement->id}: {$e->getMessage()}";
                }
            }

            DB::commit();

            if (count($errors) > 0) {
                return ApiResponseType::sendJsonResponse(
                    success: true,
                    message: "labels.commissions_settled_with_errors",
                    data: [
                        'settled_count' => $settledCount,
                        'error_count' => count($errors),
                        'errors' => $errors
                    ]
                );
            }

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: 'labels.all_commissions_settled_successfully',
                data: ['settled_count' => $settledCount]
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error settling all debit commissions: ' . $e->getMessage());

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $e->getMessage(),
                data: ['error' => $e->getMessage()],
                status: 500
            );
        }
    }

    /**
     * Return a full money breakdown for a single settlement entry, rendered as HTML
     * for the detail modal. Works for both the seller and admin panels (the seller
     * is scoped to their own statements). Covers credits (payouts) and debits
     * (returns / amounts owed), and gracefully degrades when an entry has no linked
     * order item (e.g. per-seller-order archive settlements that only carry meta).
     */
    public function commissionDetail($id): JsonResponse
    {
        $this->authorize('view', SellerStatement::class);

        $query = SellerStatement::with(['orderItem.store', 'order', 'orderReturn', 'seller.user', 'settledBy', 'heldBy'])
            ->where('id', $id);

        if ($this->getPanel() === 'seller') {
            $user = Auth::user();
            if (empty($user->seller())) {
                return ApiResponseType::sendJsonResponse(success: false, message: __('labels.seller_not_found'), data: [], status: 404);
            }
            $query->where('seller_id', $user->seller()->id);
        }

        $st = $query->first();
        if (!$st) {
            return ApiResponseType::sendJsonResponse(success: false, message: __('labels.no_detail_available'), data: [], status: 404);
        }

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: 'ok',
            data: [
                'title' => __('labels.settlement_detail') . ' #' . $st->id,
                'html' => $this->renderStatementDetail($st),
            ]
        );
    }

    /**
     * Build the HTML body for the settlement detail modal.
     */
    private function renderStatementDetail(SellerStatement $st): string
    {
        $fmt = fn ($v) => $this->currencyService->format((float) $v);
        $orderItem = $st->orderItem;
        $meta = is_array($st->meta) ? $st->meta : [];
        $isDebit = (string) $st->entry_type === 'debit';

        $f          = $this->statementFigures($st);
        $gross      = (float) $f['gross'];
        $commission = (float) $f['commission'];
        $tax        = (float) ($f['tax'] ?? 0.0);
        $hasItemMath = (bool) $f['has_item'];
        $preTax     = $hasItemMath ? round($gross - $tax, 2) : 0.0;
        $rate       = $preTax > 0 ? round($commission / $preTax * 100, 2) : 0.0;

        $entryBadge = "<span class='badge text-capitalize " . ($isDebit ? 'bg-danger-lt' : 'bg-success-lt') . "'>" . e($st->entry_type) . "</span>";
        $isSettled  = (string) $st->settlement_status === (string) SellerSettlementStatusEnum::SETTLED();
        $statusBadge = "<span class='badge " . ($isSettled ? 'bg-success-lt' : 'bg-yellow-lt') . " text-capitalize'>" . e($st->settlement_status) . "</span>";

        $row = fn ($label, $value, $strong = false) =>
            "<div class='row mb-2'><div class='col-5 text-secondary small'>" . e($label) . "</div>"
            . "<div class='col-7 " . ($strong ? 'fw-bold' : '') . "'>" . $value . "</div></div>";

        $html = "<div class='p-1'>";

        $html .= "<div class='d-flex align-items-center gap-2 mb-3'>{$entryBadge}{$statusBadge}"
            . "<span class='ms-auto fs-3 fw-bold " . ($isDebit ? 'text-danger' : 'text-primary') . "'>" . $fmt($st->amount) . "</span></div>";

        $html .= "<h4 class='mt-3 mb-2'>" . __('labels.order_information') . "</h4>";
        $orderLabel = '#' . ($st->order_id ?? '—');
        $routeName = $this->getPanel() . '.orders.show';
        if ($st->order_id && \Illuminate\Support\Facades\Route::has($routeName)) {
            $orderLabel = "<a href='" . route($routeName, ['id' => $st->order_id]) . "' target='_blank'>{$orderLabel}</a>";
        }
        $html .= $row(__('labels.order_id'), $orderLabel);
        if ($orderItem) {
            $html .= $row(__('labels.title'), e($orderItem->title));
            if (!empty($orderItem->variant_title)) {
                $html .= $row(__('labels.variant'), e($orderItem->variant_title));
            }
            $html .= $row(__('labels.store'), e($orderItem->store->name ?? '—'));
            $html .= $row(__('labels.order_current_status'), Str::ucfirst(Str::replace('_', ' ', (string) $orderItem->status)));
        } elseif (!empty($st->description)) {
            $html .= $row(__('labels.description'), e($st->description));
        }
        if ($this->getPanel() === 'admin') {
            $html .= $row(__('labels.seller'), e($st->seller->user->name ?? ($meta['seller_name'] ?? '—')));
        }

        $html .= "<h4 class='mt-4 mb-2'>" . __('labels.money_breakdown') . "</h4>";
        if (!empty($f['currency'])) {
            $html .= "<div class='text-secondary small mb-2'>" . __('labels.amounts_shown_in') . " " . e($f['currency']) . "</div>";
        }

        if ($isDebit) {
            $html .= $row(__('labels.deduction'), "<span class='text-danger'>" . $fmt($f['net']) . "</span>", true);
        } elseif ($hasItemMath) {
            $html .= $row(__('labels.gross'), $fmt($gross));
            $html .= $row(__('labels.tax_seller_keeps'), $fmt($tax));
            $html .= $row(__('labels.pre_tax_base'), $fmt($preTax));
            $html .= $row(__('labels.commission') . " (" . $rate . "%)", "<span class='text-danger'>− " . $fmt($commission) . "</span>");
            $html .= "<hr class='my-2'>";
            $html .= $row(__('labels.net_payout'), $fmt($f['net']), true);
        } else {
            $html .= $row(__('labels.gross'), $fmt($gross));
            if ($commission > 0) {
                $html .= $row(__('labels.commission'), "<span class='text-danger'>− " . $fmt($commission) . "</span>");
            }
            $html .= "<hr class='my-2'>";
            $html .= $row(__('labels.net_payout'), $fmt($f['net']), true);
        }

        if (!empty($f['market_amount']) && !empty($f['market_code'])) {
            $html .= "<hr class='my-2'>";
            $html .= $row(__('labels.market_amount'), number_format($f['market_amount'], 2) . " " . e($f['market_code']));
            if (!empty($f['rate'])) {
                $html .= $row(__('labels.fx_rate'), number_format($f['rate'], 4));
            }
        }

        $return = $st->orderReturn;
        if ($return) {
            $html .= "<h4 class='mt-4 mb-2'>" . __('labels.return_information') . "</h4>";
            if (!empty($return->reason)) {
                $html .= $row(__('labels.return_reason'), e($return->reason));
            }
            if (!empty($return->reason_type)) {
                $html .= $row(__('labels.reason_type'), Str::ucfirst(Str::replace('_', ' ', (string) $return->reason_type->value ?? $return->reason_type)));
            }
            if (!empty($return->shipping_cost_payer)) {
                $html .= $row(__('labels.fault_party'), Str::ucfirst(Str::replace('_', ' ', (string) $return->shipping_cost_payer)));
            }
            if ($return->refund_amount !== null) {
                $html .= $row(__('labels.refund_amount'), $fmt($return->refund_amount));
            }
            if (!empty($return->return_status)) {
                $html .= $row(__('labels.status'), Str::ucfirst(Str::replace('_', ' ', (string) $return->return_status)));
            }
        }

        $html .= "<h4 class='mt-4 mb-2'>" . __('labels.settlement_summary') . "</h4>";
        $html .= $row(__('labels.settlement_status'), $statusBadge);
        if ($st->on_hold) {
            $html .= $row(__('labels.hold_status'), "<span class='badge bg-yellow-lt'>" . __('labels.held') . "</span>");
            if (!empty($st->hold_reason)) {
                $html .= $row(__('labels.hold_reason'), e($st->hold_reason));
            }
            if (!empty($st->heldBy)) {
                $html .= $row(__('labels.held_by'), e($st->heldBy->name));
            }
        }
        $html .= $row(__('labels.reference_type'), Str::ucfirst(Str::replace('_', ' ', (string) $st->reference_type)) ?: '—');
        $html .= $row(__('labels.posted_at'), optional($st->posted_at)->format('Y-m-d H:i') ?? '—');
        if (!$isSettled && !$st->on_hold) {
            $avail = $st->available_at;
            $maturity = (!$avail || $avail->lte(now()))
                ? "<span class='badge bg-success-lt'>" . __('labels.matured') . "</span>"
                : __('labels.matures_on') . ' ' . e($avail->format('Y-m-d'));
            $html .= $row(__('labels.settlement_status'), $maturity);
        }
        if ($isSettled) {
            $html .= $row(__('labels.settled_on'), optional($st->settled_at)->format('Y-m-d H:i') ?? '—');
            $html .= $row(__('labels.settlement_method'), e($this->settlementMethod($st)));
            $html .= $row(__('labels.settled_by'), e($this->settledByDisplay($st)));
            if (!empty($st->settlement_reference)) {
                $html .= $row(__('labels.settlement_reference'), e($st->settlement_reference));
            }
        }

        $html .= "</div>";
        return $html;
    }
}
