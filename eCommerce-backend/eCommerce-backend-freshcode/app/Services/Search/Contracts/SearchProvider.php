<?php

namespace App\Services\Search\Contracts;

use App\Models\User;

interface SearchProvider
{
    /** @return array<int, array<string, string>> */
    public function search(string $query, string $panel, User $user, int $limit = 5): array;
}
