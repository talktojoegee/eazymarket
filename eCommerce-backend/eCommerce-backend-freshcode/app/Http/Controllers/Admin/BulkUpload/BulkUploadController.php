<?php

namespace App\Http\Controllers\Admin\BulkUpload;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkUpload\ConfirmBulkUploadRequest;
use App\Http\Requests\BulkUpload\PreviewBulkUploadRequest;
use App\Interfaces\BulkImportable;
use App\Interfaces\GroupedBulkImportable;
use App\Models\Brand;
use App\Models\BulkUploadJob;
use App\Models\BulkUploadPreviewSession;
use App\Models\Category;
use App\Models\GlobalProductAttribute;
use App\Models\Seller;
use App\Models\ShippingProfile;
use App\Models\StoreLocation;
use App\Models\TaxClass;
use App\Services\BulkUpload\BulkUploadJobService;
use App\Services\BulkUpload\BulkUploadModuleConfig;
use App\Services\BulkUpload\BulkUploadModuleRegistry;
use App\Services\BulkUpload\ProductBulkImportService;
use App\Services\BulkUpload\ProductBulkPreviewService;
use App\Services\CurrencyService;
use App\Services\DatatableExportRegistry;
use App\Types\Api\ApiResponseType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BulkUploadController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly BulkUploadModuleRegistry $registry,
        private readonly BulkUploadJobService $jobService,
        private readonly DatatableExportRegistry $exportRegistry,
        private readonly ProductBulkPreviewService $productPreviewService,
        private readonly CurrencyService $currencyService,
    ) {}

    public function page(string $module, Request $request): View
    {
        $config = $this->registry->get($module);
        $panel = $this->panel($request);
        $this->ensurePanelSupported($config, $panel);
        $this->authorize('create', $config->modelClass);
        $importer = $this->importer($config);
        $sellerId = $this->sellerId($config, $request, false);

        return view('admin.bulk-upload.upload', [
            'moduleConfig' => $config,
            'availableFields' => $importer->availableFields(),
            'availableMatchFields' => $importer->availableMatchFields(),
            'activeJob' => $sellerId ? $this->jobService->findActiveJob($config->modelClass, $sellerId) : null,
            'panelName' => $panel,
            'sellerId' => $sellerId,
            'canExport' => auth()->user() ? $this->exportRegistry->can(auth()->user(), $module, $panel) : false,
            'currencyCode' => $this->currencyService->getCode(),
            'currencySymbol' => $this->currencyService->getSymbol(),
            'sellers' => $panel === 'admin' && $config->sellerScoped
                ? Seller::query()->with('user:id,name,email')->whereKey($sellerId)->get()
                : collect(),
        ]);
    }

    public function preview(string $module, PreviewBulkUploadRequest $request): JsonResponse
    {
        try {
            $config = $this->registry->get($module);
            $panel = $this->panel($request);
            $this->ensurePanelSupported($config, $panel);
            $this->authorize('create', $config->modelClass);
            $sellerId = $this->sellerId($config, $request);

            if ($active = $this->jobService->findActiveJob($config->modelClass, $sellerId)) {
                return $this->lockoutResponse($module, $active, $panel);
            }

            $stored = $this->jobService->storeUploadedFile($request->file('csv_file'));
            $preview = $this->jobService->previewCsv(
                $stored['disk'],
                $stored['path'],
                (int) config('bulk_upload.preview_rows', 50),
            );

            if ($preview['headers'] === []) {
                Storage::disk($stored['disk'])->deleteDirectory(dirname($stored['path']));

                return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_csv_no_headers', [], 422);
            }

            if ($preview['total_rows'] === 0) {
                Storage::disk($stored['disk'])->deleteDirectory(dirname($stored['path']));

                return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_csv_no_data_rows', [], 422);
            }

            $mediaPreviews = $this->jobService->mediaPreviewData(
                $preview['headers'],
                $preview['rows'],
                $config->mediaFields ?: ['logo_url', 'image_url', 'banner_url'],
            );
            $importer = $this->importer($config);
            $groupedSummary = $importer instanceof GroupedBulkImportable && $module !== 'products'
                ? $this->jobService->groupedCsvSummary($stored['disk'], $stored['path'])
                : [];
            $primaryMediaField = $config->primaryMediaField ?? ($module === 'brands' ? 'logo_url' : 'image_url');
            if ($module === 'products') {
                $normalizedHeaders = array_map(fn ($header) => Str::snake((string) $header), $preview['headers']);
                if (! in_array('main_image_url', $normalizedHeaders, true) && in_array('image_src', $normalizedHeaders, true)) {
                    $primaryMediaField = 'image_src';
                }
            }

            $previewSession = null;
            $suggestedMapping = [];
            if ($module === 'products' && $importer instanceof ProductBulkImportService) {
                $suggestedMapping = $importer->suggestedMapping($preview['headers']);
                $previewSession = $this->productPreviewService->createSession(
                    $stored,
                    $preview['headers'],
                    $panel,
                    $sellerId,
                    auth()->id(),
                    $preview['total_rows'],
                );
            }
            $suggestedOperation = 'create';
            $suggestedMatchField = null;
            if ($module === 'products' && ! empty($suggestedMapping['product_id'])) {
                $productIdIndex = array_search($suggestedMapping['product_id'], $preview['headers'], true);
                $hasProductIds = $productIdIndex !== false && collect($preview['rows'])
                    ->contains(fn (array $row) => trim((string) ($row[$productIdIndex] ?? '')) !== '');
                if ($hasProductIds) {
                    $suggestedOperation = 'update';
                    $suggestedMatchField = 'product_id';
                }
            }

            return ApiResponseType::sendJsonResponse(true, 'labels.bulk_upload_preview_ready', [
                'preview_token' => $stored['token'],
                'original_filename' => $stored['original_filename'],
                'headers' => $preview['headers'],
                'rows' => $preview['rows'],
                'total_rows' => $preview['total_rows'],
                'ignored_blank_rows' => $preview['ignored_blank_rows'],
                'media_previews' => $mediaPreviews,
                'suggested_mapping' => $suggestedMapping,
                'suggested_operation' => $suggestedOperation,
                'suggested_match_field' => $suggestedMatchField,
                'preview_index' => $previewSession ? $this->productPreviewService->summary($previewSession) : null,
                'missing_primary_media' => $module === 'products'
                    ? ['total_count' => 0, 'entries' => [], 'truncated' => false]
                    : ($importer instanceof GroupedBulkImportable
                    ? $this->jobService->groupedMissingPrimaryMedia($stored['disk'], $stored['path'], $primaryMediaField, (int) config('bulk_upload.missing_media_entries', 50))
                    : $this->jobService->missingPrimaryMedia($stored['disk'], $stored['path'], $primaryMediaField, (int) config('bulk_upload.missing_media_entries', 50))),
                'grouped_summary' => $groupedSummary,
            ]);
        } catch (AuthorizationException) {
            return ApiResponseType::sendJsonResponse(false, 'labels.permission_denied', [], 403);
        } catch (ValidationException $exception) {
            return ApiResponseType::sendJsonResponse(false, $exception->validator->errors()->first(), $exception->errors(), 422);
        } catch (\Throwable $exception) {
            Log::error('Bulk upload preview failed.', ['module' => $module, 'exception' => $exception]);

            return ApiResponseType::sendJsonResponse(false, 'labels.something_went_wrong', [], 500);
        }
    }

    public function confirm(string $module, ConfirmBulkUploadRequest $request): JsonResponse
    {
        $config = $this->registry->get($module);
        $panel = $this->panel($request);
        $this->ensurePanelSupported($config, $panel);
        $this->authorize('create', $config->modelClass);
        $sellerId = $this->sellerId($config, $request);
        $lock = Cache::lock('bulk-upload:confirm:'.$config->modelClass.':'.($sellerId ?? 'global'), 15);

        if (! $lock->get()) {
            return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_already_in_progress', [], 409);
        }

        try {
            if ($active = $this->jobService->findActiveJob($config->modelClass, $sellerId)) {
                return $this->lockoutResponse($module, $active, $panel);
            }

            $validated = $request->validated();
            $disk = (string) config('bulk_upload.file_disk', 'local');
            $path = $this->resolveStoredCsv($disk, (string) $validated['preview_token']);
            if (! $path) {
                return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_file_not_found', [], 422);
            }

            $headers = $this->jobService->readHeaders($disk, $path);
            if (! $headers) {
                return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_csv_no_headers', [], 422);
            }

            $importer = $this->importer($config);
            $operation = strtolower((string) ($validated['operation'] ?? 'create'));
            $matchField = $validated['match_field'] ?? null;
            if ($operation === 'create') {
                try {
                    $importer->validateMapping($validated['mapping'], $headers);
                } catch (\InvalidArgumentException $exception) {
                    return ApiResponseType::sendJsonResponse(false, $exception->getMessage(), [], 422);
                }
            } elseif (collect($validated['mapping'])->filter()->contains(fn ($header) => ! in_array($header, $headers, true))) {
                return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_unknown_csv_column', [], 422);
            }

            if ($operation === 'update') {
                $allowed = collect($importer->availableMatchFields())->pluck('key')->all();
                if (! in_array($matchField, $allowed, true)) {
                    return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_invalid_match_field', [], 422);
                }
                if (empty($validated['mapping'][$matchField])) {
                    return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_match_column_missing', [], 422);
                }
            }

            $totalRows = $module === 'products' ? 0 : $this->jobService->countDataRows($disk, $path);
            $groupedSummary = $importer instanceof GroupedBulkImportable && $module !== 'products'
                ? $this->jobService->groupedCsvSummary($disk, $path)
                : [];
            if (! empty($groupedSummary['non_contiguous'])) {
                return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_product_non_contiguous_handles', [
                    'entries' => $groupedSummary['non_contiguous'],
                ], 422);
            }
            $previewSession = null;
            if ($module === 'products') {
                $previewSession = $this->previewSession((string) $validated['preview_token'], $request, $config);
                $expectedHash = $this->productPreviewService->mappingHash($validated['mapping'], $operation, $matchField);
                if ($previewSession->mapping_hash !== $expectedHash || $previewSession->status !== 'completed') {
                    return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_product_preview_stale', [], 422);
                }
                $groupedSummary = [
                    'total_records' => $previewSession->product_count,
                    'variant_count' => $previewSession->variant_count,
                    'location_count' => $previewSession->location_count,
                ];
                $totalRows = $previewSession->total_rows;
            }
            $totalRecords = $importer instanceof GroupedBulkImportable ? (int) ($groupedSummary['total_records'] ?? 0) : $totalRows;
            $job = $this->jobService->createPendingJob(
                uploadableType: $config->modelClass,
                userId: auth()->id(),
                disk: $disk,
                path: $path,
                originalFilename: (string) $validated['original_filename'],
                columnMapping: $validated['mapping'],
                defaultValues: $validated['defaults'] ?? [],
                totalRows: $totalRows,
                operation: $operation,
                matchField: $matchField,
                notifyOnFinish: (bool) ($validated['notify_on_finish'] ?? false),
                panel: $panel,
                sellerId: $sellerId,
                totalRecords: $totalRecords,
            );
            if ($previewSession) {
                $this->productPreviewService->cleanup($previewSession);
            }

            return ApiResponseType::sendJsonResponse(true, 'labels.bulk_upload_queued', [
                'job_id' => $job->id,
                'total_rows' => $job->total_rows,
                'redirect_url' => route("{$panel}.{$module}.bulk-upload.jobs.show", $job),
            ]);
        } finally {
            $lock->release();
        }
    }

    public function template(string $module, Request $request)
    {
        $config = $this->registry->get($module);
        $panel = $this->panel($request);
        $this->ensurePanelSupported($config, $panel);
        $this->authorize('viewAny', $config->modelClass);
        $importer = $this->importer($config);
        $templateName = (string) $request->query('template', array_key_first($config->templates));
        if (! array_key_exists($templateName, $config->templates)) {
            abort(404);
        }
        $template = method_exists($importer, 'template') ? $importer->template($templateName) : $importer->csvTemplate();

        return response()->streamDownload(function () use ($template) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $template['columns']);
            foreach ($template['samples'] ?? [$template['sample']] as $sample) {
                fputcsv($output, $sample);
            }
            fclose($output);
        }, $module.'_'.$templateName.'_bulk_template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportCount(string $module, Request $request): JsonResponse
    {
        $config = $this->registry->get($module);
        $panel = $this->panel($request);
        $this->ensurePanelSupported($config, $panel);
        $this->authorize('viewAny', $config->modelClass);
        $this->exportRegistry->authorize(auth()->user(), $module, $panel);
        $importer = $this->importer($config);
        $model = $config->modelClass;
        $filters = (array) $request->query('filters', []);
        if ($config->sellerScoped) {
            $filters['seller_id'] = $this->sellerId($config, $request);
        }
        $query = $importer->applyExportFilters($model::query(), $filters);

        return ApiResponseType::sendJsonResponse(true, 'labels.fetched_successfully', ['count' => $query->count()]);
    }

    public function export(string $module, Request $request)
    {
        $config = $this->registry->get($module);
        $panel = $this->panel($request);
        $this->ensurePanelSupported($config, $panel);
        $this->authorize('viewAny', $config->modelClass);
        $this->exportRegistry->authorize(auth()->user(), $module, $panel);
        $importer = $this->importer($config);
        $model = $config->modelClass;
        $filters = (array) $request->query('filters', []);
        if ($config->sellerScoped) {
            $filters['seller_id'] = $this->sellerId($config, $request);
        }
        $query = $importer->applyExportFilters($model::query(), $filters);
        $columns = $importer->exportColumns();
        $filename = $module.'_export_'.now()->format('Y-m-d_Hi').'.csv';

        return response()->streamDownload(function () use ($query, $importer, $columns) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $columns);
            $query->orderBy('id')->chunkById(500, function ($rows) use ($output, $importer, $columns) {
                foreach ($rows as $row) {
                    $payloads = method_exists($importer, 'exportRows') ? $importer->exportRows($row) : [$importer->exportRow($row)];
                    foreach ($payloads as $payload) {
                        fputcsv($output, array_map(
                            fn (string $column) => $this->jobService->escapeSpreadsheetValue($payload[$column] ?? ''),
                            $columns,
                        ));
                    }
                }
            });
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function jobsIndex(string $module, Request $request): View
    {
        $config = $this->registry->get($module);
        $panel = $this->panel($request);
        $this->ensurePanelSupported($config, $panel);
        $this->authorize('viewAny', $config->modelClass);

        $columns = [
            ['data' => 'id', 'name' => 'id', 'title' => __('labels.id')],
            ['data' => 'original_filename', 'name' => 'original_filename', 'title' => __('labels.file')],
        ];
        if ($panel === 'admin' && $config->sellerScoped) {
            $columns[] = ['data' => 'seller', 'name' => 'seller_id', 'title' => __('labels.seller')];
        }
        $columns = array_merge($columns, [
            ['data' => 'status', 'name' => 'status', 'title' => __('labels.status')],
            ['data' => 'progress', 'name' => 'progress', 'title' => __('labels.bulk_upload_progress'), 'orderable' => false, 'searchable' => false],
            ['data' => 'success_count', 'name' => 'success_count', 'title' => __('labels.bulk_upload_success_count')],
            ['data' => 'failed_count', 'name' => 'failed_count', 'title' => __('labels.bulk_upload_failed_count')],
            ['data' => 'started_at', 'name' => 'started_at', 'title' => __('labels.started_at')],
            ['data' => 'finished_at', 'name' => 'finished_at', 'title' => __('labels.finished_at')],
            ['data' => 'action', 'name' => 'action', 'title' => __('labels.action'), 'orderable' => false, 'searchable' => false],
        ]);

        return view('admin.bulk-upload.jobs-index', [
            'moduleConfig' => $config,
            'canCreate' => auth()->user()?->can('create', $config->modelClass) ?? false,
            'columns' => $columns,
            'panelName' => $panel,
        ]);
    }

    public function jobsDatatable(string $module, Request $request): JsonResponse
    {
        $config = $this->registry->get($module);
        $panel = $this->panel($request);
        $this->ensurePanelSupported($config, $panel);
        $this->authorize('viewAny', $config->modelClass);
        $query = $this->jobQuery($config, $request);
        $total = (clone $query)->count();
        $search = trim((string) data_get($request->input('search'), 'value', ''));
        if ($search !== '') {
            $query->where(fn ($builder) => $builder
                ->where('original_filename', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%"));
        }
        $filtered = (clone $query)->count();
        $columns = ['id', 'original_filename'];
        if ($panel === 'admin' && $config->sellerScoped) {
            $columns[] = 'seller_id';
        }
        $columns = array_merge($columns, ['status', 'processed_rows', 'success_count', 'failed_count', 'started_at', 'finished_at']);
        $orderIndex = (int) data_get($request->input('order'), '0.column', 0);
        $direction = data_get($request->input('order'), '0.dir') === 'asc' ? 'asc' : 'desc';
        $length = min(max((int) $request->input('length', 10), 1), 100);

        $rows = $query
            ->orderBy($columns[$orderIndex] ?? 'id', $direction)
            ->skip(max((int) $request->input('start', 0), 0))
            ->take($length)
            ->with('seller.user:id,name,email')
            ->get()
            ->map(fn (BulkUploadJob $job) => [
                'id' => $job->id,
                'original_filename' => e($job->original_filename),
                'seller' => $job->seller_id ? '#'.$job->seller_id.' — '.e($job->seller?->user?->name ?: $job->seller?->user?->email) : '—',
                'status' => $this->statusBadge($job),
                'progress' => $this->progress($job),
                'success_count' => $job->success_count,
                'failed_count' => $job->failed_count,
                'started_at' => $job->started_at?->format('Y-m-d H:i'),
                'finished_at' => $job->finished_at?->format('Y-m-d H:i'),
                'action' => '<a class="btn btn-sm btn-outline-primary" href="'.e(route("{$panel}.{$module}.bulk-upload.jobs.show", $job)).'">'.e(__('labels.view')).'</a>',
            ]);

        return response()->json([
            'draw' => (int) $request->input('draw'),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $rows,
        ]);
    }

    public function jobShow(int|string $id, string $module, Request $request): View
    {
        $config = $this->registry->get($module);
        $panel = $this->panel($request);
        $this->ensurePanelSupported($config, $panel);
        $this->authorize('viewAny', $config->modelClass);
        $job = $this->jobQuery($config, $request)->findOrFail((int) $id);

        return view('admin.bulk-upload.job-show', [
            'moduleConfig' => $config,
            'job' => $job,
            'recentLog' => $this->jobService->recentLog($job->error_log),
            'panelName' => $panel,
        ]);
    }

    public function jobStatus(int|string $id, string $module): JsonResponse
    {
        $config = $this->registry->get($module);
        $panel = $this->panel(request());
        $this->ensurePanelSupported($config, $panel);
        $this->authorize('viewAny', $config->modelClass);
        $job = $this->jobQuery($config, request())->find((int) $id);
        if (! $job) {
            return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.fetched_successfully', [
            'id' => $job->id,
            'status' => $job->status?->value,
            'status_label' => __('labels.bulk_upload_status_'.($job->status?->value ?? 'pending')),
            'is_terminal' => $job->isTerminal(),
            'total_rows' => $job->total_rows,
            'processed_rows' => $job->processed_rows,
            'total_records' => $job->total_records,
            'processed_records' => $job->processed_records,
            'success_count' => $job->success_count,
            'updated_count' => $job->updated_count,
            'skipped_count' => $job->skipped_count,
            'failed_count' => $job->failed_count,
            'operation' => $job->operation?->value,
            'progress_percent' => $job->total_rows > 0 ? (int) floor($job->processed_rows / $job->total_rows * 100) : 0,
            'recent_log' => $this->jobService->recentLog($job->error_log),
            'started_at' => $job->started_at?->toDateTimeString(),
            'finished_at' => $job->finished_at?->toDateTimeString(),
            'has_failed_rows_artifact' => (bool) $job->failed_rows_path,
            'failed_rows_expires_label' => $job->failedRowsExpiryLabel(),
        ]);
    }

    public function cancel(int|string $id, string $module): JsonResponse
    {
        $config = $this->registry->get($module);
        $this->ensurePanelSupported($config, $this->panel(request()));
        $this->authorize('create', $config->modelClass);
        $job = $this->jobQuery($config, request())->find((int) $id);
        if (! $job) {
            return ApiResponseType::sendJsonResponse(false, 'labels.not_found', [], 404);
        }
        if ($job->isTerminal()) {
            return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_cannot_cancel_terminal', [], 409);
        }

        $this->jobService->appendLog($job, __('labels.bulk_upload_cancelled_by_user'));
        $this->jobService->markCancelled($job);

        return ApiResponseType::sendJsonResponse(true, 'labels.bulk_upload_cancelled', ['job_id' => $job->id]);
    }

    public function downloadErrors(int|string $id, string $module)
    {
        $config = $this->registry->get($module);
        $this->ensurePanelSupported($config, $this->panel(request()));
        $this->authorize('viewAny', $config->modelClass);
        $job = $this->jobQuery($config, request())->find((int) $id);
        if (! $job?->failed_rows_path) {
            abort(404, __('labels.bulk_upload_errors_not_available'));
        }

        $storage = Storage::disk($job->file_disk ?: config('bulk_upload.file_disk', 'local'));
        if (! $storage->exists($job->failed_rows_path)) {
            abort(404, __('labels.bulk_upload_errors_not_available'));
        }

        return $storage->download($job->failed_rows_path, $module.'_job_'.$job->id.'_errors.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function referenceCsv(string $type, string $module, Request $request)
    {
        abort_unless(in_array($type, ['categories-brands', 'attributes', 'locations', 'tax-classes', 'shipping-profiles'], true), 404, __('labels.bulk_upload_product_reference_invalid'));
        $config = $this->registry->get($module);
        $panel = $this->panel($request);
        $this->ensurePanelSupported($config, $panel);
        abort_unless($module === 'products', 404);
        $this->authorize('viewAny', $config->modelClass);
        $sellerId = $this->sellerId($config, $request);
        [$columns, $rows] = match ($type) {
            'categories-brands' => [
                ['type', 'id', 'name', 'parent_or_scope_id'],
                Category::query()->orderBy('title')->get()->map(fn ($item) => ['category', $item->id, $item->title, $item->parent_id])
                    ->concat(Brand::query()->orderBy('title')->get()->map(fn ($item) => ['brand', $item->id, $item->title, $item->scope_id])),
            ],
            'attributes' => [
                ['attribute_id', 'attribute_name', 'display_label', 'swatch_type', 'value_id', 'value_name', 'swatch_color', 'swatch_image_url', 'swatch_value'],
                GlobalProductAttribute::query()->where('seller_id', $sellerId)->with('values')->orderBy('title')->get()
                    ->flatMap(function ($attribute) {
                        $attribute->values->each(fn ($value) => $value->setRelation('attribute', $attribute));

                        return $attribute->values->map(fn ($value) => [
                            $attribute->id,
                            $attribute->title,
                            $attribute->label,
                            $attribute->swatche_type,
                            $value->id,
                            $value->title,
                            $value->swatch_color,
                            $value->swatch_image_url,
                            $value->swatche_value,
                        ]);
                    }),
            ],
            'locations' => [
                ['store_id', 'store_name', 'location_id', 'location_name', 'is_default'],
                StoreLocation::query()->whereHas('store', fn ($query) => $query->where('seller_id', $sellerId))->with('store')->orderBy('store_id')->orderBy('name')->get()
                    ->map(fn ($location) => [$location->store_id, $location->store?->name, $location->id, $location->name, $location->is_default ? 'yes' : 'no']),
            ],
            'tax-classes' => [
                ['tax_class_id', 'tax_class_name', 'market_id'],
                TaxClass::query()->whereHas('market.stores', fn ($query) => $query->where('stores.seller_id', $sellerId))->orderBy('title')->get()
                    ->map(fn ($taxClass) => [$taxClass->id, $taxClass->title, $taxClass->market_id]),
            ],
            'shipping-profiles' => [
                ['shipping_profile_id', 'shipping_profile_name', 'store_id', 'store_name'],
                ShippingProfile::query()->whereHas('store', fn ($query) => $query->where('seller_id', $sellerId))->with('store')->orderBy('name')->get()
                    ->map(fn ($profile) => [$profile->id, $profile->name, $profile->store_id, $profile->store?->name]),
            ],
            default => abort(404),
        };
        abort_if($rows->isEmpty(), 404, __('labels.bulk_upload_product_reference_unavailable'));

        return response()->streamDownload(function () use ($columns, $rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $columns);
            foreach ($rows as $row) {
                fputcsv($output, array_map([$this->jobService, 'escapeSpreadsheetValue'], (array) $row));
            }
            fclose($output);
        }, 'product_'.$type.'_references.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function buildProductPreview(string $token, string $module, Request $request): JsonResponse
    {
        $config = $this->registry->get($module);
        $this->ensurePanelSupported($config, $this->panel($request));
        $this->authorize('create', $config->modelClass);
        $validated = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'string'],
            'operation' => ['required', 'in:create,update'],
            'match_field' => ['nullable', 'string'],
        ]);
        $session = $this->previewSession($token, $request, $config);

        return ApiResponseType::sendJsonResponse(true, 'labels.bulk_upload_product_preview_building', $this->productPreviewService->build(
            $session,
            $validated['mapping'],
            $validated['operation'],
            $validated['match_field'] ?? null,
        ));
    }

    public function productPreviewProducts(string $token, string $module, Request $request): JsonResponse
    {
        $config = $this->registry->get($module);
        $this->ensurePanelSupported($config, $this->panel($request));
        $this->authorize('create', $config->modelClass);
        $session = $this->previewSession($token, $request, $config);

        return ApiResponseType::sendJsonResponse(true, 'labels.fetched_successfully', $this->productPreviewService->listing($session, $request->only([
            'page', 'per_page', 'search', 'issue', 'sort', 'direction',
        ])));
    }

    public function productPreviewDetail(string $token, int|string $previewProduct, string $module, Request $request): JsonResponse
    {
        $config = $this->registry->get($module);
        $this->ensurePanelSupported($config, $this->panel($request));
        $this->authorize('create', $config->modelClass);
        $session = $this->previewSession($token, $request, $config);
        $product = $session->products()->findOrFail((int) $previewProduct);

        return ApiResponseType::sendJsonResponse(true, 'labels.fetched_successfully', $this->productPreviewService->detail($product));
    }

    public function productPreviewAttributes(string $token, string $module, Request $request): JsonResponse
    {
        $config = $this->registry->get($module);
        $this->ensurePanelSupported($config, $this->panel($request));
        $this->authorize('create', $config->modelClass);
        $session = $this->previewSession($token, $request, $config);

        return ApiResponseType::sendJsonResponse(true, 'labels.fetched_successfully', $this->productPreviewService->attributes($session, $request->only([
            'page', 'per_page', 'search', 'status',
        ])));
    }

    public function destroyProductPreview(string $token, string $module, Request $request): JsonResponse
    {
        $config = $this->registry->get($module);
        $this->ensurePanelSupported($config, $this->panel($request));
        $this->authorize('create', $config->modelClass);
        $session = $this->previewSession($token, $request, $config);
        $this->productPreviewService->cleanup($session, true);

        return ApiResponseType::sendJsonResponse(true, 'labels.bulk_upload_product_preview_removed');
    }

    private function importer(BulkUploadModuleConfig $config): BulkImportable
    {
        return app($config->importerClass);
    }

    private function previewSession(string $token, Request $request, BulkUploadModuleConfig $config): BulkUploadPreviewSession
    {
        $session = BulkUploadPreviewSession::query()->where('token', $token)->firstOrFail();
        abort_if($session->expires_at->isPast(), 410, __('labels.bulk_upload_product_preview_expired'));
        abort_unless((int) $session->user_id === (int) auth()->id(), 403, __('labels.permission_denied'));
        abort_unless($session->panel === $this->panel($request), 403, __('labels.permission_denied'));
        if ($config->sellerScoped) {
            abort_unless((int) $session->seller_id === (int) $this->sellerId($config, $request), 403, __('labels.permission_denied'));
        }

        return $session;
    }

    private function resolveStoredCsv(string $disk, string $token): ?string
    {
        if (! Str::isUuid($token)) {
            return null;
        }

        $directory = trim((string) config('bulk_upload.temp_dir', 'bulk-uploads'), '/').'/'.$token;
        $files = Storage::disk($disk)->files($directory);

        return collect($files)->first(fn (string $file) => strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'csv');
    }

    private function lockoutResponse(string $module, BulkUploadJob $job, string $panel): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(false, 'labels.bulk_upload_already_in_progress', [
            'active_job_id' => $job->id,
            'redirect_url' => route("{$panel}.{$module}.bulk-upload.jobs.show", $job),
        ], 409);
    }

    private function panel(Request $request): string
    {
        return $request->is('seller/*') ? 'seller' : 'admin';
    }

    private function ensurePanelSupported(BulkUploadModuleConfig $config, string $panel): void
    {
        abort_unless(in_array($panel, $config->panels, true), 404);
    }

    private function sellerId(BulkUploadModuleConfig $config, Request $request, bool $required = true): ?int
    {
        if (! $config->sellerScoped) {
            return null;
        }
        $sellerId = $this->panel($request) === 'seller'
            ? (int) (auth()->user()?->seller()?->id ?? 0)
            : (int) $request->input('seller_id', $request->query('seller_id', 0));
        if ($required && $sellerId < 1) {
            throw ValidationException::withMessages(['seller_id' => __('labels.bulk_upload_product_seller_required')]);
        }

        return $sellerId > 0 ? $sellerId : null;
    }

    private function jobQuery(BulkUploadModuleConfig $config, Request $request)
    {
        $query = BulkUploadJob::query()->forType($config->modelClass);
        if ($config->sellerScoped && $this->panel($request) === 'seller') {
            $query->where('seller_id', $this->sellerId($config, $request));
        }

        return $query;
    }

    private function statusBadge(BulkUploadJob $job): string
    {
        $value = $job->status?->value ?? 'pending';
        $class = match ($value) {
            'completed' => 'bg-success-lt',
            'processing' => 'bg-info-lt',
            'failed' => 'bg-danger-lt',
            'cancelled' => 'bg-warning-lt',
            default => 'bg-secondary-lt',
        };

        return '<span class="badge '.$class.'">'.e(__('labels.bulk_upload_status_'.$value)).'</span>';
    }

    private function progress(BulkUploadJob $job): string
    {
        $percent = $job->total_rows > 0 ? min(100, (int) floor($job->processed_rows / $job->total_rows * 100)) : 0;

        return '<div class="progress" style="height:6px"><div class="progress-bar" style="width:'.$percent.'%"></div></div>'
            .'<div class="small text-muted">'.$job->processed_rows.' / '.$job->total_rows.'</div>';
    }
}
