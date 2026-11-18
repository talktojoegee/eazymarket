<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSearchIndexer
{
    private const DESCRIPTION_LIMIT = 1500;

    /** @var array<int, true> */
    private static array $pending = [];

    private static bool $flushRegistered = false;

    public function buildSearchText(Product $product): string
    {
        $product->loadMissing([
            'brand:id,title',
            'category:id,title,parent_id',
            'category.parent:id,title,parent_id',
            'categories:id,title',
            'variants:id,product_id,title,sku,barcode',
        ]);

        $parts = [
            $product->title,
            $product->short_description,
            $this->plainDescription($product->description),
            $this->normalizeTags($product->tags),
            $product->brand?->title,
            $product->made_in,
            $product->hsn_code,
        ];

        $category = $product->category;
        while ($category) {
            $parts[] = $category->title;
            $category = $category->relationLoaded('parent') ? $category->parent : null;
        }

        foreach ($product->categories as $extraCategory) {
            $parts[] = $extraCategory->title;
        }

        foreach ($product->variants as $variant) {
            $parts[] = $variant->title;
            $parts[] = $variant->sku;
            $parts[] = $variant->barcode;
        }

        return $this->collapse($parts);
    }

    /**
     * Defer a reindex to the end of the request so a product saved alongside
     * N variants is rebuilt once, not N+1 times.
     */
    public function queue(int $productId): void
    {
        if (isset(self::$pending[$productId])) {
            return;
        }

        self::$pending[$productId] = true;

        if (self::$flushRegistered) {
            return;
        }

        self::$flushRegistered = true;
        app()->terminating(fn () => $this->flush());
    }

    public function flush(): void
    {
        $ids = array_keys(self::$pending);
        self::$pending = [];
        self::$flushRegistered = false;

        if (empty($ids)) {
            return;
        }

        Product::withTrashed()->whereIn('id', $ids)->get()->each(fn (Product $product) => $this->sync($product));
    }

    public function sync(Product $product): void
    {
        $searchText = $this->buildSearchText($product);

        if ($product->search_text === $searchText) {
            return;
        }

        DB::table('products')
            ->where('id', $product->id)
            ->update(['search_text' => $searchText]);

        $product->setAttribute('search_text', $searchText);
        $product->syncOriginalAttribute('search_text');
    }

    public function rebuildAll(?callable $onChunk = null, int $chunkSize = 200): int
    {
        $total = 0;

        Product::withTrashed()
            ->select('products.*')
            ->chunkById($chunkSize, function ($products) use (&$total, $onChunk) {
                foreach ($products as $product) {
                    $this->sync($product);
                    $total++;
                }

                if ($onChunk) {
                    $onChunk($products->count());
                }
            });

        return $total;
    }

    private function plainDescription(?string $description): string
    {
        if (! is_string($description) || $description === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Str::limit(preg_replace('/\s+/u', ' ', $text), self::DESCRIPTION_LIMIT, '');
    }

    private function normalizeTags($tags): string
    {
        if (is_array($tags)) {
            return implode(' ', array_filter($tags, 'is_scalar'));
        }

        if (! is_string($tags) || trim($tags) === '') {
            return '';
        }

        $trimmed = trim($tags);

        if (str_starts_with($trimmed, '[')) {
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return implode(' ', array_filter($decoded, 'is_scalar'));
            }
        }

        return str_replace(',', ' ', $trimmed);
    }

    private function collapse(array $parts): string
    {
        $words = [];

        foreach ($parts as $part) {
            if (! is_scalar($part)) {
                continue;
            }

            foreach (preg_split('/[^\p{L}\p{N}]+/u', (string) $part, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                $key = mb_strtolower($word);
                if (! isset($words[$key])) {
                    $words[$key] = $word;
                }
            }
        }

        return Str::limit(implode(' ', $words), 60000, '');
    }
}
