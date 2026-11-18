<?php

namespace App\Services\BulkUpload;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;

class BulkUploadModuleRegistry
{
    private array $modules = [];

    public function __construct()
    {
        $this->register(new BulkUploadModuleConfig(
            key: 'brands',
            modelClass: Brand::class,
            importerClass: BrandBulkImportService::class,
            displayNameKey: 'labels.brands',
            indexRouteName: 'admin.brands.index',
            menuKey: 'brands',
        ));
        $this->register(new BulkUploadModuleConfig(
            key: 'categories',
            modelClass: Category::class,
            importerClass: CategoryBulkImportService::class,
            displayNameKey: 'labels.categories',
            indexRouteName: 'admin.categories.index',
            menuKey: 'categories',
        ));
        $this->register(new BulkUploadModuleConfig(
            key: 'products',
            modelClass: Product::class,
            importerClass: ProductBulkImportService::class,
            displayNameKey: 'labels.products',
            indexRouteName: 'admin.products.index',
            menuKey: 'products',
            panels: ['admin', 'seller'],
            sellerScoped: true,
            templates: [
                'simple' => 'labels.bulk_upload_simple_product_template',
                'variant' => 'labels.bulk_upload_variant_product_template',
            ],
            mediaFields: ['main_image_url', 'additional_image_urls', 'variant_image_url'],
            primaryMediaField: 'main_image_url',
        ));
    }

    public function register(BulkUploadModuleConfig $config): void
    {
        $this->modules[$config->key] = $config;
    }

    public function get(string $key): BulkUploadModuleConfig
    {
        return $this->modules[$key]
            ?? throw new \InvalidArgumentException("No bulk upload module registered for [{$key}].");
    }

    public function tryGet(string $key): ?BulkUploadModuleConfig
    {
        return $this->modules[$key] ?? null;
    }

    public function all(): array
    {
        return $this->modules;
    }

    public function getByModel(string $modelClass): ?BulkUploadModuleConfig
    {
        foreach ($this->modules as $config) {
            if ($config->modelClass === $modelClass) {
                return $config;
            }
        }

        return null;
    }
}
