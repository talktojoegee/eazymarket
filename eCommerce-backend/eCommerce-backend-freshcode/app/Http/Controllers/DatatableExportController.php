<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDatatableExport;
use App\Models\DatatableExport;
use App\Models\User;
use App\Services\DatatableExportRegistry;
use App\Services\DatatableExportService;
use App\Services\MarketService;
use App\Services\SellerStoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatatableExportController extends Controller
{
    public function estimate(Request $request, DatatableExportRegistry $registry, DatatableExportService $exports): JsonResponse
    {
        [$panel, $user, $validated] = $this->validatedPayload($request, $registry);
        $count = $exports->count($validated['module'], $panel, $user, $validated['parameters'], $validated['scope']);

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $count,
                'queued' => $count > (int) config('datatable-exports.sync_limit', 1000),
            ],
        ]);
    }

    public function store(Request $request, DatatableExportRegistry $registry, DatatableExportService $exports): JsonResponse
    {
        [$panel, $user, $validated] = $this->validatedPayload($request, $registry);
        $count = $exports->count($validated['module'], $panel, $user, $validated['parameters'], $validated['scope']);

        $export = DatatableExport::create([
            'user_id' => $user->id,
            'panel' => $panel,
            'module' => $validated['module'],
            'format' => $validated['format'],
            'scope' => $validated['scope'],
            'columns' => $validated['columns'],
            'parameters' => $validated['parameters'],
            'status' => 'pending',
            'total_rows' => $count,
            'disk' => config('datatable-exports.disk', 'local'),
        ]);

        if ($count > (int) config('datatable-exports.sync_limit', 1000)) {
            GenerateDatatableExport::dispatch($export->id);

            return response()->json([
                'success' => true,
                'message' => __('labels.export_queued'),
                'data' => ['status' => 'queued', 'export_id' => $export->id],
            ], 202);
        }

        try {
            $exports->generate($export);
        } catch (\Throwable $exception) {
            $export->update([
                'status' => 'failed',
                'failure_reason' => str($exception->getMessage())->limit(1000),
                'expires_at' => now()->addHours((int) config('datatable-exports.expires_after_hours', 24)),
            ]);
            report($exception);

            return response()->json(['success' => false, 'message' => __('labels.export_failed')], 500);
        }

        return response()->json([
            'success' => true,
            'message' => __('labels.export_ready'),
            'data' => [
                'status' => 'ready',
                'download_url' => route($panel.'.datatable-exports.download', $export),
            ],
        ]);
    }

    public function download(Request $request, DatatableExport $datatableExport, DatatableExportRegistry $registry): BinaryFileResponse
    {
        $panel = $this->panel($request);
        abort_unless((int) $datatableExport->user_id === (int) $request->user()?->id, 403);
        abort_unless($datatableExport->panel === $panel, 403);
        $registry->authorize($request->user(), $datatableExport->module, $panel);
        abort_unless($datatableExport->format === 'csv', 404);
        abort_unless($datatableExport->status === 'ready', 409, __('labels.export_not_ready'));
        abort_if(! $datatableExport->expires_at || $datatableExport->expires_at->isPast(), 410, __('labels.export_expired'));
        abort_unless($datatableExport->path && Storage::disk($datatableExport->disk)->exists($datatableExport->path), 404);

        return response()->download(
            Storage::disk($datatableExport->disk)->path($datatableExport->path),
            $datatableExport->filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    private function validatedPayload(Request $request, DatatableExportRegistry $registry): array
    {
        $panel = $this->panel($request);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $validated = $request->validate([
            'module' => ['required', 'string', 'max:80'],
            'scope' => ['required', Rule::in(['current_page', 'all_filtered', 'all_records', 'selected'])],
            'format' => ['required', Rule::in(['csv'])],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['required', 'string', 'max:80', 'distinct'],
            'search' => ['nullable', 'string', 'max:200'],
            'sort' => ['nullable', 'string', 'max:80'],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'start' => ['nullable', 'integer', 'min:0'],
            'length' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'selected_ids' => ['nullable', 'array'],
            'selected_ids.*' => ['integer', 'min:1', 'distinct'],
            'filters' => ['nullable', 'array'],
            'context' => ['nullable', 'array'],
            'context.variant' => ['nullable', Rule::in(['credits', 'debits', 'history', 'pending'])],
        ]);
        if ($validated['scope'] === 'selected' && empty($validated['selected_ids'])) {
            abort(422, __('labels.select_rows_to_export'));
        }

        $registry->authorize($user, $validated['module'], $panel);
        $definition = $registry->definition($validated['module'], $panel);
        $invalidColumns = array_diff($validated['columns'], array_keys($definition['columns']));
        if ($invalidColumns !== []) {
            abort(422, __('labels.invalid_export_columns'));
        }

        $validated['parameters'] = [
            'search' => trim((string) ($validated['search'] ?? '')),
            'sort' => $validated['sort'] ?? null,
            'direction' => $validated['direction'] ?? 'desc',
            'start' => (int) ($validated['start'] ?? 0),
            'length' => (int) ($validated['length'] ?? 10),
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'selected_ids' => $validated['selected_ids'] ?? [],
            'filters' => $this->filters($validated['module'], $validated['filters'] ?? []),
            'context' => $this->context($validated['module'], $panel, $user, $validated['context'] ?? []),
        ];

        return [$panel, $user, $validated];
    }

    private function context(string $module, string $panel, User $user, array $provided): array
    {
        $context = [];
        if ($panel === 'seller') {
            $context['seller_id'] = (int) ($user->seller()?->id ?? 0);
            $context['store_id'] = app(SellerStoreService::class)->selectedId();
        } elseif (in_array($module, ['orders', 'returns', 'earnings'], true)) {
            $context['market_id'] = app(MarketService::class)->adminSelectedId();
        }
        if (in_array($module, ['earnings', 'withdrawals'], true)) {
            $context['variant'] = $provided['variant'] ?? ($module === 'earnings' ? 'credits' : 'pending');
        }

        return $context;
    }

    private function filters(string $module, array $filters): array
    {
        $allowed = match ($module) {
            'orders' => ['status', 'payment_method', 'payment_status', 'lifecycle', 'fulfillment_method', 'order_type', 'store_id', 'range'],
            'products' => ['product_type', 'product_status', 'verification_status', 'product_filter', 'badge_id', 'category_id', 'store_id', 'seller_id'],
            'earnings' => ['date_range', 'store_id'],
            'withdrawals' => ['status', 'seller_ids'],
            'returns' => ['return_status'],
            default => [],
        };

        return collect($filters)->only($allowed)->filter(fn ($value) => is_scalar($value) && $value !== '')->all();
    }

    private function panel(Request $request): string
    {
        return $request->routeIs('seller.*') ? 'seller' : 'admin';
    }
}
