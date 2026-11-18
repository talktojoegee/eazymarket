<?php

namespace App\Services\BulkUpload;

use App\Models\BulkUploadPreviewSession;
use App\Models\BulkUploadProductPreview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductBulkPreviewService
{
    public function __construct(private readonly ProductBulkImportService $importer) {}

    public function createSession(array $stored, array $headers, string $panel, ?int $sellerId, ?int $userId, int $totalRows = 0): BulkUploadPreviewSession
    {
        $mapping = $this->importer->suggestedMapping($headers);

        return BulkUploadPreviewSession::updateOrCreate(['token' => $stored['token']], [
            'user_id' => $userId,
            'seller_id' => $sellerId,
            'panel' => $panel,
            'module' => 'products',
            'file_disk' => $stored['disk'],
            'file_path' => $stored['path'],
            'operation' => 'create',
            'match_field' => null,
            'column_mapping' => $mapping,
            'mapping_hash' => $this->mappingHash($mapping, 'create', null),
            'status' => 'pending',
            'byte_offset' => $this->headerOffset($stored['disk'], $stored['path']),
            'next_row_number' => 2,
            'total_rows' => $totalRows,
            'expires_at' => now()->addHours((int) config('bulk_upload.orphan_csv_hours', 6)),
        ]);
    }

    public function build(BulkUploadPreviewSession $session, array $mapping, string $operation, ?string $matchField): array
    {
        $headers = $this->headers($session);
        if ($operation === 'create') {
            $this->importer->validateMapping($mapping, $headers);
        } else {
            if (! in_array($matchField, ['product_id', 'handle'], true) || empty($mapping[$matchField])) {
                throw ValidationException::withMessages(['match_field' => __('labels.bulk_upload_match_column_missing')]);
            }
            if (collect($mapping)->filter()->contains(fn ($header) => ! in_array($header, $headers, true))) {
                throw ValidationException::withMessages(['mapping' => __('labels.bulk_upload_unknown_csv_column')]);
            }
        }
        $hash = $this->mappingHash($mapping, $operation, $matchField);
        if ($session->mapping_hash !== $hash) {
            $this->restart($session, $mapping, $operation, $matchField, $hash);
        }

        $lock = Cache::lock('bulk-upload:product-preview:'.$session->token, 30);
        if (! $lock->get()) {
            return $this->summary($session->fresh());
        }

        try {
            $session->refresh();
            if ($session->status === 'completed') {
                return $this->summary($session);
            }
            $session->update(['status' => 'building', 'failure_reason' => null]);
            $this->buildChunk($session);

            return $this->summary($session->fresh());
        } catch (\Throwable $exception) {
            $session->update(['status' => 'failed', 'failure_reason' => $exception->getMessage()]);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    public function listing(BulkUploadPreviewSession $session, array $filters): array
    {
        $requestedPageSize = (int) ($filters['per_page'] ?? 25);
        $perPage = in_array($requestedPageSize, [25, 50, 100], true) ? $requestedPageSize : 25;
        $query = $session->products();
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where('search_text', 'like', '%'.strtolower($search).'%');
        }
        match ($filters['issue'] ?? 'all') {
            'critical' => $query->where('critical_count', '>', 0),
            'warning' => $query->where('critical_count', 0)->where('warning_count', '>', 0),
            'ready' => $query->where('critical_count', 0)->where('warning_count', 0),
            default => null,
        };
        $allowedSorts = ['sequence', 'title', 'critical_count', 'warning_count', 'variant_count', 'location_count', 'minimum_price'];
        $sort = in_array($filters['sort'] ?? '', $allowedSorts, true) ? $filters['sort'] : 'sequence';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $page = $query->orderBy($sort, $direction)->paginate($perPage);

        return [
            'rows' => collect($page->items())->map(fn (BulkUploadProductPreview $product) => $this->listRow($product))->all(),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
            'summary' => $this->summary($session),
        ];
    }

    public function detail(BulkUploadProductPreview $product): array
    {
        return array_merge($this->listRow($product), $product->payload ?: []);
    }

    public function attributes(BulkUploadPreviewSession $session, array $filters): array
    {
        $requestedPageSize = (int) ($filters['per_page'] ?? 25);
        $perPage = in_array($requestedPageSize, [25, 50, 100], true) ? $requestedPageSize : 25;
        $query = DB::table('bulk_upload_product_preview_identifiers')
            ->where('preview_session_id', $session->id)
            ->whereIn('type', ['attribute_new', 'attribute_existing']);
        $status = $filters['status'] ?? 'all';
        if (in_array($status, ['new', 'existing'], true)) {
            $query->where('type', 'attribute_'.$status);
        }
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where('value', 'like', '%'.$search.'%');
        }
        $page = $query->orderBy('value')->paginate($perPage, ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)));

        return [
            'rows' => collect($page->items())->map(fn ($item) => [
                'name' => $item->value,
                'status' => $item->type === 'attribute_new' ? 'new' : 'existing',
            ])->all(),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ];
    }

    public function cleanup(BulkUploadPreviewSession $session, bool $deleteUpload = false): void
    {
        if ($deleteUpload) {
            Storage::disk($session->file_disk)->deleteDirectory(dirname($session->file_path));
        }
        $session->delete();
    }

    public function summary(BulkUploadPreviewSession $session): array
    {
        $attributeNames = DB::table('bulk_upload_product_preview_identifiers')
            ->where('preview_session_id', $session->id)
            ->whereIn('type', ['attribute_new', 'attribute_existing'])
            ->orderBy('value');
        $newAttributes = (clone $attributeNames)->where('type', 'attribute_new');
        $existingAttributes = (clone $attributeNames)->where('type', 'attribute_existing');

        return [
            'token' => $session->token,
            'status' => $session->status,
            'is_terminal' => in_array($session->status, ['completed', 'failed'], true),
            'total_rows' => $session->total_rows,
            'processed_rows' => $session->processed_rows,
            'products' => $session->product_count,
            'variants' => $session->variant_count,
            'locations' => $session->location_count,
            'critical' => $session->critical_count,
            'warnings' => $session->warning_count,
            'information' => $session->info_count,
            'attributes' => [
                'new_count' => (clone $newAttributes)->count(),
                'existing_count' => (clone $existingAttributes)->count(),
                'new' => (clone $newAttributes)->limit(5)->pluck('value')->all(),
                'existing' => (clone $existingAttributes)->limit(5)->pluck('value')->all(),
            ],
            'can_queue' => $session->status === 'completed',
            'failure_reason' => $session->failure_reason,
            'sample' => $session->products()->orderBy('sequence')->limit(3)->get()->map(fn ($product) => $this->listRow($product))->all(),
        ];
    }

    public function mappingHash(array $mapping, string $operation, ?string $matchField): string
    {
        ksort($mapping);

        return hash('sha256', json_encode([$mapping, $operation, $matchField], JSON_THROW_ON_ERROR));
    }

    private function restart(BulkUploadPreviewSession $session, array $mapping, string $operation, ?string $matchField, string $hash): void
    {
        DB::transaction(function () use ($session, $mapping, $operation, $matchField, $hash) {
            $session->products()->delete();
            DB::table('bulk_upload_product_preview_identifiers')->where('preview_session_id', $session->id)->delete();
            $session->update([
                'column_mapping' => $mapping,
                'mapping_hash' => $hash,
                'operation' => $operation,
                'match_field' => $matchField,
                'status' => 'pending',
                'byte_offset' => $this->headerOffset($session->file_disk, $session->file_path),
                'next_row_number' => 2,
                'processed_rows' => 0,
                'product_count' => 0,
                'variant_count' => 0,
                'location_count' => 0,
                'critical_count' => 0,
                'warning_count' => 0,
                'info_count' => 0,
                'failure_reason' => null,
            ]);
        });
    }

    private function buildChunk(BulkUploadPreviewSession $session): void
    {
        $stream = Storage::disk($session->file_disk)->readStream($session->file_path);
        if (! is_resource($stream)) {
            throw ValidationException::withMessages(['file' => __('labels.bulk_upload_file_not_found')]);
        }

        try {
            $headers = $this->cleanHeaders(fgetcsv($stream) ?: []);
            fseek($stream, $session->byte_offset);
            $rowNumber = $session->next_row_number;
            $processedThisTick = 0;
            $target = (int) config('bulk_upload.preview_rows_per_request', 750);
            $eof = false;
            $groups = [];
            while ($processedThisTick < $target) {
                $groupRows = [];
                $sourceRows = [];
                $groupHandle = null;
                while (true) {
                    $rowStart = ftell($stream);
                    $row = fgetcsv($stream);
                    if ($row === false) {
                        $eof = true;
                        break;
                    }
                    $currentRowNumber = $rowNumber++;
                    if ($this->blank($row)) {
                        $processedThisTick++;

                        continue;
                    }
                    $associated = array_combine($headers, array_pad(array_slice($row, 0, count($headers)), count($headers), '')) ?: [];
                    $handleHeader = $session->column_mapping['handle'] ?? null;
                    $handle = strtolower(trim((string) ($handleHeader ? ($associated[$handleHeader] ?? '') : '')));
                    $handle = $handle !== '' ? $handle : '__row_'.$currentRowNumber;
                    if ($groupHandle !== null && $handle !== $groupHandle) {
                        fseek($stream, $rowStart);
                        $rowNumber--;
                        break;
                    }
                    $groupHandle ??= $handle;
                    $groupRows[] = $associated;
                    $sourceRows[] = $currentRowNumber;
                    $processedThisTick++;
                }
                if ($groupRows !== []) {
                    $groups[] = [
                        'rows' => $groupRows,
                        'source_rows' => $sourceRows,
                        'handle' => $groupHandle,
                        'offset' => ftell($stream),
                        'next_row_number' => $rowNumber,
                    ];
                }
                $session->byte_offset = ftell($stream);
                $session->next_row_number = $rowNumber;
                if ($eof) {
                    break;
                }
            }
            $skuHeader = $session->column_mapping['variant_sku'] ?? null;
            $barcodeHeader = $session->column_mapping['barcode'] ?? null;
            $this->importer->primePreviewVariantIdentifiers(
                $skuHeader ? collect($groups)->flatMap(fn ($group) => collect($group['rows'])->pluck($skuHeader)->filter())->all() : [],
                $barcodeHeader ? collect($groups)->flatMap(fn ($group) => collect($group['rows'])->pluck($barcodeHeader)->filter())->all() : [],
            );
            foreach ($groups as $group) {
                DB::transaction(function () use ($session, $group) {
                    $this->indexGroup($session, $group['rows'], $group['source_rows'], $group['handle']);
                    $session->processed_rows += count($group['rows']);
                    $session->byte_offset = $group['offset'];
                    $session->next_row_number = $group['next_row_number'];
                    $session->save();
                });
            }
            $session->status = $eof ? 'completed' : 'building';
            $session->save();
        } finally {
            fclose($stream);
        }
    }

    private function indexGroup(BulkUploadPreviewSession $session, array $rows, array $sourceRows, string $groupHandle): void
    {
        $preview = $this->importer->previewGroup($rows, $session->column_mapping, (int) $session->seller_id, $session->operation, $session->match_field);
        foreach ($preview['attributes'] as $attribute) {
            DB::table('bulk_upload_product_preview_identifiers')->insertOrIgnore([
                'preview_session_id' => $session->id,
                'type' => $attribute['status'] === 'new' ? 'attribute_new' : 'attribute_existing',
                'value' => mb_strtolower($attribute['name']),
            ]);
        }
        $issues = $preview['issues'];
        if ($session->products()->where('handle', $groupHandle)->exists()) {
            $issues['critical'][] = __('labels.bulk_upload_product_non_contiguous_handles');
        }
        $identifiers = [];
        foreach ($preview['variants'] as $variant) {
            foreach (['sku', 'barcode'] as $type) {
                $value = strtolower(trim((string) ($variant[$type] ?? '')));
                if ($value !== '') {
                    $identifiers[] = [$type, $value];
                }
            }
        }
        foreach ($identifiers as [$type, $value]) {
            $inserted = DB::table('bulk_upload_product_preview_identifiers')->insertOrIgnore([
                'preview_session_id' => $session->id,
                'type' => $type,
                'value' => $value,
            ]);
            if ($inserted === 0) {
                $issues['critical'][] = __('labels.bulk_upload_product_duplicate_identifier_value', ['type' => strtoupper($type), 'value' => $value]);
            }
        }
        $newLocationCount = 0;
        foreach ($preview['location_keys'] ?? [] as $locationKey) {
            $newLocationCount += DB::table('bulk_upload_product_preview_identifiers')->insertOrIgnore([
                'preview_session_id' => $session->id,
                'type' => 'location',
                'value' => (string) $locationKey,
            ]);
        }
        foreach ($issues as $severity => $messages) {
            $issues[$severity] = array_values(array_unique($messages));
        }
        $product = $preview['product'];
        $payload = array_merge($preview, ['issues' => $issues, 'source_rows' => $sourceRows]);
        $critical = count($issues['critical']);
        $warnings = count($issues['warning']);
        $info = count($issues['info']);
        BulkUploadProductPreview::create([
            'preview_session_id' => $session->id,
            'sequence' => $session->product_count + 1,
            'first_row_number' => min($sourceRows),
            'last_row_number' => max($sourceRows),
            'product_id' => $product['id'] ?: null,
            'handle' => $product['handle'] ?: $groupHandle,
            'title' => $product['title'] ?: null,
            'type' => $product['type'] ?: null,
            'status' => $product['status'] ?: null,
            'category' => $product['category'] ?: null,
            'brand' => $product['brand'] ?: null,
            'main_image_url' => $product['main_image_url'] ?: null,
            'variant_count' => $preview['variant_count'],
            'location_count' => $preview['location_count'],
            'minimum_price' => $preview['minimum_price'],
            'maximum_price' => $preview['maximum_price'],
            'inventory_total' => $preview['inventory_total'],
            'critical_count' => $critical,
            'warning_count' => $warnings,
            'info_count' => $info,
            'search_text' => strtolower(implode(' ', array_filter([
                $product['id'], $product['handle'], $product['title'], $product['category'], $product['brand'],
                ...array_column($preview['variants'], 'sku'), ...array_column($preview['variants'], 'barcode'),
                ...$issues['critical'], ...$issues['warning'],
            ]))),
            'payload' => $payload,
        ]);
        $session->product_count++;
        $session->variant_count += $preview['variant_count'];
        $session->location_count += $newLocationCount;
        $session->critical_count += $critical;
        $session->warning_count += $warnings;
        $session->info_count += $info;
    }

    private function listRow(BulkUploadProductPreview $product): array
    {
        return [
            'id' => $product->id,
            'sequence' => $product->sequence,
            'product_id' => $product->product_id,
            'handle' => $product->handle,
            'title' => $product->title,
            'type' => $product->type,
            'status' => $product->status,
            'category' => $product->category,
            'brand' => $product->brand,
            'main_image_url' => $product->main_image_url,
            'variant_count' => $product->variant_count,
            'location_count' => $product->location_count,
            'minimum_price' => $product->minimum_price,
            'maximum_price' => $product->maximum_price,
            'inventory_total' => $product->inventory_total,
            'critical_count' => $product->critical_count,
            'warning_count' => $product->warning_count,
            'info_count' => $product->info_count,
            'first_row_number' => $product->first_row_number,
            'last_row_number' => $product->last_row_number,
        ];
    }

    private function headers(BulkUploadPreviewSession $session): array
    {
        $stream = Storage::disk($session->file_disk)->readStream($session->file_path);
        if (! is_resource($stream)) {
            return [];
        }
        try {
            return $this->cleanHeaders(fgetcsv($stream) ?: []);
        } finally {
            fclose($stream);
        }
    }

    private function headerOffset(string $disk, string $path): int
    {
        $stream = Storage::disk($disk)->readStream($path);
        if (! is_resource($stream)) {
            return 0;
        }
        try {
            fgetcsv($stream);

            return (int) ftell($stream);
        } finally {
            fclose($stream);
        }
    }

    private function blank(array $row): bool
    {
        return collect($row)->every(fn ($value) => trim((string) $value) === '');
    }

    private function cleanHeaders(array $headers): array
    {
        return array_map(function ($header, int $index) {
            $value = trim((string) $header);

            return $index === 0 ? preg_replace('/^\xEF\xBB\xBF/', '', $value) : $value;
        }, $headers, array_keys($headers));
    }
}
