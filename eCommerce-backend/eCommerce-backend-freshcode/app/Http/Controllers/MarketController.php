<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\Market\MarketStatusEnum;
use App\Http\Requests\Market\StoreMarketRequest;
use App\Http\Requests\Market\UpdateMarketRequest;
use App\Models\Currency;
use App\Models\Market;
use App\Models\MarketCurrencyRate;
use App\Models\Setting;
use App\Services\LanguageService;
use App\Services\Fx\FxRateService;
use App\Services\GeoImportService;
use App\Services\MarketService;
use App\Traits\ChecksPermissions;
use App\Types\Api\ApiResponseType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin-side CRUD for Markets. Mirrors the DeliveryZoneController shape so the
 * admin UX stays familiar (datatable + form pages).
 */
class MarketController extends Controller
{
    use ChecksPermissions;

    protected bool $editPermission;
    protected bool $deletePermission;
    protected bool $createPermission;

    public function __construct()
    {
        $this->editPermission   = $this->hasPermission(AdminPermissionEnum::MARKET_EDIT());
        $this->deletePermission = $this->hasPermission(AdminPermissionEnum::MARKET_DELETE());
        $this->createPermission = $this->hasPermission(AdminPermissionEnum::MARKET_CREATE());
    }


    /**
     * Switch the market the whole admin panel is scoped to. Persisted in the
     * session by MarketService; every admin list/widget reads it back. Any
     * admin may switch their own view (no MARKET_* permission required).
     */
    public function switchAdminMarket(Request $request, MarketService $markets): RedirectResponse
    {
        $request->validate(['market_id' => 'required|integer']);

        $markets->setAdminMarket((int) $request->input('market_id'));

        return back();
    }


    public function index(): View
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::MARKET_VIEW()), 403);
        $columns = [
            ['data' => 'id',            'name' => 'id',            'title' => __('labels.id')],
            ['data' => 'code',          'name' => 'code',          'title' => __('labels.code')],
            ['data' => 'name',          'name' => 'name',          'title' => __('labels.name')],
            ['data' => 'currency_code', 'name' => 'currency_code', 'title' => __('labels.currency')],
            ['data' => 'countries',     'name' => 'countries',     'title' => __('labels.countries'), 'orderable' => false, 'searchable' => false],
            ['data' => 'is_default',    'name' => 'is_default',    'title' => __('labels.default')],
            ['data' => 'status',        'name' => 'status',        'title' => __('labels.status')],
            ['data' => 'created_at',    'name' => 'created_at',    'title' => __('labels.created_at')],
            ['data' => 'action',        'name' => 'action',        'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ];

        return view('admin.markets.index', [
            'columns'          => $columns,
            'editPermission'   => $this->editPermission,
            'deletePermission' => $this->deletePermission,
            'createPermission' => $this->createPermission,
            'fx'               => $this->buildFxSummary(),
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::MARKET_VIEW()), 403);
        $draw         = (int) $request->get('draw');
        $start        = (int) $request->get('start', 0);
        $length       = (int) $request->get('length', 10);
        $searchValue  = $request->get('search')['value'] ?? '';
        $orderIndex   = $request->get('order')[0]['column'] ?? 0;
        $orderDir     = strtolower((string) ($request->get('order')[0]['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $sortable = ['id', 'code', 'name', 'currency_code', null, 'is_default', 'status', 'created_at'];
        $orderBy  = $sortable[$orderIndex] ?? null ?: 'priority';

        $baseCode = strtoupper((string) (Currency::query()->where('is_base', true)->value('code') ?? ''));
        $query = Market::query()->with([
            'countries:id,iso2,name',
            'rates' => fn ($rates) => $rates
                ->select(['id', 'market_id', 'to_currency'])
                ->where('from_currency', $baseCode)
                ->whereNull('valid_until'),
        ]);

        $total = (clone $query)->count();

        if ($searchValue !== '') {
            $query->where(function ($q) use ($searchValue) {
                $q->where('code', 'like', "%{$searchValue}%")
                    ->orWhere('name', 'like', "%{$searchValue}%")
                    ->orWhere('currency_code', 'like', "%{$searchValue}%");
            });
        }

        $filtered = $searchValue !== '' ? (clone $query)->count() : $total;

        $activeCount = Market::query()->where('status', MarketStatusEnum::ACTIVE())->count();

        $data = $query
            ->orderBy($orderBy, $orderDir === 'desc' ? 'desc' : 'asc')
            ->skip($start)
            ->take($length)
            ->get()
            ->map(function (Market $market) use ($activeCount, $baseCode) {
                $isLastActive  = $activeCount <= 1 && $market->status === MarketStatusEnum::ACTIVE();
                $canDeleteRow  = $this->deletePermission && ! $market->is_default && ! $isLastActive;

                $marketCurrency = strtoupper((string) $market->currency_code);
                $needsFxRate    = $baseCode !== '' && $marketCurrency !== $baseCode;
                $hasFxRate = ! $needsFxRate || $market->rates->contains('to_currency', $marketCurrency);

                $currencyCell = e($market->currency_code);
                if ($needsFxRate && ! $hasFxRate) {
                    $currencyCell .= ' <span class="badge bg-yellow-lt ms-1" title="'
                        . e(__('labels.fx_missing_rate_hint'))
                        . '">' . e(__('labels.fx_missing_rate_badge')) . '</span>';
                }

                return [
                    'id'            => $market->id,
                    'code'          => e($market->code),
                    'name'          => e($market->name),
                    'currency_code' => $currencyCell,
                    'countries'     => e($market->countries->pluck('iso2')->implode(', ')) ?: '—',
                    'is_default'    => $market->is_default
                        ? '<span class="badge bg-success-lt">' . __('labels.default') . '</span>'
                        : '<span class="badge bg-secondary-lt">—</span>',
                    'status'        => view('partials.status', ['status' => $market->status])->render(),
                    'created_at'    => $market->created_at?->format('Y-m-d'),
                    'action'        => view('partials.actions', [
                        'modelName'        => 'market',
                        'id'               => $market->id,
                        'title'            => $market->name,
                        'mode'             => 'page_view',
                        'route'            => route('admin.markets.edit', $market->id),
                        'editPermission'   => $this->editPermission,
                        'deletePermission' => $canDeleteRow,
                    ])->render(),
                ];
            })
            ->toArray();

        return response()->json([
            'draw'            => $draw,
            'recordsTotal'    => $total,
            'recordsFiltered' => $filtered,
            'data'            => $data,
        ]);
    }


    public function create(LanguageService $languages): View
    {
        abort_unless($this->createPermission, 403);

        return view('admin.markets.form', [
            'market'        => null,
            'selected'      => [],
            'statuses'      => MarketStatusEnum::values(),
            'languages'     => $languages->active(),
            'mustBeDefault' => ! Market::query()->where('is_default', true)->exists(),
        ]);
    }

    public function store(StoreMarketRequest $request, FxRateService $fx): JsonResponse
    {
        abort_unless($this->createPermission, 403);

        try {
            DB::beginTransaction();

            $data = $request->validated();

            $existingDefaultExists = Market::query()->where('is_default', true)->exists();
            $newIsDefault          = ! $existingDefaultExists;

            $market = Market::create(array_merge([
                'code'             => $data['code'],
                'name'             => $data['name'],
                'currency_code'    => $data['currency_code'],
                'default_language' => $data['default_language'],
                'status'           => $data['status'] ?? MarketStatusEnum::ACTIVE(),
                'priority'         => $data['priority'] ?? 0,
                'is_default'       => $newIsDefault,
                'metadata'         => $data['metadata'] ?? null,
                'platform_fee'     => $data['platform_fee'] ?? 0,
                'cod_fee'          => $data['cod_fee'] ?? 0,
                'max_cod_amount'   => $data['max_cod_amount'] ?? 0,
            ], $this->numberFormatPayload($data)));

            $this->syncDefault($market);
            $this->syncCountries($market, $data['country_iso2']);

            DB::commit();

            $fxNote = $this->safelyRefreshFx($fx, $market);

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('messages.market_created') . $fxNote,
                data: ['id' => $market->id],
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('MarketController@store failed', ['exception' => $e]);

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('messages.market_create_failed'),
                data: ['error' => $e->getMessage()],
            );
        }
    }


    public function edit(int $id, LanguageService $languages): View
    {
        abort_unless($this->editPermission, 403);

        $market = Market::with(['countries:id,iso2', 'taxClasses:id,title,market_id', 'taxClasses.taxRates:id,title,rate'])->findOrFail($id);

        return view('admin.markets.form', [
            'market'        => $market,
            'selected'      => $market->countries->pluck('iso2')->all(),
            'statuses'      => MarketStatusEnum::values(),
            'languages'     => $languages->active(),
            'taxClasses'    => $market->taxClasses,
            'mustBeDefault' => ! Market::query()
                ->where('id', '!=', $market->id)
                ->where('is_default', true)
                ->exists(),
        ]);
    }

    public function update(UpdateMarketRequest $request, int $id, FxRateService $fx): JsonResponse
    {
        abort_unless($this->editPermission, 403);

        try {
            DB::beginTransaction();

            $market           = Market::findOrFail($id);
            $previousCurrency = strtoupper((string) $market->currency_code);
            $data             = $request->validated();

            $newStatus = $data['status'] ?? $market->status;

            $newIsDefault = array_key_exists('is_default', $data)
                ? (bool) $data['is_default']
                : (bool) $market->is_default;

            if ($market->is_default && !$newIsDefault) {
                $newIsDefault = true;
            }

            if ($newIsDefault && $newStatus === MarketStatusEnum::INACTIVE()) {
                DB::rollBack();
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.market_default_must_be_active'),
                    data: [],
                    status: 422
                );
            }

            if (
                $market->status === MarketStatusEnum::ACTIVE()
                && $newStatus === MarketStatusEnum::INACTIVE()
                && $this->isLastActiveMarket($market->id)
            ) {
                DB::rollBack();
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.market_last_active_cannot_change'),
                    data: [],
                );
            }

            $isActivating = $market->status !== MarketStatusEnum::ACTIVE()
                && $newStatus === MarketStatusEnum::ACTIVE();

            if ($isActivating) {
                $baseCode    = strtoupper((string) (Currency::query()->where('is_base', true)->value('code') ?? ''));

                if ($baseCode === '') {
                    DB::rollBack();

                    return ApiResponseType::sendJsonResponse(
                        success: false,
                        message: __('labels.fx_base_currency_missing'),
                        data: [],
                    );
                }

                $targetCode  = strtoupper((string) ($data['currency_code'] ?? $market->currency_code));
                $needsRate   = $targetCode !== $baseCode;
                $hasRate     = $needsRate
                    ? MarketCurrencyRate::query()
                        ->forMarket($market->id)
                        ->forPair($baseCode, $targetCode)
                        ->whereNull('valid_until')
                        ->exists()
                    : true;

                if ($needsRate && ! $hasRate) {
                    DB::rollBack();
                    return ApiResponseType::sendJsonResponse(
                        success: false,
                        message: __('messages.market_activate_blocked_no_fx', [
                            'from' => $baseCode,
                            'to'   => $targetCode,
                        ]),
                        data: [],
                    );
                }
            }

            $market->fill(array_merge([
                'code'             => $data['code'],
                'name'             => $data['name'],
                'currency_code'    => $data['currency_code'],
                'default_language' => $data['default_language'],
                'status'           => $newStatus,
                'priority'         => $data['priority'] ?? 0,
                'is_default'       => $newIsDefault,
                'metadata'         => $data['metadata'] ?? $market->metadata,
                'platform_fee'     => $data['platform_fee'] ?? 0,
                'cod_fee'          => $data['cod_fee'] ?? 0,
                'max_cod_amount'   => $data['max_cod_amount'] ?? 0,
            ], $this->numberFormatPayload($data)))->save();

            $this->syncDefault($market);
            $this->syncCountries($market, $data['country_iso2']);

            DB::commit();

            $currencyChanged = strtoupper((string) $market->currency_code) !== $previousCurrency;
            $fxNote = $currencyChanged ? $this->safelyRefreshFx($fx, $market) : '';

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('messages.market_updated') . $fxNote,
                data: ['id' => $market->id],
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('messages.market_not_found'),
                data: [],
            );
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('MarketController@update failed', ['exception' => $e]);

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('messages.market_update_failed'),
                data: ['error' => $e->getMessage()],
            );
        }
    }


    public function destroy(int $id): JsonResponse
    {
        abort_unless($this->deletePermission, 403);

        try {
            $market = Market::findOrFail($id);
            if (
                $this->isLastActiveMarket($market->id)
                && $market->status === MarketStatusEnum::ACTIVE()
            ) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.market_last_active_cannot_change'),
                    data: [],
                );
            }

            if ($market->is_default) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.market_default_cannot_delete'),
                    data: [],
                );
            }

            $hasOrders = $market->orders()->exists();
            if ($hasOrders) {
                return ApiResponseType::sendJsonResponse(
                    success: false,
                    message: __('messages.market_in_use_orders'),
                    data: [],
                );
            }

            DB::transaction(function () use ($market) {
                $market->countries()->detach();
                $market->delete();
            });

            return ApiResponseType::sendJsonResponse(
                success: true,
                message: __('messages.market_deleted'),
                data: [],
            );
        } catch (ModelNotFoundException) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('messages.market_not_found'),
                data: [],
            );
        } catch (\Throwable $e) {
            Log::error('MarketController@destroy failed', ['exception' => $e]);

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('messages.market_delete_failed'),
                data: ['error' => $e->getMessage()],
            );
        }
    }


    /**
     * Search endpoint for the reusable <x-tom-select-market> component.
     * Returns a flat array of {value, text, code, name} rows.
     *
     *   ?search=ind            substring on code/name
     *   ?only_active=1         filter on status (default true)
     *   ?find=IN               exact lookup by code (single)
     *   ?find_in[]=1&find_in[]=2   bulk lookup by id (for pre-hydrate)
     *
     * Open to any authenticated admin or seller — markets are already public
     * via /api/markets; this lighter endpoint is shaped for TomSelect.
     */
    public function searchTom(Request $request): JsonResponse
    {
        $onlyActive = $request->input('only_active', '1') === '1';
        $query      = (string) $request->input('search', '');
        $limit = max(1, min((int) $request->input('limit', 20), 50));

        $base = Market::query()
            ->when($onlyActive, fn ($q) => $q->where('status', MarketStatusEnum::ACTIVE()))
            ->orderByDesc('priority')
            ->orderBy('name');

        if ($request->filled('find')) {
            $rows = (clone $base)->where('code', strtoupper((string) $request->input('find')))->get();
        } elseif ($request->filled('find_in') && is_array($request->input('find_in'))) {
            $values = array_filter((array) $request->input('find_in'));
            $rows = (clone $base)
                ->where(function ($q) use ($values) {
                    $q->whereIn('id', array_filter($values, 'is_numeric'))
                        ->orWhereIn('code', array_map(fn ($v) => strtoupper((string) $v), $values));
                })
                ->get();
        } else {
            $rows = (clone $base)
                ->when($query !== '', function ($q) use ($query) {
                    $q->where(function ($sub) use ($query) {
                        $sub->where('code', 'like', "%{$query}%")
                            ->orWhere('name', 'like', "%{$query}%");
                    });
                })
                ->limit($limit)
                ->get();
        }

        return response()->json(
            $rows->map(fn (Market $m) => [
                'value' => (string) $m->id,
                'text'  => $m->code . ' — ' . $m->name,
                'code'  => $m->code,
                'name'  => $m->name,
            ])->values(),
        );
    }

    /**
     * Filter the validated payload down to the 7 per-market number-format
     * keys. Pulled into a helper so store() + update() share the same
     * normalisation — and so a future schema column can be added in one
     * place instead of two.
     *
     * Returns only keys that were actually present in $data — null/missing
     * keys fall through to the column defaults / existing row values rather
     * than getting clobbered to null.
     */
    protected function numberFormatPayload(array $data): array
    {
        $keys = [
            'symbol_position',
            'space_between_symbol',
            'thousand_separator',
            'decimal_separator',
            'grouping_style',
            'decimal_places',
            'negative_format',
        ];

        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $out[$key] = $data[$key];
            }
        }
        return $out;
    }

    /**
     * True when removing/deactivating $marketId would leave zero active markets.
     * Used by destroy() and update() to block last-market changes.
     */
    protected function isLastActiveMarket(int $marketId): bool
    {
        return Market::query()
            ->where('status', MarketStatusEnum::ACTIVE())
            ->where('id', '!=', $marketId)
            ->doesntExist();
    }

    /**
     * Refresh the FX rate for a single market without ever letting a provider
     * outage bubble up to the caller. Returns a short suffix that can be
     * appended to the success message (or "" if nothing useful to surface).
     */
    protected function safelyRefreshFx(FxRateService $fx, Market $market): string
    {
        try {
            $summary = $fx->refreshForMarket($market);
        } catch (\Throwable $e) {
            Log::warning('MarketController: FX refresh failed for market', [
                'market_id' => $market->id,
                'error'     => $e->getMessage(),
            ]);
            return ' ' . __('messages.fx_refresh_skipped_provider_down');
        }

        if (! empty($summary['missing'])) {
            return ' ' . __('messages.fx_refresh_missing_for_currency', [
                'currency' => $market->currency_code,
            ]);
        }

        return $summary['updated'] > 0
            ? ' ' . __('messages.fx_refresh_inline_updated')
            : '';
    }

    /**
     * If this market is being marked default, demote all others. Also keeps
     * the `default_market_id` Setting in sync so MarketService picks it up
     * on the next resolution.
     */
    protected function syncDefault(Market $market): void
    {
        if (! $market->is_default) {
            return;
        }

        Market::query()
            ->where('id', '!=', $market->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);

        Setting::query()->updateOrCreate(
            ['variable' => 'default_market_id'],
            ['value' => (string) $market->id],
        );
    }

    /**
     * Replace the market_country pivot rows for this market.
     *
     * @param  array<int, string>  $iso2s
     */
    protected function syncCountries(Market $market, array $iso2s): void
    {
        $iso2s = array_values(array_unique(array_map('strtoupper', $iso2s)));
        $now = now();

        DB::table('market_country')->where('market_id', $market->id)->delete();

        if (empty($iso2s)) {
            return;
        }

        $rows = array_map(fn ($iso2) => [
            'market_id'    => $market->id,
            'country_iso2' => $iso2,
            'created_at'   => $now,
            'updated_at'   => $now,
        ], $iso2s);

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('market_country')->insert($chunk);
        }

        $geo = app(GeoImportService::class);
        $geo->ensureCountries($iso2s);
        $geo->ensurePincodes($iso2s);
    }


    /**
     * GET fragment used by the markets index page after a manual refresh —
     * returns the rendered partial HTML so the page can swap it in without
     * a full reload (avoids duplicating markup in JS).
     */
    public function fxRates(): JsonResponse
    {
        abort_unless($this->hasPermission(AdminPermissionEnum::MARKET_VIEW()), 403);
        $fx = $this->buildFxSummary();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('messages.fx_rates_loaded'),
            data: [
                'summary' => $fx,
                'html'    => view('admin.markets.partials.fx-rates-table', ['fx' => $fx])->render(),
            ],
        );
    }

    /**
     * Trigger a synchronous FX refresh from the admin UI. Same code path as
     * the scheduled fx:refresh-rates command — the service snapshots one row
     * per active market into market_currency_rates.
     *
     * Gated on MARKET_EDIT permission to match the rest of the markets UX.
     */
    public function fxRefresh(FxRateService $fx): JsonResponse
    {
        abort_unless($this->editPermission, 403);

        try {
            $summary = $fx->refresh();
        } catch (\Throwable $e) {
            Log::error('MarketController@fxRefresh failed', ['exception' => $e]);

            return ApiResponseType::sendJsonResponse(
                success: false,
                message: __('messages.fx_refresh_failed'),
                data: ['error' => $e->getMessage()],
            );
        }

        $fx = $this->buildFxSummary();

        return ApiResponseType::sendJsonResponse(
            success: true,
            message: __('messages.fx_refresh_succeeded', [
                'updated' => $summary['updated'],
                'base'    => $summary['base'],
            ]),
            data: [
                'summary'         => $summary,
                'html'            => view('admin.markets.partials.fx-rates-table', ['fx' => $fx])->render(),
                'last_updated_at' => $fx['last_updated_at'],
            ],
        );
    }

    /**
     * Snapshot of the current FX state used by both the index render and the
     * fxRates() AJAX endpoint. Keeps a single source of truth so the markup
     * after a refresh matches the markup on initial page load.
     */
    protected function buildFxSummary(): array
    {
        $base = Currency::query()->where('is_base', true)->first();

        $rows = MarketCurrencyRate::query()
            ->with(['market:id,code,name,currency_code'])
            ->whereNull('valid_until')
            ->whereHas('market', fn ($q) => $q->where('status', MarketStatusEnum::ACTIVE()))
            ->orderBy('market_id')
            ->get();

        $rates = $rows->map(fn (MarketCurrencyRate $r) => [
            'market_id'     => $r->market_id,
            'market_code'   => $r->market?->code,
            'market_name'   => $r->market?->name,
            'from_currency' => $r->from_currency,
            'to_currency'   => $r->to_currency,
            'rate'          => (float) $r->rate,
            'valid_from'    => $r->valid_from?->toDateString(),
        ])->values();

        $coveredMarketIds = $rows->pluck('market_id')->all();
        $pending = Market::query()
            ->active()
            ->whereNotIn('id', $coveredMarketIds)
            ->get(['id', 'code', 'name', 'currency_code']);

        return [
            'base_currency'    => $base?->code,
            'base_symbol'      => $base?->symbol,
            'provider'         => config('services.fx.default', 'frankfurter'),
            'rates'            => $rates,
            'pending'          => $pending->map(fn ($m) => [
                'market_id'   => $m->id,
                'market_code' => $m->code,
                'market_name' => $m->name,
                'currency'    => $m->currency_code,
            ])->values(),
            'last_updated_at'  => $rows->max('updated_at')?->toIso8601String(),
        ];
    }
}
