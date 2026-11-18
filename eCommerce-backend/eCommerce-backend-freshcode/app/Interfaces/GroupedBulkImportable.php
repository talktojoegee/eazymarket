<?php

namespace App\Interfaces;

use App\Models\BulkUploadJob;

interface GroupedBulkImportable extends BulkImportable
{
    public function groupingKey(array $row, BulkUploadJob $job): string;

    public function processGroup(array $rows, BulkUploadJob $job): array;

    public function previewSummary(array $headers, array $rows): array;
}
