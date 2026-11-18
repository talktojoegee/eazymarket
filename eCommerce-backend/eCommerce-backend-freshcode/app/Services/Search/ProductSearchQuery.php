<?php

namespace App\Services\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductSearchQuery
{
    public const MODE_ALL = 'all';
    public const MODE_ANY = 'any';

    private const MIN_TOKEN_LENGTH = 3;
    private const MAX_TOKENS = 12;

    public function isSupported(): bool
    {
        return DB::connection()->getDriverName() === 'mysql';
    }

    public function tokenize(string $term): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_slice(array_values(array_unique(array_map('mb_strtolower', $tokens))), 0, self::MAX_TOKENS);
    }

    public function canRelax(string $term): bool
    {
        return count($this->tokenize($term)) > 1;
    }

    public function applyFilter(Builder $query, string $term, string $mode = self::MODE_ALL): void
    {
        $tokens = $this->tokenize($term);

        if (empty($tokens)) {
            return;
        }

        if (! $this->isSupported()) {
            $this->applyLikeFilter($query, $term, $tokens);

            return;
        }

        [$indexed, $short] = $this->splitTokens($tokens);

        $query->where(function (Builder $outer) use ($indexed, $short, $mode, $term) {
            if (! empty($indexed)) {
                $expression = $this->booleanExpression($indexed, $mode);

                $outer->whereRaw('MATCH(products.search_text) AGAINST (? IN BOOLEAN MODE)', [$expression]);

                if ($mode === self::MODE_ANY) {
                    $outer->orWhere('products.title', 'LIKE', "%{$term}%");
                }
            }

            foreach ($short as $token) {
                if (empty($indexed) || $mode === self::MODE_ALL) {
                    $outer->where(function (Builder $inner) use ($token) {
                        $inner->where('products.title', 'LIKE', "%{$token}%")
                            ->orWhere('products.search_text', 'LIKE', "%{$token}%");
                    });

                    continue;
                }

                $outer->orWhere('products.title', 'LIKE', "%{$token}%");
            }
        });
    }

    public function applyRelevanceOrder(Builder $query, string $term): void
    {
        $tokens = $this->tokenize($term);

        if (empty($tokens)) {
            return;
        }

        if (! $this->isSupported()) {
            $query->orderByRaw('CASE WHEN products.title LIKE ? THEN 0 WHEN products.title LIKE ? THEN 1 ELSE 2 END', [$term, "{$term}%"]);

            return;
        }

        [$indexed] = $this->splitTokens($tokens);
        $natural = implode(' ', $indexed ?: $tokens);

        $expression = 'CASE WHEN products.title = ? THEN 1000 ELSE 0 END'
            .' + CASE WHEN products.title LIKE ? THEN 300 ELSE 0 END'
            .' + CASE WHEN products.title LIKE ? THEN 120 ELSE 0 END'
            .' + (MATCH(products.title) AGAINST (? IN NATURAL LANGUAGE MODE) * 12)'
            .' + (MATCH(products.search_text) AGAINST (? IN NATURAL LANGUAGE MODE) * 3)'
            ." + CASE WHEN products.featured = '1' THEN 5 ELSE 0 END";

        $bindings = [$term, "{$term}%", "%{$term}%", $natural, $natural];

        $query->orderByRaw('('.$expression.') DESC', $bindings);
    }

    private function applyLikeFilter(Builder $query, string $term, array $tokens): void
    {
        $query->where(function (Builder $outer) use ($tokens, $term) {
            $outer->where('products.title', 'LIKE', "%{$term}%")
                ->orWhere('products.search_text', 'LIKE', "%{$term}%");

            foreach ($tokens as $token) {
                $outer->orWhere('products.search_text', 'LIKE', "%{$token}%");
            }
        });
    }

    private function splitTokens(array $tokens): array
    {
        $indexed = [];
        $short = [];

        foreach ($tokens as $token) {
            if (mb_strlen($token) >= self::MIN_TOKEN_LENGTH) {
                $indexed[] = $token;

                continue;
            }

            $short[] = $token;
        }

        return [$indexed, $short];
    }

    private function booleanExpression(array $tokens, string $mode): string
    {
        $prefix = $mode === self::MODE_ALL ? '+' : '';

        return implode(' ', array_map(
            fn (string $token) => $prefix.$this->escapeToken($token).'*',
            $tokens
        ));
    }

    private function escapeToken(string $token): string
    {
        return preg_replace('/[+\-><\(\)~*"@]+/u', '', $token);
    }
}
