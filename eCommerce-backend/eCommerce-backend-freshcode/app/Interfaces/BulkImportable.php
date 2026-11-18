<?php

namespace App\Interfaces;

use App\Models\BulkUploadJob;

interface BulkImportable
{
    public const OUTCOME_CREATED = 'created';

    public const OUTCOME_UPDATED = 'updated';

    public const OUTCOME_SKIPPED = 'skipped';

    public const OUTCOME_FAILED = 'failed';

    public function moduleKey(): string;

    public function modelClass(): string;

    public function csvTemplate(): array;

    public function availableFields(): array;

    public function validateMapping(array $mapping, array $headers): void;

    public function processRow(array $row, BulkUploadJob $job): array;

    public function exportColumns(): array;

    public function exportRow($model): array;

    public function exportFilters(): array;

    public function applyExportFilters($query, array $filters);

    public function availableMatchFields(): array;
}
