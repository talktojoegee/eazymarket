<?php

namespace App\Support;

class CsvSafe
{
    private const RISKY_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public static function value(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], self::RISKY_PREFIXES, true) ? "'" . $value : $value;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, mixed>
     */
    public static function row(array $row): array
    {
        return array_map(static fn ($value) => self::value($value), $row);
    }
}
