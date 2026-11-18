<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

final class DatabaseSchema
{
    private array $tables = [];

    private bool $connectionFailed = false;

    public function hasTable(string $table): bool
    {
        if ($this->connectionFailed) {
            return false;
        }

        try {
            return $this->tables[$table] ??= Schema::hasTable($table);
        } catch (\Throwable) {
            $this->connectionFailed = true;

            return false;
        }
    }
}
