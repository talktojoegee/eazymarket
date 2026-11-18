<?php

namespace App\Services\BulkUpload;

class BulkUploadModuleConfig
{
    public function __construct(
        public readonly string $key,
        public readonly string $modelClass,
        public readonly string $importerClass,
        public readonly string $displayNameKey,
        public readonly string $indexRouteName,
        public readonly string $menuKey,
        public readonly array $panels = ['admin'],
        public readonly bool $sellerScoped = false,
        public readonly array $templates = ['default' => 'labels.download_template'],
        public readonly array $mediaFields = [],
        public readonly ?string $primaryMediaField = null,
    ) {}
}
